<?php

use App\Http\Controllers\Auth\API\AuthController;
use App\Http\Controllers\Backend\API\DashboardController;
use App\Http\Controllers\Backend\API\NotificationsController;
use App\Http\Controllers\Backend\API\InvoiceController;

use App\Http\Controllers\Backend\API\SettingController as APISettingController;
use Modules\Frontend\Http\Controllers\PaymentController;
use Modules\Frontend\Http\Controllers\PerviewPaymentController;
use Modules\Frontend\Http\Controllers\QueryOptimizeController;

use Modules\User\Http\Controllers\API\UserController;
use Modules\Entertainment\Http\Controllers\API\EntertainmentsController;
use Modules\LiveTV\Http\Controllers\API\LiveTVsController;
use App\Http\Controllers\TvAuthController;
use App\Http\Controllers\Backend\SettingController;
use App\Http\Controllers\Auth\WebQrLoginController;
use Modules\CastCrew\Http\Controllers\API\CastCrewController;
use Modules\Frontend\Http\Controllers\Auth\OTPController;
use Modules\Frontend\Http\Controllers\API\DistributionController;
use Modules\Frontend\Http\Controllers\API\FooterController;
use Modules\Frontend\Http\Controllers\API\NavigationMenuController;
use App\Http\Controllers\Api\Private\CoreTvAccessController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('user-detail', [AuthController::class, 'userDetails']);

Route::prefix('private/core')->middleware([\App\Http\Middleware\VerifyCorePrivateApi::class])->group(function () {
    Route::post('users/sync', [CoreTvAccessController::class, 'syncUser']);
    Route::post('subscriptions/activate', [CoreTvAccessController::class, 'activate']);
    Route::post('subscriptions/cancel', [CoreTvAccessController::class, 'cancel']);
    Route::get('users/{coreUserId}/access', [CoreTvAccessController::class, 'status'])->whereNumber('coreUserId');
});

Route::get('/optimize', [QueryOptimizeController::class, 'optimize'])->name('optimize');

Route::middleware(['auth:sanctum', 'checkApiDevice'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('social-login', 'socialLogin');
    Route::post('forgot-password', 'forgotPassword');
    Route::get('logout', 'logout');
    Route::get('csrf-token', 'getCsrfToken');
});

Route::post('check-mobile-exists', [OTPController::class, 'checkMobileExists'])->name('api.check.mobile.exists');

Route::get('core/package-categories/{category}/packages', function (Request $request, string $category) {
    $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
    $token = (string) config('services.core_api.token');

    if ($baseUrl === '' || $token === '') {
        return response()->json(['message' => 'Core API is not configured.'], 503);
    }

    $client = \Illuminate\Support\Facades\Http::withToken($token)
        ->acceptJson()
        ->timeout(12);

    $host = trim((string) config('services.core_api.host'));
    if ($host !== '') {
        $client = $client->withHeaders(['Host' => $host]);
    }

    try {
        $response = $client->get($baseUrl.'/api/package-categories/'.$category.'/packages', [
            'status' => $request->query('status', 'active'),
            'limit' => $request->query('limit', 50),
            'page' => $request->query('page', 1),
        ]);
    } catch (\Throwable $exception) {
        report($exception);
        return response()->json(['message' => 'Core API could not be reached.'], 502);
    }

    return response($response->body(), $response->status())
        ->header('Content-Type', $response->header('Content-Type', 'application/json'));
})->where('category', '[A-Za-z0-9_.-]+');
Route::post('subscription/webhook', [PaymentController::class, 'handleSubscriptionWebhook'])->name('api.subscription.webhook');
Route::post('/store-access-token', [SettingController::class, 'storeToken']);
Route::post('/token-revoke', [SettingController::class, 'revokeToken']);
Route::get('get-tranding-data', [DashboardController::class, 'getTrandingData']);

