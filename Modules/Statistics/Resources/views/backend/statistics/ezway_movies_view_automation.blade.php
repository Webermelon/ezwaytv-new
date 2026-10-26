@extends('backend.layouts.app')

@section('title', $module_title)

@section('content')
<div class="card-main mb-5" style="max-width:720px">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0 fw-bold">Live TV View Automation</h3>
            <p class="text-muted mb-0 small">Admin-only scheduled display-count configuration. Runs at :00 and :30 UTC.</p>
            <p class="mb-0 small text-warning">Next run: {{ $nextRun->format('H:i') }} UTC (in {{ $minutesUntilNextRun }} {{ $minutesUntilNextRun === 1 ? 'minute' : 'minutes' }})</p>
        </div>
        <a href="{{ route('backend.statistics.booster') }}" class="btn btn-sm btn-dark">Content Booster</a>
    </div>

    <form action="{{ route('backend.statistics.live_tv_view_automation.save') }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Enable</th>
                            <th>Views per half-hour</th>
                            <th>Times run</th>
                            <th>Last processed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($channels as $channel)
                            @php($row = $config[$channel->id])
                            <tr>
                                <td><div class="fw-semibold">{{ $channel->name }}</div><div class="small text-muted">{{ $channel->slug }}</div></td>
                                <td>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="channels[{{ $channel->id }}][enabled]" value="1" {{ $row['enabled'] ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td>
                                    <input class="form-control" type="number" name="channels[{{ $channel->id }}][increment]" min="1" max="100000" value="{{ old("channels.{$channel->id}.increment", $row['increment']) }}" required>
                                    @error("channels.{$channel->id}.increment")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </td>
                                <td>{{ number_format($row['run_count']) }}</td>
                                <td class="small text-muted">{{ $row['last_window'] ?? 'Not run yet' }} UTC</td>
                                <td class="text-end">
                                    <form action="{{ route('backend.statistics.live_tv_view_automation.run', $channel->id) }}" method="POST" onsubmit="return confirm('Run {{ $channel->name }} now?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary" {{ $row['enabled'] ? '' : 'disabled' }}>Run now</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save channel settings</button>
        </div>
    </form>
</div>
@endsection
