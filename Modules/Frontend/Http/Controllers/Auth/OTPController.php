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
use App\Services\CoreTvAccessService;
use App\Models\Device;
use App\Models\Setting;
use Modules\Frontend\Trait\LoginTrait;
use App\Models\WebQrSession;


class OTPController extends Controller
{
    use LoginTrait;

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

    public function sendSpaOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            $user = $this->syncMissingUserFromCore($validated['email']);
        }

        if (!$user || $user->user_type !== 'user') {
            return response()->json([
                'status' => false,
                'message' => 'We could not find an active eZWay TV account with that email.',
            ], 404);
        }

        $otp = (string) random_int(1000, 9999);
        $user->forceFill(['otp' => $otp])->save();
        $request->session()->put('tv_login_otp_email', $user->email);
        $request->session()->put('tv_login_otp_expires_at', now()->addMinutes(10)->timestamp);

        if (! $this->sendOtpViaCore($user->email, $otp, trim($user->first_name.' '.$user->last_name))) {
            return response()->json([
                'status' => false,
                'message' => 'Could not send the login code right now. Please check Core email delivery settings and try again.',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'We sent a 4-digit login code to your email.',
        ]);
    }

    public function verifySpaOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|digits:4',
        ]);

        $sessionEmail = $request->session()->get('tv_login_otp_email');
        $expiresAt = (int) $request->session()->get('tv_login_otp_expires_at', 0);

        if (!$sessionEmail || strcasecmp($sessionEmail, $validated['email']) !== 0 || $expiresAt < now()->timestamp) {
            return response()->json([
                'status' => false,
                'message' => 'Your login code has expired. Please request a new one.',
            ], 422);
        }

        $user = User::where('email', $validated['email'])->where('otp', $validated['otp'])->first();

        if (!$user || $user->user_type !== 'user') {
            return response()->json([
                'status' => false,
                'message' => 'The code you entered is not valid.',
            ], 422);
        }

        $request->session()->forget(['tv_login_otp_email', 'tv_login_otp_expires_at']);
        $request->session()->regenerate();
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
    private function syncMissingUserFromCore(string $email): ?User
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
                    'package_slug' => 'tv-channel-access-monthly',
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
            return null;
        }

        $payload = $response->json('data');
        if (! is_array($payload) || empty($payload['core_user_id'])) {
            return null;
        }

        try {
            $access = app(CoreTvAccessService::class);
            $access->activate($payload);
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }

        return User::query()
            ->where('network_user_id', (int) $payload['core_user_id'])
            ->orWhere('email', $email)
            ->first();
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
