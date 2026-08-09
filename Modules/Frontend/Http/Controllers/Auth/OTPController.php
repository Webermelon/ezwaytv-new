<?php


namespace Modules\Frontend\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;
use Auth;
use Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CoreTvAccessService;
use App\Models\Device;
use App\Models\Setting;
use Modules\Frontend\Trait\LoginTrait;
use App\Models\WebQrSession;


class OTPController extends Controller
{
    use LoginTrait;

    private const OTP_SEND_MAX_ATTEMPTS = 5;
    private const OTP_VERIFY_MAX_ATTEMPTS = 5;
    private const OTP_ATTEMPT_WINDOW_SECONDS = 900;
    private const OTP_BLOCK_SECONDS = 900;

    public function otpLogin()
    {
        $userId = auth()->id();

        $settings = Setting::getAllSettings($userId);
        $isOtpLoginEnabled = Setting::where('name', 'is_otp_login')->value('val') == 1;

         // Generate QR token
        $qrSession = WebQrSession::create([
            'session_id' => Str::uuid(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5) // expires in 5 mins
        ]);

        // URL for mobile app scan
        // $qrUrl = route('api.web-qr.scan', ['session_id' => $qrSession->session_id]);

        // Generate QR code
        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate($qrSession->session_id);

        return view('frontend::auth.otp_login', compact('settings', 'isOtpLoginEnabled', 'qrCode', 'qrSession'));
    }


