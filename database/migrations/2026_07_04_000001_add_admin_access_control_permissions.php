<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $modules = [
        'author_channels',
        'categories',
        'users',
        'users_submission',
        'reviews',
        'core_api_keys',
        'email_logs',
        'faqs',
        'statistics',
        'statistics_booster',
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

        $permissions = collect($this->modules)
            ->flatMap(fn ($module) => collect($this->actions)->map(fn ($action) => "{$action}_{$module}"))
            ->map(fn ($name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::whereIn('name', ['admin', 'demo_admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect($this->modules)
            ->flatMap(fn ($module) => collect($this->actions)->map(fn ($action) => "{$action}_{$module}"))
            ->all();

        Permission::whereIn('name', $permissionNames)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
