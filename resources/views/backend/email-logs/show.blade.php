@extends('backend.layouts.app')

@section('title') Email Log Details @endsection

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">
            <i class="ph ph-envelope-open"></i> Email Log #{{ $log->id }}
        </h4>
        <a href="{{ route('backend.email-logs.index') }}" class="btn btn-light">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><strong>Status:</strong> {{ ucfirst($log->status ?: 'sent') }}</div>
            <div class="col-md-6"><strong>Mailer:</strong> {{ $log->mailer ?: '-' }}</div>
            <div class="col-md-6"><strong>From:</strong> {{ $log->from_email ?: '-' }}</div>
            <div class="col-md-6"><strong>To:</strong> {{ $log->to_emails ?: '-' }}</div>
            <div class="col-md-6"><strong>CC:</strong> {{ $log->cc_emails ?: '-' }}</div>
            <div class="col-md-6"><strong>BCC:</strong> {{ $log->bcc_emails ?: '-' }}</div>
            <div class="col-md-6"><strong>Subject:</strong> {{ $log->subject ?: '-' }}</div>
            <div class="col-md-6"><strong>Mailable:</strong> {{ $log->mailable_class ?: '-' }}</div>
            <div class="col-md-6"><strong>Message ID:</strong> {{ $log->message_id ?: '-' }}</div>
            <div class="col-md-6"><strong>Sent At:</strong> {{ optional($log->sent_at)->format('Y-m-d H:i:s') ?: '-' }}</div>
            @if(is_array($decodedPayload) && !empty($decodedPayload['reset_link']))
                <div class="col-12"><strong>Reset Link:</strong> <a href="{{ $decodedPayload['reset_link'] }}" target="_blank">{{ $decodedPayload['reset_link'] }}</a></div>
            @endif
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Email Body (Rendered)</strong></div>
    <div class="card-body">
        @if(!empty($log->body))
            <div class="border rounded p-3" style="max-height: 420px; overflow: auto; background: #fff;">
                {!! $log->body !!}
            </div>
        @else
            <div class="text-muted">No body stored.</div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Email Body (Raw Source)</strong></div>
    <div class="card-body">
        @if(!empty($log->body))
            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ $log->body }}</pre>
        @else
            <div class="text-muted">No body stored.</div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Payload</strong></div>
    <div class="card-body">
        @if(is_array($decodedPayload))
            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @elseif(!empty($log->payload))
            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ $log->payload }}</pre>
        @else
            <div class="text-muted">No payload stored.</div>
        @endif
    </div>
</div>
@endsection
