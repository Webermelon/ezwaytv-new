@extends('frontend::layouts.master')

@section('title')
    eZWay Music - Streaming The eZWay
@endsection

@push('after-styles')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('music-landing/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('music-landing/assets/css/page.css') }}">
    <style>
        .music-landing {
            background-color: var(--ez-dark);
            color: var(--ez-white);
            font-family: "Poppins", sans-serif;
            overflow-x: hidden;
        }

        .music-landing .ez-hero {
            padding-top: 10px;
        }

        .music-landing .ez-submission-form {
            background: linear-gradient(160deg, rgba(31, 28, 72, 0.7) 0%, rgba(16, 13, 36, 0.94) 100%);
            border: 1px solid rgba(245, 166, 35, 0.22);
            border-radius: 18px;
            padding: clamp(24px, 4vw, 40px);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.32);
        }

        .music-landing .ez-submission-form .form-label {
            color: var(--ez-white);
            font-size: 13px;
            font-weight: 600;
        }

        .music-landing .ez-submission-form .form-control {
            color: var(--ez-white);
            background: rgba(6, 4, 16, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .music-landing .ez-submission-form .form-control:focus {
            border-color: var(--ez-golden-border);
            box-shadow: 0 0 0 0.2rem rgba(245, 166, 35, 0.18);
        }
    </style>
@endpush

@section('content')
    @php
        $musicHtml = file_get_contents(public_path('music-landing/source.html'));
        $start = strpos($musicHtml, '<section class="ez-hero"');
        $end = strpos($musicHtml, '<footer class="ez-footer"');
        $musicContent = $start !== false && $end !== false
            ? substr($musicHtml, $start, $end - $start)
            : '';
        $musicContent = str_replace('/music/assets/', asset('music-landing/assets') . '/', $musicContent);
    @endphp

    <div class="music-landing">
        {!! $musicContent !!}

        <section class="ez-section" id="music-video-submission">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-9">
                        <div class="ez-section-label text-center">
                            <i class="bi bi-upload"></i> MUSIC VIDEO SUBMISSION
                        </div>
                        <h2 class="ez-h-2 text-center mb-2">
                            Upload to <span class="ez-gold-text">eZWay Music</span>
                        </h2>
                        <p class="ez-text-xl text-center mb-5">
                            Login, confirm your purchase, then upload your poster and music video for review and scheduling.
                        </p>

                        <div class="ez-submission-form text-center">
                            <div class="ez-section-label text-center mb-3">
                                <i class="bi bi-lock-fill"></i> LOCKED CHANNEL: EZWAY MUSIC
                            </div>
                            <a href="{{ route('upload-your-videoes', ['channel' => 'ezway-music']) }}" class="ez-btn-gold ez-btn-lg">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                                Continue to Upload
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('after-scripts')
    <script src="{{ asset('music-landing/assets/js/main.js') }}"></script>
@endpush
