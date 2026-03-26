<div class="d-flex gap-2">
    <a href="{{ route('backend.adbannersides.edit', $row->id) }}"
       class="btn btn-info-subtle btn-sm fs-4"
       data-bs-toggle="tooltip" title="{{ __('messages.edit') }}">
        <i class="ph ph-pencil align-middle"></i>
    </a>

    @if($row->trashed())
        <button type="button"
            class="btn btn-warning-subtle btn-sm fs-4 restore-btn"
            data-id="{{ $row->id }}"
            data-url="{{ route('backend.adbannersides.restore', $row->id) }}"
            data-bs-toggle="tooltip" title="{{ __('messages.restore') }}">
            <i class="ph ph-arrow-counter-clockwise align-middle"></i>
        </button>

        <button type="button"
            class="btn btn-danger-subtle btn-sm fs-4 force-delete-btn"
            data-id="{{ $row->id }}"
            data-url="{{ route('backend.adbannersides.force_delete', $row->id) }}"
            data-bs-toggle="tooltip" title="{{ __('messages.permanent_dlt') }}">
            <i class="ph ph-trash align-middle"></i>
        </button>
    @else
        <button type="button"
            class="btn btn-danger-subtle btn-sm fs-4 delete-btn"
            data-id="{{ $row->id }}"
            data-url="{{ route('backend.adbannersides.destroy', $row->id) }}"
            data-bs-toggle="tooltip" title="{{ __('messages.delete') }}">
            <i class="ph ph-trash align-middle"></i>
        </button>
    @endif
</div>
