<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFrontendMaintenance;
use App\Http\Middleware\CheckInstallation;
use App\Services\TvContentFeedService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TvContentFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutMiddleware([CheckInstallation::class, CheckFrontendMaintenance::class]);
        Http::preventStrayRequests();

        foreach (['videos', 'entertainments', 'episodes', 'live_tv_channel'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->string('poster_url')->nullable();
                $table->string('access')->nullable();
                $table->string('movie_access')->nullable();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('entertainment_id')->nullable();
                $table->unsignedBigInteger('season_id')->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('is_restricted')->default(false);
                $table->date('release_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::table('live_tv_channel', function (Blueprint $table): void {
            $table->unsignedBigInteger('vod_channel_id')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('connect_user_id')->nullable();
        });
        Schema::create('author_channels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('username');
            $table->softDeletes();
        });
        foreach (['author_channel_video' => 'video_id', 'author_channel_entertainment' => 'entertainment_id'] as $name => $contentColumn) {
            Schema::create($name, function (Blueprint $table) use ($contentColumn): void {
                $table->unsignedBigInteger('author_channel_id');
                $table->unsignedBigInteger($contentColumn);
            });
        }
        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status')->default(true);
            $table->softDeletes();
        });
    }

    public function test_guest_can_read_all_tv_content_types_without_external_rss(): void
    {
        $this->catalog();
        $response = $this->getJson('/api/v3/tv-content-feed')->assertOk()
            ->assertJsonPath('status', true)->assertJsonPath('meta.total', 5);
        $items = collect($response->json('data'))->keyBy('content_type');
        $this->assertEqualsCanonicalizing(['video', 'movie', 'tvshow', 'episode', 'livetv'], $items->keys()->all());
        foreach ([
            'video' => '/video-details/1', 'movie' => '/watch-movie/1',
            'tvshow' => '/tv-shows/2', 'episode' => '/tv-shows/2/episodes/1', 'livetv' => '/livetv/1',
        ] as $type => $path) {
            $this->assertSame(url($path), $items[$type]['platform_url']);
            $this->assertSame('eZWay TV', $items[$type]['source']);
        }
        $this->assertSame('TV & media', $items['video']['description']);
        Http::assertNothingSent();
    }

    public function test_drafts_deleted_future_and_unpublished_parent_content_are_excluded(): void
    {
        $this->catalog();
        foreach (['videos', 'entertainments', 'episodes'] as $table) {
            foreach ([['status' => 0], ['deleted_at' => now()], ['release_date' => now()->addDay()->toDateString()]] as $attributes) {
                $this->insert($table, array_merge(['type' => 'movie', 'entertainment_id' => 2, 'season_id' => 1], $attributes));
            }
        }
        $this->insert('live_tv_channel', ['status' => 0]);
        $this->insert('live_tv_channel', ['deleted_at' => now()]);
        $this->insert('episodes', ['entertainment_id' => 999, 'season_id' => 1]);
        $this->insert('episodes', ['entertainment_id' => 2, 'season_id' => 999]);
        $this->getJson('/api/v3/tv-content-feed')->assertOk()->assertJsonPath('meta.total', 5);

        DB::table('entertainments')->where('id', 2)->update(['status' => 0]);
        $this->getJson('/api/v3/tv-content-feed')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_pagination_is_stable_across_types_with_overlapping_ids(): void
    {
        $this->catalog();
        $ids = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->getJson('/api/v3/tv-content-feed?per_page=2&page='.$page)->assertOk()
                ->assertJsonPath('hasMore', $page < 3)->assertJsonPath('meta.total', 5);
            $ids = array_merge($ids, array_column($response->json('data'), 'id'));
        }
        $this->assertCount(5, array_unique($ids));
        $this->getJson('/api/v3/tv-content-feed?page=4&per_page=2')->assertOk()->assertJsonPath('data', [])->assertJsonPath('hasMore', false);
        $this->getJson('/api/v3/tv-content-feed?page=0')->assertUnprocessable();
        $this->getJson('/api/v3/tv-content-feed?per_page=1000')->assertUnprocessable();
    }

    public function test_child_profile_excludes_restricted_content(): void
    {
        $this->catalog();
        DB::table('entertainments')->where('id', 2)->update(['is_restricted' => 1]);
        $this->assertSame(3, app(TvContentFeedService::class)->page(1, 12, true)['meta']['total']);
    }

    public function test_visitors_can_filter_each_content_type(): void
    {
        $this->catalog();
        foreach (['video', 'movie', 'tvshow', 'episode', 'livetv'] as $type) {
            $this->getJson('/api/v3/tv-content-feed?content_type='.$type)->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.content_type', $type)
                ->assertJsonPath('hasMore', false);
        }
        $this->getJson('/api/v3/tv-content-feed?content_type=invalid')->assertUnprocessable();
    }

    public function test_public_feed_exposes_catalog_metadata_without_playback_sources(): void
    {
        $this->catalog();
        Schema::table('videos', function (Blueprint $table): void {
            $table->text('video_url_input')->nullable();
        });
        DB::table('videos')->where('id', 1)->update(['name' => 'TV & <Shows> "Today"', 'video_url_input' => 'https://private.example.test/playback-token']);
        $response = $this->getJson('/api/v3/tv-content-feed')->assertOk();
        $items = collect($response->json('data'))->keyBy('content_type');
        $this->assertSame('TV & <Shows> "Today"', $items['video']['title']);
        $this->assertSame(url('/video-details/1'), $items['video']['platform_url']);
        $this->assertArrayHasKey('published_at_iso', $items['video']);
        $this->assertNull($items['video']['author_id']);
        $this->assertStringNotContainsString('private.example.test', $response->getContent());
        $this->assertArrayNotHasKey('video_url_input', $items['video']);
        Http::assertNothingSent();
    }

    public function test_feed_filters_authors_by_connect_identity_and_channel(): void
    {
        $this->catalog();
        DB::table('users')->insert([['id' => 7, 'connect_user_id' => 1007], ['id' => 8, 'connect_user_id' => 1008]]);
        DB::table('author_channels')->insert([
            ['id' => 10, 'user_id' => 7, 'name' => 'Creator Studio', 'username' => 'creator-studio'],
            ['id' => 20, 'user_id' => 8, 'name' => 'Live Studio', 'username' => 'live-studio'],
        ]);
        DB::table('author_channel_video')->insert(['author_channel_id' => 10, 'video_id' => 1]);
        DB::table('author_channel_entertainment')->insert(['author_channel_id' => 10, 'entertainment_id' => 1]);
        DB::table('live_tv_channel')->where('id', 1)->update(['vod_channel_id' => 20]);
        $response = $this->getJson('/api/v3/tv-content-feed?author_id=1007')->assertOk()->assertJsonPath('meta.total', 2);
        foreach ($response->json('data') as $item) {
            $this->assertSame(1007, $item['author_id']);
            $this->assertSame(10, $item['author_channel_id']);
            $this->assertSame('Creator Studio', $item['author']);
        }
        $this->getJson('/api/v3/tv-content-feed?channel_id=20')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.content_type', 'livetv')->assertJsonPath('data.0.author_id', 1008);
        $this->getJson('/api/v3/tv-content-feed?channel_name=Creator')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v3/tv-content-feed?author_id=7')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_feed_date_name_and_access_filters_match_the_published_catalog(): void
    {
        $this->catalog();
        DB::table('videos')->where('id', 1)->update(['name' => 'Creator Interview', 'access' => 'paid', 'created_at' => '2026-02-10 12:00:00']);
        $this->getJson('/api/v3/tv-content-feed?from=2026-02-01&to=2026-02-28&name=Interview&access=paid')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', 'video:1');
        $this->getJson('/api/v3/tv-content-feed?from=2026-03-01&to=2026-02-01')->assertUnprocessable();
    }

    public function test_feed_route_has_no_authentication_requirement(): void
    {
        $route = Route::getRoutes()->getByName('api.v3.tv-content-feed');
        $this->assertNotNull($route);
        foreach ($route->gatherMiddleware() as $middleware) {
            $this->assertDoesNotMatchRegularExpression('/^(auth|user|checkApiDevice)(:|$)/', $middleware);
        }
    }

    private function catalog(): void
    {
        $this->insert('videos');
        $this->insert('entertainments', ['id' => 1, 'type' => 'movie']);
        $this->insert('entertainments', ['id' => 2, 'type' => 'tvshow']);
        DB::table('seasons')->insert(['id' => 1]);
        $this->insert('episodes', ['entertainment_id' => 2, 'season_id' => 1]);
        $this->insert('live_tv_channel');
    }

    private function insert(string $table, array $attributes = []): void
    {
        DB::table($table)->insert(array_merge([
            'name' => 'TV content', 'description' => '<p>TV &amp; media</p>',
            'created_at' => '2026-01-01 12:00:00',
        ], $attributes));
    }

    public function test_public_feed_hides_cancelled_channel_upload_and_restores_it_on_renewal(): void
    {
        config(['vod.enabled' => true]);
        Schema::table('videos', function (Blueprint $t) {
            $t->unsignedBigInteger('publishing_channel_id')->nullable();
            $t->unsignedBigInteger('creator_channel_id')->nullable();
        });
        Schema::table('author_channels', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        (require database_path('migrations/2026_11_20_010000_create_vod_channel_entitlements.php'))->up();
        $this->catalog();
        DB::table('users')->insert(['id' => 7, 'connect_user_id' => 1007]);
        DB::table('author_channels')->insert(['id' => 10, 'user_id' => 7, 'name' => 'Creator Studio', 'username' => 'creator-studio']);
        DB::table('videos')->where('id', 1)->update(['publishing_channel_id' => 10]);
        DB::table('author_channel_video')->insert(['author_channel_id' => 10, 'video_id' => 1]);
        DB::table('vod_channel_entitlements')->insert(['assignment_id' => '00000000-0000-4000-8000-000000000001',
            'subscription_id' => 1, 'member_id' => 1007, 'channel_id' => 10, 'status' => 'cancelled',
            'paid_until' => now()->addMonth(), 'version' => 1, 'payload_hash' => str_repeat('a', 64)]);
        $this->getJson('/api/v3/tv-content-feed?content_type=video')->assertOk()->assertJsonPath('meta.total', 0);
        DB::table('vod_channel_entitlements')->where('channel_id', 10)->update(['status' => 'active', 'version' => 2]);
        $this->getJson('/api/v3/tv-content-feed?content_type=video')->assertOk()->assertJsonPath('meta.total', 1);
        DB::table('vod_channel_entitlements')->where('channel_id', 10)->update(['paid_until' => now()->subSecond()]);
        $this->getJson('/api/v3/tv-content-feed?content_type=video')->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertSame(1, DB::table('videos')->where('id', 1)->value('status'));
    }
}
