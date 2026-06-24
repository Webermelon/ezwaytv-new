<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CoreCheckoutBridgeController extends Controller
{
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = $this->viewer($request);
        if (! $user) {
            return response()->json(['message' => 'Please sign in before choosing a plan.'], 401);
        }

        $core = $this->coreClient();
        if (! $core) {
            return response()->json(['message' => 'Core API is not configured.'], 503);
        }

        [$client, $baseUrl] = $core;
        $coreUser = (int) ($user->network_user_id ?: $user->id);

        try {
            $response = $client->get($baseUrl.'/api/users/'.$coreUser.'/payment-methods');
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Saved cards could not be loaded right now.', 'data' => []], 502);
        }

        return response()->json($response->json() ?? [], $response->status());
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $this->viewer($request);
        if (! $user) {
            return response()->json(['message' => 'Please sign in before choosing a plan.'], 401);
        }

        $data = $request->validate([
            'package_slug' => ['required', 'string', 'max:255'],
            'payment_method_id' => ['nullable', 'string', 'max:255'],
        ]);

        $core = $this->coreClient();
        if (! $core) {
            return response()->json(['message' => 'Core API is not configured.'], 503);
        }

        [$client, $baseUrl] = $core;
        $name = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
        $successUrl = url('/subscription-plan?checkout_status=success');
        $cancelUrl = url('/subscription-plan?checkout_status=cancelled');
        $coreUser = (int) ($user->network_user_id ?: $user->id);

        try {
            $response = $client->post($baseUrl.'/api/checkouts', [
                'package_slugs' => [$data['package_slug']],
                'user_id' => $coreUser,
                'customer' => [
                    'name' => $name !== '' ? $name : ($user->name ?? $user->email),
                    'email' => $user->email,
                    'phone' => $user->mobile ?? null,
                ],
                'platform' => ['slug' => 'ezway-tv'],
                'subject_type' => 'tv_subscription',
                'subject_id' => (string) $coreUser,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'failed_url' => url('/subscription-plan?checkout_status=failed'),
                'checkout_mode' => 'auto',
                'payment_method_id' => trim((string) ($data['payment_method_id'] ?? '')) ?: null,
                'metadata' => [
                    'source_app' => 'ezway_tv',
                    'tv_user_id' => (string) $user->id,
                    'network_user_id' => (string) ($user->network_user_id ?: ''),
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Core checkout could not be reached.'], 502);
        }

        return response()->json($response->json() ?? [], $response->status());
    }

    private function viewer(Request $request): mixed
    {
        return $request->user() ?: auth()->user();
    }

    private function coreClient(): ?array
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        $client = Http::withToken($token)->acceptJson()->timeout(20);
        $host = trim((string) config('services.core_api.host'));
        if ($host !== '') {
            $client = $client->withHeaders(['Host' => $host]);
        }

        return [$client, $baseUrl];
    }
}