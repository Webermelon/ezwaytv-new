<?php

namespace Modules\Ad\database\seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class VideoAdsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'view_video_ads',
            'add_video_ads',
            'edit_video_ads',
            'delete_video_ads',
            'restore_video_ads',
            'force_delete_video_ads',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
            echo "✓ Created permission: {$permission}\n";
        }

        // Assign to administrator role
        $adminRole = Role::where('name', 'administrator')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
            echo "✓ Permissions assigned to administrator role\n";
        }

        // Assign to super admin role if exists
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permissions);
            echo "✓ Permissions assigned to super_admin role\n";
        }

        // Assign to all super admin users
        $superAdmins = User::where('user_type', 'super_admin')->get();
        foreach ($superAdmins as $admin) {
            $admin->givePermissionTo($permissions);
            echo "✓ Permissions assigned to user: {$admin->email}\n";
        }

        echo "\n✅ Video Ads permissions seeded successfully!\n";
    }
}
