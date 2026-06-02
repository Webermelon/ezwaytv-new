<?php

namespace Modules\Ad\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ad\Models\VideoAd;
use Modules\Ad\Services\VastXmlGenerator;
use Illuminate\Support\Facades\Log;

class VastXmlController extends Controller
{
    protected $vastGenerator;

    public function __construct(VastXmlGenerator $vastGenerator)
    {
        $this->vastGenerator = $vastGenerator;
    }

    /**
     * Generate and serve VAST XML for a specific video ad
     */
    public function generate(Request $request, $id)
    {
        try {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            $videoAd = VideoAd::active()->findOrFail($id);
            
            Log::info('VAST XML requested', [
                'video_ad_id' => $id,
                'video_ad_name' => $videoAd->name,
            ]);
            
            $vastXml = $this->vastGenerator->generate($videoAd);
            
            return $this->xmlResponse($vastXml, 200, $request);
                
        } catch (\Exception $e) {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            Log::error('VAST XML generation failed', [
                'video_ad_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            // Return empty VAST on error
            return $this->xmlResponse($this->getEmptyVast(), 200, $request);
        }
    }
    
    /**
     * Generate VAST wrapper XML
     */
    public function generateWrapper(Request $request, $id)
    {
        try {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            $videoAd = VideoAd::active()->findOrFail($id);
            $vastTagUrl = $request->input('vast_tag_url');
            
            if (!$vastTagUrl) {
                throw new \Exception('vast_tag_url parameter is required');
            }
            
            $vastXml = $this->vastGenerator->generateWrapper($videoAd, $vastTagUrl);
            
            return $this->xmlResponse($vastXml, 200, $request);
                
        } catch (\Exception $e) {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            Log::error('VAST wrapper generation failed', [
                'video_ad_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return $this->xmlResponse($this->getEmptyVast(), 200, $request);
        }
    }
    
    /**
     * Generate VMAP XML for multiple ads
     */
    public function generateVmap(Request $request)
    {
        try {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Expect format: [{'id': 1, 'time_offset': 'start'}, {'id': 2, 'time_offset': '00:00:30'}]
            $ads = $request->input('ads', []);
            
            if (empty($ads)) {
                throw new \Exception('No ads provided');
            }
            
            $vmapXml = $this->vastGenerator->generateVmap($ads);
            
            return $this->xmlResponse($vmapXml, 200, $request);
                
        } catch (\Exception $e) {
            // Clean any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            Log::error('VMAP generation failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $this->xmlResponse($this->getEmptyVast(), 200, $request);
        }
    }

    protected function xmlResponse(string $xml, int $status, Request $request)
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = [
            config('app.url'),
            'https://ezway.tv',
            'https://www.ezway.tv',
            'https://imasdk.googleapis.com',
        ];

        $allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));
        $corsOrigin = in_array($origin, $allowedOrigins, true) ? $origin : config('app.url');

        return response($xml, $status)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Access-Control-Allow-Origin', $corsOrigin)
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Vary', 'Origin');
    }
    
    /**
     * Get empty VAST XML (fallback)
     */
    protected function getEmptyVast(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><VAST version="4.0"></VAST>';
    }
}
