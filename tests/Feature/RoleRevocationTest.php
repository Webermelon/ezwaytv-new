<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFrontendMaintenance;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\RoleBasedRouteAccess;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleRevocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutMiddleware([CheckInstallation::class, CheckFrontendMaintenance::class]);
        (require database_path('migrations/2023_01_01_000010_create_users_table.php'))->up();
        (require database_path('migrations/2023_01_01_000021_create_permission_tables.php'))->up();
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
        foreach (['user', 'admin', 'content_manager', 'super-admin'] as $name) {
            Role::create(['name' => $name, 'title' => $name, 'guard_name' => 'web']);
        }
        Route::middleware(['web', 'user'])->get('/__role-check/profile', fn () => response()->json([
            'is_admin' => auth()->user()->hasBackendAccess(),
            'roles' => auth()->user()->getRoleNames()->values()->all(),
        ]));
        Route::middleware(['web', 'auth', 'admin'])->get('/app/role-check', fn () => response('backend ok'))
            ->name('backend.home');
        Route::middleware(['web', 'auth', 'admin'])->get('/app/admin-middleware-check', fn () => response('backend ok'))
            ->withoutMiddleware(RoleBasedRouteAccess::class)->name('backend.app');
    }

    public function test_revoked_role_is_not_restored_by_the_old_global_cache(): void
    {
        $user = $this->user();
        $user->assignRole(['admin', 'user']);
        $this->assertTrue($user->hasRole('admin'));
        Cache::forever('roles_cache', Role::select('roles.*', 'model_has_roles.*')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')->get());
        $user->syncRoles([]);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('admin'));
        $this->assertFalse($fresh->hasBackendAccess());
        $this->assertSame([], $fresh->getRoleNames()->all());
        $this->actingAs($fresh)->getJson('/__role-check/profile')->assertOk()
            ->assertJsonPath('is_admin', false)->assertJsonPath('roles', []);
    }

    public function test_user_without_roles_cannot_open_backend_after_demotion(): void
    {
        $user = $this->user();
        $user->assignRole('admin');
        $this->actingAs($user)->get('/app/role-check')->assertOk();
        $user->syncRoles([]);
        $this->get('/app/role-check')->assertRedirect(route('user.login'));
        $this->getJson('/__role-check/profile')->assertOk()->assertJsonPath('is_admin', false);
    }

    public function test_legacy_admin_account_type_does_not_grant_backend_access(): void
    {
        $user = $this->user('admin');
        $this->assertFalse($user->hasBackendAccess());
        $this->actingAs($user)->get('/app/role-check')->assertRedirect(route('user.login'));
        $this->getJson('/__role-check/profile')->assertOk()->assertJsonPath('is_admin', false);
    }

    public function test_admin_middleware_itself_denies_accounts_without_backend_roles(): void
    {
        $this->actingAs($this->user('admin'))->get('/app/admin-middleware-check')
            ->assertRedirect(route('user.login'));
    }

    public function test_member_role_can_use_frontend_profile_but_cannot_use_backend(): void
    {
        $user = $this->user();
        $user->assignRole('user');
        $this->actingAs($user)->getJson('/__role-check/profile')->assertOk()->assertJsonPath('is_admin', false);
        $this->get('/app/role-check')->assertRedirect(route('user.login'));
    }

    public function test_assigned_staff_roles_are_authoritative_even_with_member_account_type(): void
    {
        foreach (['admin', 'content_manager', 'super-admin'] as $role) {
            $user = $this->user();
            $user->assignRole($role);
            $this->assertTrue($user->hasBackendAccess());
            $this->actingAs($user)->get('/app/role-check')->assertOk();
            $this->getJson('/__role-check/profile')->assertOk()->assertJsonPath('is_admin', true);
        }
    }

    private function user(string $type = 'user'): User
    {
        return User::create([
            'email' => 'role-'.uniqid().'@example.test',
            'password' => 'test-only-hash',
            'status' => 1,
            'user_type' => $type,
        ]);
    }
}
