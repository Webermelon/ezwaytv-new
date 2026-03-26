@extends('backend.layouts.app')

@section('title')
    {{ __('messages.lbl_banner_slider_settings') }}
@endsection

@section('content')
    <x-back-button-component route="backend.adbannersides.index" />

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ph ph-sliders me-2"></i>{{ __('messages.lbl_banner_slider_settings') }}</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('backend.adbannersides.save_settings') }}">
                @csrf

                {{-- Autoplay Interval --}}
                <div class="row mb-4 align-items-center border-bottom pb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1">{{ __('messages.lbl_slider_interval') }}</label>
                        <small class="d-block text-muted">{{ __('messages.lbl_slider_interval_hint') }}</small>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group" style="max-width:220px">
                            <input type="number"
                                   name="ad_banner_autoplay_speed"
                                   class="form-control @error('ad_banner_autoplay_speed') is-invalid @enderror"
                                   value="{{ old('ad_banner_autoplay_speed', $settings['ad_banner_autoplay_speed']) }}"
                                   min="1000" max="30000" step="500">
                            <span class="input-group-text">ms</span>
                        </div>
                        @error('ad_banner_autoplay_speed')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">e.g. 3000 = 3 sec, 5000 = 5 sec</small>
                    </div>
                </div>

                {{-- Slide Direction --}}
                <div class="row mb-4 align-items-center border-bottom pb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold mb-1">{{ __('messages.lbl_slide_direction') }}</label>
                        <small class="d-block text-muted">{{ __('messages.lbl_slide_direction_hint') }}</small>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ad_banner_direction"
                                       id="dir_ltr" value="ltr"
                                       {{ old('ad_banner_direction', $settings['ad_banner_direction']) === 'ltr' ? 'checked' : '' }}>
                                <label class="form-check-label" for="dir_ltr">
                                    <i class="ph ph-arrow-right me-1"></i>{{ __('messages.lbl_slide_ltr') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ad_banner_direction"
                                       id="dir_rtl" value="rtl"
                                       {{ old('ad_banner_direction', $settings['ad_banner_direction']) === 'rtl' ? 'checked' : '' }}>
                                <label class="form-check-label" for="dir_rtl">
                                    <i class="ph ph-arrow-left me-1"></i>{{ __('messages.lbl_slide_rtl') }}
                                </label>
                            </div>
                        </div>
                        @error('ad_banner_direction')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-floppy-disk me-1"></i>{{ __('messages.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
