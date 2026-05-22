<?php

namespace Modules\Ad\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Yajra\DataTables\DataTables;
use App\Traits\ModuleTrait;
use Modules\Ad\Models\VideoAd;
use Modules\Ad\Http\Requests\VideoAdRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoAdController extends Controller
{
    use ModuleTrait {
        initializeModuleTrait as private traitInitializeModuleTrait;
    }

    public function __construct()
    {
        $this->traitInitializeModuleTrait(
            'messages.video_ads', // module title
            'messages.video_ads', // module name
            'fa-solid fa-video' // module icon
        );
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];

        $module_action = 'List';
        $export_import = false;
        $module_name = __('messages.video_ads');
        
        return view('ad::backend.video-ads.index', compact('module_action', 'filter', 'module_name'));
    }

    public function index_data(Datatables $datatable, Request $request)
    {
        $query = VideoAd::query()->withTrashed();

        $filter = $request->filter;

        if (isset($filter['search'])) {
            $searchTerm = $filter['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('advertiser', 'like', '%' . $searchTerm . '%');
            });
        }

        if (isset($filter['status']) && $filter['status'] !== '') {
            $query->where('status', $filter['status']);
        }

        if (isset($filter['trashed']) && $filter['trashed'] !== '') {
            if ($filter['trashed'] == 1) {
                $query->onlyTrashed();
            }
        }

        return $datatable->eloquent($query)
            ->addIndexColumn()
            ->addColumn('check', function ($data) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $data->id . '" name="datatable_ids[]" value="' . $data->id . '" onclick="dataTableRowCheck(' . $data->id . ')">';
            })
            ->editColumn('name', function ($data) {
                return '<strong>' . $data->name . '</strong>';
            })
            ->editColumn('video_file', function ($data) {
                if ($data->video_url) {
                    return '<video width="200" controls><source src="' . $data->video_url . '" type="' . $data->mime_type . '"></video>';
                }
                return '-';
            })
            ->editColumn('duration', function ($data) {
                return $data->formatted_duration;
            })
            ->addColumn('vast_url', function ($data) {
                $url = $data->vast_xml_url;
                return '<div class="input-group">
                    <input type="text" class="form-control" value="' . $url . '" readonly id="vast-url-' . $data->id . '">
                    <button class="btn btn-sm btn-primary" onclick="copyVastUrl(' . $data->id . ')">
                        <i class="fa fa-copy"></i> Copy
                    </button>
                </div>';
            })
            ->editColumn('status', function ($data) {
                return $data->status 
                    ? '<span class="badge bg-success">Active</span>' 
                    : '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('action', function ($data) {
                return view('ad::backend.video-ads.action_column', compact('data'));
            })
            ->rawColumns(['check', 'name', 'video_file', 'vast_url', 'status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $module_action = 'Create';
        return view('ad::backend.video-ads.create', compact('module_action'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VideoAdRequest $request)
    {
        try {
            $data = $request->validated();

            // Handle video file from file manager URL
            if ($request->filled('video_file')) {
                $videoUrl = $request->video_file;
                
                // Check if it's a full URL (external storage like S3, DigitalOcean Spaces)
                if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                    // Store full URL as-is
                    $data['video_file'] = $videoUrl;
                } elseif (strpos($videoUrl, '/storage/') !== false) {
                    // Extract path after /storage/ for local storage
                    $data['video_file'] = str_replace(url('/storage/'), '', $videoUrl);
                } else {
                    // Assume it's already a relative path
                    $data['video_file'] = $videoUrl;
                }
                
                // Detect mime type from extension
                $extension = pathinfo($videoUrl, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'avi' => 'video/avi',
                    'mov' => 'video/mov',
                ];
                $data['mime_type'] = $mimeTypes[strtolower($extension)] ?? 'video/mp4';
            }

            VideoAd::create($data);

            return redirect()->route('backend.video-ads.index')
                ->with('success', __('messages.video_ad_created'));
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $videoAd = VideoAd::findOrFail($id);
        $module_action = 'Edit';
        
        return view('ad::backend.video-ads.edit', compact('videoAd', 'module_action'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VideoAdRequest $request, $id)
    {
        try {
            $videoAd = VideoAd::findOrFail($id);
            $data = $request->validated();

            // Handle video file from file manager URL
            if ($request->filled('video_file_url')) {
                $videoUrl = $request->video_file_url;
                
                // Only update if URL is different
                if ($videoUrl !== $videoAd->video_url) {
                    // Check if it's a full URL (external storage)
                    if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                        // Store full URL as-is
                        $data['video_file'] = $videoUrl;
                    } elseif (strpos($videoUrl, '/storage/') !== false) {
                        // Extract path after /storage/ for local storage
                        $data['video_file'] = str_replace(url('/storage/'), '', $videoUrl);
                    } else {
                        // Assume it's already a relative path
                        $data['video_file'] = $videoUrl;
                    }
                    
                    // Detect mime type from extension
                    $extension = pathinfo($videoUrl, PATHINFO_EXTENSION);
                    $mimeTypes = [
                        'mp4' => 'video/mp4',
                        'webm' => 'video/webm',
                        'ogg' => 'video/ogg',
                        'avi' => 'video/avi',
                        'mov' => 'video/mov',
                    ];
                    $data['mime_type'] = $mimeTypes[strtolower($extension)] ?? 'video/mp4';
                }
            }
            
            // Remove video_file_url from data as it's not a database column
            unset($data['video_file_url']);

            $videoAd->update($data);

            return redirect()->route('backend.video-ads.index')
                ->with('success', __('messages.video_ad_updated'));
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $videoAd = VideoAd::findOrFail($id);
        $videoAd->delete();

        return redirect()->route('backend.video-ads.index')
            ->with('success', __('messages.video_ad_deleted'));
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore($id)
    {
        $videoAd = VideoAd::withTrashed()->findOrFail($id);
        $videoAd->restore();

        return redirect()->route('backend.video-ads.index')
            ->with('success', __('messages.video_ad_restored'));
    }

    /**
     * Handle bulk actions
     */
    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;

        switch ($actionType) {
            case 'change-status':
                VideoAd::whereIn('id', $ids)->update(['status' => $request->status]);
                return response()->json(['status' => true, 'message' => __('messages.bulk_update')]);
                
            case 'delete':
                VideoAd::whereIn('id', $ids)->delete();
                return response()->json(['status' => true, 'message' => __('messages.bulk_video_ad_deleted')]);
                
            case 'restore':
                VideoAd::withTrashed()->whereIn('id', $ids)->restore();
                return response()->json(['status' => true, 'message' => __('messages.bulk_video_ad_restored')]);
                
            case 'permanently-delete':
                VideoAd::withTrashed()->whereIn('id', $ids)->forceDelete();
                return response()->json(['status' => true, 'message' => __('messages.bulk_video_ad_force_deleted')]);
                
            default:
                return response()->json(['status' => false, 'message' => __('messages.invalid_action')]);
        }
    }

    /**
     * Update status of a single video ad
     */
    public function update_status($id)
    {
        $videoAd = VideoAd::findOrFail($id);
        $videoAd->status = !$videoAd->status;
        $videoAd->save();

        return response()->json(['status' => true, 'message' => __('messages.status_updated')]);
    }
}
