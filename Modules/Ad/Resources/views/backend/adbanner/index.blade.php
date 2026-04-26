@extends('backend.layouts.app')

@section('title')
    {{ __('sidebar.ad_banner_slides') }}
@endsection

@section('content')
    <div class="card-body mb-4">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                <x-backend.quick-action url="{{ route('backend.adbannersides.bulk_action') }}"
                    :entity_name="__('messages.lbl_ad_banner_slide')"
                    :entity_name_plural="__('messages.lbl_ad_banner_slides')">
                    <div class="">
                        <select name="action_type" class="form-control col-12 select2" id="quick-action-type" style="width:100%">
                            <option value="">{{ __('messages.no_action') }}</option>
                            <option value="change-status">{{ __('messages.lbl_status') }}</option>
                            <option value="delete">{{ __('messages.delete') }}</option>
                            <option value="restore">{{ __('messages.restore') }}</option>
                            <option value="permanently-delete">{{ __('messages.permanent_dlt') }}</option>
                        </select>
                    </div>
                    <div class="select-status d-none quick-action-field" id="change-status-action">
                        <select name="status" class="form-control select2" id="status" style="width:100%">
                            <option value="" selected>{{ __('messages.select_status') }}</option>
                            <option value="1">{{ __('messages.active') }}</option>
                            <option value="0">{{ __('messages.inactive') }}</option>
                        </select>
                    </div>
                </x-backend.quick-action>
            </div>
            <x-slot name="toolbar">
                <div class="input-group flex-nowrap">
                    <span class="input-group-text pe-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control dt-search" placeholder="{{ __('messages.search') }}..." aria-label="Search">
                </div>
                <a href="{{ route('backend.adbannersides.create') }}" class="btn btn-primary d-flex align-items-center gap-1">
                    <i class="ph ph-plus-circle"></i>{{ __('messages.new') }}
                </a>
            </x-slot>
        </x-backend.section-header>

        <table id="datatable" class="table table-responsive">
            <thead>
                <tr>
                    <th><input type="checkbox" class="form-check-input" id="select-all-table-row"></th>
                    <th>{{ __('messages.lbl_image') }}</th>
                    <th>{{ __('messages.lbl_title') }}</th>
                    <th>{{ __('messages.lbl_placements') }}</th>
                    <th>{{ __('messages.lbl_order') }}</th>
                    <th>{{ __('messages.lbl_status') }}</th>
                    <th>{{ __('messages.updated_at') }}</th>
                    <th>{{ __('messages.action') }}</th>
                </tr>
            </thead>
        </table>
    </div>
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script type="text/javascript" src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script src="{{ asset('js/form-modal/index.js') }}" defer></script>
    <script src="{{ asset('js/form/index.js') }}" defer></script>
    <script type="text/javascript">
    $(document).ready(function () {
        var table = $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('backend.adbannersides.index_data') }}',
                type: 'GET',
            },
            columns: [
                { data: 'check', orderable: false, searchable: false },
                { data: 'image', orderable: false, searchable: false },
                { data: 'title', name: 'title' },
                { data: 'placements', name: 'placements', orderable: false },
                { data: 'sort_order', name: 'sort_order' },
                { data: 'status', name: 'status', orderable: false },
                { data: 'updated_at', name: 'updated_at' },
                { data: 'action', orderable: false, searchable: false },
            ],
            order: [[4, 'asc']],
            pageLength: 25,
        });

        // search input
        $('.dt-search').on('keyup', function () {
            table.search(this.value).draw();
        });
    });
        // Handle ajax delete/restore/force-delete buttons
        $(document).on('click', '.delete-btn, .force-delete-btn, .restore-btn', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const url = $btn.data('url');
            const isRestore = $btn.hasClass('restore-btn');
            const method = isRestore ? 'POST' : 'DELETE';
            const confirmMessage = isRestore ? '{{ __('messages.restore_confirm') }}' : '{{ __('messages.are_you_sure?') }}';

            if (!confirm(confirmMessage)) return;

            $.ajax({
                url: url,
                type: method,
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function (res) {
                    if (res.status) {
                        if (typeof renderedDataTable !== 'undefined') {
                            renderedDataTable.ajax.reload(null, false);
                        } else if (typeof table !== 'undefined') {
                            table.ajax.reload(null, false);
                        }
                        window.successSnackbar && window.successSnackbar(res.message);
                    } else {
                        alert(res.message || 'Error');
                    }
                },
                error: function (err) {
                    console.error(err);
                    alert('Request failed');
                }
            });
        });
    </script>
@endpush
