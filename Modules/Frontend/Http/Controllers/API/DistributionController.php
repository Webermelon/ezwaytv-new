<?php

namespace Modules\Frontend\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DistributionController extends Controller
{
    /**
     * Return rendered distribution page HTML (no site header/footer).
     */
    public function html(Request $request)
    {
        // Render the Blade view and return raw HTML so mobile clients can embed it
        // The view lives at Modules/Frontend/Resources/views/distribution.blade.php
        $html = view('frontend::distribution')->render();
        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
