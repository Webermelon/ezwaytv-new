@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Author Channels</h1>
    <div class="row">
        @foreach($channels as $channel)
        <div class="col-md-4">
            <div class="card mb-3">
                <img src="{{ $channel->banner ?: '/images/default-banner.jpg' }}" class="card-img-top" alt="{{ $channel->name }}">
                <div class="card-body">
                    <h5 class="card-title">{{ $channel->name }}</h5>
                    <p class="card-text">{{ Str::limit($channel->description, 120) }}</p>
                    <a href="{{ route('author_channels.show', $channel->id) }}" class="btn btn-primary">View Channel</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    {{ $channels->links() }}
</div>
@endsection
