<?php

namespace Modules\Ad\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ad\Http\Requests\AdBannerSlideRequest;
use Modules\Ad\Models\AdBannerSlide;
use Modules\Setting\Models\Setting;
use Yajra\DataTables\DataTables;

class AdBannerSlideController extends Controller
{
    public function index()
    {
        return view('ad::backend.adbanner.index');
    }

    public function index_data(DataTables $datatable)
    {
        $query = AdBannerSlide::withTrashed()->orderBy('sort_order')->orderBy('id');

        return $datatable->eloquent($query)
            ->addColumn('check', fn($row) => '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="ad_banner_slides" onclick="dataTableRowCheck(' . $row->id . ',this)">')
            ->editColumn('image', fn($row) => $row->image ? '<img src="' . e($row->image_proxy_url) . '" class="img-fluid rounded" style="height:50px;object-fit:cover;">' : '-')
            ->editColumn('placements', fn($row) => $row->placements ? implode(', ', (array) $row->placements) : '-')
            ->editColumn('status', function ($row) {
                $checked = $row->status ? 'checked="checked"' : '';
                $disabled = $row->trashed() ? 'disabled' : '';
                return '
                <div class="form-check form-switch">
                    <input type="checkbox" data-url="' . route('backend.adbannersides.update_status', $row->id) . '"
                           data-token="' . csrf_token() . '" class="switch-status-change form-check-input"
                           id="datatable-row-' . $row->id . '" name="status" value="' . $row->id . '"
                           ' . $checked . ' ' . $disabled . '>
                </div>';
            })
            ->addColumn('action', fn($row) => view('ad::backend.adbanner.action', compact('row'))->render())
            ->editColumn('updated_at', fn($row) => $row->updated_at?->diffForHumans())
            ->rawColumns(['action', 'status', 'check', 'image'])
            ->orderColumns(['id'], '-:column $1')
            ->make(true);
    }

    public function create()
    {
        return view('ad::backend.adbanner.create');
    }

    public function store(AdBannerSlideRequest $request)
    {
        $data = $request->validated();
        $data['status'] = $request->has('status') ? 1 : 0;

        AdBannerSlide::create($data);
        AdBannerSlide::clearCache();

        return redirect()->route('backend.adbannersides.index')
            ->with('success', __('messages.create_form', ['form' => 'Ad Banner Slide']));
    }

    public function edit(int $id)
    {
        $data = AdBannerSlide::findOrFail($id);
        return view('ad::backend.adbanner.edit', compact('data'));
    }

    public function update(AdBannerSlideRequest $request, int $id)
    {
        $slide = AdBannerSlide::findOrFail($id);
        $data = $request->validated();
        $data['status'] = $request->has('status') ? 1 : 0;

        $slide->update($data);
        AdBannerSlide::clearCache();

        return redirect()->route('backend.adbannersides.index')
            ->with('success', __('messages.update_form', ['form' => 'Ad Banner Slide']));
    }

    public function update_status(Request $request, int $id)
    {
        $slide = AdBannerSlide::findOrFail($id);
        $slide->update(['status' => $request->status]);
        AdBannerSlide::clearCache();

        return response()->json(['status' => true, 'message' => __('messages.status_updated')]);
    }

    public function destroy(int $id)
    {
        AdBannerSlide::findOrFail($id)->delete();
        AdBannerSlide::clearCache();

        return response()->json(['status' => true, 'message' => __('messages.delete_form', ['form' => 'Ad Banner Slide'])]);
    }

    public function restore(int $id)
    {
        AdBannerSlide::withTrashed()->findOrFail($id)->restore();
        AdBannerSlide::clearCache();

        return response()->json(['status' => true, 'message' => __('messages.restore_form', ['form' => 'Ad Banner Slide'])]);
    }

    public function forceDelete(int $id)
    {
        AdBannerSlide::withTrashed()->findOrFail($id)->forceDelete();
        AdBannerSlide::clearCache();

        return response()->json(['status' => true, 'message' => __('messages.force_delete_form', ['form' => 'Ad Banner Slide'])]);
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);

        foreach ($ids as $id) {
            $slide = AdBannerSlide::withTrashed()->find($id);
            if (!$slide) continue;

            match ($request->action_type) {
                'delete'             => $slide->delete(),
                'restore'            => $slide->restore(),
                'permanently-delete' => $slide->forceDelete(),
                'change-status'      => $slide->update(['status' => $request->status]),
                default              => null,
            };
        }

        AdBannerSlide::clearCache();

        return response()->json(['status' => true, 'message' => __('messages.bulk_action_success')]);
    }

    public function settings()
    {
        $settings = [
            'ad_banner_autoplay_speed' => setting('ad_banner_autoplay_speed', 3000),
            'ad_banner_direction'      => setting('ad_banner_direction', 'ltr'),
        ];

        return view('ad::backend.adbanner.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'ad_banner_autoplay_speed' => 'required|integer|min:1000|max:30000',
            'ad_banner_direction'      => 'required|in:ltr,rtl',
        ]);

        Setting::add('ad_banner_autoplay_speed', $request->ad_banner_autoplay_speed);
        Setting::add('ad_banner_direction', $request->ad_banner_direction);

        \Cache::forget('ad_banner_slider_settings');

        return redirect()->route('backend.adbannersides.settings')
            ->with('success', __('messages.update_form', ['form' => 'Banner Slider Settings']));
    }
}
