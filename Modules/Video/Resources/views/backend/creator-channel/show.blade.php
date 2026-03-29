@extends('backend.layouts.app')

@section('title', $module_title)

@section('content')
<x-back-button-component route="backend.creator-channels.index" />

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0"><i class="{{ $module_icon }}"></i> {{ $channel->name }}</h4>
    <div>
        <form action="{{ route('backend.creator-channels.toggle-status', $channel->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm {{ $channel->status ? 'btn-success' : 'btn-secondary' }}">
                {{ $channel->status ? 'Active' : 'Set Inactive' }}
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Channel details --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        @if($channel->poster_url)
            <img src="{{ $channel->poster_url }}" class="img-fluid rounded" style="max-height:220px;">
        @else
            <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height:220px;">
                <span class="text-muted">No poster</span>
            </div>
        @endif
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-4">Owner</dt>
                    <dd class="col-8">
                        {{ $channel->owner?->first_name }} {{ $channel->owner?->last_name }}
                        <br><small class="text-muted">{{ $channel->owner?->email }}</small>
                    </dd>

                    <dt class="col-4">Slug</dt>
                    <dd class="col-8"><code>{{ $channel->slug }}</code></dd>

                    <dt class="col-4">Status</dt>
                    <dd class="col-8">
                        <span class="badge {{ $channel->status ? 'bg-success' : 'bg-secondary' }}">
                            {{ $channel->status ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>

                    <dt class="col-4">Total Videos</dt>
                    <dd class="col-8">{{ $channel->videos_count }}</dd>

                    <dt class="col-4">Created</dt>
                    <dd class="col-8">{{ $channel->created_at?->format('d M Y H:i') }}</dd>

                    @if($channel->description)
                    <dt class="col-4">Description</dt>
                    <dd class="col-8">{{ $channel->description }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

{{-- Videos in channel --}}
<h5 class="mb-3">Videos in this Channel</h5>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Poster</th>
                        <th>Title</th>
                        <th>Access</th>
                        <th>Duration</th>
                        <th>Release Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($videos as $video)
                    <tr>
                        <td>{{ $video->id }}</td>
                        <td>
                            @if($video->poster_url)
                                <img src="{{ $video->poster_url }}" style="max-width:50px;max-height:50px;" class="rounded">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $video->name }}</td>
                        <td><span class="badge bg-info-subtle text-dark">{{ $video->access }}</span></td>
                        <td>{{ $video->duration }}</td>
                        <td>{{ $video->release_date?->format('d M Y') }}</td>
                        <td>
                            <span class="badge {{ $video->status ? 'bg-success' : 'bg-secondary' }}">
                                {{ $video->status ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('backend.videos.edit', $video->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">No videos assigned to this channel yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $videos->links() }}
    </div>
</div>
@endsection
