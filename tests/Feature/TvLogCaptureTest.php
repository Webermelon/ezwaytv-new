<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFrontendMaintenance;
use App\Http\Middleware\CheckInstallation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TvLogCaptureTest extends TestCase
{
    private string $spool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([CheckInstallation::class, CheckFrontendMaintenance::class]);
        $this->spool = sys_get_temp_dir().'/tv-logging-test-'.bin2hex(random_bytes(8));
        foreach (['critical', 'analytics', 'quarantine'] as $directory) {
            mkdir($this->spool.'/'.$directory, 0700, true);
        }

        config([
            'tv-logs.enabled' => true,
            'tv-logs.spool' => $this->spool,
            'database.connections.tv_logs' => array_merge(config('database.connections.tv_logs'), [
                'host' => '127.0.0.1',
                'port' => 1,
                'username' => 'unused',
                'password' => '',
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->spool);
        parent::tearDown();
    }

    public function test_admin_password_login_and_admin_action_work_with_log_database_down(): void
    {
        (require database_path('migrations/2023_01_01_000010_create_users_table.php'))->up();
        (require database_path('migrations/2023_01_01_000021_create_permission_tables.php'))->up();

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->softDeletes();
        });
        Schema::create('devices', function (Blueprint $table): void {
            $table->string('session_id');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });

        $admin = User::create(['email' => 'audit-test@example.test', 'password' => Hash::make('test-password'), 'user_type' => 'admin', 'status' => 1]);
        $admin->assignRole(Role::create(['name' => 'admin', 'title' => 'Admin', 'guard_name' => 'web']));
        Artisan::shouldReceive('call')->andReturn(0);
        Route::middleware('web')->post('/app/tv-log-test/{record}', fn () => response()->json(['success' => true]))
            ->name('backend.tv-log-test.update');

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'incorrect-password'])
            ->assertRedirect();
        $this->assertGuest();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'test-password'])
            ->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->postJson('/app/tv-log-test/123', ['secret' => 'test-hidden-value'])->assertOk();

        $failed = $this->event('critical', 'auth.login_failed');
        $this->assertSame('invalid_credentials', $failed['reason_code']);
        $this->assertNotEmpty($failed['account_fingerprint']);
        $this->assertSame($admin->id, $this->event('critical', 'auth.login_succeeded')['tv_user_id']);
        $audit = $this->event('critical', 'admin.request');
        $this->assertSame('accepted', $audit['outcome']);
        $this->assertSame('123', $audit['target_id']);
        $this->assertStringNotContainsString('test-hidden-value', $audit['details']);
    }

    public function test_playback_endpoints_and_request_contract_survive_failed_delivery(): void
    {
        foreach ([
            '2026_04_04_000001_create_page_views_table.php',
            '2026_04_04_000002_create_play_events_table.php',
            '2026_04_04_000003_create_stat_settings_table.php',
            '2026_04_04_122049_add_page_name_to_stat_page_views.php',
        ] as $migration) {
            (require base_path('Modules/Statistics/database/migrations/'.$migration))->up();
        }
        foreach (['stat_page_views', 'stat_play_events'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->unsignedBigInteger('channel_id')->nullable());
        }

        $this->postJson('/api/statistics/track-view', [
            'content_type' => 'video', 'content_id' => 7,
            'page_url' => 'https://ezway.tv/video-details/7?utm_medium=email&token=secret-token',
            'referrer' => 'https://example.test/path?email=hidden@example.test',
        ])->assertOk()->assertExactJson(['status' => 'ok']);

        $play = $this->postJson('/api/statistics/track-play', ['content_type' => 'video', 'content_id' => 7])
            ->assertOk()->assertJsonPath('status', 'ok')->json('play_id');
        $this->postJson('/api/statistics/update-watch-time', ['play_id' => $play, 'watch_seconds' => 16])
            ->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertDatabaseHas('stat_play_events', ['id' => $play, 'watch_seconds' => 16]);
        $view = $this->event('analytics', 'viewer.page_view');
        $this->assertSame('email', $view['utm_medium']);
        $this->assertSame('example.test', $view['referrer_domain']);
        $this->assertStringNotContainsString('secret-token', json_encode($view));
        $this->assertSame($this->event('analytics', 'playback.legacy_play')['playback_id'], $this->event('analytics', 'playback.legacy_position')['playback_id']);

        $this->artisan('tv-logs:flush')->assertExitCode(1);
        $this->assertCount(3, glob($this->spool.'/analytics/*.json'));
    }

    public function test_system_exceptions_are_redacted_before_enqueueing(): void
    {
        report(new RuntimeException('secret-request-body'));

        $event = $this->event('critical', 'system.exception');
        $this->assertSame(RuntimeException::class, $event['error_code']);
        $this->assertStringNotContainsString('secret-request-body', json_encode($event));
    }

    private function event(string $directory, string $name): array
    {
        foreach (glob($this->spool.'/'.$directory.'/*.json') as $path) {
            $row = json_decode(file_get_contents($path), true)['row'];
            if ($row['event_name'] === $name) {
                return $row;
            }
        }

        $this->fail('Missing event '.$name);
    }
}
