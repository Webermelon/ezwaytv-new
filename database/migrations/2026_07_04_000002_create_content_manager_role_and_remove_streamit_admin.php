<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $contentModules = [
        'media',
        'author_channels',
        'genres',
        'categories',
        'movies',
        'tvshows',
        'seasons',
        'episodes',
        'videos',
        'livetv',
        'tvcategory',
        'tvchannel',
        'castcrew',
        'actor',
        'director',
        'users_submission',
    ];

    private array $actions = [
        'view',
        'add',
        'edit',
        'delete',
        'restore',
        'force_delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(
            ['name' => 'content_manager', 'guard_name' => 'web'],
            ['title' => 'Content Manager', 'is_fixed' => false]
        );

        $role->title = 'Content Manager';
        $role->is_fixed = false;
        $role->save();

        $permissions = collect($this->contentModules)
            ->flatMap(fn ($module) => collect($this->actions)->map(fn ($action) => "{$action}_{$module}"))
            ->map(fn ($name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        $role->syncPermissions($permissions);

        User::where('email', 'admin@streamit.com')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::withTrashed()
            ->where('email', 'admin@streamit.com')
            ->restore();

        Role::where('name', 'content_manager')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
