@extends('backend.layouts.app')

@section('title') Core API Keys @endsection

@section('content')
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <h4 class="card-title mb-2"><i class="ph ph-key"></i> Generate API Key</h4>
                <p class="text-muted mb-4">Create a private server-to-server key for Core or another trusted service. The full token and signing secret are shown once.</p>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('backend.core-api-keys.store') }}" class="d-grid gap-3">
                    @csrf
                    <div>
                        <label class="form-label">Key name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name', 'Core Integration') }}" class="form-control" maxlength="120" required>
                    </div>
                    <div>
                        <label class="form-label">Allowed IPs</label>
                        <textarea name="allowed_ips" rows="3" class="form-control" placeholder="One IP per line or comma separated">{{ old('allowed_ips') }}</textarea>
                        <small class="text-muted">Leave blank to allow any server IP.</small>
                    </div>
                    <div>
                        <label class="form-label">Expires at</label>
                        <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Where this key will be used">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-plus-circle"></i> Generate key
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        @if(session('generated_key'))
            <div class="alert alert-warning border border-warning-subtle">
                <h5 class="alert-heading mb-2">Copy these values now</h5>
                <p class="mb-3">They will not be visible again after this page is refreshed.</p>
                <label class="form-label fw-semibold">Bearer token</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace" value="{{ session('generated_key.token') }}" readonly onclick="this.select()">
                    <button type="button" class="btn btn-dark" data-copy-value="{{ session('generated_key.token') }}">Copy</button>
                </div>
                <label class="form-label fw-semibold">Signing secret</label>
                <div class="input-group mb-1">
                    <input type="text" class="form-control font-monospace" value="{{ session('generated_key.secret') }}" readonly onclick="this.select()">
                    <button type="button" class="btn btn-dark" data-copy-value="{{ session('generated_key.secret') }}">Copy</button>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title mb-0"><i class="ph ph-shield-check"></i> Existing API Keys</h4>
                    <span class="badge bg-secondary">{{ $keys->total() }} total</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Prefix</th>
                                <th>Status</th>
                                <th>Last used</th>
                                <th>Expires</th>
                                <th>Created by</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($keys as $key)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $key->name }}</div>
                                        @if($key->notes)
                                            <small class="text-muted">{{ Str::limit($key->notes, 90) }}</small>
                                        @endif
                                    </td>
                                    <td><code>{{ $key->key_prefix }}...</code></td>
                                    <td>
                                        @if($key->revoked_at)
                                            <span class="badge bg-danger">Revoked</span>
                                        @elseif($key->expires_at && $key->expires_at->isPast())
                                            <span class="badge bg-warning text-dark">Expired</span>
                                        @else
                                            <span class="badge bg-success">Active</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($key->last_used_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>{{ optional($key->expires_at)->format('Y-m-d H:i') ?: 'Never' }}</td>
                                    <td>{{ optional($key->creator)->full_name ?? optional($key->creator)->name ?? '-' }}</td>
                                    <td>
                                        @if(!$key->revoked_at)
                                            <form method="POST" action="{{ route('backend.core-api-keys.revoke', $key) }}" onsubmit="return confirm('Revoke this API key? Services using it will stop working.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                            </form>
                                        @else
                                            <small class="text-muted">Revoked {{ optional($key->revoked_at)->format('Y-m-d H:i') }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No API keys generated yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $keys->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    document.querySelectorAll('[data-copy-value]').forEach((button) => {
        button.addEventListener('click', async () => {
            await navigator.clipboard.writeText(button.getAttribute('data-copy-value'));
            const original = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => button.textContent = original, 1200);
        });
    });
</script>
@endpush
