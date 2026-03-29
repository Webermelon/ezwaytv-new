<?php

namespace App\Listeners\Notification;

use App\Models\EmailLog;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LogMailNotificationSent
{
    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        try {
            if ($event->channel !== 'mail' || !Schema::hasTable('email_logs')) {
                return;
            }

            $notification = $event->notification;
            if (!method_exists($notification, 'toMail')) {
                return;
            }

            $mailMessage = $notification->toMail($event->notifiable);
            $to = $event->notifiable->routeNotificationFor('mail', $notification);

            if (is_array($to)) {
                $to = implode(',', $to);
            }

            $subject = null;
            if (isset($mailMessage->subject) && is_string($mailMessage->subject)) {
                $subject = $mailMessage->subject;
            }

            // Prevent duplicates when MessageSent listener already recorded this email.
            $alreadyLogged = EmailLog::query()
                ->where('to_emails', (string) $to)
                ->where('subject', $subject)
                ->where('sent_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($alreadyLogged) {
                return;
            }

            EmailLog::create([
                'mailer' => config('mail.default'),
                'from_email' => config('mail.from.address'),
                'to_emails' => (string) $to,
                'subject' => $subject,
                'mailable_class' => get_class($notification),
                'message_id' => null,
                'status' => 'sent',
                'body' => null,
                'payload' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notification mail log write failed: ' . $e->getMessage());
        }
    }
}
