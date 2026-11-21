<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class VodCoreCreation
{
    public function canPublish(User $user, int $channel): bool
    {
        $member = (int) ($user->connect_user_id ?: ($user->is_network_user ? $user->network_user_id : 0));
        if ($member <= 0) {
            return false;
        }
        $base = rtrim((string) config('services.core_api.base_url'), '/');
        if (str_ends_with((string) parse_url(config('app.url'), PHP_URL_HOST), '.webermelon.dev')) {
            abort_unless(str_ends_with((string) parse_url($base, PHP_URL_HOST), '.webermelon.dev'), 503);
        }
        abort_unless(str_starts_with($base, 'https://'), 503);
        $response = Http::acceptJson()->withOptions(config('vod.loopback') ? ['curl' => [CURLOPT_RESOLVE => [parse_url($base, PHP_URL_HOST).':443:127.0.0.1']]] : [])->timeout(8)->withoutRedirecting()->withToken(config('services.core_api.token'))->get($base.'/api/tv/users/'.$member.'/channels/'.$channel.'/access');
        abort_unless($response->successful(), 503, 'Publishing access could not be checked. Please try again.');

        return $response->json('can_publish') === true;
    }

    public function create(User $user, array $input): int
    {
        $member = (int) ($user->connect_user_id ?: ($user->is_network_user ? $user->network_user_id : 0));
        abort_unless($member > 0, 403, 'Sign in with eZWay Connect to create your channel.');
        $base = rtrim((string) config('services.core_api.base_url'), '/');
        abort_unless(str_starts_with($base, 'https://'), 503, 'Channel setup is temporarily unavailable.');
        if (str_ends_with((string) parse_url(config('app.url'), PHP_URL_HOST), '.webermelon.dev')) {
            abort_unless(str_ends_with((string) parse_url($base, PHP_URL_HOST), '.webermelon.dev'), 503);
        }
        $response = Http::acceptJson()->withOptions(config('vod.loopback') ? ['curl' => [CURLOPT_RESOLVE => [parse_url($base, PHP_URL_HOST).':443:127.0.0.1']]] : [])->timeout(25)->withoutRedirecting()->withToken(config('services.core_api.token'))->post($base.'/api/tv/users/'.$member.'/channels', $input);
        if (! $response->successful()) {
            abort(response()->json(['message' => $response->json('message') ?: 'Channel creation could not be completed.', 'errors' => $response->json('errors')], $response->status()));
        }

        return (int) $response->json('data.id');
    }
}
