<div class="d-flex gap-2 align-items-center justify-content-end">
    @if(!$data->trashed())
        <a class="btn btn-warning-subtle btn-sm fs-4" data-bs-toggle="tooltip" title="{{ __('messages.edit') }}" href="{{ route('backend.categories.edit', $data->id) }}">
            <i class="ph ph-pencil-simple-line align-middle"></i>
        </a>
        <a href="{{ route('backend.categories.destroy', $data->id) }}" id="delete-category-{{ $data->id }}" class="btn btn-secondary-subtle btn-sm fs-4" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}" data-bs-toggle="tooltip" title="{{ __('messages.delete') }}" data-confirm="{{ __('messages.are_you_sure?') }}">
            <i class="ph ph-trash align-middle"></i>
        </a>
    @else
        <a class="btn btn-success-subtle btn-sm fs-4 restore-tax" data-confirm-message="{{ __('messages.are_you_sure_restore') }}" data-success-message="Category restored successfully." data-bs-toggle="tooltip" title="{{ __('messages.restore') }}" href="{{ route('backend.categories.restore', $data->id) }}">
            <i class="ph ph-arrow-clockwise align-middle"></i>
        </a>
        <a href="{{ route('backend.categories.force_delete', $data->id) }}" id="force-delete-category-{{ $data->id }}" class="btn btn-danger-subtle btn-sm fs-4" data-type="ajax" data-method="DELETE" data-token="{{ csrf_token() }}" data-bs-toggle="tooltip" title="{{ __('messages.force_delete') }}" data-confirm="{{ __('messages.are_you_sure_permanently_delete_entity', ['name' => $data->name, 'entity' => 'Category']) }}">
            <i class="ph ph-trash align-middle"></i>
        </a>
    @endif
</div>
