@extends('backend.layouts.app')

@section('title')
    Statistics Settings
@endsection

@section('content')
<div class="card-main mb-5" style="max-width:800px">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0 fw-bold"><i class="ph ph-gear me-2"></i>Statistics Settings</h3>
            <p class="text-muted mb-0 small">Configure what gets tracked, data retention, and exclusions.</p>
        </div>
        <a href="{{ route('backend.statistics.index') }}" class="btn btn-sm btn-dark">
            <i class="ph ph-chart-bar me-1"></i>View Dashboard
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('backend.statistics.settings.save') }}" method="POST">
        @csrf

        {{-- ── Tracking Toggles ── --}}
        <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Tracking</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="track_page_views" name="track_page_views" value="1"
                                {{ ($settings['track_page_views'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="track_page_views">
                                <span class="fw-medium">Track Page Views</span><br>
                                <small class="text-muted">Record every content/page visit.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="track_play_events" name="track_play_events" value="1"
                                {{ ($settings['track_play_events'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="track_play_events">
                                <span class="fw-medium">Track Play Events</span><br>
                                <small class="text-muted">Record every time a video is played.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="track_watch_time" name="track_watch_time" value="1"
                                {{ ($settings['track_watch_time'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="track_watch_time">
                                <span class="fw-medium">Track Watch Time</span><br>
                                <small class="text-muted">Record how many seconds each play lasts.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="track_guests" name="track_guests" value="1"
                                {{ ($settings['track_guests'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="track_guests">
                                <span class="fw-medium">Track Guest Visitors</span><br>
                                <small class="text-muted">Track users who are not logged in.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="exclude_bots" name="exclude_bots" value="1"
                                {{ ($settings['exclude_bots'] ?? '1') == '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="exclude_bots">
                                <span class="fw-medium">Exclude Bots</span><br>
                                <small class="text-muted">Filter out crawler/bot user agents.</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Data Settings ── --}}
        <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Data &amp; Retention</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-medium" for="retention_days">Data Retention (days)</label>
                        <input type="number" class="form-control" id="retention_days" name="retention_days"
                            value="{{ $settings['retention_days'] ?? 365 }}" min="1" max="3650">
                        <div class="form-text">Records older than this many days will be pruned automatically. (0 = keep forever)</div>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-medium" for="heartbeat_interval">Watch-time Heartbeat (seconds)</label>
                        <input type="number" class="form-control" id="heartbeat_interval" name="heartbeat_interval"
                            value="{{ $settings['heartbeat_interval'] ?? 30 }}" min="5" max="300">
                        <div class="form-text">How often the player reports watch progress. Lower = more accurate, more requests.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Exclude IPs ── --}}
        <div class="card border-0 mb-4" style="background:rgba(255,255,255,0.04); border-radius:12px;">
            <div class="card-body">
                <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">IP Exclusions</h6>
                <label class="form-label fw-medium" for="exclude_ips">Excluded IP Addresses</label>
                <textarea class="form-control font-monospace" id="exclude_ips" name="exclude_ips" rows="5"
                    placeholder="One IP per line, e.g.:&#10;192.168.1.1&#10;203.0.113.42">{{ $settings['exclude_ips'] ?? '' }}</textarea>
                <div class="form-text">Visits from these IPs will not be recorded. Include your own IP to exclude admin traffic.</div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="ph ph-floppy-disk me-1"></i>Save Settings
            </button>
            <a href="{{ route('backend.statistics.index') }}" class="btn btn-dark">Cancel</a>
        </div>
    </form>

    {{-- ── Boost (admin-only) ──────────────────────────── --}}
    @if(auth()->user()->hasRole('admin'))
    <form action="{{ route('backend.statistics.settings.save') }}" method="POST" class="mt-4">
        @csrf
        <div class="card border-0 mb-4" style="background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25); border-radius:12px;">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="ph ph-rocket-launch text-danger fs-5"></i>
                    <h6 class="fw-semibold mb-0 text-danger">Stats Booster</h6>
                    <span class="badge bg-danger ms-1" style="font-size:.65rem;">ADMIN ONLY</span>
                </div>
                <p class="text-muted small mb-3">Multiply displayed numbers and/or add a fixed base count. Changes are display-only and invisible to users.</p>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="boost_enabled" name="boost_enabled" value="1"
                        {{ ($settings['boost_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="boost_enabled">Enable Booster</label>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-medium" for="boost_multiplier">Multiplier</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="boost_multiplier" name="boost_multiplier"
                                value="{{ $settings['boost_multiplier'] ?? 1 }}" min="1" max="100" step="0.1">
                            <span class="input-group-text">×</span>
                        </div>
                        <div class="form-text">e.g. 2 = double all numbers</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-medium" for="boost_fixed_plays">Fixed Plays to Add</label>
                        <input type="number" class="form-control" id="boost_fixed_plays" name="boost_fixed_plays"
                            value="{{ $settings['boost_fixed_plays'] ?? 0 }}" min="0">
                        <div class="form-text">Added on top after multiplier</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-medium" for="boost_fixed_views">Fixed Views to Add</label>
                        <input type="number" class="form-control" id="boost_fixed_views" name="boost_fixed_views"
                            value="{{ $settings['boost_fixed_views'] ?? 0 }}" min="0">
                        <div class="form-text">Added on top after multiplier</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label fw-medium" for="boost_fixed_visitors">Fixed Visitors to Add</label>
                        <input type="number" class="form-control" id="boost_fixed_visitors" name="boost_fixed_visitors"
                            value="{{ $settings['boost_fixed_visitors'] ?? 0 }}" min="0">
                        <div class="form-text">Added on top after multiplier</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger">
                <i class="ph ph-rocket-launch me-1"></i>Save Boost Settings
            </button>
        </div>
    </form>
    @endif

</div>
@endsection
