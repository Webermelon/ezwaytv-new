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
            $this->assertSame(url($path), $items[$type]['url']);
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
            $response = $this->getJson('/api/v3/tv-content-feed?per_page=2&page=' . $page)->assertOk()
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
            $this->getJson('/api/v3/tv-content-feed?content_type=' . $type)->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.content_type', $type)
                ->assertJsonPath('hasMore', false);
        }
        $this->getJson('/api/v3/tv-content-feed?content_type=invalid')->assertUnprocessable();
    }

    public function test_public_rss_is_valid_xml_and_uses_tv_viewing_links(): void
    {
        $this->catalog();
        DB::table('videos')->where('id', 1)->update(['name' => 'TV & <Shows> "Today"']);
        $response = $this->get('/feed/rss')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('eZWay TV', (string) $xml->channel->title);
        $this->assertCount(5, $xml->channel->item);
        $this->assertStringNotContainsString('ezwaynetwork.com/feed', $response->getContent());
        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string) $item->title;
            $this->assertStringStartsWith(url('/'), (string) $item->link);
        }
        $this->assertContains('TV & <Shows> "Today"', $titles);
        Http::assertNothingSent();
    }

    public function test_feed_routes_have_no_authentication_requirement(): void
    {
        foreach (['tv-content-feed', 'tv-content-feed.rss', 'api.v3.tv-content-feed'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            foreach ($route->gatherMiddleware() as $middleware) {
                $this->assertDoesNotMatchRegularExpression('/^(auth|user|checkApiDevice)(:|$)/', $middleware);
            }
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
}
