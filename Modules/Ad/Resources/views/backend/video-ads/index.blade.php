@extends('backend.layouts.app')

@section('title')
    {{ __('messages.video_ads') }}
@endsection

@section('content')
    <div class="card-body mb-4">
        <x-backend.section-header>
            <div class="d-flex flex-wrap gap-3">
                <x-backend.quick-action url="{{ route('backend.video-ads.bulk_action') }}" :entity_name="__('messages.video_ad')" :entity_name_plural="__('messages.video_ads')">
                    <div class="">
                        <select name="action_type" class="form-control col-12 select2" id="quick-action-type"
                            style="width:100%">
                            <option value="">{{ __('messages.no_action') }}</option>
                            <option value="change-status">{{ __('messages.lbl_status') }}</option>
                            @hasPermission('delete_video_ads')
                                <option value="delete">{{ __('messages.delete') }}</option>
                            @endhasPermission
                            @hasPermission('restore_video_ads')
                                <option value="restore">{{ __('messages.restore') }}</option>
                            @endhasPermission
                            @hasPermission('force_delete_video_ads')
                                <option value="permanently-delete">{{ __('messages.permanent_dlt') }}</option>
                            @endhasPermission
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
                <div>
                    <div class="datatable-filter" style="width: 190px;">
                        <select name="column_status" id="column_status" class="select2 form-control"
                            data-filter="select" style="width: 100%">
                            <option value="">{{ __('messages.all') }}</option>
                            <option value="0" {{ $filter['status'] == '0' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                            <option value="1" {{ $filter['status'] == '1' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3">
                    @hasPermission('add_video_ads')
                        <a href="{{ route('backend.video-ads.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus"></i> {{ __('messages.create') }} {{ __('messages.video_ad') }}
                        </a>
                    @endhasPermission
                </div>
            </x-slot>
        </x-backend.section-header>

        <table id="datatable" class="table table-striped border table-hover datatable">
            <thead>
                <tr>
                    <th scope="col" class="text-center">
                        <input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table"
                            onclick="selectAllTable(this)">
                    </th>
                    <th scope="col">{{ __('messages.ad_name') }}</th>
                    <th scope="col">{{ __('messages.video_preview') }}</th>
                    <th scope="col">{{ __('messages.duration') }}</th>
                    <th scope="col">{{ __('messages.advertiser') }}</th>
                    <th scope="col">{{ __('messages.vast_url') }}</th>
                    <th scope="col">{{ __('messages.status') }}</th>
                    <th scope="col" class="text-end">{{ __('messages.lbl_action') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
@endsection

@push('after-styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatable/datatables.min.css') }}">
@endpush

@push('after-scripts')
    <script src="{{ asset('vendor/datatable/datatables.min.js') }}"></script>
    <script type="text/javascript" defer>
        const columns = [{
                name: 'check',
                data: 'check',
                title: '<input type="checkbox" class="form-check-input" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                width: '0%',
                exportable: false,
                orderable: false,
                searchable: false,
            },
            {
                data: 'name',
                name: 'name',
                title: "{{ __('messages.ad_name') }}",
                orderable: false,
            },
            {
                data: 'video_file',
                name: 'video_file',
                title: "{{ __('messages.video_preview') }}",
                orderable: false,
                searchable: false,
            },
            {
                data: 'duration',
                name: 'duration',
                title: "{{ __('messages.duration') }}",
                orderable: false,
            },
            {
                data: 'advertiser',
                name: 'advertiser',
                title: "{{ __('messages.advertiser') }}",
                orderable: false,
            },
            {
                data: 'vast_url',
                name: 'vast_url',
                title: "{{ __('messages.vast_url') }}",
                orderable: false,
                searchable: false,
            },
            {
                data: 'status',
                name: 'status',
                orderable: false,
                searchable: false,
                title: "{{ __('messages.status') }}",
                width: '5%'
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false,
                title: "{{ __('messages.lbl_action') }}",
                width: '5%'
            }

        ]

        const actionColum = [{
            title: 'Action',
            data: 'action',
            name: 'action',
            render: function(data) {
                return data
            }
        }]

        const customFieldDatatable = function() {
            if (!$('#datatable').length) {
                return
            }

            window.renderedDataTable = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                responsive: true,
                dom: '<"row align-items-center"<"col-md-6" l><"col-md-6" f>><"table-responsive my-3" rt><"d-flex" <"flex-grow-1" i><"flex-grow-1 mb-1" p>><"clear">',
                ajax: {
                    "type": "GET",
                    "url": '{{ route('backend.video-ads.index_data') }}',
                    data: function(d) {
                        d.search = {
                            value: $('.dt-search').val()
                        };
                        d.filter = {
                            column_status: $('#column_status').val(),
                        }
                    },
                },
                columns: columns,
                order: [
                    [1, 'asc']
                ]
            });
        }

        customFieldDatatable();

        $(document).on('change', '.select2', function() {
            if (!$(this).attr('data-filter')) {
                return
            }

            window.renderedDataTable.draw();
        });

        function copyVastUrl(id) {
            const input = document.getElementById('vast-url-' + id);
            input.select();
            input.setSelectionRange(0, 99999); // For mobile devices
            navigator.clipboard.writeText(input.value);
            
            // Optional: Show a toast notification
            alert('VAST URL copied to clipboard!');
        }
    </script>
@endpush
