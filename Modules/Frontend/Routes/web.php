<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\MovieController;
use Modules\Frontend\Http\Controllers\FrontendController;
use Modules\Frontend\Http\Controllers\PaymentController;
use Modules\Frontend\Http\Controllers\LiveTvChatController;
use Modules\Frontend\Http\Controllers\LiveTvController;
use Modules\Frontend\Http\Controllers\Auth\AuthController;
use Modules\Frontend\Http\Controllers\Auth\OTPController;
use Modules\Frontend\Http\Controllers\Auth\WordPressSsoController;
use App\Http\Controllers\LanguageController;
use Modules\Frontend\Http\Controllers\TvShowController;
use Modules\Frontend\Http\Controllers\CastCrewController;
use Modules\Frontend\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Frontend\Http\Controllers\Auth\UserController;
use Modules\Frontend\Http\Controllers\PerviewPaymentController;
use Modules\Entertainment\Http\Controllers\Backend\EntertainmentsController;
use Modules\NotificationTemplate\Http\Controllers\Backend\NotificationTemplatesController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware(['checkInstallation'])->group(function () {

// Login with Applelo
Route::get('/auth/apple', [AuthController::class, 'redirectToApple'])->name('auth.apple');
Route::get('/auth/apple/callback', [AuthController::class, 'handleAppleCallback'])->name('auth.apple.callback');


// Login with OTP
Route::view('/login', 'react-modernization')->name('login');

Route::view('/otp-login', 'react-modernization')->name('otp-login');
Route::post('/auth/otp-login-store', [OTPController::class, 'otpLoginStore'])->name('auth.otp-login-store');
Route::get('/auth/check-user-exists', [OTPController::class, 'checkUserExists'])->name('check.user.exists');
Route::post('/auth/check-mobile-exists', [OTPController::class, 'checkMobileExists'])->name('check.mobile.exists');


// Login with Google
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');



Route::get('language/{language}', [LanguageController::class, 'switch'])->name('frontend.language.switch');
Route::view('/login-page', 'react-modernization')->name('login-page');
Route::get('/sso/wp/login', [WordPressSsoController::class, 'login'])->name('wordpress-sso.login');
Route::post('/store-user', [AuthController::class, 'store'])->name('store-user');
Route::view('/register', 'react-modernization')->name('register-page');
Route::view('/forget-password', 'react-modernization')->name('forget-password');


Route::post('/security-control', function(Request $request) {
    $user = auth()->user();

    if ($user) {
        $user->is_parental_lock_enable=1;
        $user->save();

        return response()->json(['status' => 'success','parent_control' => 1]);
    }

    return response()->json(['status' => 'fail'], 400);
})->name('security-control-enable');

Route::post('/disable-security', function(Request $request) {
    $user = auth()->user();
    $user->is_parental_lock_enable=0;
    $user->save();

    if ($user) {
        return response()->json(['status' => 'success','parent_control' => 0]);
    }

    return response()->json(['status' => 'fail'], 400);
})->name('disable-security');





Route::view('movies/genre/{genre_id}', 'react-modernization')->middleware('checkModule')->name('movies.genre');
Route::view('movies/{language}', 'react-modernization')->middleware('checkModule')->name('movies.language');
Route::view('/movies', 'react-modernization')->middleware('checkModule')->name('movies');
Route::view('/movie-details/{id}', 'react-modernization')->middleware('checkModule')->name('movie-details');
Route::view('/tv-shows', 'react-modernization')->middleware('checkModule')->name('tv-shows');
Route::view('/tvshow-details/{id}', 'react-modernization')->middleware('checkModule')->name('tvshow-details');
Route::view('/episode-details/{id}', 'react-modernization')->middleware('checkModule')->name('episode-details');
Route::view('/videos', 'react-modernization')->middleware('checkModule')->name('videos');
Route::view('/videos/category/{slug}', 'react-modernization')->middleware('checkModule')->name('videos.by-category');
Route::view('/video-details/{id}', 'react-modernization')->middleware('checkModule')->name('video-detail');
Route::view('/pay-per-view', 'react-modernization')->name('pay-per-view');
Route::view('/content/{type}', 'react-modernization')->middleware('checkModule')->name('content.list');
Route::view('/section/{slug}', 'react-modernization')->name('custom-section');
Route::view('/comming-soon-details/{id}', 'react-modernization')->name('comming-soon-details');


Route::view('/comingsoon', 'react-modernization')->name('comingsoon');
Route::view('/livetv', 'react-modernization')->middleware('checkModule')->name('livetv');
Route::view('/livetv/{path}', 'react-modernization')->where('path', '^(?!details|channels|chat).*$')->middleware('checkModule')->name('livetv.spa-detail');
Route::get('/livetv-details/{id}', [LiveTvController::class, 'liveTvDetails'])->middleware('checkModule')->name('livetv-details');
Route::get('/livetv-channels/{id}', [LiveTvController::class, 'livetvChannelsList'])->middleware('checkModule')->name('livetv-channels');
Route::get('/livetv-chat/{channelId}/messages', [LiveTvChatController::class, 'index'])->middleware('checkModule')->name('livetv-chat.messages');
Route::post('/livetv-chat/{channelId}/session', [LiveTvChatController::class, 'storeGuest'])->middleware('checkModule')->name('livetv-chat.session');
Route::post('/livetv-chat/{channelId}/messages', [LiveTvChatController::class, 'storeMessage'])->middleware('checkModule')->name('livetv-chat.store');



Route::view('/castcrew-detail/{id}', 'react-modernization')->name('castcrew-detail');
Route::view('/castcrew-list', 'react-modernization')->name('castcrewList');
Route::view('/castcrew-list/{type}/{id}', 'react-modernization')->name('movie-castcrew-list');

Route::view('/continuewatch-list', 'react-modernization')->name('continueWatchList');
Route::view('/language-list', 'react-modernization')->name('languageList');
Route::view('/topchannel-list', 'react-modernization')->name('topChannelList');
Route::view('/genres-list', 'react-modernization')->name('genresList');
Route::get('/languages-data',[FrontendController::class, 'languageData'])->name(name: 'languageData');
Route::view('/search', 'react-modernization')->name('search');



Route::view('/watch-list', 'react-modernization')->name('watchList');

Route::view('/faq', 'react-modernization')->name('faq');

Route::view('/all-review/{id}', 'react-modernization')->name('all-review');
Route::view('/video-details/{id}', 'react-modernization')->name('video-details');


Route::post('/decrypt-url', [FrontendController::class, 'decryptUrl'])->name('decrypt.url');

Route::post('/get-available-promotions', [PaymentController::class, 'getAvailablePromotions'])
    ->name('get-available-promotions');
});