Route::get('v2/dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV2']);
Route::get('v2/dashboard-detail', [DashboardController::class, 'DashboardDetailV2']);
Route::get('v2/episode-details', [EntertainmentsController::class, 'episodeDetailsV2']);
Route::get('v2/livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV2']);
Route::get('v2/tvshow-details', [EntertainmentsController::class, 'tvshowDetailsV2']);
Route::get('v2/movie-details', [EntertainmentsController::class, 'movieDetailsV2']);

Route::get('v2/pay-per-view-list', [DashboardController::class, 'getPayPerViewUnlockedContent']);

// Episode / TV Show helpers
Route::get('tvshows/{tvshow}/plan-level', function ($tvshowId) {
    $tvshow = \Modules\Entertainment\Models\Entertainment::with('plan')->find($tvshowId);
    if (!$tvshow || !$tvshow->plan) {
        return response()->json(['level' => null]);
    }
    return response()->json(['level' => (int) $tvshow->plan->level]);
});

Route::middleware(['auth:sanctum', 'checkApiDevice', 'throttle:api'])->group(function () {
    Route::post('/web-qr-scan', [WebQrLoginController::class, 'scan'])->name('api.web-qr.scan');

    Route::apiResource('setting', SettingController::class);
    Route::apiResource('notification', NotificationsController::class);

    Route::get('notification-list', [NotificationsController::class, 'notificationList']);
    Route::get('notification-count', [NotificationsController::class, 'notificationCount']);


    Route::get('gallery-list', [DashboardController::class, 'globalGallery']);
    Route::get('search-list', [DashboardController::class, 'searchList']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);

    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('delete-account', [AuthController::class, 'deleteAccount']);

    Route::get('unlocked-content', [PerviewPaymentController::class, 'allUnlockVideos']);

    Route::get('download-invoice/{id}', [InvoiceController::class, 'download']);
    Route::get('pay-per-view-invoice/{id}', [InvoiceController::class, 'downloadPayPerViewInvoice']);



    ### v2 api`s

    Route::get('v2/profile-details', [UserController::class, 'profileDetailsV2']);

    Route::post('/change-pin', [AuthController::class, 'changePin'])->name('change-pin');

    Route::get('/send-otp', [AuthController::class, 'sendOtp'])->name('send-otp')->middleware('throttle:1,1'); // 1 request per minute
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp')->middleware('throttle:5,1'); // 5 attempts per minute
    
    Route::post('/verify-pin', [AuthController::class, 'verifyPin'])->name('verify-pin');

    Route::post('/update-parental-lock', [AuthController::class, 'changeParentalLock'])->name('update-parental-lock');
    Route::post('/tv/confrim-session', [TvAuthController::class, 'confirmSession'])->name('confirmSession');

});

Route::prefix('v3')->middleware(['throttle:api'])->group(function () {
    Route::get('/payment-methods', [APISettingController::class, 'getPaymentMethods'])->name('payment.methods');
    Route::get('app-configuration', [APISettingController::class, 'appConfiguratonV3']);
    Route::get('content-details', [EntertainmentsController::class, 'contentDetailsV3']);
    Route::get('dashboard-detail', [DashboardController::class, 'DashboardDetailV3']);
    Route::get('dashboard-detail-data', [DashboardController::class, 'DashboardDetailDataV3']);
    Route::get('livetv-dashboard', [LiveTVsController::class, 'liveTvDashboardV3']);
    Route::get('pay-per-view-list', [DashboardController::class, 'getPayPerViewUnlockedContentV3']);
    Route::get('banner-data', [DashboardController::class, 'getEntertainmentDataV3']);
    Route::get('footer-data', [FooterController::class, 'show'])->name('api.v3.footer-data');
    Route::get('navigation-menu', [NavigationMenuController::class, 'show'])->name('api.v3.navigation-menu');
    // Ad banner sliders (public)
    Route::get('ad-banner-sliders', [\Modules\Ad\Http\Controllers\API\AdBannerSlideApiController::class, 'index'])->name('api.v3.ad-banner-sliders');
    Route::get('cast-details', [CastCrewController::class, 'castCrewDetailsV3'])->name('api.cast_crew_details_v3');

});
// Public endpoint returning distribution page HTML (no header/footer)
Route::get('distribution/html', [DistributionController::class, 'html']);
Route::prefix('v3')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('profile-details', [UserController::class, 'profileDetailsV3'])->name('api.v3.profile-details');
    Route::get('rented-content-list', [PerviewPaymentController::class, 'allUnlockVideosV3']);
    Route::post('delete-notification', [NotificationsController::class, 'deleteNotification']);

});


