<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyVideoApiKey;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoApiAuthenticationTest extends TestCase
{
    #[DataProvider('protectedVideoEndpointProvider')]
    public function test_video_endpoints_reject_requests_without_an_api_key(string $uri): void
    {
        $this->getJson($uri)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthorized.']);
    }

    public static function protectedVideoEndpointProvider(): array
    {
        return [
            'legacy video list' => ['/api/video-list'],
            'legacy video details' => ['/api/video-details'],
            'v3 video list' => ['/api/v3/video-list'],
        ];
    }

    public function test_configured_video_api_key_is_accepted(): void
    {
        config(['services.video_api.key' => 'test-video-key']);

        $request = Request::create('/api/video-list', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer test-video-key',
        ]);

        $response = (new VerifyVideoApiKey())->handle(
            $request,
            fn () => response()->json(['authenticated' => true])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['authenticated' => true], $response->getData(true));
    }

    public function test_api_key_cannot_retrieve_playback_sources(): void
    {
        config(['services.video_api.key' => 'test-video-key']);

        $request = Request::create('/api/video-details', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer test-video-key',
        ]);

        $response = (new VerifyVideoApiKey())->handle(
            $request,
            fn () => response()->json([
                'data' => [
                    'name' => 'Visible catalog title',
                    'poster_image' => 'https://cdn.test/poster.jpg',
                    'video_url_input' => 'https://cdn.test/video.m3u8',
                    'bunny_video_url' => 'https://bunny.test/video.m3u8',
                    'trailer_url' => 'https://cdn.test/trailer.mp4',
                    'download_url' => 'https://cdn.test/download.mp4',
                    'video_links' => [['url' => 'https://cdn.test/quality.mp4']],
                    'download_quality' => [['url' => 'https://cdn.test/download-1080.mp4']],
                    'clips' => [['url' => 'https://cdn.test/clip.mp4']],
                    'html' => '<div data-preview="https://cdn.test/preview.mp4"></div>',
                    'more_items' => [[
                        'video_url' => 'https://cdn.test/related.mp4',
                    ]],
                ],
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame('Visible catalog title', data_get($payload, 'data.name'));
        $this->assertSame('https://cdn.test/poster.jpg', data_get($payload, 'data.poster_image'));
        $this->assertNull(data_get($payload, 'data.video_url_input'));
        $this->assertNull(data_get($payload, 'data.bunny_video_url'));
        $this->assertNull(data_get($payload, 'data.trailer_url'));
        $this->assertNull(data_get($payload, 'data.download_url'));
        $this->assertSame([], data_get($payload, 'data.video_links'));
        $this->assertSame([], data_get($payload, 'data.download_quality'));
        $this->assertSame([], data_get($payload, 'data.clips'));
        $this->assertNull(data_get($payload, 'data.html'));
        $this->assertNull(data_get($payload, 'data.more_items.0.video_url'));
    }

    public function test_same_origin_site_request_with_session_token_is_accepted(): void
    {
        $request = Request::create('https://tv.test/api/video-list', 'GET', server: [
            'HTTP_REFERER' => 'https://tv.test/videos',
            'HTTP_X_CSRF_TOKEN' => 'site-session-token',
        ]);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $session->put('_token', 'site-session-token');
        $request->setLaravelSession($session);

        $response = (new VerifyVideoApiKey())->handle(
            $request,
            fn () => response()->json([
                'authenticated' => true,
                'video_url_input' => 'https://cdn.test/site-video.m3u8',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'https://cdn.test/site-video.m3u8',
            $response->getData(true)['video_url_input']
        );
    }

    public function test_cross_origin_request_cannot_use_a_site_session_token(): void
    {
        $request = Request::create('https://tv.test/api/video-list', 'GET', server: [
            'HTTP_ORIGIN' => 'https://attacker.test',
            'HTTP_X_CSRF_TOKEN' => 'site-session-token',
        ]);
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $session->put('_token', 'site-session-token');
        $request->setLaravelSession($session);

        $response = (new VerifyVideoApiKey())->handle(
            $request,
            fn () => response()->json(['authenticated' => true])
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
