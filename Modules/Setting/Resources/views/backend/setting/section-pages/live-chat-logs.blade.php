@extends('setting::backend.setting.index')

@section('title')
    Live Chat Logs
@endsection

@section('settings-content')
    <div class="card">
        <div class="card-header p-0 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h3 class="mb-0"><i class="ph ph-chats-circle"></i> Live Chat Logs</h3>
            <a href="{{ route('backend.settings.misc') }}" class="btn btn-dark">Back To Misc</a>
        </div>

        <div class="card-body p-0">
            <form method="GET" action="{{ route('backend.settings.live-chat-logs.index') }}" class="row gy-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="Search by message, anonymous name, or channel"
                    >
                </div>
                <div class="col-md-4">
                    <label class="form-label">Channel</label>
                    <select name="channel_id" class="form-control select2">
                        <option value="">All channels</option>
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->id }}" {{ (string) request('channel_id') === (string) $channel->id ? 'selected' : '' }}>
                                {{ $channel->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('backend.settings.live-chat-logs.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Channel</th>
                            <th>Anonymous Name</th>
                            <th>Message</th>
                            <th>Posted</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>{{ optional($log->channel)->name ?? 'Unknown channel' }}</td>
                                <td>{{ $log->guest_name }}</td>
                                <td style="max-width: 520px; white-space: normal;">{{ $log->message }}</td>
                                <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('backend.settings.live-chat-logs.destroy', $log->id) }}" onsubmit="return confirm('Delete this chat log?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger-subtle btn-sm">
                                            <i class="ph ph-trash align-middle"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">No live chat logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
@endsection