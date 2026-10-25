<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CheckFrontendMaintenance;
use App\Http\Middleware\CheckInstallation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConnectSsoTest extends TestCase
{
    private const STATE = 'saved-random-state';
    private const CODE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        // These tests must never use the site's configured database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config([
            'connect_sso.url' => 'https://connect.example.test',
            'connect_sso.client_id' => 'ezway-tv',
            'connect_sso.secret' => 'test-only-connect-secret',
            'app.debug' => false,
        ]);
        $this->withoutMiddleware([CheckInstallation::class, CheckFrontendMaintenance::class]);
        Http::preventStrayRequests();

        (require database_path('migrations/2023_01_01_000010_create_users_table.php'))->up();
        $this->migration()->up();
        (require database_path('migrations/2023_01_01_000021_create_permission_tables.php'))->up();
        (require database_path('migrations/2024_09_18_111806_create_user_multi_profiles_table.php'))->up();
        Role::create(['name' => 'user', 'title' => 'User', 'guard_name' => 'web']);
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->unsignedBigInteger('active_profile')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    public function test_start_saves_state_and_local_destination_and_forwards_registration(): void
    {
        $response = $this->get('/auth/connect/start?register=1&redirect_to='.urlencode('/videos/123?from=home'));
        $response->assertRedirect();
        $url = parse_url($response->headers->get('Location'));
        $this->assertSame('connect.example.test', $url['host']);
        $this->assertSame('/portal/authorize', $url['path']);
        parse_str($url['query'], $query);
        $this->assertSame('ezway-tv', $query['client_id']);
        $this->assertSame('1', $query['register']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $query['state']);
        $response->assertSessionHas('connect_sso.state', $query['state']);
        $response->assertSessionHas('connect_sso.destination', '/videos/123?from=home');
        $this->assertStringNotContainsString('test-only-connect-secret', $response->headers->get('Location'));
        Http::assertNothingSent();
    }

    /** @dataProvider unsafeDestinations */
    public function test_start_rejects_unsafe_destinations(mixed $destination): void
    {
        $this->get('/auth/connect/start?'.http_build_query(['redirect_to' => $destination]))
            ->assertRedirect()->assertSessionHas('connect_sso.destination', '/');
    }

    public static function unsafeDestinations(): array
    {
        return array_map(fn ($value) => [$value], [
            'https://evil.test', '//evil.test', '/\\evil.test', "/\nevil.test", "/\tevil.test", ['bad'],
        ]);
    }

    public function test_start_requires_a_secret_and_https_connect_url(): void
    {
        config(['connect_sso.secret' => '']);
        $this->get('/auth/connect/start')->assertStatus(503)->assertSessionMissing('connect_sso');
        config(['connect_sso.secret' => 'secret', 'connect_sso.url' => 'http://connect.example.test']);
        $this->get('/auth/connect/start')->assertStatus(503)->assertSessionMissing('connect_sso');
        Http::assertNothingSent();
    }

    public function test_callback_signs_exact_body_and_logs_in_only_linked_identity(): void
    {
        $user = $this->user(['connect_user_id' => 42]);
        $this->user(['email' => 'other@example.test', 'connect_user_id' => 43]);
        Http::fake(['connect.example.test/*' => Http::response(['data' => ['user_id' => 42, 'email' => 'other@example.test']])]);
        $this->withSession(['tv_pending_core_login' => ['email' => 'old@example.test']]);
        $sessionId = session()->getId();
        // LoginTrait's browser detector reads PHP's server globals.
        $originalUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';
        try {
            $this->intent('/videos/123?from=home')->get($this->callbackUrl())
                ->assertRedirect('/videos/123?from=home')
                ->assertSessionMissing('connect_sso')->assertSessionMissing('tv_pending_core_login');
        } finally {
            if ($originalUserAgent === null) {
                unset($_SERVER['HTTP_USER_AGENT']);
            } else {
                $_SERVER['HTTP_USER_AGENT'] = $originalUserAgent;
            }
        }
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'session_id' => session()->getId()]);
        Http::assertSent(function (ClientRequest $request): bool {
            $body = json_encode(['client_id' => 'ezway-tv', 'code' => self::CODE, 'state' => self::STATE], JSON_THROW_ON_ERROR);
            $timestamp = $request->header('X-Portal-Sso-Timestamp')[0];
            $nonce = $request->header('X-Portal-Sso-Nonce')[0];

            return $request->method() === 'POST'
                && $request->url() === 'https://connect.example.test/api/portal/sso/exchange'
                && $request->body() === $body
                && preg_match('/^[a-f0-9]{32}$/', $nonce) === 1
                && abs((int) $timestamp - time()) < 60
                && $request->hasHeader('X-Portal-Sso-Signature', hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, 'test-only-connect-secret'));
        });
        Http::assertSentCount(1);
    }

    public function test_successful_callback_cannot_be_replayed(): void
    {
        $this->user(['connect_user_id' => 42]);
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42]])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/');
        Auth::guard('web')->logout();
        $this->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    /** @dataProvider invalidCallbacks */
    public function test_invalid_callback_never_exchanges(array $query, bool $expired = false): void
    {
        $this->intent('/', $expired)->get('/auth/connect/callback?'.http_build_query($query))
            ->assertRedirect('/login?connect_status=failed')->assertSessionMissing('connect_sso');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public static function invalidCallbacks(): array
    {
        return [
            'missing state' => [['code' => self::CODE]],
            'wrong state' => [['state' => 'wrong', 'code' => self::CODE]],
            'array state' => [['state' => ['invalid'], 'code' => self::CODE]],
            'missing code' => [['state' => self::STATE]],
            'bad code' => [['state' => self::STATE, 'code' => 'invalid']],
            'array code' => [['state' => self::STATE, 'code' => ['invalid']]],
            'expired intent' => [['state' => self::STATE, 'code' => self::CODE], true],
        ];
    }

    public function test_missing_intent_and_consumed_intent_cannot_be_reused(): void
    {
        $this->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->intent()->get($this->callbackUrl('wrong'))->assertRedirect('/login?connect_status=failed');
        $this->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        Http::assertNothingSent();
    }

    public function test_cancellation_requires_valid_state_and_does_not_exchange(): void
    {
        $this->intent()->get('/auth/connect/callback?state='.self::STATE.'&error=access_denied')
            ->assertRedirect('/login?connect_status=cancelled')->assertSessionMissing('connect_sso');
        Http::assertNothingSent();
    }

    public function test_new_connect_identity_creates_regular_account_and_profile_once(): void
    {
        $identity = [
            'user_id' => 42, 'email' => 'NEW@Example.test', 'name' => 'Connect Member', 'username' => 'connect.member',
            'user_type' => 'admin', 'roles' => ['super-admin'], 'is_subscribe' => 1, 'status' => 0,
        ];
        Http::fake(['*' => Http::response(['data' => $identity])]);
        $this->intent('/videos')->get($this->callbackUrl())->assertRedirect('/videos');
        $user = User::where('connect_user_id', 42)->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('new@example.test', $user->email);
        $this->assertSame('Connect', $user->first_name);
        $this->assertSame('Member', $user->last_name);
        $this->assertSame('connect.member', $user->username);
        $this->assertSame('connect_sso', $user->login_type);
        $this->assertSame('user', $user->user_type);
        $this->assertSame(1, (int) $user->status);
        $this->assertSame(0, (int) $user->is_subscribe);
        $this->assertNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);
        $this->assertSame(['user'], $user->getRoleNames()->all());
        $this->assertDatabaseHas('user_multi_profiles', ['user_id' => $user->id, 'name' => 'Connect']);

        // The stable ID remains authoritative even if Connect's email later changes.
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'changed@example.test']])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/');
        $this->assertSame(1, User::count());
        $this->assertSame('new@example.test', $user->fresh()->email);
        $this->assertSame(1, DB::table('user_multi_profiles')->count());
    }

    public function test_existing_email_signs_in_without_a_separate_tv_login(): void
    {
        $user = $this->user(['email' => 'same@example.test', 'is_subscribe' => 1, 'login_type' => 'otp', 'first_name' => 'Original']);
        $user->assignRole('user');
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'same@example.test']])]);
        $this->intent('/auth/connect/test')->get($this->callbackUrl())->assertRedirect('/auth/connect/test');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame(42, (int) $user->fresh()->connect_user_id);
        $this->assertSame(1, (int) $user->fresh()->is_subscribe);
        $this->assertSame('otp', $user->fresh()->login_type);
        $this->assertSame('Original', $user->fresh()->first_name);
        $this->assertSame('test-password-hash', $user->fresh()->password);
        $this->assertSame(['user'], $user->roles()->pluck('name')->all());
    }

    public function test_email_match_is_case_insensitive(): void
    {
        $user = $this->user(['email' => 'same@example.test', 'is_subscribe' => 1, 'login_type' => 'otp']);
        $user->assignRole('user');
        $this->get('/auth/connect/start')->assertRedirect();
        $state = session('connect_sso.state');
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'Same@Example.test']])]);
        $this->get($this->callbackUrl($state))->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(42, (int) $user->fresh()->connect_user_id);
        $this->assertSame(1, (int) $user->fresh()->is_subscribe);
        $this->assertSame('otp', $user->fresh()->login_type);
        $this->assertSame(1, User::count());
    }

    public function test_signed_connect_email_selects_the_account_even_with_another_tv_session(): void
    {
        $user = $this->user(['email' => 'same@example.test']);
        $otherUser = $this->user(['email' => 'other@example.test']);
        $this->actingAs($otherUser);
        $this->get('/auth/connect/start')->assertRedirect();
        $state = session('connect_sso.state');
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'same@example.test']])]);
        $this->get($this->callbackUrl($state))->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(42, (int) $user->fresh()->connect_user_id);
        $this->assertNull($otherUser->fresh()->connect_user_id);
    }

    public function test_existing_link_cannot_be_replaced_by_another_connect_identity(): void
    {
        $user = $this->user(['email' => 'same@example.test', 'connect_user_id' => 99]);
        $this->get('/auth/connect/start')->assertRedirect();
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'same@example.test']])]);
        $this->get($this->callbackUrl(session('connect_sso.state')))->assertRedirect('/login?connect_status=identity_conflict');
        $this->assertGuest();
        $this->assertSame(99, (int) $user->fresh()->connect_user_id);
    }

    public function test_browser_email_cannot_override_the_signed_connect_email(): void
    {
        $user = $this->user(['email' => 'trusted@example.test']);
        $otherUser = $this->user(['email' => 'browser@example.test']);
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'trusted@example.test']])]);
        $this->intent()->get($this->callbackUrl().'&email=browser%40example.test')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($otherUser->fresh()->connect_user_id);
    }

    public function test_ambiguous_email_matches_do_not_select_an_arbitrary_account(): void
    {
        $this->user(['email' => 'same@example.test']);
        $this->user(['email' => 'SAME@example.test']);
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'same@example.test']])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=identity_conflict');
        $this->assertGuest();
        $this->assertSame(0, User::whereNotNull('connect_user_id')->count());
    }

    /** @dataProvider unavailableUsers */
    public function test_new_identity_cannot_bypass_an_unavailable_local_account(array $attributes): void
    {
        $this->user(array_merge(['email' => 'same@example.test'], $attributes));
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'same@example.test']])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=disabled');
        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->count());
        $this->assertNull(User::withTrashed()->first()->connect_user_id);
    }

    public function test_missing_email_on_new_identity_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42]])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_username_collision_generates_an_available_username(): void
    {
        $this->user(['username' => 'taken']);
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'new@example.test', 'username' => 'taken']])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/');
        $this->assertStringStartsWith('connect-42-', User::where('connect_user_id', 42)->sole()->username);
    }

    public function test_profile_failure_rolls_back_the_new_account_and_role(): void
    {
        Schema::drop('user_multi_profiles');
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42, 'email' => 'new@example.test']])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    /** @dataProvider unavailableUsers */
    public function test_unavailable_local_users_are_rejected(array $attributes, string $status): void
    {
        $user = $this->user(array_merge(['connect_user_id' => 42], $attributes));
        Http::fake(['*' => Http::response(['data' => ['user_id' => 42]])]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status='.$status);
        $this->assertGuest();
        $this->assertSame($attributes['status'] ?? 1, (int) $user->fresh()->status);
    }

    public static function unavailableUsers(): array
    {
        return [
            'disabled' => [['status' => 0], 'disabled'],
            'banned' => [['is_banned' => 1], 'disabled'],
            'deleted' => [['deleted_at' => '2026-01-01 00:00:00'], 'disabled'],
        ];
    }

    /** @dataProvider rejectedResponses */
    public function test_failed_or_malformed_exchange_does_not_authenticate(mixed $body, int $status): void
    {
        $this->user(['connect_user_id' => 42]);
        Http::fake(['*' => Http::response($body, $status)]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->assertGuest();
    }

    public static function rejectedResponses(): array
    {
        return [
            'expired or reused code' => [['message' => 'Invalid code'], 422],
            'inactive Connect user' => [['message' => 'Inactive user'], 403],
            'bad signature or nonce' => [['message' => 'Unauthorized'], 401],
            'redirect' => [['data' => ['user_id' => 42]], 302],
            'server failure' => [['data' => ['user_id' => 42]], 500],
            'invalid json' => ['<html>Unavailable</html>', 200],
            'missing identity' => [[], 200],
            'malformed identity' => [['data' => 'bad'], 200],
            'malformed id' => [['data' => ['user_id' => '42oops']], 200],
            'boolean id' => [['data' => ['user_id' => true]], 200],
            'array id' => [['data' => ['user_id' => [42]]], 200],
            'nonpositive id' => [['data' => ['user_id' => 0]], 200],
        ];
    }

    public function test_network_failure_does_not_retry_or_authenticate(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->intent()->get($this->callbackUrl())->assertRedirect('/login?connect_status=failed');
        $this->assertGuest();
    }

    public function test_connect_fallback_reaches_real_login_route(): void
    {
        $this->get('/auth/login?connect_status=failed')->assertRedirect('/login?connect_status=failed');
        $this->get('/auth/login?connect_status[]=failed')->assertRedirect('/login');
    }

    public function test_identity_mapping_is_guarded_and_migration_is_reversible(): void
    {
        $user = new User(['connect_user_id' => 42]);
        $this->assertNull($user->connect_user_id);
        $this->assertContains('connect_user_id', $user->getHidden());
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('users', 'connect_user_id'));
        $this->migration()->up();
        $this->assertTrue(Schema::hasColumn('users', 'connect_user_id'));
    }

    public function test_identity_mapping_is_unique_but_allows_unlinked_accounts(): void
    {
        $this->user(['email' => 'one@example.test']);
        $this->user(['email' => 'two@example.test']);
        $this->user(['connect_user_id' => 42]);
        $this->expectException(QueryException::class);
        $this->user(['email' => 'duplicate@example.test', 'connect_user_id' => 42]);
    }

    private function user(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill(array_merge(['email' => 'tv@example.test', 'password' => 'test-password-hash', 'status' => 1, 'is_banned' => 0], $attributes))->save();

        return $user;
    }

    private function intent(string $destination = '/', bool $expired = false): static
    {
        return $this->withSession(['connect_sso' => [
            'state' => self::STATE,
            'destination' => $destination,
            'expires_at' => now()->timestamp + ($expired ? -1 : 1800),
        ]]);
    }

    private function callbackUrl(string $state = self::STATE): string
    {
        return '/auth/connect/callback?'.http_build_query(['code' => self::CODE, 'state' => $state]);
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_10_22_000001_add_connect_user_id_to_users_table.php');
    }
}
