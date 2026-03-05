<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFrontendMaintenance
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $enabled = setting('frontend_maintenance_enabled');
        } catch (\Exception $e) {
            $enabled = null;
        }

        // If maintenance not enabled, continue
        if (! $enabled) {
            return $next($request);
        }

        // Allow backend/admin URLs and admin users
        if ($request->is('admin*') || $request->is('app/*') || $request->is('storage/*') || $request->is('api/*')) {
            return $next($request);
        }

        if (auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('admin')) {
            return $next($request);
        }

        // Show maintenance page
        $template = setting('frontend_maintenance_template');

        return response()->view('frontend.maintenance', ['template' => $template], 503);
    }
}
