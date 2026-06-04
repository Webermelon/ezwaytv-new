@extends('backend.layouts.app')

@section('title')
    Users Submission Details
@endsection

@section('content')
    <div class="card-main mb-5">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <a href="{{ route('backend.users_submission.index') }}" class="btn btn-sm btn-dark">
                    <i class="ph ph-arrow-left"></i> Back
                </a>
                <h4 class="mb-0">Users Submission Details</h4>
            </div>
        </x-backend.section-header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-body">
                        <video controls preload="metadata" poster="{{ $submission->poster_url }}" style="width: 100%; aspect-ratio: 16/9; background: #000;">
                            <source src="{{ $submission->video_url }}" type="{{ $submission->video_mime ?: 'video/mp4' }}">
                        </video>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="{{ route('backend.users_submission.download', $submission) }}" class="btn btn-primary">
                                <i class="ph ph-download-simple"></i> Download Video
                            </a>
                            <a href="{{ $submission->video_url }}" target="_blank" class="btn btn-dark">
                                <i class="ph ph-arrow-square-out"></i> Open Video URL
                            </a>
                            <a href="{{ $submission->poster_url }}" target="_blank" class="btn btn-dark">
                                <i class="ph ph-image"></i> Open Poster
                            </a>
                            <form method="POST" action="{{ route('backend.users_submission.destroy', $submission) }}" onsubmit="return confirm('Move this user submission to trash? Uploaded files will remain until you delete it from trash.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">
                                    <i class="ph ph-trash"></i> Move to Trash
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5>{{ $submission->title }}</h5>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Channel</dt>
                            <dd class="col-sm-8">{{ $submission->channel_name ?: $submission->channel_slug }}</dd>
                            <dt class="col-sm-4">Artist</dt>
                            <dd class="col-sm-8">{{ $submission->artist_name ?: '-' }}</dd>
                            <dt class="col-sm-4">Submitter</dt>
                            <dd class="col-sm-8">{{ $submission->submitter_name ?: '-' }}</dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">{{ $submission->submitter_email ?: '-' }}</dd>
                            <dt class="col-sm-4">Phone</dt>
                            <dd class="col-sm-8">{{ $submission->submitter_phone ?: '-' }}</dd>
                            <dt class="col-sm-4">Purchase</dt>
                            <dd class="col-sm-8">
                                {{ $submission->purchase_reference ?: 'Confirmed without reference' }}
                                @if ($submission->purchase_confirmed_at)
                                    <div class="text-muted small">{{ $submission->purchase_confirmed_at->format('M d, Y g:i A') }}</div>
                                @endif
                            </dd>
                            <dt class="col-sm-4">File</dt>
                            <dd class="col-sm-8">{{ $submission->video_original_name ?: basename($submission->video_path) }}</dd>
                            <dt class="col-sm-4">Size</dt>
                            <dd class="col-sm-8">{{ $submission->video_size_for_humans }}</dd>
                            <dt class="col-sm-4">Notes</dt>
                            <dd class="col-sm-8">{{ $submission->notes ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <form method="POST" action="{{ route('backend.users_submission.status', $submission) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $submission->status === 'scheduled' ? 'submitted' : 'scheduled' }}">
                                <button type="submit" class="btn {{ $submission->status === 'scheduled' ? 'btn-danger' : 'btn-success' }}">
                                    {{ $submission->status === 'scheduled' ? '- Schedule' : 'Scheduled' }}
                                </button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('backend.users_submission.update', $submission) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-control">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $submission->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="scheduled_at" class="form-label">Schedule Date / Time</label>
                                <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $submission->scheduled_at ? $submission->scheduled_at->format('Y-m-d\TH:i') : '') }}" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="admin_notes" class="form-label">Backend Notes</label>
                                <textarea id="admin_notes" name="admin_notes" rows="5" class="form-control">{{ old('admin_notes', $submission->admin_notes) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
