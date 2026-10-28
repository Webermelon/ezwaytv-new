<?php

namespace Tests\Feature;

use App\Http\Controllers\CoreCheckoutBridgeController;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CorePaymentHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.core_api.base_url' => 'https://core.example.test',
            'services.core_api.token' => 'test-only-core-key',
            'services.core_api.platform_slug' => 'ezway-tv',
        ]);
        Http::preventStrayRequests();
    }

    public function test_history_uses_authorized_platform_and_authenticated_core_user(): void
    {
        Http::fake(['*' => Http::response(['data' => ['invoices' => [['id' => 10]], 'subscriptions' => []]])]);
        $request = $this->request(['platform_slug' => 'another-platform', 'subject_id' => '999']);
        $response = app(CoreCheckoutBridgeController::class)->paymentHistory($request);
        $this->assertSame(200, $response->status());
        Http::assertSent(function (ClientRequest $request): bool {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query === ['platform_slug' => 'ezway-tv', 'subject_type' => 'tv_subscription', 'subject_id' => '101'];
        });
    }

    public function test_no_matching_payment_history_returns_empty_results(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Payment status not found.'], 404)]);
        $response = app(CoreCheckoutBridgeController::class)->paymentHistory($this->request());
        $this->assertSame(200, $response->status());
        $this->assertSame([], $response->getData(true)['data']['invoices']);
        $this->assertSame([], $response->getData(true)['data']['subscriptions']);
    }

    public function test_real_platform_denial_is_not_hidden_as_empty_history(): void
    {
        Http::fake(['*' => Http::response(['message' => 'This Core API key cannot access that platform.'], 403)]);
        $response = app(CoreCheckoutBridgeController::class)->paymentHistory($this->request());
        $this->assertSame(403, $response->status());
        $this->assertSame('This Core API key cannot access that platform.', $response->getData(true)['message']);
    }

    public function test_checkout_uses_same_authorized_platform(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'redirect_url' => 'https://core.example.test/checkout'])]);
        $response = app(CoreCheckoutBridgeController::class)->checkout($this->request(['package_slug' => 'tv-subscription-monthly']));
        $this->assertSame(200, $response->status());
        Http::assertSent(fn (ClientRequest $request): bool => $request['platform']['slug'] === 'ezway-tv' && $request['user_id'] === 101);
    }

    public function test_signed_out_history_does_not_call_core(): void
    {
        $request = Request::create('/core/payment-history');
        $response = app(CoreCheckoutBridgeController::class)->paymentHistory($request);
        $this->assertSame(401, $response->status());
        Http::assertNothingSent();
    }

    private function request(array $input = []): Request
    {
        $user = new User;
        $user->forceFill(['id' => 17, 'network_user_id' => 101, 'first_name' => 'Test', 'email' => 'payment@example.test']);
        $request = Request::create('/core/payment-history', 'GET', $input);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
