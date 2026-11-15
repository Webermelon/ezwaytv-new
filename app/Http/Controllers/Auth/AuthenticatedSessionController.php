<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Trait\AuthTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use App\Services\TvLog;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    use AuthTrait;

    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {

        $isLogin = $this->loginTrait($request);
        $status = $isLogin['status'] ?? null;
        $authorized = $status === 200;
        $reason = match ($status) {
            200 => 'session_issued',
            403 => 'demo_login_disabled',
            406 => 'role_denied',
            default => 'invalid_credentials',
        };
        TvLog::auth($request, $authorized ? 'login_succeeded' : 'login_failed',
            $authorized ? 'success' : 'failed', $authorized ? Auth::id() : null, $reason, 'admin_password');
        if ($isLogin) {

            if (isset($isLogin['error'])) {

                return back()->withErrors([
                    'email' => $isLogin['error']
                ])->onlyInput('email');
            } elseif ($isLogin['status'] == 406) {

                return back()->withErrors([
                    'email' => $isLogin['message']
                ])->onlyInput('email');
            } elseif ($isLogin['status'] == 403) {

                return back()->withErrors([
                    'email' => $isLogin['message']
                ])->onlyInput('email');

            } else {

                $request->session()->regenerate();

                return redirect()->route('backend.home');
            }
        }

        return back()->withErrors([
            'email' => __('messages.not_matched'),
        ])->onlyInput('email');
    }

    /**
     * Destroy an authenticated session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $userId = Auth::id();
        $method = $request->session()->get('tv_admin_login_method', 'admin_password');
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        TvLog::auth($request, 'logout', 'success', $userId, 'session_invalidated', $method);

        return redirect('/admin/login');
    }
}
