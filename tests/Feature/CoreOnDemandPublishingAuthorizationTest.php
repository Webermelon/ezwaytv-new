<?php

namespace Tests\Feature;

use App\Models\AuthorChannel;
use App\Models\AuthorChannelPlaylist;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Video\Models\Video;
use Tests\TestCase;

class CoreOnDemandPublishingAuthorizationTest extends TestCase
{
    private const SAFE_CHANNEL_404 = [
        'code' => 'TV_CHANNEL_NOT_FOUND',
        'message' => "We couldn't find that TV channel. It may have been removed, or you may no longer have access to it.",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Config::set('services.core_private_api.token', 'test-core-token');
        Config::set('services.core_private_api.secret', '');
        Config::set('app.debug', false);

        $this->createSchema();
    }

    public function test_missing_channel_returns_exact_safe_404(): void
    {
        $this->coreJson('get', '/api/private/core/on-demand/channels/999/videos', ['core_user_id' => 101])
            ->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_channel_belonging_to_another_user_returns_same_exact_404(): void
    {
        $otherUser = $this->user(202);
        $foreignChannel = $this->channel($otherUser, 'Foreign Channel');

        $this->coreJson('get', "/api/private/core/on-demand/channels/{$foreignChannel->id}/videos", ['core_user_id' => 101])
            ->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_valid_owned_channel_succeeds_with_existing_response_shape(): void
    {
        $user = $this->user(101);
        $channel = $this->channel($user, 'Owned Channel');
        $video = $this->video($user, ['name' => 'Owned Video', 'status' => 1, 'processing_status' => 'published']);
        $channel->videos()->attach($video->id);

        $response = $this->coreJson('get', "/api/private/core/on-demand/channels/{$channel->id}/videos", ['core_user_id' => 101])
            ->assertOk()
            ->json();

        $this->assertTrue($response['success']);
        $this->assertSame($video->id, $response['data']['data'][0]['id']);
        $this->assertSame('Owned Video', $response['data']['data'][0]['title']);
        $this->assertSame('published', $response['data']['data'][0]['status']);
    }

    public function test_channel_update_is_scoped_to_authenticated_core_user(): void
    {
        $user = $this->user(101);
        $ownedChannel = $this->channel($user, 'Before');
        $foreignChannel = $this->channel($this->user(202), 'Foreign Before');

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$ownedChannel->id}", [
            'core_user_id' => 101,
            'name' => 'After',
        ])->assertOk()
            ->assertJsonPath('data.name', 'After');

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$foreignChannel->id}", [
            'core_user_id' => 101,
            'name' => 'Hacked',
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);

        $this->assertSame('Foreign Before', $foreignChannel->refresh()->name);
    }

    public function test_video_endpoints_are_scoped_through_owned_channel(): void
    {
        $user = $this->user(101);
        $channel = $this->channel($user, 'Owned Channel');
        $video = $this->video($user, ['name' => 'Original']);
        $foreignVideo = $this->video($this->user(202), ['name' => 'Foreign']);
        $channel->videos()->attach($video->id);

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$channel->id}/videos/{$video->id}", [
            'core_user_id' => 101,
            'title' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated');

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$channel->id}/videos/{$foreignVideo->id}", [
            'core_user_id' => 101,
            'title' => 'Leaked',
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);

        $this->coreJson('delete', "/api/private/core/on-demand/channels/{$channel->id}/videos/{$foreignVideo->id}", [
            'core_user_id' => 101,
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_playlist_endpoints_are_scoped_through_owned_channel(): void
    {
        $user = $this->user(101);
        $channel = $this->channel($user, 'Owned Channel');
        $playlist = $this->playlist($channel, 'Owned Playlist');
        $foreignPlaylist = $this->playlist($this->channel($this->user(202), 'Foreign Channel'), 'Foreign Playlist');

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$playlist->id}", [
            'core_user_id' => 101,
            'name' => 'Updated Playlist',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Playlist');

        $this->coreJson('put', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$foreignPlaylist->id}", [
            'core_user_id' => 101,
            'name' => 'Leaked Playlist',
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_assignment_unassignment_playlist_video_and_reorder_operations_are_scoped(): void
    {
        $user = $this->user(101);
        $channel = $this->channel($user, 'Owned Channel');
        $playlist = $this->playlist($channel, 'Owned Playlist');
        $assignableVideo = $this->video($user, ['name' => 'Assignable']);
        $ownedVideo = $this->video($user, ['name' => 'Owned']);
        $foreignUser = $this->user(202);
        $foreignChannel = $this->channel($foreignUser, 'Foreign Channel');
        $foreignVideo = $this->video($foreignUser, ['name' => 'Foreign']);

        $channel->videos()->attach($ownedVideo->id);
        $foreignChannel->videos()->attach($foreignVideo->id);

        $this->coreJson('post', "/api/private/core/on-demand/channels/{$channel->id}/videos/assign", [
            'core_user_id' => 101,
            'video_id' => $assignableVideo->id,
        ])->assertCreated()
            ->assertJsonPath('data.added', true);

        $this->coreJson('post', "/api/private/core/on-demand/channels/{$channel->id}/videos/assign", [
            'core_user_id' => 101,
            'video_id' => $foreignVideo->id,
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);

        $this->coreJson('post', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$playlist->id}/videos", [
            'core_user_id' => 101,
            'video_id' => $ownedVideo->id,
        ])->assertCreated()
            ->assertJsonPath('data.added', true);

        $this->coreJson('post', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$playlist->id}/videos", [
            'core_user_id' => 101,
            'video_id' => $foreignVideo->id,
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);

        $this->coreJson('delete', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$playlist->id}/videos/{$foreignVideo->id}", [
            'core_user_id' => 101,
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);

        DB::table('author_channel_playlist_video')->insert([
            'author_channel_playlist_id' => $playlist->id,
            'video_id' => $foreignVideo->id,
            'sort_order' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->coreJson('patch', "/api/private/core/on-demand/channels/{$channel->id}/playlists/{$playlist->id}/videos/reorder", [
            'core_user_id' => 101,
            'video_ids' => [$foreignVideo->id, $ownedVideo->id],
        ])->assertOk()
            ->assertJsonPath('data.video_ids', [$ownedVideo->id]);

        $this->assertDatabaseHas('author_channel_playlist_video', [
            'author_channel_playlist_id' => $playlist->id,
            'video_id' => $foreignVideo->id,
            'sort_order' => 9,
        ]);

        $this->coreJson('delete', "/api/private/core/on-demand/channels/{$channel->id}/videos/{$foreignVideo->id}/unassign", [
            'core_user_id' => 101,
        ])->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_canonical_publishing_channel_allows_reassignment_without_creator_audit_fields(): void
    {
        $owner = $this->user(101);
        $origin = $this->channel($owner, 'Original publishing channel');
        $destination = $this->channel($owner, 'Second owned channel');
        $video = $this->video($owner, ['created_by' => null]);
        $video->forceFill(['publishing_channel_id' => $origin->id])->saveQuietly();
        $this->assertNull($video->refresh()->created_by);
        $this->assertNull($video->creator_channel_id);
        $payload = ['core_user_id' => 101, 'video_id' => $video->id];
        $url = "/api/private/core/on-demand/channels/{$destination->id}/videos/assign";

        $this->coreJson('post', $url, $payload)->assertCreated()->assertJsonPath('data.added', true);
        $this->coreJson('post', $url, $payload)->assertOk()->assertJsonPath('data.added', false);
        $this->assertSame(1, DB::table('author_channel_video')->where('author_channel_id', $destination->id)->where('video_id', $video->id)->count());
        $this->assertSame($origin->id, $video->refresh()->publishing_channel_id);
        $this->coreJson('delete', "/api/private/core/on-demand/channels/{$destination->id}/videos/{$video->id}/unassign", ['core_user_id' => 101])->assertOk();
        $this->coreJson('post', $url, $payload)->assertCreated();

        $foreignOwner = $this->user(202);
        $foreignChannel = $this->channel($foreignOwner, 'Foreign destination');
        $this->coreJson('post', "/api/private/core/on-demand/channels/{$foreignChannel->id}/videos/assign", ['core_user_id' => 202, 'video_id' => $video->id])
            ->assertNotFound()->assertExactJson(self::SAFE_CHANNEL_404);
        $this->assertDatabaseMissing('author_channel_video', ['author_channel_id' => $foreignChannel->id, 'video_id' => $video->id]);
    }

    public function test_core_uploaded_video_can_be_unassigned_and_reassigned_to_its_publishing_channel(): void
    {
        $user = $this->user(101);
        $channel = $this->channel($user, 'Original Channel');
        $base = "/api/private/core/on-demand/channels/{$channel->id}/videos";
        $videoId = $this->coreJson('post', $base, ['core_user_id' => 101, 'title' => 'Core upload', 'status' => 'draft'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('videos', ['id' => $videoId, 'publishing_channel_id' => $channel->id]);
        $this->coreJson('delete', "$base/$videoId/unassign", ['core_user_id' => 101])->assertOk();
        $this->coreJson('post', "$base/assign", ['core_user_id' => 101, 'video_id' => $videoId])->assertCreated()->assertJsonPath('data.added', true);
        $this->assertDatabaseHas('videos', ['id' => $videoId, 'publishing_channel_id' => $channel->id]);
    }

    public function test_publishing_channel_migration_adds_missing_field_and_preserves_existing_provenance(): void
    {
        Schema::table('videos', fn (Blueprint $table) => $table->dropColumn('publishing_channel_id'));
        $migration = require database_path('migrations/2026_11_20_000001_add_publishing_channel_id_to_videos.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('videos', 'publishing_channel_id'));
        $owner = $this->user(101);
        $channel = $this->channel($owner, 'Publishing Channel');
        $video = $this->video($owner);
        $video->forceFill(['publishing_channel_id' => $channel->id])->saveQuietly();
        $migration->up();
        $migration->down();
        $this->assertSame($channel->id, $video->refresh()->publishing_channel_id);
    }

    public function test_model_not_found_exposes_no_internal_details_with_debug_enabled(): void
    {
        Config::set('app.debug', true);

        $this->coreJson('get', '/api/private/core/on-demand/channels/999/videos', ['core_user_id' => 101])
            ->assertNotFound()
            ->assertExactJson(self::SAFE_CHANNEL_404);
    }

    public function test_channel_list_endpoint_remains_filtered_to_authenticated_core_user(): void
    {
        $user = $this->user(101);
        $this->channel($user, 'Visible Channel');
        $this->channel($this->user(202), 'Hidden Channel');

        $this->coreJson('get', '/api/private/core/on-demand/channels', ['core_user_id' => 101])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible Channel');
    }

    public function test_creators_log_channel_and_first_publication_under_connect_identity(): void
    {
        $spool = sys_get_temp_dir().'/tv-creator-test-'.bin2hex(random_bytes(8));
        mkdir($spool.'/critical', 0700, true);
        config(['tv-logs.enabled' => true, 'tv-logs.spool' => $spool]);

        try {
            $user = $this->user(101);
            $identity = ['core_user_id' => 101, 'connect_user_id' => 501];
            $base = '/api/private/core/on-demand/channels';
            config(['vod.enabled' => true]);
            (require database_path('migrations/2026_11_20_010000_create_vod_channel_entitlements.php'))->up();
            $user->forceFill(['connect_user_id' => 501])->save();
            $channel = app(\App\Services\VodChannelEntitlements::class)->synchronize([
                'assignment_id' => (string) Str::uuid(), 'subscription_id' => 1, 'member_id' => 501,
                'channel_id' => null, 'version' => 1, 'status' => 'active',
                'starts_at' => now()->subDay()->utc()->format('Y-m-d H:i:s'),
                'paid_until' => now()->addMonth()->utc()->format('Y-m-d H:i:s'),
                'create' => ['channel' => ['name' => 'Publishing Channel']],
            ])['channel_id'];
            $otherChannel = $this->channel($user, 'Second Channel');

            $videoId = $this->coreJson('post', "$base/$channel/videos", $identity + [
                'title' => 'Creator Test Video',
                'status' => 'draft',
            ])->assertCreated()->json('data.id');
            $this->assertCount(1, glob($spool.'/critical/*.json'));

            $otherChannel->videos()->attach($videoId);
            $payload = $identity + ['status' => 'published', 'video_url' => 'https://example.test/video.mp4'];
            $this->coreJson('put', "$base/{$otherChannel->id}/videos/$videoId", $payload)->assertOk();
            $this->coreJson('put', "$base/{$otherChannel->id}/videos/$videoId", $payload)->assertOk();

            $rows = collect(glob($spool.'/critical/*.json'))
                ->map(fn ($path) => json_decode(file_get_contents($path), true)['row'])
                ->keyBy('event_name');
            $this->assertCount(2, $rows);
            $this->assertSame(501, (int) $rows['creator.channel_created']['connect_user_id']);
            $this->assertSame(501, (int) $rows['creator.video_published']['connect_user_id']);
            $this->assertEquals($channel, $rows['creator.video_published']['publishing_channel_id']);
            $this->assertEquals($videoId, $rows['creator.video_published']['video_id']);
            $this->assertEquals($user->id, $rows['creator.video_published']['tv_user_id']);

            $context = \App\Services\TvCreatorEvents::viewingContext('video', [
                'content_id' => $videoId, 'watched_channel_id' => $otherChannel->id, 'player_surface' => 'embed',
            ]);
            $this->assertEquals([
                'player_surface' => 'embed', 'publishing_channel_id' => $channel, 'watched_channel_id' => $otherChannel->id,
            ], $context);
            $direct = \App\Services\TvCreatorEvents::viewingContext('video', ['content_id' => $videoId, 'player_surface' => 'watch']);
            $this->assertArrayNotHasKey('watched_channel_id', $direct);

            foreach (['2026_04_04_000001_create_page_views_table.php', '2026_04_04_000002_create_play_events_table.php',
                '2026_04_04_000003_create_stat_settings_table.php', '2026_04_04_122049_add_page_name_to_stat_page_views.php'] as $migration) {
                (require base_path('Modules/Statistics/database/migrations/'.$migration))->up();
            }
            foreach (['stat_page_views', 'stat_play_events'] as $table) {
                Schema::table($table, fn (Blueprint $table) => $table->unsignedBigInteger('channel_id')->nullable());
            }
            mkdir($spool.'/analytics', 0700);
            $tracking = [
                'content_type' => 'video', 'content_id' => $videoId,
                'player_surface' => 'embed', 'watched_channel_id' => $otherChannel->id,
                'channel_id' => $otherChannel->id,
            ];
            $this->postJson('/api/statistics/track-view', $tracking)->assertOk()->assertJsonPath('status', 'ok');
            $this->postJson('/api/statistics/track-play', $tracking)->assertOk()->assertJsonPath('status', 'ok');

            $analytics = glob($spool.'/analytics/*.json');
            $this->assertCount(2, $analytics);
            foreach ($analytics as $path) {
                $row = json_decode(file_get_contents($path), true)['row'];
                $this->assertSame('embed', $row['player_surface']);
                $this->assertEquals($channel, $row['publishing_channel_id']);
                $this->assertEquals($otherChannel->id, $row['watched_channel_id']);
            }
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($spool);
        }
    }

    private function coreJson(string $method, string $uri, array $payload = [])
    {
        return $this->withToken('test-core-token')->json($method, $uri, $payload);
    }

    private function user(int $coreUserId): User
    {
        return User::query()->create([
            'network_user_id' => $coreUserId,
            'is_network_user' => 1,
            'first_name' => 'Core',
            'last_name' => 'User '.$coreUserId,
            'username' => 'core-user-'.$coreUserId,
            'email' => 'core-user-'.$coreUserId.'@example.test',
            'mobile' => '',
            'status' => 1,
            'user_type' => 'user',
            'login_type' => 'otp',
            'email_verified_at' => now(),
            'password' => bcrypt(Str::random(24)),
        ]);
    }

    private function channel(User $user, string $name): AuthorChannel
    {
        return AuthorChannel::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'username' => Str::slug($name).'-'.Str::random(6),
            'description' => '',
            'is_active' => true,
            'access' => 'free',
        ]);
    }

    private function video(User $user, array $attributes = []): Video
    {
        $name = $attributes['name'] ?? 'Video '.Str::random(6);

        $video = Video::query()->create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'type' => 'video',
            'access' => 'free',
            'status' => 0,
            'processing_status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'video_upload_type' => 'URL',
        ], $attributes));
        // Creator audit fields are server-managed, not mass assignable.
        $video->forceFill([
            'created_by' => array_key_exists('created_by', $attributes) ? $attributes['created_by'] : $user->id,
            'updated_by' => $user->id,
        ])->saveQuietly();

        return $video;
    }

    private function playlist(AuthorChannel $channel, string $name): AuthorChannelPlaylist
    {
        return $channel->playlists()->create([
            'name' => $name,
            'description' => '',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('author_channel_playlist_video');
        Schema::dropIfExists('author_channel_playlists');
        Schema::dropIfExists('author_channel_video');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('author_channels');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('network_user_id')->nullable()->index();
            $table->unsignedBigInteger('connect_user_id')->nullable();
            $table->boolean('is_network_user')->default(false);
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->string('login_type')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->tinyInteger('is_banned')->default(0);
            $table->tinyInteger('is_subscribe')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->string('user_type')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('username')->unique();
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->string('banner')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('access')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->boolean('movies_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('poster_url')->nullable();
            $table->string('access')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('creator_channel_id')->nullable();
            $table->unsignedBigInteger('publishing_channel_id')->nullable();
            $table->string('duration')->nullable();
            $table->date('release_date')->nullable();
            $table->longText('short_desc')->nullable();
            $table->longText('description')->nullable();
            $table->string('video_upload_type')->nullable();
            $table->text('video_url_input')->nullable();
            $table->boolean('status')->default(false);
            $table->string('processing_status')->nullable();
            $table->text('processing_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_channel_video', function (Blueprint $table) {
            $table->unsignedBigInteger('author_channel_id');
            $table->unsignedBigInteger('video_id');
            $table->primary(['author_channel_id', 'video_id']);
            $table->timestamps();
        });

        Schema::create('author_channel_playlists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('author_channel_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('author_channel_playlist_video', function (Blueprint $table) {
            $table->unsignedBigInteger('author_channel_playlist_id');
            $table->unsignedBigInteger('video_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['author_channel_playlist_id', 'video_id'], 'ac_playlist_video_primary');
        });
    }
}
