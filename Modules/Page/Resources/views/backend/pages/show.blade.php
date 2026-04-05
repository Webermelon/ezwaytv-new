@extends('frontend::layouts.master')

@section('title')
    {{ $page->name }}
@endsection

@push('after-styles')
<style>
    .page-content-body,
    .page-content-body * {
        color: inherit !important;
        font-family: inherit !important;
    }

    .page-embed-body iframe {
        width: 100% !important;
        min-height: 720px;
        border: 0;
    }
</style>
@endpush

@section('content')
    <div class="page-title">
        <h4 class="m-0 text-center">{{ $page->name }}</h4>
    </div>

    <div class="section-spacing-bottom">

        <div class="container">
            @if (empty($page->description))
                @if (($page->content_type ?? \Modules\Page\Models\Page::CONTENT_TYPE_LANDING) === \Modules\Page\Models\Page::CONTENT_TYPE_EMBED && !empty($page->embed_code))
                    <div class="page-embed-body">{!! $page->embed_code !!}</div>
                @else
                <div class="text-center">
                    <img src="{{ asset('img/NoData.png') }}" alt="No Data" class="img-fluid">
                    <p>No data found</p>
                </div>
                @endif
            @else
                @if (($page->content_type ?? \Modules\Page\Models\Page::CONTENT_TYPE_LANDING) === \Modules\Page\Models\Page::CONTENT_TYPE_EMBED)
                    <div class="page-embed-body">{!! $page->embed_code !!}</div>
                @else
                    <div class="page-content-body">{!! $page->description !!}</div>
                @endif
            @endif
        </div>
    </div>
@endsection
