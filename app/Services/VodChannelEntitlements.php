<?php

namespace App\Services;

use App\Models\AuthorChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class VodChannelEntitlements
{
    public function active(object $row): bool
    {
        return in_array($row->status, ['active', 'past_due'], true) && $row->paid_until
            && CarbonImmutable::parse($row->paid_until, 'UTC')->gt(now()) && (! $row->starts_at || CarbonImmutable::parse($row->starts_at, 'UTC')->lte(now()));
    }

    public function assertWritable(int $channel): void
    {
        if (! config('vod.enabled') && ! \Illuminate\Support\Facades\Schema::hasTable('vod_channel_entitlements')) {
            return;
        }
        $row = DB::table('vod_channel_entitlements')->where('channel_id', $channel)->first();
        abort_if($row && ! $this->active($row), 403, 'Your channel subscription needs renewal before you can publish or make changes. Your videos are preserved.');
    }

    public function mayPublish(int $channel): bool
    {
        if (! config('vod.enabled') && ! \Illuminate\Support\Facades\Schema::hasTable('vod_channel_entitlements')) {
            return true;
        }
        $row = DB::table('vod_channel_entitlements')->where('channel_id', $channel)->first();

        return ! $row || $this->active($row);
    }

    public function member(int $id): ?User
    {
        return User::query()->where('connect_user_id', $id)->first()
            ?? User::query()->whereNull('connect_user_id')->where('network_user_id', $id)->first();
    }

    public function channels(int $member): array
    {
        $user = $this->member($member);

        return $user ? AuthorChannel::query()->where('user_id', $user->id)->orderBy('name')->get(['id', 'name', 'username'])->toArray() : [];
    }

    public function synchronize(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $retired = \Illuminate\Support\Facades\Schema::hasTable('vod_channel_entitlement_history')
                ? DB::table('vod_channel_entitlement_history')->where('assignment_id', $payload['assignment_id'])->first() : null;
            if ($retired) {
                abort_unless((int) $retired->subscription_id === (int) $payload['subscription_id'] && (int) $retired->member_id === (int) $payload['member_id'], 409, 'Retired assignment identity cannot change.');
                return $this->result((int) $retired->channel_id) + ['stale' => true];
            }
            $row = DB::table('vod_channel_entitlements')->where('assignment_id', $payload['assignment_id'])->lockForUpdate()->first();
            if (!$row && !empty($payload['replaces_assignment_id'])) {
                $former = DB::table('vod_channel_entitlements')->where('assignment_id', $payload['replaces_assignment_id'])->lockForUpdate()->first();
                abort_unless($former && (int) $former->channel_id === (int) ($payload['channel_id'] ?? 0)
                    && (int) $former->member_id === (int) $payload['member_id'], 409, 'The former channel assignment did not match.');
                abort_if((int) $former->subscription_id === (int) $payload['subscription_id'], 409, 'A replacement requires a different subscription.');
                abort_if($this->active($former), 409, 'The former subscription must be inactive before replacement.');
                abort_unless($payload['status'] === 'active' && $this->active((object) $payload), 422, 'Confirmed current access is required before restoring a channel.');
                DB::table('vod_channel_entitlement_history')->insert(['assignment_id' => $former->assignment_id,
                    'replaced_by' => $payload['assignment_id'], 'channel_id' => $former->channel_id, 'subscription_id' => $former->subscription_id,
                    'member_id' => $former->member_id, 'snapshot' => json_encode($former, JSON_THROW_ON_ERROR), 'replaced_at' => now()]);
                DB::table('vod_channel_entitlements')->where('assignment_id', $former->assignment_id)->delete();
            }

            if ($row) {
                abort_unless((int) $row->subscription_id === (int) $payload['subscription_id'] && (int) $row->member_id === (int) $payload['member_id'], 409, 'Assignment identity cannot change.');
                abort_if(! empty($payload['channel_id']) && (int) $row->channel_id !== (int) $payload['channel_id'], 409, 'A subscription cannot switch to another channel.');
                if ((int) $payload['version'] < (int) $row->version) {
                    return $this->result((int) $row->channel_id) + ['stale' => true];
                }
                if ((int) $payload['version'] === (int) $row->version) {
                    abort_unless(hash_equals($row->payload_hash, $hash), 409, 'Conflicting assignment version.');

                    return $this->result((int) $row->channel_id) + ['duplicate' => true];
                }
            }
            $user = $this->member((int) $payload['member_id']);
            $channelId = $row->channel_id ?? ($payload['channel_id'] ?? null);
            if (! $channelId) {
                abort_unless(! empty($payload['create']['channel']['name']) && $payload['status'] === 'active' && $this->active((object) $payload), 422, 'A confirmed current paid period is required to create this channel.');
                if (! $user) {
                    $conflict = User::withTrashed()->where('network_user_id', $payload['member_id'])->whereNotNull('connect_user_id')->where('connect_user_id', '!=', $payload['member_id'])->exists();
                    abort_if($conflict, 409, 'TV identity needs an administrator review.');
                    $identity = $payload['create']['member'] ?? [];
                    $user = app(CoreTvAccessService::class)->syncUser($identity + ['core_user_id' => $payload['member_id'], 'connect_user_id' => $payload['member_id']]);
                }
                $data = $payload['create']['channel'];
                $channel = AuthorChannel::query()->create([
                    'user_id' => $user->id, 'name' => $data['name'],
                    'username' => $data['channel_username'] ?? AuthorChannel::generateUsername($data['name']),
                    'description' => $data['description'] ?? null, 'avatar' => $data['avatar'] ?? null, 'banner' => $data['banner'] ?? null,
                    'is_active' => true, 'access' => 'free', 'plan_id' => null,
                ]);
                $channelId = $channel->id;
                DB::afterCommit(fn () => TvCreatorEvents::record('creator.channel_created', request(), $user, (int) $channelId));
            }
            abort_unless($user && AuthorChannel::query()->where('id', $channelId)->where('user_id', $user->id)->exists(), 422, 'Channel must belong to this Connect member.');
            abort_if(DB::table('vod_channel_entitlements')->where('channel_id', $channelId)->where('assignment_id', '!=', $payload['assignment_id'])->exists(), 409, 'Channel already has another subscription.');
            $values = ['subscription_id' => $payload['subscription_id'], 'member_id' => $payload['member_id'], 'channel_id' => $channelId,
                'status' => $payload['status'], 'starts_at' => $payload['starts_at'] ?? null, 'paid_until' => $payload['paid_until'] ?? null,
                'version' => $payload['version'], 'payload_hash' => $hash, 'updated_at' => now()];
            if ($row) {
                DB::table('vod_channel_entitlements')->where('assignment_id', $row->assignment_id)->update($values);
            } else {
                DB::table('vod_channel_entitlements')->insert($values + ['assignment_id' => $payload['assignment_id'], 'created_at' => now()]);
            }

            return $this->result((int) $channelId) + ['has_access' => $this->active((object) $values)];
        });
    }

    private function result(int $id): array
    {
        $channel = AuthorChannel::query()->findOrFail($id);

        return ['channel_id' => $id, 'channel' => [
            'id' => $id, 'name' => (string) $channel->name, 'username' => (string) $channel->username,
            'description' => (string) ($channel->description ?? ''),
            'avatar_url' => $channel->avatar ? setBaseUrlWithFileNameV2($channel->avatar) : null,
            'banner_url' => $channel->banner ? setBaseUrlWithFileNameV2($channel->banner) : null,
            'is_active' => (bool) $channel->is_active, 'videos_count' => $channel->videos()->count(),
            'profile_url' => url('/on-demand/'.$channel->username),
        ]];
    }
}
