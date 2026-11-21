<?php

namespace App\Http\Controllers\Api\Private;

use App\Services\VodChannelEntitlements;
use Illuminate\Http\Request;

class VodSyncController
{
    public function __invoke(Request $request, VodChannelEntitlements $vod)
    {
        $data = $request->validate([
            'operation' => ['required', 'in:list,sync'], 'member_id' => ['required', 'integer', 'min:1'],
            'assignment_id' => ['required_if:operation,sync', 'uuid'], 'subscription_id' => ['required_if:operation,sync', 'integer', 'min:1'],
            'channel_id' => ['nullable', 'integer', 'min:1'], 'version' => ['required_if:operation,sync', 'integer', 'min:1'],
            'status' => ['required_if:operation,sync', 'string', 'max:24'], 'starts_at' => ['nullable', 'date'], 'paid_until' => ['nullable', 'date'],
            'replaces_assignment_id' => ['sometimes', 'uuid'],
            'create' => ['nullable', 'array'], 'create.channel' => ['array'], 'create.channel.name' => ['string', 'max:255'],
            'create.channel.channel_username' => ['nullable', 'alpha_dash', 'max:100'], 'create.channel.description' => ['nullable', 'string', 'max:2000'],
            'create.channel.avatar' => ['nullable', 'string', 'max:1000'], 'create.channel.banner' => ['nullable', 'string', 'max:1000'],
            'create.member' => ['array'], 'create.member.email' => ['email', 'max:255'], 'create.member.username' => ['string', 'max:255'],
            'create.member.first_name' => ['nullable', 'string', 'max:255'], 'create.member.last_name' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($data['operation'] === 'list' ? ['channels' => $vod->channels($data['member_id'])] : $vod->synchronize($data));
    }
}
