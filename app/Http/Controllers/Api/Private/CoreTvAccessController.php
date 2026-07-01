<?php

namespace App\Http\Controllers\Api\Private;

use App\Http\Controllers\Controller;
use App\Services\CoreTvAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoreTvAccessController extends Controller
{
    public function __construct(private CoreTvAccessService $access)
    {
    }

    public function syncUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $this->access->syncUser($data);

        return response()->json([
            'success' => true,
            'tv_user_id' => (int) $user->id,
            'network_user_id' => (int) $user->network_user_id,
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate($this->subscriptionRules());

        return response()->json(['success' => true] + $this->access->activate($data));
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'core_user_id' => ['required', 'integer', 'min:1'],
            'core_subscription_id' => ['nullable'],
            'capability' => ['required', 'string', 'max:190'],
            'tv_plan_id' => ['nullable', 'integer', 'min:1'],
            'cancelled_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:190'],
        ]);

        return response()->json(['success' => true] + $this->access->cancel($data));
    }

    public function status(Request $request, int $coreUserId): JsonResponse
    {
        $data = $request->validate([
            'capability' => ['required', 'string', 'max:190'],
            'tv_plan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['success' => true] + $this->access->status($data + ['core_user_id' => $coreUserId]));
    }

    private function subscriptionRules(): array
    {
        return [
            'core_user_id' => ['required', 'integer', 'min:1'],
            'connect_user_id' => ['nullable', 'integer', 'min:1'],
            'email' => ['nullable', 'email'],
            'username' => ['nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:190'],
            'last_name' => ['nullable', 'string', 'max:190'],
            'avatar' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'core_subscription_id' => ['required'],
            'core_invoice_id' => ['nullable'],
            'core_package_id' => ['nullable', 'integer', 'min:1'],
            'access_package_id' => ['nullable', 'integer', 'min:1'],
            'capability' => ['required', 'string', 'max:190'],
            'tv_plan_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:60'],
        ];
    }
}
