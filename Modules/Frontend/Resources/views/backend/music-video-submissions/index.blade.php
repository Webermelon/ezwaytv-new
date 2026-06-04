@extends('backend.layouts.app')

@section('title')
    Users Submission
@endsection

@section('content')
    <div class="card-main mb-5">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                <h4 class="mb-0">{{ $isTrash ? 'Users Submission Trash' : 'Users Submission' }}</h4>
            </div>

            <x-slot name="toolbar">
                <form method="GET" action="{{ $isTrash ? route('backend.users_submission.trash') : route('backend.users_submission.index') }}" class="d-flex flex-wrap gap-2">
                    @unless ($isTrash)
                        <select name="status" class="form-control" style="width: 180px">
                            <option value="">All Statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endunless
                    <div class="input-group flex-nowrap">
                        <span class="input-group-text pe-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search title, artist, email">
                    </div>
                    <button class="btn btn-primary" type="submit">Filter</button>
                    <a href="{{ $isTrash ? route('backend.users_submission.index') : route('backend.users_submission.trash') }}" class="btn btn-dark">
                        {{ $isTrash ? 'Back to Submissions' : 'Trash' }}
                    </a>
                </form>
            </x-slot>
        </x-backend.section-header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="text-muted small">Total</div><h3 class="mb-0">{{ $stats['total'] }}</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="text-muted small">Submitted</div><h3 class="mb-0">{{ $stats['submitted'] }}</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="text-muted small">Scheduled</div><h3 class="mb-0">{{ $stats['scheduled'] }}</h3></div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body"><div class="text-muted small">{{ $isTrash ? 'Trash' : 'Used' }}</div><h3 class="mb-0">{{ $isTrash ? $stats['trash'] : $stats['used'] }}</h3></div></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Poster</th>
                        <th>Submission</th>
                        <th>Channel</th>
                        <th>Submitter</th>
                        <th>Purchase</th>
                        <th>Status</th>
                        <th>Schedule</th>
                        <th>Uploaded</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td style="width: 96px">
                                <img src="{{ $submission->poster_url }}" alt="{{ $submission->title }}" class="rounded" style="width: 80px; aspect-ratio: 16/9; object-fit: cover;">
                            </td>
                            <td>
                                <strong>{{ $submission->title }}</strong>
                                @if ($submission->artist_name)
                                    <div class="text-muted small">{{ $submission->artist_name }}</div>
                                @endif
                                <div class="text-muted small">{{ $submission->video_original_name ?: basename($submission->video_path) }}</div>
                                <div class="text-muted small">{{ $submission->video_mime ?: '-' }} · {{ $submission->video_size_for_humans }}</div>
                            </td>
                            <td>{{ $submission->channel_name ?: $submission->channel_slug }}</td>
                            <td>
                                {{ $submission->submitter_name ?: '-' }}
                                @if ($submission->submitter_email)
                                    <div class="text-muted small">{{ $submission->submitter_email }}</div>
                                @endif
                                @if ($submission->submitter_phone)
                                    <div class="text-muted small">{{ $submission->submitter_phone }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $submission->purchase_reference ?: '-' }}
                                @if ($submission->purchase_confirmed_at)
                                    <div class="text-muted small">Confirmed {{ $submission->purchase_confirmed_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-dark">{{ $statuses[$submission->status] ?? $submission->status }}</span>
                            </td>
                            <td>{{ $submission->scheduled_at ? $submission->scheduled_at->format('M d, Y g:i A') : '-' }}</td>
                            <td>{{ $isTrash ? $submission->deleted_at->diffForHumans() : $submission->created_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    @if ($isTrash)
                                        <form method="POST" action="{{ route('backend.users_submission.restore', $submission->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Restore</button>
                                        </form>
                                        <form method="POST" action="{{ route('backend.users_submission.force_destroy', $submission->id) }}" onsubmit="return confirm('Permanently delete this submission and its uploaded poster/video files from storage? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Delete Forever</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('backend.users_submission.status', $submission) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $submission->status === 'scheduled' ? 'submitted' : 'scheduled' }}">
                                            <button type="submit" class="btn btn-sm {{ $submission->status === 'scheduled' ? 'btn-danger' : 'btn-success' }}">
                                                {{ $submission->status === 'scheduled' ? '- Schedule' : 'Scheduled' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('backend.users_submission.download', $submission) }}" class="btn btn-sm btn-dark">
                                            <i class="ph ph-download-simple"></i>
                                        </a>
                                        <a href="{{ route('backend.users_submission.show', $submission) }}" class="btn btn-sm btn-primary">Review</a>
                                        <form method="POST" action="{{ route('backend.users_submission.destroy', $submission) }}" onsubmit="return confirm('Move this user submission to trash? Uploaded files will remain until you delete it from trash.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">{{ $isTrash ? 'Trash is empty.' : 'No user submissions yet.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $submissions->links() }}
    </div>
@endsection
