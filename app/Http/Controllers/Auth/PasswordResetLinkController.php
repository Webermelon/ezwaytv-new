<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            Log::error('Forgot password mail send failed: ' . $e->getMessage());

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('email_logs')) {
                    \App\Models\EmailLog::create([
                        'mailer' => config('mail.default'),
                        'from_email' => config('mail.from.address'),
                        'to_emails' => (string) $request->email,
                        'subject' => 'Password Reset',
                        'mailable_class' => 'ForgotPassword',
                        'status' => 'failed',
                        'payload' => $e->getMessage(),
                        'sent_at' => now(),
                    ]);
                }
            } catch (\Throwable $ignored) {
            }

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'There was an issue sending the reset email. Please check SMTP configuration.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
