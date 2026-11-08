<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VideoEmbedController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $parameters = ['slug' => $slug];

        if ($request->filled('ondemand_channel')) {
            $parameters['ondemand_channel'] = (string) $request->query('ondemand_channel');
        }

        return view('react-modernization', [
            // The player needs a source, but the standard detail API remains session/API-key protected.
            'embedApiUrl' => URL::temporarySignedRoute(
                'video-embed-details',
                now()->addMinutes(10),
                $parameters,
            ),
        ]);
    }
}
