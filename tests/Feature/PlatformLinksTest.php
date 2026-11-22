<?php
namespace Tests\Feature;
use App\Services\PlatformLinks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
class PlatformLinksTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        config(['cache.default'=>'array','platform-links.core_url'=>'https://core.links.test','platform-links.core_token'=>'read-only-test-token','platform-links.core_host'=>null]);
        Cache::flush();Http::preventStrayRequests();
    }
    private function fake(array $rows, int $status=200): void {
        config(['testing.platform_rows'=>$rows,'testing.platform_status'=>$status]);
        Http::fake(fn()=>Http::response(['data'=>config('testing.platform_rows'),'meta'=>['has_more'=>false]],config('testing.platform_status')));
    }
    public function test_core_setting_overrides_stale_environment_urls_and_refreshes_without_a_build(): void {
        config(['services.portal.url'=>'https://wrong.example.test','directory.portal_url'=>'https://wrong.example.test']);
        $this->fake([['slug'=>'portal','url'=>'https://configured.links.test/team/','status'=>'active']]);
        $this->assertSame('https://configured.links.test/team/auth/connect/start',(new PlatformLinks)->required('portal','/auth/connect/start',true));
        Http::assertSent(fn($request)=>$request->hasHeader('Authorization','Bearer read-only-test-token') && $request['status']==='active');
        Cache::flush();
        $this->fake([['slug'=>'portal','url'=>'https://changed.links.test','status'=>'active']]);
        $this->assertSame('https://changed.links.test',(new PlatformLinks)->url('portal'));
    }
    public function test_inactive_and_unsafe_settings_are_not_links(): void {
        $rows=[];
        foreach(['javascript:alert(1)','https://name:password@links.test','https://links.test/?redirect=elsewhere','https://links.test/#fragment'] as $i=>$url)$rows[]=['slug'=>'invalid'.$i,'url'=>$url,'status'=>'active'];
        $rows[]=['slug'=>'inactive','url'=>'https://links.test','status'=>'inactive'];
        $this->fake($rows);$this->assertSame([],(new PlatformLinks)->all());
    }
    public function test_missing_or_failed_metadata_never_uses_a_guessed_host(): void {
        $this->fake([],503);$this->assertNull((new PlatformLinks)->url('portal'));
        $this->expectException(HttpException::class);(new PlatformLinks)->required('portal');
    }
    public function test_settings_are_cached_and_auth_links_require_https(): void {
        $this->fake([['slug'=>'portal','url'=>'http://configured.links.test','status'=>'active']]);
        $this->assertSame('http://configured.links.test',(new PlatformLinks)->url('portal'));
        $this->assertSame('http://configured.links.test',(new PlatformLinks)->url('portal'));Http::assertSentCount(1);
        $this->expectException(HttpException::class);(new PlatformLinks)->required('portal','',true);
    }
    public function test_tv_signin_uses_catalog_instead_of_stale_environment(): void {
        config(['connect_sso.secret'=>'test-only-secret','connect_sso.url'=>'https://wrong.example.test']);
        $this->withoutMiddleware([\App\Http\Middleware\CheckInstallation::class,\App\Http\Middleware\CheckFrontendMaintenance::class]);
        $this->fake([['slug'=>'connect','url'=>'https://configured.connect.test','status'=>'active']]);
        $response=$this->get('/auth/connect/start?redirect_to=%2Fvideos%2F123%3Ffrom%3Dhome')->assertRedirect();
        $this->assertStringStartsWith('https://configured.connect.test/portal/authorize?', $response->headers->get('Location'));
        $response->assertSessionHas('connect_sso.connect_url','https://configured.connect.test');
        $response->assertSessionHas('connect_sso.destination','/videos/123?from=home');
    }

}
