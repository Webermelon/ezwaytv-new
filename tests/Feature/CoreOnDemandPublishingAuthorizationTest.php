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

        return Video::query()->create(array_merge([
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
