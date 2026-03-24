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
</style>
@endpush

@section('content')
    <div class="page-title">
        <h4 class="m-0 text-center">{{ $page->name }}</h4>
    </div>

    <div class="section-spacing-bottom">

        <div class="container">
            @if (empty($page->description))
                <div class="text-center">
                    <img src="{{ asset('img/NoData.png') }}" alt="No Data" class="img-fluid">
                    <p>No data found</p>
                </div>
            @else
                <div class="page-content-body">{!! $page->description !!}</div>
            @endif
        </div>
    </div>
@endsection
