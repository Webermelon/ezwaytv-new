@extends('backend.layouts.app')

@section('title')
    {{ __($module_title) }}
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title mb-0">{{ __('messages.permission_role') }}</h4>
                    </div>
                    <div>
                        <x-backend.section-header>
                            <div>

                            </div>
                            <x-slot name="toolbar">


                                <div class="input-group flex-nowrap">
                                </div>


                            </x-slot>
                        </x-backend.section-header>


                    </div>
                </div>
                <div class="card-body">
                    @foreach ($roles as $role)
                        @if ($role->name !== 'admin' && $role->name !== 'user')
                            {{ html()->form('post', route('backend.permission-role.store', $role->id))->open() }}


                            <div class="permission-collapse border rounded p-3 mb-3" id="permission_{{ $role->id }}">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6>{{ ucfirst($role->title) }}</h6>
                                    <div class="toggle-btn-groups">
                                        @if ($role->is_fixed == 0)
                                            <button class="btn btn-danger" type="button"
                                                onclick="delete_role({{ $role->id }})">
                                                Delete
                                            </button>
                                        @endif

                                        <button class="btn btn-primary ms-2" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseBox1_{{ $role->id }}" aria-expanded="false"
                                            aria-controls="collapseExample_{{ $role->id }}">
                                            {{ __('messages.permission') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="collapse pt-3" id="collapseBox1_{{ $role->id }}">
                                    <div class="table-responsive">
                                        <table class="table table-condensed table-striped mb-0">
                                            <thead class="sticky-top">
                                                <tr>
                                                    <th>{{ __('messages.modules') }}</th>
                                                    <th>{{ __('messages.view') }}</th>
                                                    <th>{{ __('messages.add') }}</th>
                                                    <th>{{ __('messages.edit') }}</th>
                                                    <th>{{ __('messages.delete') }}</th>
                                                    <th>{{ __('messages.restore') }}e</th>
                                                    <th>{{ __('messages.force_delete') }}</th>
                                                    <th class="text-end">
                                                        {{ html()->submit(__('messages.save'))->class('btn btn-md btn-secondary') }}
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($modules as $mKey => $module)
                                                    <tr>
                                                        <td>{{ ucwords($module['module_name']) }}</td>
                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-view_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[view_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'view_' . strtolower(str_replace(' ', '_', $module['module_name']))) ? 'checked' : '' }}></span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-add_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[add_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'add_' . strtolower(str_replace(' ', '_', $module['module_name']))) ? 'checked' : '' }}>
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-edit_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[edit_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'edit_' . strtolower(str_replace(' ', '_', str_replace(' ', '_', $module['module_name'])))) ? 'checked' : '' }}>
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-delete_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[delete_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'delete_' . strtolower(str_replace(' ', '_', $module['module_name']))) ? 'checked' : '' }}>
                                                                </span>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-forcedelete_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[restore_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'restore_' . strtolower(str_replace(' ', '_', $module['module_name']))) ? 'checked' : '' }}>
                                                                </span>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            @if (isset($module['is_custom_permission']) && !$module['is_custom_permission'])
                                                                <span>
                                                                    <input type="checkbox"
                                                                        id="role-{{ $role->name }}-permission-force_delete_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}"
                                                                        name="permission[force_delete_{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}][]"
                                                                        value="{{ $role->name }}"
                                                                        class="form-check-input"
                                                                        {{ AuthHelper::checkRolePermission($role, 'force_delete_' . strtolower(str_replace(' ', '_', $module['module_name']))) ? 'checked' : '' }}>
                                                                </span>
                                                            @endif
                                                        </td>

                                                        @if (isset($module['more_permission']) && is_array($module['more_permission']))
                                                            <td class="text-end">

                                                                <a data-bs-toggle="collapse"
                                                                    data-bs-target="#demo_{{ $mKey }}"
                                                                    class="accordion-toggle  btn btn-primary btn-xs"><i
                                                                        class="fa-solid fa-chevron-down me-2">
                                                                    </i>{{ __('messages.more') }}</a>
                                                            </td>
                                                        @else
                                                            <td>

                                                            </td>
                                                        @endif
                                                    </tr>

                                                    <tr>
                                                        <td colspan="12" class="hiddenRow">
                                                            <div class="accordian-body collapse"
                                                                id="demo_{{ $mKey }}">
                                                                <table class="table table-striped mb-0">
                                                                    <tbody>
                                                                        @if (isset($module['more_permission']) && is_array($module['more_permission']))
                                                                            @foreach ($module['more_permission'] as $permission_data)
                                                                                <tr>
                                                                                    <td
                                                                                        class="d-flex justify-content-center">
                                                                                        {{ ucwords($module['module_name']) }}
                                                                                        {{ ucwords(str_replace('_', ' ', $permission_data)) }}

                                                                                        <span class="ms-5">
                                                                                            <div
                                                                                                class="form-check form-switch">
                                                                                                <input type="checkbox"
                                                                                                    id="role-{{ $role->name }}-permission-{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}_{{ strtolower(str_replace(' ', '_', $permission_data)) }}"
                                                                                                    name="permission[{{ strtolower(str_replace(' ', '_', $module['module_name'])) }}_{{ strtolower(str_replace(' ', '_', $permission_data)) }}][]"
                                                                                                    value="{{ $role->name }}"
                                                                                                    class="form-check-input"
                                                                                                    {{ AuthHelper::checkRolePermission($role, strtolower(str_replace(' ', '_', $module['module_name']) . '_' . strtolower(str_replace(' ', '_', $permission_data)))) ? 'checked' : '' }}>
                                                                                            </div>
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        @endif


                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>

                                        </table>
                                    </div>
                                </div>
                            </div>

                            {{ html()->form()->close() }}
                        @endif
                    @endforeach




                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Admin User Access</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Assign roles for backend/admin users from here.</p>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adminUsers as $adminUser)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ trim(($adminUser->first_name ?? '') . ' ' . ($adminUser->last_name ?? '')) ?: ($adminUser->name ?? '—') }}</div>
                                            <small class="text-muted">ID: {{ $adminUser->id }} | Type: {{ $adminUser->user_type ?? '—' }}</small>
                                        </td>
                                        <td>{{ $adminUser->email }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-3">
                                                @foreach($manageableRoles as $roleOption)
                                                    <div class="form-check me-2">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="roles[]"
                                                            id="user-{{ $adminUser->id }}-role-{{ $roleOption->id }}"
                                                            value="{{ $roleOption->name }}"
                                                            form="admin-user-access-{{ $adminUser->id }}"
                                                            {{ $adminUser->roles->contains('name', $roleOption->name) ? 'checked' : '' }}
                                                        >
                                                        <label class="form-check-label" for="user-{{ $adminUser->id }}-role-{{ $roleOption->id }}">
                                                            {{ $roleOption->title ?: ucfirst(str_replace('_', ' ', $roleOption->name)) }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('backend.permission-role.user-access.update', $adminUser->id) }}" id="admin-user-access-{{ $adminUser->id }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">Update Access</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No admin users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div data-render="app">
                <manage-role-form create-title="{{ __('messages.create') }}  {{ __('page.lbl_role') }}">
                </manage-role-form>

            </div>

        </div>
    </div>



    <script>
        function reset_permission(role_id) {

            var url = "/app/permission-role/reset/" + role_id;

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    successSnackbar(response.message);
                    window.location.reload();
                },
                error: function(response) {
                    alert('error');
                }
            });
        }



        function delete_role(role_id) {
            var url = "{{ route('backend.role.destroy', ['role' => ':role_id']) }}";
            url = url.replace(':role_id', role_id);

            $.ajax({
                url: url,
                type: 'DELETE',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#permission_' + role_id).hide();
                    successSnackbar(response.message);

                },
                error: function(response) {
                    alert('error');
                }
            });
        }
    </script>


    <style>
        .permission-collapse table tr td.hiddenRow {
            padding: 0;
        }

        .permission-collapse table tr td.hiddenRow table td {
            padding: 20px;
        }

        .permission-collapse table tr td.hiddenRow table tr:last-child td {
            border: none;
        }
    </style>
@endsection
