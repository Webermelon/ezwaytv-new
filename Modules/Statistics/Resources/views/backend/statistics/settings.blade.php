@extends('backend.layouts.app')

@section('title')
    Statistics Settings
@endsection

@section('content')
<div class="card-main mb-5">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0 fw-bold"><i class="ph ph-gear me-2"></i>Statistics Settings</h3>
            <p class="text-muted mb-0 small">Configure tracking, display, and boost settings.</p>
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

    <div class="row g-4">
        {{-- ═══ LEFT COLUMN: Statistics Settings ═══ --}}
        <div class="col-lg-6">
            <form action="{{ route('backend.statistics.settings.save') }}" method="POST">
                @csrf

                {{-- Tracking Toggles --}}
                <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Tracking</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="track_page_views" name="track_page_views" value="1"
                                        {{ ($settings['track_page_views'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="track_page_views">
                                        <span class="fw-medium">Track Page Views</span><br>
                                        <small class="text-muted">Record every content/page visit.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="track_play_events" name="track_play_events" value="1"
                                        {{ ($settings['track_play_events'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="track_play_events">
                                        <span class="fw-medium">Track Play Events</span><br>
                                        <small class="text-muted">Record every time a video is played.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="track_watch_time" name="track_watch_time" value="1"
                                        {{ ($settings['track_watch_time'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="track_watch_time">
                                        <span class="fw-medium">Track Watch Time</span><br>
                                        <small class="text-muted">Record how many seconds each play lasts.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="track_guests" name="track_guests" value="1"
                                        {{ ($settings['track_guests'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="track_guests">
                                        <span class="fw-medium">Track Guest Visitors</span><br>
                                        <small class="text-muted">Track users who are not logged in.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
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

                {{-- Dashboard Display --}}
                <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Dashboard Display</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_page_views" name="show_page_views" value="1"
                                        {{ ($settings['show_page_views'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_page_views">
                                        <span class="fw-medium">Show Page Views</span><br>
                                        <small class="text-muted">Show in Statistics dashboard cards.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_player_plays" name="show_player_plays" value="1"
                                        {{ ($settings['show_player_plays'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_player_plays">
                                        <span class="fw-medium">Show Player Plays</span><br>
                                        <small class="text-muted">Show in Statistics dashboard cards.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Frontend Display --}}
                <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Frontend Display</h6>
                        <p class="text-muted small mb-3">What visitors see on content pages.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_views_frontend" name="show_views_frontend" value="1"
                                        {{ ($settings['show_views_frontend'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_views_frontend">
                                        <span class="fw-medium">Show Page Views</span><br>
                                        <small class="text-muted">Display page view count on content pages.</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_plays_frontend" name="show_plays_frontend" value="1"
                                        {{ ($settings['show_plays_frontend'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_plays_frontend">
                                        <span class="fw-medium">Show Player Plays</span><br>
                                        <small class="text-muted">Display play count on content pages.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Data & Retention --}}
                <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">Data &amp; Retention</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-medium" for="retention_days">Data Retention (days)</label>
                                <input type="number" class="form-control" id="retention_days" name="retention_days"
                                    value="{{ $settings['retention_days'] ?? 365 }}" min="1" max="3650">
                                <div class="form-text">Records older than this will be pruned. (0 = keep forever)</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium" for="heartbeat_interval">Watch-time Heartbeat (sec)</label>
                                <input type="number" class="form-control" id="heartbeat_interval" name="heartbeat_interval"
                                    value="{{ $settings['heartbeat_interval'] ?? 30 }}" min="5" max="300">
                                <div class="form-text">How often the player reports watch progress.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- IP Exclusions --}}
                <div class="card border-0 mb-3" style="background:rgba(255,255,255,0.04); border-radius:12px;">
                    <div class="card-body">
                        <h6 class="fw-semibold mb-3 text-uppercase opacity-60" style="font-size:.75rem; letter-spacing:.08em;">IP Exclusions</h6>
                        <label class="form-label fw-medium" for="exclude_ips">Excluded IP Addresses</label>
                        <textarea class="form-control font-monospace" id="exclude_ips" name="exclude_ips" rows="4"
                            placeholder="One IP per line">{{ $settings['exclude_ips'] ?? '' }}</textarea>
                        <div class="form-text">Visits from these IPs will not be recorded.</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="ph ph-floppy-disk me-1"></i>Save Settings
                </button>
            </form>
        </div>

        {{-- ═══ RIGHT COLUMN: Stats Booster ═══ --}}
        @if(auth()->user()->hasRole('admin'))
        <div class="col-lg-6">
            <form action="{{ route('backend.statistics.settings.save') }}" method="POST">
                @csrf
                <input type="hidden" name="_form" value="boost">

                <div class="card border-0 mb-3" style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.25); border-radius:12px;">
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
                            <div class="col-12">
                                <label class="form-label fw-medium" for="boost_multiplier">Multiplier</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="boost_multiplier" name="boost_multiplier"
                                        value="{{ $settings['boost_multiplier'] ?? 1 }}" min="1" max="100" step="0.1">
                                    <span class="input-group-text">×</span>
                                </div>
                                <div class="form-text">e.g. 2 = double all numbers</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium" for="boost_fixed_plays">Fixed Plays</label>
                                <input type="number" class="form-control" id="boost_fixed_plays" name="boost_fixed_plays"
                                    value="{{ $settings['boost_fixed_plays'] ?? 0 }}" min="0">
                                <div class="form-text">Added after multiplier</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-medium" for="boost_fixed_views">Fixed Views</label>
                                <input type="number" class="form-control" id="boost_fixed_views" name="boost_fixed_views"
                                    value="{{ $settings['boost_fixed_views'] ?? 0 }}" min="0">
                                <div class="form-text">Added after multiplier</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium" for="boost_fixed_visitors">Fixed Visitors</label>
                                <input type="number" class="form-control" id="boost_fixed_visitors" name="boost_fixed_visitors"
                                    value="{{ $settings['boost_fixed_visitors'] ?? 0 }}" min="0">
                                <div class="form-text">Added after multiplier</div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger w-100">
                    <i class="ph ph-rocket-launch me-1"></i>Save Boost Settings
                </button>
            </form>

            <div class="mt-3">
                <a href="{{ route('backend.statistics.booster') }}" class="btn btn-outline-secondary w-100">
                    <i class="ph ph-target me-1"></i>Manage Content Boosts
                </a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
