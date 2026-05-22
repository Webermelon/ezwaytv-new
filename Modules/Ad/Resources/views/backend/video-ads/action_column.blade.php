<div class="d-flex gap-2 align-items-center justify-content-end">
    @hasPermission('edit_video_ads')
        <a href="{{ route('backend.video-ads.edit', $data->id) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit">
            <i class="ph ph-pencil"></i>
        </a>
    @endhasPermission

    @if($data->deleted_at)
        @hasPermission('restore_video_ads')
            <form action="{{ route('backend.video-ads.restore', $data->id) }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Restore">
                    <i class="ph ph-arrow-counter-clockwise"></i>
                </button>
            </form>
        @endhasPermission
        
        @hasPermission('force_delete_video_ads')
            <form action="{{ route('backend.video-ads.force_delete', $data->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Permanently Delete">
                    <i class="ph ph-trash"></i>
                </button>
            </form>
        @endhasPermission
    @else
        @hasPermission('delete_video_ads')
            <form action="{{ route('backend.video-ads.destroy', $data->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete">
                    <i class="ph ph-trash"></i>
                </button>
            </form>
        @endhasPermission
    @endif
</div>
