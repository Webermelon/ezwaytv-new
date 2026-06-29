<?php

namespace Modules\Frontend\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\UserMultiProfile;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Transformers\WatchlistResource;
use Modules\User\Transformers\UserMultiProfileResource;
use Auth;
use Hash;
use Modules\NotificationTemplate\Jobs\SendBulkNotification;

class UserController extends Controller
{
    public function accountSettingsData()
    {
        $user = Auth::user();
        $devices = Device::where('user_id', $user->id)->orderByDesc('updated_at')->get();
        $currentDevice = $devices->firstWhere('device_id', request()->ip()) ?? $devices->first();
        $otherDevices = $devices
            ->reject(fn ($device) => $currentDevice && $device->id === $currentDevice->id)
            ->values()
            ->map(fn ($device) => $this->serializeDevice($device))
            ->all();

        return response()->json([
            'status' => true,
            'data' => [
                'profile' => $this->serializeUser($user),
                'plan_details' => $user->subscriptionPackage ?: null,
                'register_mobile_number' => $user->mobile,
                'your_device' => $currentDevice ? $this->serializeDevice($currentDevice) : null,
                'other_device' => $otherDevices,
            ],
            'message' => __('users.account_setting'),
        ]);
    }

    public function updateProfileData(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['required', Rule::unique('users', 'mobile')->ignore($user->id)],
            'country_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'file_url' => ['nullable', 'image', 'max:5120'],
        ]);

        unset($validated['file_url']);

        if ($request->hasFile('file_url')) {
            $file = $request->file('file_url');
            $filePath = $file->storeAs('streamit-laravel', $file->getClientOriginalName(), 'public');
            $validated['file_url'] = extractFileNameFromUrl('/storage/' . $filePath, 'users');
        }

        $user->update($validated);
        $user = $user->fresh();

        return response()->json([
            'status' => true,
            'data' => $this->serializeUser($user),
            'message' => __('messages.profile_update'),
        ]);
    }

    public function watchlistData(Request $request)
    {
        $user = Auth::user();
        $type = $request->input('type', 'all');
        $perPage = $request->input('per_page', 24);
        $profileId = getCurrentProfile($user->id, $request);

        $query = Watchlist::with('entertainment', 'video.authorChannels')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at');

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        if (in_array($type, ['movie', 'tvshow'], true)) {
            $query->where('type', $type)
                ->whereHas('entertainment', fn ($subQuery) => $subQuery->where('status', 1)->whereNull('deleted_at'));
        } elseif ($type === 'ondemand') {
            $query->where('type', 'video')
                ->whereHas('video', function ($subQuery) {
                    $subQuery->where('status', 1)
                        ->whereNull('deleted_at')
                        ->whereHas('authorChannels', fn ($channelQuery) => $channelQuery->where('is_active', 1)->whereNull('author_channels.deleted_at'));
                });
        } elseif ($type === 'video') {
            $query->where('type', 'video')
                ->whereHas('video', fn ($subQuery) => $subQuery->where('status', 1)->whereNull('deleted_at'));
        } else {
            $query->where(function ($scope) {
                $scope->where(function ($q) {
                    $q->whereIn('type', ['movie', 'tvshow'])
                        ->whereHas('entertainment', fn ($subQuery) => $subQuery->where('status', 1)->whereNull('deleted_at'));
                })->orWhere(function ($q) {
                    $q->where('type', 'video')
                        ->whereHas('video', fn ($subQuery) => $subQuery->where('status', 1)->whereNull('deleted_at'));
                });
            });
        }

        $watchlist = $query->orderByDesc('updated_at')->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => WatchlistResource::collection($watchlist),
            'meta' => [
                'current_page' => $watchlist->currentPage(),
                'last_page' => $watchlist->lastPage(),
                'total' => $watchlist->total(),
                'has_more' => $watchlist->hasMorePages(),
            ],
            'message' => __('movie.watch_list'),
        ]);
    }

    public function deleteWatchlistItem(Request $request)
    {
        $request->validate([
            'entertainment_id' => ['required'],
            'type' => ['required', 'in:movie,tvshow,video'],
        ]);

        $user = Auth::user();
        $profileId = getCurrentProfile($user->id, $request);

        $query = Watchlist::where('user_id', $user->id)
            ->where('entertainment_id', $request->entertainment_id)
            ->where('type', $request->type);

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $query->delete();

        if (function_exists('clearWatchlistCache')) {
            clearWatchlistCache();
        }

        return response()->json([
            'status' => true,
            'message' => __('movie.watchlist_delete'),
        ]);
    }

    public function saveWatchlistItem(Request $request)
    {
        $request->validate([
            'entertainment_id' => ['required'],
            'type' => ['required', 'in:movie,tvshow,video'],
        ]);

        $user = Auth::user();
        $profileId = getCurrentProfile($user->id, $request);

        Watchlist::updateOrCreate(
            [
                'entertainment_id' => $request->entertainment_id,
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'type' => $request->type,
            ],
            [
                'entertainment_id' => $request->entertainment_id,
                'user_id' => $user->id,
                'profile_id' => $profileId,
                'type' => $request->type,
            ]
        );

        if (function_exists('clearWatchlistCache')) {
            clearWatchlistCache();
        }

        return response()->json([
            'status' => true,
            'message' => __('movie.watchlist_add'),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function securityControl()
    {
        if(getCurrentProfileSession('is_child_profile') == 1){
            return redirect()->route('user.login');
        }

        $user = Auth::user();

        $Profile = $user->userMultiProfile;
        
        $userProfile = UserMultiProfileResource::collection($Profile);

        return view('frontend::securityControl',compact('user','userProfile'));
    }


    /**
     * Display a listing of the resource.
     */
    public function editProfile()
    {
        $user =Auth::user();

        $dev = Device::where('user_id', $user->id)
                ->where('device_id', request()->ip())
                ->orderBy('id','DESC')
                ->get();

        if(count($dev) > 1)
        {
            Device::where('user_id', $user->id)
                ->where('device_id', request()->ip())
                ->where('id','!=',$dev[0]->id)
                ->delete();
        }

        $Profile = $user->userMultiProfile;
        $profileCount = $Profile->count();
        $userProfile = UserMultiProfileResource::collection($Profile);

        return view('frontend::editProfile',compact('user','userProfile','profileCount'));
    }

    public function updateProfile()
    {
        if(getCurrentProfileSession('is_child_profile') == 1 || !Auth::check()){
            return redirect()->route('user.login');
        }

        $user =Auth::user();

        return view('frontend::updateProfile',compact('user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('frontend::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('frontend::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('frontend::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
public function destroy(UserMultiProfile $profile)
{
    $user = auth()->user();

    if ($profile->user_id !== $user->id) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    if ($profile->is_child_profile == 0) {
        $remainingParentProfiles = UserMultiProfile::where('user_id', $user->id)
            ->where('is_child_profile', 0)
            ->where('id', '!=', $profile->id)
            ->count();
        
        if ($remainingParentProfiles < 1) {
            return response()->json([
                'success' => false,
                'message' => __('messages.atleast_one_parent_profile_is_required')
            ], 406);
        }
    }

    $profile->delete();

    return response()->json([
        'success' => true,
        'message' => __('messages.profile_deleted_successfully'),
        'data' => UserMultiProfileResource::collection(UserMultiProfile::where('user_id', $user->id)->get())
    ]);
}

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => ['required','confirmed','min:8','different:old_password',
            ],
        ]);

        if (!Hash::check($request->old_password, auth()->user()->password)) {
            return response()->json([
                'success' => false,
                'errors' => ['old_password' => 'The old password is incorrect.'],
            ], 422);
        }

        auth()->user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        $user = auth()->user();


        // sendNotification([
        //     'notification_type' => 'change_password',
        //     'user_id' => $user->id,
        //     'user_name' => $user->full_name ?? $user->name ?? $user->username,
        // ]);


        $notificationData = [
        'notification_type' => 'change_password',
             'user_id' => $user->id,
            'user_name' => $user->full_name ?? $user->name ?? $user->username,
        ];

    SendBulkNotification::dispatch($notificationData)->onQueue('notifications');
        return response()->json(['success' => true]);
    }


    public function manageProfile()
{
    // Redirect to account settings instead of showing manage profile page
    return redirect()->route('accountSetting');
}

public function changePassword()
{
    if(getCurrentProfileSession('is_child_profile') == 1 || !Auth::check()){
        return redirect()->route('user.login');
    }

    return view('frontend::changePassword');
}

private function serializeDevice($device)
{
    return [
        'id' => $device->id,
        'user_id' => $device->user_id,
        'device_id' => $device->device_id,
        'device_name' => $device->device_name,
        'active_profile' => $device->active_profile,
        'platform' => $device->platform,
        'created_at' => formatDateTimeWithTimezone($device->created_at),
        'updated_at' => formatDateTimeWithTimezone($device->updated_at),
    ];
}

private function serializeUser($user)
{
    return [
        'id' => $user->id,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'name' => $user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
        'email' => $user->email,
        'mobile' => $user->mobile,
        'country_code' => $user->country_code,
        'address' => $user->address,
        'gender' => $user->gender,
        'date_of_birth' => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d') : null,
        'avatar' => $user->file_url ? setBaseUrlWithFileName($user->file_url, 'image', 'users') : asset('dummy-images/avatars/icon1.png'),
        'login' => $user->login ?? null,
        'login_type' => $user->login_type ?? null,
    ];
}

}
