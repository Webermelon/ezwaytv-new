<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckInstallation;
use App\Models\User;
use App\Services\CoreTvAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class ConnectSsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckInstallation::class);

        config([
            'connect.url' => 'https://connect.example.test',
            'connect.sso_client_id' => 'ezway-tv',
            'connect.sso_secret' => 'sso-test-secret',
            'connect.sso_timeout' => 2,
        ]);
    }

    public function test_connect_login_exchanges_code_and_creates_tv_session(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'connect.example.test/api/portal/sso/exchange' => Http::response([
                'data' => [
                    'id' => 42,
                    'network_id' => 1042,
                    'username' => 'member',
                    'email' => 'member@example.test',
                    'first_name' => 'Test',
                    'last_name' => 'Member',
                    'avatar' => '',
                ],
            ]),
        ]);

        $user = new User([
            'id' => 7,
            'email' => 'member@example.test',
            'first_name' => 'Test',
            'last_name' => 'Member',
        ]);
        $user->id = 7;

        $this->mock(CoreTvAccessService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('syncUser')
                ->once()
                ->withArgs(fn (array $data): bool => $data['core_user_id'] === 1042
                    && $data['connect_user_id'] === 42
                    && $data['email'] === 'member@example.test')
                ->andReturn($user);
        });

        $start = $this->get('/auth/connect/start?redirect=%2Fmanage-profile%3Ftab%3Dprofiles')
            ->assertRedirect();
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $authorizeQuery);

        $this->assertSame('ezway-tv', $authorizeQuery['client_id']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $authorizeQuery['state']);

        $this->get('/auth/connect/callback?'.http_build_query([
            'state' => $authorizeQuery['state'],
            'code' => str_repeat('a', 64),
        ]))->assertRedirect('/manage-profile?tab=profiles')
            ->assertSessionHas(Auth::guard()->getName(), 7)
            ->assertSessionMissing('connect_sso');

        Http::assertSent(function ($request) use ($authorizeQuery): bool {
            $timestamp = $request->header('X-Portal-Sso-Timestamp')[0] ?? '';
            $nonce = $request->header('X-Portal-Sso-Nonce')[0] ?? '';
            $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->body(), 'sso-test-secret');

            return $request->url() === 'https://connect.example.test/api/portal/sso/exchange'
                && $request->hasHeader('X-Portal-Sso-Signature', $signature)
                && $request['client_id'] === 'ezway-tv'
                && $request['state'] === $authorizeQuery['state'];
        });
    }

    public function test_invalid_state_fails_closed_without_calling_connect(): void
    {
        Http::preventStrayRequests();

        $this->get('/auth/connect/start?redirect=https%3A%2F%2Fevil.example')
            ->assertRedirect()
            ->assertSessionHas('connect_sso.destination', '/');

        $this->get('/auth/connect/callback?'.http_build_query([
            'state' => str_repeat('0', 64),
            'code' => str_repeat('a', 64),
        ]))->assertRedirect('/login?connect_status=failed&redirect=%2F')
            ->assertSessionMissing('connect_sso');

        Http::assertNothingSent();
    }

    public function test_connect_failure_returns_to_visible_login_error(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'connect.example.test/api/portal/sso/exchange' => Http::response([
                'message' => 'The authorization code is invalid or has expired.',
            ], 422),
        ]);

        $start = $this->get('/auth/connect/start')->assertRedirect();
        parse_str((string) parse_url($start->headers->get('Location'), PHP_URL_QUERY), $authorizeQuery);

        $this->get('/auth/connect/callback?'.http_build_query([
            'state' => $authorizeQuery['state'],
            'code' => str_repeat('a', 64),
        ]))->assertRedirect('/login?connect_status=failed&redirect=%2F')
            ->assertSessionMissing('connect_sso');
    }
}
