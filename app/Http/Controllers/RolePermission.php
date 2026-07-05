<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermission extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        // Page Title
        $this->module_title = 'messages.access_control';

        // module name
        $this->module_name = 'permission';
    }

    public function index()
    {
        $module_title = $this->module_title;
        $module_name = $this->module_name;
        $roles = Role::get();
        $manageableRoles = Role::query()
            ->whereNotIn('name', ['user'])
            ->orderBy('title')
            ->get();
        $adminUsers = User::query()
            ->with('roles:id,name,title')
            ->where(function ($query) {
                $query->whereIn('user_type', ['admin', 'demo_admin'])
                    ->orWhereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', '!=', 'user');
                    });
            })
            ->orderByDesc('id')
            ->get();
        $modules = config('constant.MODULES');
        $permissions = Permission::get();
        $module_action = 'List';

        return view('permission-role.permissions', compact(
            'roles',
            'permissions',
            'module_title',
            'module_name',
            'module_action',
            'modules',
            'adminUsers',
            'manageableRoles'
        ));
    }

    public function store(Request $request, Role $role_id)
    {
        if (setting('demo_login')) {
            return redirect()->back()->with('error', __('messages.permission_denied'));
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = Permission::get()->pluck('name')->toArray();
        $role_id->revokePermissionTo($permissions);
        if (isset($request->permission) && is_array($request->permission)) {
            foreach ($request->permission as $permission => $roles) {
                $pr = Permission::findOrCreate($permission);
                $role_id->permissions()->syncWithoutDetaching([$pr->id]);
            }
        }

        \Artisan::call('cache:clear');

        return redirect()->route('backend.permission-role.list')->withSuccess(__('permission-role.save_form'));
    }

    public function reset_permission($role_id)
    {
        $message = __('messages.reset_form', ['form' => __('page.lbl_role')]);
        try {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $role = Role::find($role_id);

            $permissions = Permission::get()->pluck('name')->toArray();

            if ($role) {
                $role->permissions()->detach();
            }

            \Artisan::call('cache:clear');
        } catch (\Exception $th) {
        }

        return response()->json(['status' => true, 'message' => $message]);
    }

    public function updateUserAccess(Request $request, User $user)
    {
        if (setting('demo_login')) {
            return redirect()->back()->with('error', __('messages.permission_denied'));
        }

        $roleNames = Role::query()
            ->whereNotIn('name', ['user'])
            ->pluck('name')
            ->toArray();

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'in:' . implode(',', $roleNames)],
        ]);

        $newRoles = array_values(array_unique($validated['roles']));

        $isTargetAdmin = $user->hasRole('admin');
        $removingAdminRole = $isTargetAdmin && !in_array('admin', $newRoles, true);
        if ($removingAdminRole) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return redirect()->back()->with('error', 'At least one admin user must remain with admin role.');
            }
        }

        $user->syncRoles($newRoles);

        if (in_array('admin', $newRoles, true)) {
            $user->user_type = 'admin';
        } elseif (in_array('demo_admin', $newRoles, true)) {
            $user->user_type = 'demo_admin';
        } else {
            $user->user_type = 'admin';
        }
        $user->save();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Artisan::call('cache:clear');

        return redirect()->route('backend.permission-role.list')->withSuccess('Admin user access updated successfully.');
    }
}
