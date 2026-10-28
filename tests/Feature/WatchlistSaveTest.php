<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFrontendMaintenance;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Entertainment\Models\Watchlist;
use Tests\TestCase;

class WatchlistSaveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutMiddleware([CheckInstallation::class, CheckFrontendMaintenance::class]);
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        (require database_path('migrations/2023_01_01_000010_create_users_table.php'))->up();
        (require database_path('migrations/2023_01_01_000021_create_permission_tables.php'))->up();
        (require base_path('Modules/Entertainment/database/migrations/2024_06_08_043704_create_watchlists_table.php'))->up();
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id');
            $table->unsignedBigInteger('active_profile')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
        $this->withSession(['_token' => 'current-session-token']);
    }

    public function test_stale_token_is_rejected_then_refresh_allows_persistent_save(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withHeader('X-CSRF-TOKEN', 'old-page-token')
            ->postJson('/account/watchlist/save', $this->item())->assertStatus(419);
        $this->assertSame(0, Watchlist::count());

        $response = $this->getJson('/auth/csrf-token')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->withHeader('X-CSRF-TOKEN', $response->json('csrf_token'))
            ->postJson('/account/watchlist/save', $this->item())->assertOk()->assertJsonPath('status', true);
        $this->assertDatabaseHas('watchlists', ['user_id' => $user->id, 'entertainment_id' => 119, 'type' => 'movie', 'deleted_at' => null]);

        $this->postJson('/account/watchlist/save', $this->item())->assertOk();
        $this->assertSame(1, Watchlist::count());
    }

    public function test_save_delete_and_save_again_work_for_the_current_profile(): void
    {
        $user = $this->user();
        DB::table('devices')->insert(['user_id' => $user->id, 'device_id' => '127.0.0.1', 'active_profile' => 7]);
        $this->actingAs($user)->withHeader('X-CSRF-TOKEN', 'current-session-token');
        $this->postJson('/account/watchlist/save', $this->item())->assertOk();
        $this->assertSame(7, (int) Watchlist::sole()->profile_id);
        $this->postJson('/account/watchlist/delete', $this->item())->assertOk();
        $this->assertSame(0, Watchlist::count());
        $this->postJson('/account/watchlist/save', $this->item())->assertOk();
        $this->assertSame(1, Watchlist::count());
    }

    public function test_removing_an_item_does_not_remove_another_users_saved_item(): void
    {
        $user = $this->user();
        $other = $this->user('other@example.test');
        Watchlist::create(['entertainment_id' => 119, 'type' => 'movie', 'user_id' => $other->id]);
        $this->actingAs($user)->withHeader('X-CSRF-TOKEN', 'current-session-token');
        $this->postJson('/account/watchlist/save', $this->item())->assertOk();
        $this->postJson('/account/watchlist/delete', $this->item())->assertOk();
        $this->assertSame($other->id, (int) Watchlist::sole()->user_id);
    }

    public function test_signed_out_save_returns_json_401_instead_of_a_login_page(): void
    {
        $this->withHeader('X-CSRF-TOKEN', 'current-session-token')
            ->postJson('/account/watchlist/save', $this->item())->assertUnauthorized()->assertJsonPath('status', false);
        $this->assertSame(0, Watchlist::count());
    }

    public function test_signed_out_browser_navigation_still_redirects_to_login(): void
    {
        $this->get('/account/settings-data')->assertRedirect(route('login-page'));
    }

    public function test_disabled_account_save_is_rejected_without_writing_an_item(): void
    {
        $user = $this->user();
        $user->update(['status' => 0]);
        $this->actingAs($user)->withHeader('X-CSRF-TOKEN', 'current-session-token')
            ->postJson('/account/watchlist/save', $this->item())->assertForbidden()->assertJsonPath('status', false);
        $this->assertSame(0, Watchlist::count());
    }

    private function user(string $email = 'watchlist@example.test'): User
    {
        return User::create(['email' => $email, 'password' => 'test-only-hash', 'status' => 1, 'user_type' => 'user']);
    }

    private function item(): array
    {
        return ['entertainment_id' => 119, 'type' => 'movie'];
    }
}