Route::view('/trending-movies', 'react-modernization')->name('trending.movies');

Route::group(['middleware' => ['user']], function () {
    Route::post('/account/password/update', [UserController::class, 'updatePassword'])->name('account.password.update');
    Route::get('/logout', [AuthController::class, 'Logout'])->name('user-logout');
    Route::view('/account-setting', 'react-modernization')->name('accountSetting');
    // Profile management removed per request. Route disabled.
    // Route::get('/profile-management', [FrontendController::class, 'profileManagement'])->name('profile-management');
    Route::delete('/profile/delete/{profile}', [UserController::class, 'destroy'])->name('profile.destroy');
    Route::post('/device-logout', [FrontendController::class, 'deviceLogout'])->name('device-logout');
    Route::view('/subscription-payment', 'react-modernization')->name('subscription-payment');
    Route::view('/payment-history', 'react-modernization')->name('payment-history');
    Route::view('/transaction-history', 'react-modernization')->name('transaction-history');
    Route::view('/pay-per-view/invoice/{id}', 'react-modernization')->name('payperview.invoice');
    Route::post('/get-payment-details', [FrontendController::class, 'getPaymentDetails']);
    Route::get('invoice-download', [FrontendController::class, 'downloadInvoice'])->name('downloadinvoice');
    Route::view('/subscription-plan', 'react-modernization')->name('subscriptionPlan');
    Route::post('/process-payment', [PaymentController::class, 'processPayment'])->name('process-payment');
    Route::post('/select-plan', [PaymentController::class, 'selectPlan'])->name('select.plan');
    Route::view('/payment/success', 'react-modernization')->name('payment.success');
    Route::view('/security-control', 'react-modernization')->name('security-control');
    Route::view('/manage-profile', 'react-modernization')->name('manage-profile');



    Route::post('/cancel-subscription', [FrontendController::class, 'cancelSubscription'])->name('cancelSubscription');
});

Route::get('/video/stream/{encryptedUrl}', [TvShowController::class, 'stream'])->name('video.stream');
Route::get('/video/1/{encryptedUrl}', [TvShowController::class, 'streamLocal'])->name('video.1');
Route::get('/check-device-type', [FrontendController::class, 'checkDeviceType'])->middleware('auth');
Route::get('/check-subscription/{planId}', [FrontendController::class, 'checkSubscription'])->middleware('auth');




Route::group(['as' => 'frontend.', 'middleware' => ['auth']], function () {
    Route::post('/clear-cache-config', function () {
        \Artisan::call('config:clear');
        \Artisan::call('cache:clear');
        return response()->json(['message' => 'Cache and Config cleared']);
    })->name('cache_config_clear'); // Define the name for the route
});

Route::view('/payment-form/pay-per-view', 'react-modernization')->name('pay-per-view.paymentform');
Route::post('/process-payment/pay-per-view', [PerviewPaymentController::class, 'processPayment'])->name('process-payment.payperview');
Route::view('/payment/success/pay-per-view', 'react-modernization')->name('payperview.payment.success');
Route::view('/unlock-videos', 'react-modernization')->name('unlock.videos');

// Notification routes
Route::view('/notifications', 'react-modernization')->name('notifications.index');
Route::get('/notifications/mark-all-read', [\Modules\Frontend\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
Route::post('/notifications/{id}/mark-read', [\Modules\Frontend\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
Route::delete('/notifications/delete-all', [\Modules\Frontend\Http\Controllers\NotificationController::class, 'deleteAll'])->name('notifications.deleteAll');
Route::delete('/notifications/delete-selected', [\Modules\Frontend\Http\Controllers\NotificationController::class, 'deleteSelected'])->name('notifications.deleteSelected');

Route::post('/pay-per-view/start-date', [PerviewPaymentController::class, 'setStartDate'])->name('pay-per-view.start-date');
Route::view('/update-profile', 'react-modernization')->name('update-profile');
Route::view('/change-password', 'react-modernization')->name('change-password');

// Distribution page route (static view)
Route::view('/distribution', 'react-modernization')->name('distribution');

// API: distribution data
Route::get('/api/distribution', function () {
    $path = base_path('Modules/Frontend/Resources/data/distribution.json');
    if (!file_exists($path)) {
        return response()->json(['error' => 'Data not found'], 404);
    }
    $json = file_get_contents($path);
    return response($json, 200)->header('Content-Type', 'application/json');
})->name('api.distribution');
