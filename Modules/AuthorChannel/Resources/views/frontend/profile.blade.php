@extends('layouts.app')

@section('content')
<div class="container">
    <div class="channel-header mb-4">
        <img src="{{ $channel->banner ?: '/images/default-banner.jpg' }}" class="w-100" style="max-height:300px;object-fit:cover">
        <div class="media mt-3">
            <img src="{{ $channel->avatar ?: '/images/default-avatar.png' }}" class="mr-3 rounded-circle" style="width:80px;height:80px;object-fit:cover">
            <div class="media-body">
                <h2>{{ $channel->name }}</h2>
                <p>{{ $channel->description }}</p>
            </div>
        </div>
    </div>

    <h4>Videos</h4>
    <div class="row">
        @foreach($channel->videos as $video)
        <div class="col-md-3 mb-3">
            <div class="card">
                <img src="{{ $video->thumbnail_url ? setBaseUrlWithFileNameV2($video->thumbnail_url) : asset('images/default-thumb.jpg') }}" class="card-img-top">
                <div class="card-body">
                    <h6 class="card-title">{{ Str::limit($video->name, 60) }}</h6>
                    <a href="/watch/{{ $video->slug }}" class="btn btn-sm btn-primary">Watch</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
