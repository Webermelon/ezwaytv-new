<?php

namespace App\Listeners\Mail;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LogSentEmail
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            // Avoid failures during install/early bootstrap before migrations are applied.
            if (!Schema::hasTable('email_logs')) {
                return;
            }

            $message = $event->message;

            $to = collect($message->getTo() ?? [])->map(fn($address) => $address->getAddress())->implode(',');
            $cc = collect($message->getCc() ?? [])->map(fn($address) => $address->getAddress())->implode(',');
            $bcc = collect($message->getBcc() ?? [])->map(fn($address) => $address->getAddress())->implode(',');
            $from = collect($message->getFrom() ?? [])->map(fn($address) => $address->getAddress())->first();

            $payload = json_encode($event->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                $payload = null;
            }

            $mailableClass = null;
            if (isset($event->data['__laravel_notification']) && is_object($event->data['__laravel_notification'])) {
                $mailableClass = get_class($event->data['__laravel_notification']);
            }

            if ($mailableClass === null && isset($event->data['__laravel_mailable']) && is_object($event->data['__laravel_mailable'])) {
                $mailableClass = get_class($event->data['__laravel_mailable']);
            }

            EmailLog::create([
                'mailer' => config('mail.default'),
                'from_email' => $from,
                'to_emails' => $to,
                'cc_emails' => $cc,
                'bcc_emails' => $bcc,
                'subject' => $message->getSubject(),
                'mailable_class' => $mailableClass,
                'message_id' => $message->getMessageId(),
                'status' => 'sent',
                'body' => $message->getHtmlBody() ?: $message->getTextBody(),
                'payload' => $payload,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Email log write failed: ' . $e->getMessage());
        }
    }
}
