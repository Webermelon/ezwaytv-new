<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;

class CoreTvAccessService
{
    public function syncUser(array $data): User
    {
        $coreUserId = (int) ($data['core_user_id'] ?? 0);
        $connectUserId = (int) ($data['connect_user_id'] ?? 0);
        $email = trim((string) ($data['email'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));

        $user = User::query()->where('network_user_id', $coreUserId)->first();

        if (! $user && $email !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        $values = [
            'network_user_id' => $coreUserId ?: null,
            'is_network_user' => $coreUserId > 0 ? 1 : 0,
            'first_name' => (string) ($data['first_name'] ?? $user?->first_name ?? ''),
            'last_name' => (string) ($data['last_name'] ?? $user?->last_name ?? ''),
            'email' => $email !== '' ? $email : ($user?->email ?? ('network-'.$coreUserId.'@ezway.local')),
            'username' => $username !== '' ? $username : ($user?->username ?? ('network_'.$coreUserId)),
            'mobile' => (string) ($data['phone'] ?? $user?->mobile ?? ''),
            'status' => 1,
            'user_type' => $user?->user_type ?? 'user',
            'login_type' => $user?->login_type ?? 'otp',
            'email_verified_at' => $user?->email_verified_at ?? now(),
        ];

        if ($user) {
            $user->forceFill($values)->save();
            return $user->refresh();
        }

        $values['password'] = Hash::make(Str::password(32));

        return User::query()->create($values);
    }

    public function activate(array $data): array
    {
        $tvPlanId = $this->tvPlanId($data);
        $plan = Plan::query()->where('id', $tvPlanId)->firstOrFail();
        $user = $this->syncUser($data);
        $now = now();
        $startsAt = $this->date($data['starts_at'] ?? null) ?: $now;
        $endsAt = $this->date($data['ends_at'] ?? null);
        $providerSubscriptionId = (string) ($data['core_subscription_id'] ?? '');

        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('plan_id', '!=', $plan->id)
            ->update(['status' => 'inactive', 'end_date' => $now, 'updated_at' => $now]);

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('payment_id', $providerSubscriptionId)
            ->latest('id')
            ->first();

        if (! $subscription) {
            $subscription = new Subscription();
            $subscription->user_id = $user->id;
            $subscription->payment_id = $providerSubscriptionId !== '' ? $providerSubscriptionId : null;
        }

        $subscription->fill([
            'plan_id' => $plan->id,
            'start_date' => $startsAt,
            'end_date' => $endsAt,
            'status' => 'active',
            'is_manual' => 0,
            'amount' => (float) ($data['amount'] ?? $plan->total_price ?? $plan->price ?? 0),
            'discount_percentage' => (float) ($plan->discount_percentage ?? 0),
            'tax_amount' => (float) ($data['tax_amount'] ?? 0),
            'total_amount' => (float) ($data['total_amount'] ?? $data['amount'] ?? $plan->total_price ?? $plan->price ?? 0),
            'name' => (string) $plan->name,
            'identifier' => (string) $plan->identifier,
            'type' => (string) ($plan->duration ?? 'month'),
            'duration' => (int) ($plan->duration_value ?? 1),
            'level' => (int) ($plan->level ?? 0),
            'plan_type' => $plan->relationLoaded('planLimitation') ? $plan->planLimitation->toJson() : null,
        ])->save();

        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('id', '!=', $subscription->id)
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        $user->forceFill(['is_subscribe' => 1])->save();

        return [
            'tv_user_id' => (int) $user->id,
            'network_user_id' => (int) $user->network_user_id,
            'tv_subscription_id' => (int) $subscription->id,
            'tv_plan_id' => (int) $plan->id,
            'status' => (string) $subscription->status,
            'ends_at' => $subscription->end_date,
        ];
    }

    public function cancel(array $data): array
    {
        $tvPlanId = $this->tvPlanId($data);
        $user = $this->findUser($data);
        $cancelledAt = $this->date($data['cancelled_at'] ?? null) ?: now();

        if (! $user) {
            return ['tv_user_id' => null, 'tv_plan_id' => $tvPlanId, 'status' => 'not_found'];
        }

        $query = Subscription::query()
            ->where('user_id', $user->id)
            ->where('plan_id', $tvPlanId)
            ->where('status', 'active');

        if (! empty($data['core_subscription_id'])) {
            $query->where('payment_id', (string) $data['core_subscription_id']);
        }

        $updated = $query->update([
            'status' => 'cancelled',
            'end_date' => $cancelledAt,
            'updated_at' => now(),
        ]);

        $hasActiveSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>', now()))
            ->exists();

        if (! $hasActiveSubscription) {
            $user->forceFill(['is_subscribe' => 0])->save();
        }

        return [
            'tv_user_id' => (int) $user->id,
            'tv_plan_id' => $tvPlanId,
            'status' => $updated > 0 ? 'cancelled' : 'not_active',
        ];
    }

    public function status(array $data): array
    {
        $tvPlanId = $this->tvPlanId($data);
        $user = $this->findUser($data);

        if (! $user) {
            return ['has_access' => false, 'tv_user_id' => null, 'tv_plan_id' => $tvPlanId, 'status' => 'not_found'];
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('plan_id', $tvPlanId)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>', now()))
            ->latest('id')
            ->first();

        return [
            'has_access' => (bool) $subscription,
            'tv_user_id' => (int) $user->id,
            'tv_plan_id' => $tvPlanId,
            'status' => $subscription?->status ?? 'inactive',
            'ends_at' => $subscription?->end_date,
        ];
    }

    private function findUser(array $data): ?User
    {
        $coreUserId = (int) ($data['core_user_id'] ?? 0);
        if ($coreUserId > 0) {
            $user = User::query()->where('network_user_id', $coreUserId)->first();
            if ($user) {
                return $user;
            }
        }

        $email = trim((string) ($data['email'] ?? ''));
        return $email !== '' ? User::query()->where('email', $email)->first() : null;
    }

    private function tvPlanId(array $data): int
    {
        if (! empty($data['tv_plan_id'])) {
            return (int) $data['tv_plan_id'];
        }

        if (preg_match('/access\.premium\.(\d+)/', (string) ($data['capability'] ?? ''), $match)) {
            return (int) $match[1];
        }

        abort(422, 'TV plan id could not be resolved.');
    }

    private function date(?string $date): ?string
    {
        return $date ? CarbonImmutable::parse($date)->toDateTimeString() : null;
    }
}
