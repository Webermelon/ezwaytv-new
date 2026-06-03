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
    </div>
@endsection

@push('after-scripts')
    <script src="{{ asset('music-landing/assets/js/main.js') }}"></script>
@endpush
