@extends('backend.layouts.app')

@section('title') Email Logs @endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="card-title mb-0">
                <i class="ph ph-envelope-open"></i> Email Logs
            </h4>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="email" value="{{ request('email') }}" class="form-control" placeholder="Search recipient email">
            </div>
            <div class="col-md-4">
                <input type="text" name="subject" value="{{ request('subject') }}" class="form-control" placeholder="Search subject">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('backend.email-logs.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Mailable</th>
                        <th>Status</th>
                        <th>Sent At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->to_emails ?: '-' }}</td>
                            <td>{{ $log->subject ?: '-' }}</td>
                            <td><small>{{ $log->mailable_class ?: '-' }}</small></td>
                            <td>
                                @php
                                    $statusClass = match (strtolower((string) $log->status)) {
                                        'failed' => 'bg-danger',
                                        'queued' => 'bg-warning text-dark',
                                        default => 'bg-success',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ ucfirst($log->status ?: 'sent') }}</span>
                            </td>
                            <td>{{ optional($log->sent_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <a href="{{ route('backend.email-logs.show', $log->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No email logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</div>
@endsection
