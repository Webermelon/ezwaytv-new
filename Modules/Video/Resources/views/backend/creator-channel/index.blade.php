@extends('backend.layouts.app')

@section('title', $module_title)

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0"><i class="{{ $module_icon }}"></i> {{ $module_title }}</h4>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ __('messages.name') }}</th>
                        <th>Owner</th>
                        <th>Videos</th>
                        <th>{{ __('plan.lbl_status') }}</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($channels as $channel)
                    <tr class="{{ $channel->deleted_at ? 'table-danger' : '' }}">
                        <td>{{ $channel->id }}</td>
                        <td>
                            <a href="{{ route('backend.creator-channels.show', $channel->id) }}">
                                {{ $channel->name }}
                            </a>
                            @if($channel->deleted_at)
                                <span class="badge bg-danger ms-1">Deleted</span>
                            @endif
                        </td>
                        <td>
                            @if($channel->owner)
                                {{ $channel->owner->first_name }} {{ $channel->owner->last_name }}<br>
                                <small class="text-muted">{{ $channel->owner->email }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $channel->videos_count }}</td>
                        <td>
                            @if(!$channel->deleted_at)
                                <form action="{{ route('backend.creator-channels.toggle-status', $channel->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $channel->status ? 'btn-success' : 'btn-secondary' }}">
                                        {{ $channel->status ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-danger">Deleted</span>
                            @endif
                        </td>
                        <td>{{ $channel->created_at?->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('backend.creator-channels.show', $channel->id) }}" class="btn btn-sm btn-info">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @if($channel->deleted_at)
                                <form action="{{ route('backend.creator-channels.restore', $channel->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fa-solid fa-rotate-left"></i> Restore
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('backend.creator-channels.destroy', $channel->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this channel?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No creator channels found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $channels->links() }}
    </div>
</div>
@endsection
