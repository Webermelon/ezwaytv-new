<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CoreApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CoreApiKeyController extends Controller
{
    public function index(): View
    {
        $keys = CoreApiKey::with(['creator', 'revoker'])
            ->latest('id')
            ->paginate(25);

        return view('backend.core-api-keys.index', compact('keys'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plainToken = 'eztv_' . Str::random(64);
        $plainSecret = 'ezsig_' . Str::random(64);

        CoreApiKey::create([
            'name' => $data['name'],
            'key_prefix' => substr($plainToken, 0, 14),
            'token_hash' => hash('sha256', $plainToken),
            'secret_encrypted' => Crypt::encryptString($plainSecret),
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'notes' => $data['notes'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('backend.core-api-keys.index')
            ->with('generated_key', [
                'token' => $plainToken,
                'secret' => $plainSecret,
            ])
            ->with('success', 'API key generated. Copy it now because it will not be shown again.');
    }

    public function revoke(CoreApiKey $coreApiKey): RedirectResponse
    {
        if ($coreApiKey->revoked_at === null) {
            $coreApiKey->forceFill([
                'revoked_at' => now(),
                'revoked_by' => Auth::id(),
            ])->save();
        }

        return redirect()
            ->route('backend.core-api-keys.index')
            ->with('success', 'API key revoked.');
    }
}