    public function otpLoginStore(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
            'mobile' => 'required|string|max:255|unique:users,mobile',
        ], [
            'email.required' => __('frontend.email_required'),
            'email.email' => __('frontend.email_invalid_format'),
            'email.unique' => __('frontend.email_already_taken'),
            'mobile.required' => __('frontend.mobile_required'),
            'mobile.unique' => __('frontend.mobile_already_exists'),
        ]);

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' =>  $request->email,
            'mobile' =>  $request->mobile,
            'password' => Hash::make(Str::random(8)),
            'user_type' => 'user',
            'login_type' => 'otp',
            'country_code' => $request->country_code
        ];

        $user=User::where('email', $request->email)->first();

        $user = User::create($data);

        $request->session()->regenerate();

        $user->createOrUpdateProfileWithAvatar();

        $user->assignRole($data['user_type']);

        $user->save();

        if($user->login_type == 'otp' )
        {
            Auth::login($user);
            $this->setDevice($user, $request);
        }
        else
        {
            $user=Auth::user();
            Auth::logout();
            $this->removeDevice($user, $request);
           return Redirect::to('/login')->with('error', 'Something went wrong! During login');
        }

        return redirect('/'); // Redirect to intended page
    }


    public function checkSpaUsername(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^(?=(?:.*[A-Za-z]){3,})[A-Za-z0-9_.-]+$/'],
        ]);

        $localExists = User::withTrashed()->where('username', $validated['username'])->exists();
        if ($localExists) {
            return response()->json([
                'status' => true,
                'available' => false,
                'message' => 'Already taken.',
            ]);
        }

        $response = $this->coreGet('/api/users/check-username', [
            'username' => $validated['username'],
        ]);

        if (!$response || $response->serverError()) {
            return response()->json([
                'status' => true,
                'available' => true,
                'message' => 'Username is valid',
            ]);
        }

        if ($response->successful()) {
            $payload = $response->json();
            if (is_array($payload) && array_key_exists('available', $payload)) {
                $payload['message'] = $payload['available'] ? 'Username is valid' : ($payload['message'] ?? 'Already taken.');
                return response()->json($payload, $response->status());
            }
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type', 'application/json'));
    }

    public function checkSpaEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $localExists = User::withTrashed()->where('email', $validated['email'])->exists();
        if ($localExists) {
            return response()->json([
                'status' => true,
                'available' => false,
                'message' => 'Already taken.',
            ]);
        }

        $response = $this->coreGet('/api/users/check-email', [
            'email' => $validated['email'],
        ]);

        if (!$response || $response->serverError()) {
            return response()->json([
                'status' => true,
                'available' => true,
                'message' => 'Email is valid',
            ]);
        }

        if ($response->successful()) {
            $payload = $response->json();
            if (is_array($payload) && array_key_exists('available', $payload)) {
                $payload['message'] = $payload['available'] ? 'Email is valid' : ($payload['message'] ?? 'Already taken.');
                return response()->json($payload, $response->status());
            }
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type', 'application/json'));
    }

    public function registerSpa(Request $request)
    {
        $validated = $request->validate([
            'invite_code' => ['nullable', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:32'],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^(?=(?:.*[A-Za-z]){3,})[A-Za-z0-9_.-]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:32'],
        ]);

        $existing = User::withTrashed()->where('email', $validated['email'])->first();
        if ($existing) {
            return response()->json([
                'status' => false,
                'message' => 'This email already has an eZWay TV account. Please sign in instead.',
            ], 409);
        }

        $otp = $this->makeLoginOtp();
        $name = trim($validated['first_name'].' '.$validated['last_name']);

        if (! $this->sendOtpViaCore($validated['email'], $otp, $name)) {
            return response()->json([
                'status' => false,
                'message' => 'Could not send the login code right now. Please check Core email delivery settings and try again.',
            ], 500);
        }

        $request->session()->put('tv_pending_registration', [
            'data' => $validated,
            'otp' => $otp,
            'email' => $validated['email'],
            'expires_at' => now()->addMinutes(10)->timestamp,
            'ip_address' => $request->ip(),
        ]);
        $request->session()->put('tv_login_otp_email', $validated['email']);
        $request->session()->put('tv_login_otp_expires_at', now()->addMinutes(10)->timestamp);

        return response()->json([
            'status' => true,
            'message' => 'We sent a 4-digit login code to your email.',
        ]);
    }

    private function createCoreRegistrationUser(Request $request, array $validated): array
    {
        $existing = User::withTrashed()->where('email', $validated['email'])->first();
        if ($existing) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => 'This email already has an eZWay TV account. Please sign in instead.',
                ], 409),
            ];
        }

        $response = $this->corePost('/api/users', [
            'invite_code' => trim((string) ($validated['invite_code'] ?? '')) ?: null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'source' => 'ezway-tv',
            'timezone' => config('app.timezone', 'UTC'),
            'active' => '1',
            'ip_address' => $request->ip(),
        ], 20);

        if (!$response) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => 'Core signup is not available right now.',
                ], 503),
            ];
        }

        if (!$response->successful() && $response->status() !== 409) {
            Log::warning('Core user signup failed for TV.', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return [
                'response' => response($response->body(), $response->status())
                    ->header('Content-Type', $response->header('Content-Type', 'application/json')),
            ];
        }

        if ($response->status() === 409) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => $response->json('message') ?: 'This account already exists. Please sign in instead.',
                ], 409),
            ];
        }

        $coreUser = $response->json('data');
        if (!is_array($coreUser) || empty($coreUser['id'])) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => 'Core signup did not return a valid user.',
                ], 502),
            ];
        }

        try {
            $user = app(CoreTvAccessService::class)->syncUser([
                'core_user_id' => (int) $coreUser['id'],
                'connect_user_id' => (int) $coreUser['id'],
                'email' => (string) ($coreUser['email'] ?? $validated['email']),
                'username' => (string) ($coreUser['username'] ?? $validated['username']),
                'first_name' => (string) ($coreUser['first_name'] ?? $validated['first_name']),
                'last_name' => (string) ($coreUser['last_name'] ?? $validated['last_name']),
                'phone' => (string) ($coreUser['phone_number'] ?? $validated['phone_number'] ?? ''),
            ]);

            if (!$user->hasRole('user')) {
                $user->assignRole('user');
            }

            $user->createOrUpdateProfileWithAvatar();
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => 'The account was created in Core, but TV could not prepare the local account.',
                ], 500),
            ];
        }

        return ['user' => $user];
    }

    public function sendSpaOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = $this->normalizeEmail($validated['email']);

        if ($response = $this->otpBlockedResponseIfNeeded($request, $email, 'verify')) {
            return $response;
        }

        if ($response = $this->otpBlockedResponseIfNeeded($request, $email, 'send')) {
            return $response;
        }

        if ($response = $this->recordOtpAttempt($request, $email, 'send', self::OTP_SEND_MAX_ATTEMPTS)) {
            return $response;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user && $user->trashed()) {
            $user->restore();
            $user->forceFill([
                'status' => 1,
                'user_type' => $user->user_type ?: 'user',
                'login_type' => $user->login_type ?: 'otp',
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
            $user = $user->refresh();
        }

        if ($user) {
            if ($user->user_type !== 'user') {
                return response()->json([
                    'status' => false,
                    'message' => 'We could not find an active eZWay TV account with that email.',
                ], 404);
            }

            if (empty($user->network_user_id) && ($coreLogin = $this->coreLoginPayloadForEmail($email))) {
                return $this->sendPendingCoreLoginOtp($request, $email, $coreLogin);
            }

            return $this->sendLoginOtpForUser($request, $user, 'We sent a 4-digit login code to your email.');
        }

        $coreLogin = $this->coreLoginPayloadForEmail($email);

        if (!$coreLogin) {
            return response()->json([
                'status' => false,
                'message' => 'We could not find an active eZWay TV account with that email.',
            ], 404);
        }

        return $this->sendPendingCoreLoginOtp($request, $email, $coreLogin);
    }

    public function verifySpaOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|digits:4',
        ]);

        $email = $this->normalizeEmail($validated['email']);
        $otp = (string) $validated['otp'];

        if ($response = $this->otpBlockedResponseIfNeeded($request, $email, 'verify')) {
            return $response;
        }

        $sessionEmail = $request->session()->get('tv_login_otp_email');
        $expiresAt = (int) $request->session()->get('tv_login_otp_expires_at', 0);

        if (!$sessionEmail || strcasecmp($sessionEmail, $email) !== 0 || $expiresAt < now()->timestamp) {
            return response()->json([
                'status' => false,
                'message' => 'Your login code has expired. Please request a new one.',
            ], 422);
        }

        $pendingRegistration = $request->session()->get('tv_pending_registration');
        if (is_array($pendingRegistration) && strcasecmp((string) ($pendingRegistration['email'] ?? ''), $email) === 0) {
            if ((int) ($pendingRegistration['expires_at'] ?? 0) < now()->timestamp) {
                $request->session()->forget(['tv_pending_registration', 'tv_login_otp_email', 'tv_login_otp_expires_at']);

                return response()->json([
                    'status' => false,
                    'message' => 'Your login code has expired. Please request a new one.',
                ], 422);
            }

            if (!hash_equals((string) ($pendingRegistration['otp'] ?? ''), $otp)) {
                return $this->recordOtpFailure($request, $email);
            }

            $registrationData = $pendingRegistration['data'] ?? null;
            if (!is_array($registrationData)) {
                $request->session()->forget(['tv_pending_registration', 'tv_login_otp_email', 'tv_login_otp_expires_at']);

                return response()->json([
                    'status' => false,
                    'message' => 'Registration details expired. Please start again.',
                ], 422);
            }

            $result = $this->createCoreRegistrationUser($request, $registrationData);
            if (isset($result['response'])) {
                return $result['response'];
            }

            $user = $result['user'];
            $request->session()->forget(['tv_pending_registration', 'tv_login_otp_email', 'tv_login_otp_expires_at']);
            $request->session()->regenerate();
            $this->clearOtpAttempts($request, $email);

            Auth::login($user);
            $this->setDevice($user, $request);

            return response()->json([
                'status' => true,
                'message' => 'You are signed in.',
                'data' => [
                    'redirect_url' => '/subscription-plan',
                ],
            ]);
        }

        $pendingCoreLogin = $request->session()->get('tv_pending_core_login');
        if (is_array($pendingCoreLogin) && strcasecmp((string) ($pendingCoreLogin['email'] ?? ''), $email) === 0) {
            if ((int) ($pendingCoreLogin['expires_at'] ?? 0) < now()->timestamp) {
                $request->session()->forget(['tv_pending_core_login', 'tv_login_otp_email', 'tv_login_otp_expires_at']);

                return response()->json([
                    'status' => false,
                    'message' => 'Your login code has expired. Please request a new one.',
                ], 422);
            }

            if (!hash_equals((string) ($pendingCoreLogin['otp'] ?? ''), $otp)) {
                return $this->recordOtpFailure($request, $email);
            }

            try {
                $user = $this->createUserFromVerifiedCoreLogin($pendingCoreLogin);
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json([
                    'status' => false,
                    'message' => 'TV could not prepare your local account right now.',
                ], 500);
            }

            if (!$user || $user->user_type !== 'user') {
                return response()->json([
                    'status' => false,
                    'message' => 'We could not find an active eZWay TV account with that email.',
                ], 404);
            }

            $request->session()->forget(['tv_pending_core_login', 'tv_login_otp_email', 'tv_login_otp_expires_at']);
            $request->session()->regenerate();
            $this->clearOtpAttempts($request, $email);

            Auth::login($user);
            $this->setDevice($user, $request);

            return response()->json([
                'status' => true,
                'message' => 'You are signed in.',
                'data' => [
                    'redirect_url' => route('user.login'),
                ],
            ]);
        }

        $user = User::where('email', $email)->where('otp', $otp)->first();

        if (!$user || $user->user_type !== 'user') {
            return $this->recordOtpFailure($request, $email);
        }

        $request->session()->forget(['tv_login_otp_email', 'tv_login_otp_expires_at']);
        $request->session()->regenerate();
        $this->clearOtpAttempts($request, $email);
        $user->forceFill(['otp' => null])->save();

        Auth::login($user);
        $this->setDevice($user, $request);

        return response()->json([
            'status' => true,
            'message' => 'You are signed in.',
            'data' => [
                'redirect_url' => route('user.login'),
            ],
        ]);
    }

    private function sendLoginOtpForUser(Request $request, User $user, string $message)
    {
        $otp = $this->makeLoginOtp();

        if (! $this->sendOtpViaCore($user->email, $otp, trim($user->first_name.' '.$user->last_name))) {
            return response()->json([
                'status' => false,
                'message' => 'Could not send the login code right now. Please check Core email delivery settings and try again.',
            ], 500);
        }

        $this->storeLoginOtp($request, $user, $otp);

        return response()->json([
            'status' => true,
            'message' => $message,
        ]);
    }

    private function sendPendingCoreLoginOtp(Request $request, string $email, array $coreLogin)
    {
        $otp = $this->makeLoginOtp();
        $syncData = $coreLogin['sync_data'] ?? [];
        $name = trim((string) (($syncData['first_name'] ?? '').' '.($syncData['last_name'] ?? '')));

        if (! $this->sendOtpViaCore($email, $otp, $name)) {
            return response()->json([
                'status' => false,
                'message' => 'Could not send the login code right now. Please check Core email delivery settings and try again.',
            ], 500);
        }

        $request->session()->put('tv_pending_core_login', [
            'email' => $email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'sync_data' => $syncData,
            'access_data' => $coreLogin['access_data'] ?? null,
        ]);
        $request->session()->put('tv_login_otp_email', $email);
        $request->session()->put('tv_login_otp_expires_at', now()->addMinutes(10)->timestamp);

        return response()->json([
            'status' => true,
            'message' => 'We sent a 4-digit login code to your email.',
        ]);
    }

    private function makeLoginOtp(): string
    {
        return (string) random_int(1000, 9999);
    }

    private function storeLoginOtp(Request $request, User $user, string $otp): void
    {
        $user->forceFill(['otp' => $otp])->save();
        $request->session()->put('tv_login_otp_email', $user->email);
        $request->session()->put('tv_login_otp_expires_at', now()->addMinutes(10)->timestamp);
    }

    private function recordOtpFailure(Request $request, string $email)
    {
        if ($response = $this->recordOtpAttempt($request, $email, 'verify', self::OTP_VERIFY_MAX_ATTEMPTS)) {
            return $response;
        }

        return response()->json([
            'status' => false,
            'message' => 'The code you entered is not valid.',
        ], 422);
    }

    private function recordOtpAttempt(Request $request, string $email, string $action, int $maxAttempts)
    {
        $email = $this->normalizeEmail($email);

        foreach ($this->otpAttemptKeys($request, $email, $action) as $key) {
            Cache::add($key, 0, now()->addSeconds(self::OTP_ATTEMPT_WINDOW_SECONDS));
            $attempts = Cache::increment($key);

            if ($attempts >= $maxAttempts) {
                $this->blockOtpIdentity($request, $email, $action);

                return $this->otpBlockedResponse($this->otpBlockRemainingSeconds($request, $email, $action));
            }
        }

        return null;
    }

    private function otpBlockedResponseIfNeeded(Request $request, string $email, string $action)
    {
        $remainingSeconds = $this->otpBlockRemainingSeconds($request, $email, $action);

        return $remainingSeconds > 0 ? $this->otpBlockedResponse($remainingSeconds) : null;
    }

    private function otpBlockedResponse(int $remainingSeconds)
    {
        $remainingSeconds = max(1, $remainingSeconds);
        $minutes = max(1, (int) ceil($remainingSeconds / 60));

        return response()->json([
            'status' => false,
            'message' => "Too Many Attempts. Please try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.',
            'retry_after_seconds' => $remainingSeconds,
            'retry_after_at' => now()->addSeconds($remainingSeconds)->toIso8601String(),
        ], 429)->header('Retry-After', (string) $remainingSeconds);
    }

    private function blockOtpIdentity(Request $request, string $email, string $action): void
    {
        $blockedUntil = now()->addSeconds(self::OTP_BLOCK_SECONDS)->timestamp;

        foreach ($this->otpBlockKeys($request, $email, $action) as $key) {
            Cache::put($key, $blockedUntil, now()->addSeconds(self::OTP_BLOCK_SECONDS));
        }
    }

    private function otpBlockRemainingSeconds(Request $request, string $email, string $action): int
    {
        $remainingSeconds = 0;

        foreach ($this->otpBlockKeys($request, $email, $action) as $key) {
            $blockedUntil = (int) Cache::get($key, 0);
            $remainingSeconds = max($remainingSeconds, $blockedUntil - now()->timestamp);
        }

        return max(0, $remainingSeconds);
    }

    private function clearOtpAttempts(Request $request, string $email): void
    {
        foreach (['send', 'verify'] as $action) {
            foreach (array_merge(
                $this->otpAttemptKeys($request, $email, $action),
                $this->otpBlockKeys($request, $email, $action)
            ) as $key) {
                Cache::forget($key);
            }
        }
    }

    private function otpAttemptKeys(Request $request, string $email, string $action): array
    {
        return [
            'tv_otp_attempts:'.$action.':email:'.sha1($this->normalizeEmail($email)),
            'tv_otp_attempts:'.$action.':ip:'.sha1((string) $request->ip()),
        ];
    }

    private function otpBlockKeys(Request $request, string $email, string $action): array
    {
        return [
            'tv_otp_block:'.$action.':email:'.sha1($this->normalizeEmail($email)),
            'tv_otp_block:'.$action.':ip:'.sha1((string) $request->ip()),
        ];
    }

    private function coreGet(string $path, array $query = [], int $timeout = 12): ?\Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        try {
            return $this->coreApiRequest($timeout)->get($baseUrl.$path, $query);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function corePost(string $path, array $payload = [], int $timeout = 15): ?\Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        try {
            return $this->coreApiRequest($timeout)->post($baseUrl.$path, $payload);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }
    }

    private function coreApiRequest(int $timeout = 15): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withToken((string) config('services.core_api.token'))
            ->acceptJson()
            ->timeout($timeout);

        $host = trim((string) config('services.core_api.host'));
        if ($host !== '') {
            $request = $request->withHeaders(['Host' => $host]);
        }

        return $request;
    }
    private function sendOtpViaCore(string $email, string $otp, string $name = ''): bool
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            Log::warning('TV OTP email skipped because Core API is not configured.');
            return false;
        }

        $bodyText = "Your eZWay TV login code is {$otp}. This code expires in 10 minutes.";
        $bodyHtml = '<p>Your eZWay TV login code is:</p>'
            .'<p style="font-size:28px;font-weight:800;letter-spacing:8px;margin:18px 0;">'.e($otp).'</p>'
            .'<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>';

        try {
            $response = $this->coreApiRequest(15)
                ->post($baseUrl.'/api/emails/send', [
                    'mode' => 'direct',
                    'to' => $email,
                    'to_name' => $name !== '' ? $name : null,
                    'subject' => 'Your eZWay TV login code',
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'from_name' => 'eZWay TV',
                    'variables' => [
                        'platform' => [
                            'name' => 'eZWay TV',
                            'network_name' => 'eZWay TV',
                            'url' => config('app.url'),
                            'network_url' => config('app.url'),
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }

        if (! $response->successful()) {
            Log::warning('Core email API failed to send TV OTP.', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
            return false;
        }

        return true;
    }
    private function coreLoginPayloadForEmail(string $email): ?array
    {
        $baseUrl = rtrim((string) config('services.core_api.base_url'), '/');
        $token = (string) config('services.core_api.token');

        if ($baseUrl === '' || $token === '') {
            Log::warning('TV OTP Core lookup skipped because Core API is not configured.');
            return null;
        }

        try {
            $response = $this->coreApiRequest(12)
                ->get($baseUrl.'/api/tv/login-user', [
                    'email' => $email,
                    'package_slug' => $this->coreTvPackageSlug(),
                ]);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }

        if (! $response->successful()) {
            Log::info('TV OTP Core lookup did not return access.', [
                'email' => $email,
                'status' => $response->status(),
            ]);
        } else {
            $payload = $response->json('data');
            if (is_array($payload) && !empty($payload['core_user_id'])) {
                return [
                    'sync_data' => $this->coreUserFromAccessPayload($payload, $email),
                    'access_data' => $payload,
                ];
            }
        }

        return $this->coreIdentityPayloadByEmail($email);
    }

    private function coreIdentityPayloadByEmail(string $email): ?array
    {
        $email = $this->normalizeEmail($email);
        $response = $this->coreGet('/api/users/check-email', ['email' => $email]);

        if (!$response || !$response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (!is_array($payload) || !$this->coreEmailExists($payload)) {
            return null;
        }

        return [
            'sync_data' => $this->coreUserFromLookupPayload($payload) ?: $this->fallbackCoreUserFromEmail($email),
            'access_data' => null,
        ];
    }

    private function createUserFromVerifiedCoreLogin(array $pendingCoreLogin): ?User
    {
        $accessData = $pendingCoreLogin['access_data'] ?? null;
        if (is_array($accessData) && !empty($accessData['core_user_id'])) {
            app(CoreTvAccessService::class)->activate($accessData);

            return User::query()
                ->where('network_user_id', (int) $accessData['core_user_id'])
                ->orWhere('email', $this->normalizeEmail((string) ($accessData['email'] ?? $pendingCoreLogin['email'] ?? '')))
                ->first();
        }

        $syncData = $pendingCoreLogin['sync_data'] ?? null;
        if (!is_array($syncData)) {
            return null;
        }

        if ((int) ($syncData['core_user_id'] ?? 0) > 0) {
            $user = app(CoreTvAccessService::class)->syncUser($syncData);
            $user->forceFill(['is_subscribe' => 0])->save();

            if (!$user->hasRole('user')) {
                $user->assignRole('user');
            }

            $user->createOrUpdateProfileWithAvatar();

            return $user->refresh();
        }

        return $this->createFreeCoreShellUser($syncData);
    }

    private function createFreeCoreShellUser(array $syncData): User
    {
        $email = $this->normalizeEmail((string) ($syncData['email'] ?? ''));
        $localPart = Str::of($email)->before('@')->replaceMatches('/[^A-Za-z0-9_.-]+/', '-')->trim('-')->lower();
        $requestedUsername = trim((string) ($syncData['username'] ?? ''));
        $username = $this->uniqueLocalUsername($requestedUsername !== '' ? $requestedUsername : (string) ($localPart ?: 'tv-user'));

        $user = User::withTrashed()->where('email', $email)->first();
        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill([
                'status' => 1,
                'user_type' => $user->user_type ?: 'user',
                'login_type' => $user->login_type ?: 'otp',
                'email_verified_at' => $user->email_verified_at ?: now(),
                'is_subscribe' => 0,
            ])->save();

            return $user->refresh();
        }

        $name = (string) Str::of($localPart)->replace(['.', '_', '-'], ' ')->title();

        $user = User::create([
            'first_name' => trim((string) ($syncData['first_name'] ?? '')) ?: ($name ?: 'eZWay'),
            'last_name' => trim((string) ($syncData['last_name'] ?? '')) ?: 'Member',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make(Str::password(32)),
            'status' => 1,
            'user_type' => 'user',
            'login_type' => 'otp',
            'email_verified_at' => now(),
            'is_network_user' => 0,
            'is_subscribe' => 0,
        ]);

        if (!$user->hasRole('user')) {
            $user->assignRole('user');
        }

        $user->createOrUpdateProfileWithAvatar();

        return $user->refresh();
    }

    private function coreEmailExists(array $payload): bool
    {
        if (array_key_exists('available', $payload)) {
            return $payload['available'] === false || $payload['available'] === 0 || $payload['available'] === 'false';
        }

        foreach (['exists', 'registered', 'is_registered', 'taken'] as $key) {
            if (array_key_exists($key, $payload) && filter_var($payload[$key], FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        $message = strtolower((string) ($payload['message'] ?? ''));

        return str_contains($message, 'already')
            || str_contains($message, 'registered')
            || str_contains($message, 'exist')
            || str_contains($message, 'taken');
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function coreUserFromLookupPayload(array $payload): ?array
    {
        $coreUser = $payload['data']['user'] ?? $payload['data'] ?? $payload['user'] ?? null;
        if (!is_array($coreUser)) {
            return null;
        }

        $coreUserId = (int) ($coreUser['core_user_id'] ?? $coreUser['id'] ?? $coreUser['user_id'] ?? 0);
        if ($coreUserId < 1) {
            return null;
        }

        return [
            'core_user_id' => $coreUserId,
            'connect_user_id' => (int) ($coreUser['connect_user_id'] ?? $coreUserId),
            'email' => (string) ($coreUser['email'] ?? ''),
            'username' => (string) ($coreUser['username'] ?? ''),
            'first_name' => (string) ($coreUser['first_name'] ?? $coreUser['firstName'] ?? ''),
            'last_name' => (string) ($coreUser['last_name'] ?? $coreUser['lastName'] ?? ''),
            'phone' => (string) ($coreUser['phone'] ?? $coreUser['phone_number'] ?? ''),
        ];
    }

    private function coreUserFromAccessPayload(array $payload, string $email): array
    {
        $coreUser = $payload['user'] ?? $payload['core_user'] ?? $payload;
        if (!is_array($coreUser)) {
            $coreUser = [];
        }

        $coreUserId = (int) ($payload['core_user_id'] ?? $coreUser['core_user_id'] ?? $coreUser['id'] ?? $coreUser['user_id'] ?? 0);
        $connectUserId = (int) ($payload['connect_user_id'] ?? $coreUser['connect_user_id'] ?? $coreUserId);

        return [
            'core_user_id' => $coreUserId,
            'connect_user_id' => $connectUserId,
            'email' => (string) ($payload['email'] ?? $coreUser['email'] ?? $email),
            'username' => (string) ($payload['username'] ?? $coreUser['username'] ?? ''),
            'first_name' => (string) ($payload['first_name'] ?? $coreUser['first_name'] ?? $coreUser['firstName'] ?? ''),
            'last_name' => (string) ($payload['last_name'] ?? $coreUser['last_name'] ?? $coreUser['lastName'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? $payload['phone_number'] ?? $coreUser['phone'] ?? $coreUser['phone_number'] ?? ''),
        ];
    }

    private function fallbackCoreUserFromEmail(string $email): array
    {
        $email = $this->normalizeEmail($email);
        $localPart = (string) Str::of($email)->before('@')->replaceMatches('/[^A-Za-z0-9_.-]+/', '-')->trim('-')->lower();
        $name = (string) Str::of($localPart)->replace(['.', '_', '-'], ' ')->title();

        return [
            'core_user_id' => 0,
            'connect_user_id' => 0,
            'email' => $email,
            'username' => $localPart,
            'first_name' => $name ?: 'eZWay',
            'last_name' => 'Member',
            'phone' => '',
        ];
    }

    private function uniqueLocalUsername(string $base): string
    {
        $base = trim($base) !== '' ? $base : 'tv-user';
        $username = $base;
        $suffix = 2;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base.'-'.$suffix;
            $suffix++;
        }

        return $username;
    }

    private function coreTvPackageSlug(): string
    {
        return (string) config('services.core_api.tv_package_slug', 'tv-subscription-monthly');
    }
    public function checkUserExists(Request $request)
    {
        $data = $request->all();

        // Use IP address as device_id (old code)
        $current_device = $request->has('device_id') ? $request->device_id : $request->getClientIp();

        $flag = 0;
        $user = User::where('mobile', $request->mobile)->with('subscriptionPackage')->first();

        if(!empty($user))
        {

            if($user->user_type !='user'){

                return response()->json(['message'=>"Admin doesn't have access to login", 'status' => 406]);
            }

            $response=$this->CheckDeviceLimit($user, $current_device);

            if(isset($response['error'])) {
                $devices = Device::where('user_id', $user->id)->get();
                $other_device = $devices->toArray();

                return response()->json([
                    'message' => $response['error'],
                    'status' => 406,
                    'other_device' => $other_device
                ]);
            }

            $this->setDevice($user, $request);

            Auth::login($user);
            $flag = 1;
        }

        return response()->json(['is_user_exists' => $flag, 'url' => route('user.login')]);
    }

    /**
     * Simple API to check if mobile number exists before sending OTP
     * Returns: { exists: true/false }
     */
    public function checkMobileExists(Request $request)
    {
        $mobile = $request->input('mobile');
        
        if (empty($mobile)) {
            return response()->json([
                'status' => false,
                'message' => __('frontend.mobile_required')
            ]);
        }

        // Check if mobile exists in database (for OTP login users)
        $user = User::where('mobile', $mobile)->first();

        if ($user) {
            return response()->json([
                'status' => true,
                'message' => __('messages.mobile_is_registered')
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => __('messages.mobile_is_not_registered')
        ]);
    }
}