Route::get('app-configuration', [APISettingController::class, 'appConfiguraton']);

Route::prefix('tv')->group(function () {
    Route::get('/initiate-session', [TvAuthController::class, 'initiateSession']);
    Route::post('/check-session', [TvAuthController::class, 'checkSession']);
});

$coreClient = function () {
    $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
    $token = (string) config('services.core_api.token');

    if ($baseUrl === '' || $token === '') {
        return null;
    }

    $client = \Illuminate\Support\Facades\Http::withToken($token)
        ->acceptJson()
        ->timeout(20);

    $host = trim((string) config('services.core_api.host'));
    if ($host !== '') {
        $client = $client->withHeaders(['Host' => $host]);
    }

    return [$client, $baseUrl];
};

Route::get('core/payment-methods', function (Request $request) use ($coreClient) {
    $user = $request->user() ?: auth()->user();
    if (! $user) {
        return response()->json(['message' => 'Please sign in before choosing a plan.'], 401);
    }

    $core = $coreClient();
    if (! $core) {
        return response()->json(['message' => 'Core API is not configured.'], 503);
    }

    [$client, $baseUrl] = $core;
    $coreUser = (int) ($user->network_user_id ?: $user->id);

    try {
        $response = $client->get($baseUrl.'/api/users/'.$coreUser.'/payment-methods');
    } catch (\Throwable $exception) {
        report($exception);
        return response()->json(['message' => 'Saved cards could not be loaded right now.', 'data' => []], 502);
    }

    return response($response->body(), $response->status())
        ->header('Content-Type', $response->header('Content-Type', 'application/json'));
});

Route::post('core/checkouts', function (Request $request) use ($coreClient) {
    $user = $request->user() ?: auth()->user();
    if (! $user) {
        return response()->json(['message' => 'Please sign in before choosing a plan.'], 401);
    }

    $data = $request->validate([
        'package_slug' => ['required', 'string', 'max:255'],
        'payment_method_id' => ['nullable', 'string', 'max:255'],
    ]);

    $core = $coreClient();
    if (! $core) {
        return response()->json(['message' => 'Core API is not configured.'], 503);
    }

    [$client, $baseUrl] = $core;
    $name = trim((string) (($user->first_name ?? '').' '.($user->last_name ?? '')));
    $successUrl = url('/subscription-plan?checkout_status=success');
    $cancelUrl = url('/subscription-plan?checkout_status=cancelled');
    $coreUser = (int) ($user->network_user_id ?: $user->id);

    try {
        $response = $client->post($baseUrl.'/api/checkouts', [
            'package_slugs' => [$data['package_slug']],
            'user_id' => $coreUser,
            'customer' => [
                'name' => $name !== '' ? $name : ($user->name ?? $user->email),
                'email' => $user->email,
                'phone' => $user->mobile ?? null,
            ],
            'platform' => ['slug' => 'ezway-tv'],
            'subject_type' => 'tv_subscription',
            'subject_id' => (string) $coreUser,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'failed_url' => url('/subscription-plan?checkout_status=failed'),
            'checkout_mode' => 'auto',
            'payment_method_id' => trim((string) ($data['payment_method_id'] ?? '')) ?: null,
            'metadata' => [
                'source_app' => 'ezway_tv',
                'tv_user_id' => (string) $user->id,
                'network_user_id' => (string) ($user->network_user_id ?: ''),
            ],
        ]);
    } catch (\Throwable $exception) {
        report($exception);
        return response()->json(['message' => 'Core checkout could not be reached.'], 502);
    }

    return response($response->body(), $response->status())
        ->header('Content-Type', $response->header('Content-Type', 'application/json'));
});