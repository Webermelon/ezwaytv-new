<?php

namespace Tests\Feature;

use App\Models\AuthorChannel;
use App\Models\User;
use App\Services\VodChannelEntitlements;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VodChannelEntitlementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'vod_test', 'database.connections.vod_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array',
            'vod.enabled' => true, 'vod.sync_secret' => str_repeat('s', 40), 'services.core_api.base_url' => 'https://core.example.test', 'services.core_api.token' => 'fixture-token']);
        CarbonImmutable::setTestNow('2026-11-20 12:00:00');
        \Illuminate\Support\Carbon::setTestNow('2026-11-20 12:00:00');
        $this->createSchema();
        (require database_path('migrations/2026_11_20_010000_create_vod_channel_entitlements.php'))->up();
        (require database_path('migrations/2026_11_20_030000_create_vod_channel_entitlement_history.php'))->up();
        DB::table('users')->insert(['id' => 1, 'network_user_id' => 7, 'connect_user_id' => 7, 'is_network_user' => 1, 'email' => 'owner@example.test', 'password' => 'unused']);
        DB::table('users')->insert(['id' => 2, 'network_user_id' => 8, 'connect_user_id' => 8, 'is_network_user' => 1, 'email' => 'other@example.test', 'password' => 'unused']);
        foreach ([10, 20] as $id) {
            DB::table('author_channels')->insert(['id' => $id, 'user_id' => 1, 'name' => 'Channel '.$id, 'username' => 'channel-'.$id]);
        }
        DB::table('author_channels')->insert(['id' => 30, 'user_id' => 2, 'name' => 'Foreign', 'username' => 'foreign']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(int $sub = 1, int $channel = 10): array
    {
        return ['operation' => 'sync', 'assignment_id' => sprintf('00000000-0000-4000-8000-%012d', $sub), 'subscription_id' => $sub, 'member_id' => 7,
            'channel_id' => $channel, 'version' => 1, 'status' => 'active', 'starts_at' => '2026-11-01 00:00:00', 'paid_until' => '2026-12-20 12:00:00', 'create' => null];
    }

    private function signed(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $time = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $path = '/api/private/vod';

        return $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_VOD_TIME' => $time, 'HTTP_X_VOD_NONCE' => $nonce,
            'HTTP_X_VOD_SIGNATURE' => hash_hmac('sha256', implode("\n", [$time, $nonce, $path, $body]), config('vod.sync_secret'))], $body);
    }

    public function test_signed_sync_renewal_cancel_and_local_expiry_affect_only_the_linked_channel(): void
    {
        $a = $this->payload();
        $b = $this->payload(2, 20);
        $b['paid_until'] = '2027-02-20 12:00:00';
        $this->signed($a)->assertOk();
        $this->signed($b)->assertOk();
        $a['version'] = 2;
        $a['paid_until'] = '2027-01-20 12:00:00';
        $this->signed($a)->assertOk();
        $a['version'] = 3;
        $a['status'] = 'cancelled';
        $this->signed($a)->assertOk();
        $this->assertFalse(app(VodChannelEntitlements::class)->mayPublish(10));
        CarbonImmutable::setTestNow('2027-01-20 12:00:01');
        \Illuminate\Support\Carbon::setTestNow('2027-01-20 12:00:01');
        try {
            app(VodChannelEntitlements::class)->assertWritable(10);
            $this->fail('Expired channel writable.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        app(VodChannelEntitlements::class)->assertWritable(20);
        $this->assertSame(3, DB::table('author_channels')->count());
        $this->assertSame(2, DB::table('vod_channel_entitlements')->count());
    }

    public function test_duplicate_stale_and_conflicting_versions_cannot_extend_or_rebind_access(): void
    {
        $a = $this->payload();
        $this->signed($a)->assertOk();
        $this->signed($a)->assertOk()->assertJsonPath('duplicate', true);
        $new = $a;
        $new['version'] = 3;
        $new['status'] = 'expired';
        $this->signed($new)->assertOk();
        $this->signed($a)->assertOk()->assertJsonPath('stale', true);
        $this->assertSame('expired', DB::table('vod_channel_entitlements')->where('channel_id', 10)->value('status'));
        $new['channel_id'] = 20;
        $new['version'] = 4;
        $this->signed($new)->assertStatus(409);
        $this->signed($this->payload(2, 10))->assertStatus(409);
        $foreign = $this->payload(3, 30);
        $this->signed($foreign)->assertStatus(422);
    }

    public function test_channel_creation_retry_is_idempotent_and_requires_current_paid_access(): void
    {
        $p = $this->payload();
        $p['channel_id'] = null;
        $p['create'] = ['channel' => ['name' => 'Subscription channel']];
        $id = $this->signed($p)->assertOk()->json('channel_id');
        $this->signed($p)->assertOk()->assertJsonPath('channel_id', $id)->assertJsonPath('duplicate', true);
        $this->assertSame(4, DB::table('author_channels')->count());
        $p = $this->payload(2);
        $p['channel_id'] = null;
        $p['status'] = 'incomplete';
        $p['create'] = ['channel' => ['name' => 'Unpaid channel']];
        $this->signed($p)->assertStatus(422);
        $this->assertSame(4, DB::table('author_channels')->count());
    }

    public function test_unsigned_sync_is_rejected_and_native_expired_channel_cannot_be_changed(): void
    {
        $this->postJson('/api/private/vod', $this->payload())->assertUnauthorized();
        $p = $this->payload();
        $p['paid_until'] = '2026-11-19 12:00:00';
        $this->signed($p)->assertOk();
        Sanctum::actingAs(User::find(1));
        $this->putJson('/api/v3/ondemand/10', ['name' => 'Should not change'])->assertForbidden();
        $this->assertSame('Channel 10', DB::table('author_channels')->where('id', 10)->value('name'));
    }

    public function test_native_unmanaged_channel_checks_existing_core_membership_access(): void
    {
        Http::fake(['core.example.test/*' => Http::response(['can_publish' => false])]);
        Sanctum::actingAs(User::find(1));
        $this->putJson('/api/v3/ondemand/10', ['name' => 'No access'])->assertForbidden();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/tv/users/7/channels/10/access'));
    }

    public function test_expired_channel_does_not_publish_finished_processing_during_a_read(): void
    {
        $p = $this->payload();
        $p['paid_until'] = '2026-11-19 12:00:00';
        $this->signed($p)->assertOk();
        DB::table('videos')->insert(['id' => 1, 'name' => 'Preserved video', 'publishing_channel_id' => 10, 'status' => 0, 'processing_status' => 'processing', 'video_url_input' => 'https://example.test/ready.mp4']);
        $controller = app(\App\Http\Controllers\Api\Private\CoreOnDemandPublishingController::class);
        (new \ReflectionMethod($controller, 'reconcileProcessingVideos'))->invoke($controller, AuthorChannel::find(10), \Illuminate\Http\Request::create('/videos', 'GET'));
        $this->assertSame(0, DB::table('videos')->where('id', 1)->value('status'));
        $this->assertSame('Preserved video', DB::table('videos')->where('id', 1)->value('name'));
    }

    public function test_known_expired_entitlement_stays_locked_if_new_vod_creation_is_disabled(): void
    {
        $p = $this->payload();
        $p['paid_until'] = '2026-11-19 12:00:00';
        $this->signed($p)->assertOk();
        config(['vod.enabled' => false]);
        $this->assertFalse(app(VodChannelEntitlements::class)->mayPublish(10));
        Sanctum::actingAs(User::find(1));
        $this->putJson('/api/v3/ondemand/10', ['name' => 'No bypass'])->assertForbidden();
    }

    public function test_subscription_channel_creation_keeps_existing_creator_analytics_once(): void
    {
        $spool = sys_get_temp_dir().'/vod-creator-test-'.bin2hex(random_bytes(6));
        mkdir($spool.'/critical', 0700, true);
        config(['tv-logs.enabled' => true, 'tv-logs.spool' => $spool]);
        try {
            $p = $this->payload();
            $p['channel_id'] = null;
            $p['create'] = ['channel' => ['name' => 'Tracked channel']];
            $channel = $this->signed($p)->assertOk()->json('channel_id');
            $this->signed($p)->assertOk();
            $events = collect(glob($spool.'/critical/*.json'))->map(fn ($file) => json_decode(file_get_contents($file), true)['row'])
                ->filter(fn ($row) => ($row['event_name'] ?? '') === 'creator.channel_created');
            $this->assertCount(1, $events);
            $this->assertEquals(7, $events->first()['connect_user_id']);
            $this->assertEquals($channel, $events->first()['publishing_channel_id']);
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($spool);
        }
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
    public function test_native_creation_requires_core_subscription_and_never_creates_locally(): void
    {
        Sanctum::actingAs(User::find(1));
        Http::fake(['core.example.test/*' => Http::response(['message' => 'An available active VOD subscription is required.'], 422)]);
        $this->postJson('/api/v3/ondemand', ['name' => 'No subscription'])->assertStatus(422);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/tv/users/7/channels'));
        $this->assertSame(3, DB::table('author_channels')->count());
    }

    public function test_native_creation_fails_closed_when_vod_is_disabled(): void
    {
        config(['vod.enabled' => false]);
        Sanctum::actingAs(User::find(1));
        $this->postJson('/api/v3/ondemand', ['name' => 'No fallback'])->assertStatus(503);
        Http::assertNothingSent();
        $this->assertSame(3, DB::table('author_channels')->count());
    }

    public function test_legacy_private_creation_and_tv_admin_form_cannot_bypass_subscription_assignment(): void
    {
        $access = $this->mock(\App\Services\CoreTvAccessService::class);
        $access->shouldNotReceive('syncUser');
        $private = new \App\Http\Controllers\Api\Private\CoreOnDemandPublishingController($access);
        $admin = app(\Modules\AuthorChannel\Http\Controllers\Admin\AuthorChannelController::class);
        foreach ([fn () => $private->storeChannel(\Illuminate\Http\Request::create('/', 'POST', ['core_user_id' => 7, 'name' => 'Bypass'])),
            fn () => $admin->store(\Illuminate\Http\Request::create('/', 'POST', ['name' => 'Admin bypass'])),
            fn () => $admin->create()] as $i => $attempt) {
            try {
                $attempt();
                $this->fail('Unassigned channel creation succeeded.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $this->assertSame($i === 0 ? 410 : 403, $e->getStatusCode());
            }
        }
        $this->assertSame(3, DB::table('author_channels')->count());
    }

    public function test_cancelled_and_past_due_sync_cannot_create_new_channels(): void
    {
        foreach (['cancelled', 'past_due'] as $i => $status) {
            $p = $this->payload(50 + $i);
            $p['channel_id'] = null;
            $p['status'] = $status;
            $p['create'] = ['channel' => ['name' => 'Not active']];
            $this->signed($p)->assertStatus(422);
        }
        $this->assertSame(3, DB::table('author_channels')->count());
    }


    public function test_cancelled_channel_blocks_native_writes_but_keeps_content_and_renews_in_place(): void
    {
        $p = $this->payload();
        $this->signed($p)->assertOk();
        DB::table('videos')->insert(['id' => 91, 'name' => 'Keep me', 'publishing_channel_id' => 10, 'status' => 1]);
        DB::table('author_channel_video')->insert(['author_channel_id' => 10, 'video_id' => 91]);
        $p['version'] = 2; $p['status'] = 'cancelled';
        $this->signed($p)->assertOk()->assertJsonPath('has_access', false);
        Sanctum::actingAs(User::find(1));
        $this->putJson('/api/v3/ondemand/10', ['name' => 'Denied'])->assertForbidden();
        $this->deleteJson('/api/v3/ondemand/10')->assertForbidden();
        $this->assertSame('Channel 10', AuthorChannel::find(10)->name);
        $this->assertTrue(AuthorChannel::find(10)->is_active);
        $this->assertSame(1, DB::table('videos')->where('id', 91)->value('status'));
        $this->assertSame(1, DB::table('author_channel_video')->count());
        $this->assertFalse(AuthorChannel::publiclyVisible()->whereKey(10)->exists());
        $this->assertFalse(\Modules\Video\Models\Video::publiclyVisible()->whereKey(91)->exists());
        $p['version'] = 3; $p['status'] = 'active'; $p['paid_until'] = '2027-01-20 12:00:00';
        $this->signed($p)->assertOk()->assertJsonPath('has_access', true);
        $this->assertTrue(AuthorChannel::publiclyVisible()->whereKey(10)->exists());
        $this->assertTrue(\Modules\Video\Models\Video::publiclyVisible()->whereKey(91)->exists());
        app(VodChannelEntitlements::class)->assertWritable(10);
        $this->assertSame(3, DB::table('author_channels')->count());
    }

    public function test_warm_public_cache_expires_at_deadline_without_cron_and_renewal_keeps_drafts(): void
    {
        $p = $this->payload(); $this->signed($p)->assertOk();
        $cache = fn () => \App\Services\VodPublicCache::remember('lifecycle-list', 86400, fn () => AuthorChannel::publiclyVisible()->pluck('id')->all());
        $this->assertContains(10, $cache());
        CarbonImmutable::setTestNow('2026-12-20 12:00:00');
        \Illuminate\Support\Carbon::setTestNow('2026-12-20 12:00:00');
        $this->assertNotContains(10, $cache());
        $this->assertContains(20, $cache()); // Unlinked legacy channels unaffected.
        $this->assertFalse(app(VodChannelEntitlements::class)->mayPublish(10));
        DB::table('author_channels')->where('id', 10)->update(['is_active' => false]);
        $p['version'] = 2; $p['paid_until'] = '2027-01-20 12:00:00';
        $this->signed($p)->assertOk();
        $this->assertTrue(app(VodChannelEntitlements::class)->mayPublish(10));
        $this->assertFalse(AuthorChannel::find(10)->is_active); // Renewal never publishes an owner's draft.
        $this->assertSame(0, AuthorChannel::publiclyVisible()->where('is_active', true)->whereKey(10)->count());
    }

    public function test_public_http_queries_and_warm_cache_cannot_expose_cancelled_channel_or_its_video(): void
    {
        $p = $this->payload(); $this->signed($p)->assertOk();
        DB::table('videos')->insert(['id' => 92, 'name' => 'Private after cancellation', 'publishing_channel_id' => 10, 'status' => 1]);
        \Illuminate\Support\Facades\Route::get('/api/vod-public-fixture', function () {
            return \App\Services\VodPublicCache::remember('public-fixture', 86400, fn () => [
                'channels' => AuthorChannel::pluck('id')->all(),
                'videos' => \Modules\Video\Models\Video::pluck('id')->all(),
            ]);
        });
        $this->getJson('/api/vod-public-fixture')->assertOk()->assertJsonFragment(['videos' => [92]]);
        $p['version'] = 2; $p['status'] = 'cancelled';
        $this->signed($p)->assertOk();
        $result = $this->getJson('/api/vod-public-fixture')->assertOk()->json();
        $this->assertNotContains(10, $result['channels']);
        $this->assertSame([], $result['videos']);
        // Private management reads still retrieve the preserved records.
        $this->app->instance('request', \Illuminate\Http\Request::create('/api/private/core/on-demand/channels'));
        $this->assertNotNull(AuthorChannel::find(10));
        $this->assertNotNull(\Modules\Video\Models\Video::find(92));
    }

    public function test_old_pivot_only_video_and_signed_embed_query_are_hidden_but_other_channels_stay_visible(): void
    {
        $p = $this->payload(); $p['status'] = 'expired'; $this->signed($p)->assertOk();
        DB::table('videos')->insert(['id' => 93, 'name' => 'Legacy upload', 'status' => 1]);
        DB::table('author_channel_video')->insert(['author_channel_id' => 10, 'video_id' => 93]);
        $this->assertFalse(\Modules\Video\Models\Video::publiclyVisible()->whereKey(93)->exists());
        $this->assertTrue(AuthorChannel::publiclyVisible()->whereKey(20)->exists());
        $request = \Illuminate\Http\Request::create('/api/video-embed-details/legacy-upload');
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'api/video-embed-details/{slug}', fn () => null));
        $this->app->instance('request', $request);
        $this->assertTrue(\App\Services\VodPublicVisibility::publicRequest());
        $this->assertFalse(\Modules\Video\Models\Video::whereKey(93)->exists());
    }


    public function test_real_public_channel_and_video_list_routes_reject_cancelled_and_expired_channels(): void
    {
        DB::table('videos')->insert(['id' => 94, 'name' => 'Channel video', 'slug' => 'channel-video', 'publishing_channel_id' => 10, 'status' => 1]);
        foreach (['cancelled', 'expired'] as $version => $status) {
            $p = $this->payload(); $p['status'] = $status; $p['version'] = $version + 1;
            $this->signed($p)->assertOk();
            $this->getJson('/api/v3/ondemand/channel-10')->assertNotFound();
            $this->getJson('/api/v3/ondemand/channel-10/videos')->assertNotFound();
            $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('video-embed-details', now()->addMinutes(5), ['slug' => 'channel-video']);
            $this->getJson($url)->assertNotFound();
        }
    }

    public function test_confirmed_replacement_restores_the_original_channel_without_recreating_content(): void
    {
        DB::table('videos')->insert(['id'=>95,'name'=>'Original video','publishing_channel_id'=>10,'status'=>1]);
        DB::table('author_channel_video')->insert(['author_channel_id'=>10,'video_id'=>95]);
        $p=$this->payload();$p['status']='cancelled';$this->signed($p)->assertOk();
        $before=DB::table('author_channels')->where('id',10)->first();
        $replacement=$this->payload(2,10);$replacement['replaces_assignment_id']=$p['assignment_id'];
        $this->signed($replacement)->assertOk()->assertJsonPath('channel_id',10)->assertJsonPath('has_access',true);
        $this->assertEquals($before,DB::table('author_channels')->where('id',10)->first());
        $this->assertSame(1,DB::table('vod_channel_entitlements')->count());
        $this->assertSame(1,DB::table('vod_channel_entitlement_history')->count());
        $this->assertSame(2,(int)DB::table('vod_channel_entitlements')->where('channel_id',10)->value('subscription_id'));
        $this->assertTrue(AuthorChannel::publiclyVisible()->whereKey(10)->exists());
        $this->assertTrue(\Modules\Video\Models\Video::publiclyVisible()->whereKey(95)->exists());
        $this->signed($replacement)->assertOk()->assertJsonPath('duplicate',true);
        $p['version']=999;$p['status']='active';$p['paid_until']='2035-01-01';
        $this->signed($p)->assertOk()->assertJsonPath('stale',true);
        $this->assertSame(2,(int)DB::table('vod_channel_entitlements')->value('subscription_id'));
        $this->assertSame(1,DB::table('videos')->count());
        $this->assertSame(1,DB::table('author_channel_video')->count());
    }
    public function test_replacement_rejects_active_former_agreement_unpaid_or_foreign_member(): void
    {
        $p=$this->payload();$this->signed($p)->assertOk();
        $r=$this->payload(2,10);$r['replaces_assignment_id']=$p['assignment_id'];
        $this->signed($r)->assertStatus(409);
        $p['version']=2;$p['status']='cancelled';$this->signed($p)->assertOk();
        $r['status']='incomplete';$this->signed($r)->assertStatus(422);
        $r['status']='active';$r['member_id']=8;$this->signed($r)->assertStatus(409);
        $this->assertSame(0,DB::table('vod_channel_entitlement_history')->count());
        $this->assertSame(1,(int)DB::table('vod_channel_entitlements')->value('subscription_id'));
    }
}
