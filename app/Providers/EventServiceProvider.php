<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use App\Listeners\Mail\LogSentEmail;
use App\Listeners\Notification\LogMailNotificationSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Registered::class => [
        //     SendEmailVerificationNotification::class,
        // ],
        'App\Events\Auth\UserLoginSuccess' => [
        ],
        'App\Events\Backend\UserCreated' => [
            'App\Listeners\Backend\UserCreated\UserCreatedNotifySuperUser',
        ],
        'App\Events\Backend\UserUpdated' => [
            'App\Listeners\Backend\UserUpdated\UserUpdatedNotifyUser',
        ],

        'App\Events\Frontend\UserRegistered' => [
            'App\Listeners\Frontend\UserRegistered\UserRegisteredListener',
        ],
        'App\Events\Frontend\UserUpdated' => [
            'App\Listeners\Frontend\UserUpdated\UserUpdatedNotifyUser',
        ],
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],

        MessageSent::class => [
            LogSentEmail::class,
        ],

        NotificationSent::class => [
            LogMailNotificationSent::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Queue\Events\JobFailed::class,
            static function (\Illuminate\Queue\Events\JobFailed $event): void {
                $job = $event->job;
                \App\Services\TvLog::record('operation_events', 'system.job_failed', [
                    'run_id' => (string) ($job->getJobId() ?? \Illuminate\Support\Str::uuid()),
                    'operation_type' => 'queue',
                    'operation_name' => substr($job->resolveName(), 0, 96),
                    'attempt_no' => $job->attempts(),
                    'outcome' => 'failed',
                    'error_code' => substr(get_class($event->exception), 0, 96),
                ]);
            }
        );
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskFailed::class,
            static function (\Illuminate\Console\Events\ScheduledTaskFailed $event): void {
                // Do not store the scheduler command line, which can contain arguments.
                \App\Services\TvLog::record('operation_events', 'system.scheduled_task_failed', [
                    'run_id' => (string) \Illuminate\Support\Str::uuid(),
                    'operation_type' => 'scheduler',
                    'operation_name' => 'task',
                    'outcome' => 'failed',
                    'error_code' => substr(get_class($event->exception), 0, 96),
                ]);
            }
        );
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
