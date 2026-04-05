@extends('backend.layouts.app')

@section('title')
    Stats Booster
@endsection

@section('content')
<div class="card-main mb-5" style="max-width:800px">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0 fw-bold"><i class="ph ph-rocket-launch me-2 text-danger"></i>Stats Booster</h3>
            <p class="text-muted mb-0 small">Admin-only display multiplier and fixed counts.</p>
        </div>
        <a href="{{ route('backend.statistics.index') }}" class="btn btn-sm btn-dark">
            <i class="ph ph-chart-bar me-1"></i>Dashboard
        </a>
    </div>

    <form action="{{ route('backend.statistics.settings.save') }}" method="POST">
        @csrf
        <div class="card border-0 mb-3" style="background:rgba(220,38,38,0.08); border:1px solid rgba(220,38,38,0.25); border-radius:12px;">
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
            <a href="{{ route('backend.statistics.booster') }}" class="btn btn-outline-secondary">Manage Content Boosts</a>
        </div>
    </form>
</div>
@endsection
