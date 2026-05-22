<?php

namespace Modules\Ad\Services;

use Modules\Ad\Models\VideoAd;
use Illuminate\Support\Str;

class VastXmlGenerator
{
    /**
     * Generate VAST 4.0 XML for a video ad
     */
    public function generate(VideoAd $videoAd): string
    {
        $xml = $this->getXmlHeader();
        
        $xml .= '<VAST version="4.0" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns="http://www.iab.com/VAST">';
        $xml .= '<Ad id="' . $videoAd->id . '">';
        $xml .= '<InLine>';
        
        // Ad System
        $xml .= '<AdSystem>eZWay TV</AdSystem>';
        
        // Ad Title
        $xml .= '<AdTitle><![CDATA[' . ($videoAd->title ?: $videoAd->name) . ']]></AdTitle>';
        
        // Description (optional)
        if ($videoAd->description) {
            $xml .= '<Description><![CDATA[' . $videoAd->description . ']]></Description>';
        }
        
        // Advertiser (optional)
        if ($videoAd->advertiser) {
            $xml .= '<Advertiser><![CDATA[' . $videoAd->advertiser . ']]></Advertiser>';
        }
        
        // Impression tracking
        if ($videoAd->impression_url) {
            $xml .= '<Impression><![CDATA[' . $videoAd->impression_url . ']]></Impression>';
        }
        
        // Creatives
        $xml .= '<Creatives>';
        $xml .= '<Creative id="' . $videoAd->id . '" sequence="1">';
        $xml .= '<Linear' . ($videoAd->is_skippable && $videoAd->skip_offset ? ' skipoffset="' . $this->formatSkipOffset($videoAd->skip_offset) . '"' : '') . '>';
        
        // Duration
        $xml .= '<Duration>' . $videoAd->formatted_duration . '</Duration>';
        
        // Click tracking
        $xml .= '<VideoClicks>';
        if ($videoAd->click_through_url) {
            $xml .= '<ClickThrough><![CDATA[' . $videoAd->click_through_url . ']]></ClickThrough>';
        }
        if ($videoAd->click_tracking_url) {
            $xml .= '<ClickTracking><![CDATA[' . $videoAd->click_tracking_url . ']]></ClickTracking>';
        }
        $xml .= '</VideoClicks>';
        
        // Media Files
        $xml .= '<MediaFiles>';
        $xml .= '<MediaFile delivery="progressive" type="' . $videoAd->mime_type . '" width="' . $videoAd->width . '" height="' . $videoAd->height . '">';
        $xml .= '<![CDATA[' . $videoAd->video_url . ']]>';
        $xml .= '</MediaFile>';
        $xml .= '</MediaFiles>';
        
        $xml .= '</Linear>';
        $xml .= '</Creative>';
        $xml .= '</Creatives>';
        
        $xml .= '</InLine>';
        $xml .= '</Ad>';
        $xml .= '</VAST>';
        
        return $xml;
    }
    
    /**
     * Generate VAST wrapper XML (wraps another VAST URL)
     */
    public function generateWrapper(VideoAd $videoAd, string $vastTagUrl): string
    {
        $xml = $this->getXmlHeader();
        
        $xml .= '<VAST version="4.0" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns="http://www.iab.com/VAST">';
        $xml .= '<Ad id="' . $videoAd->id . '">';
        $xml .= '<Wrapper>';
        
        $xml .= '<AdSystem>eZWay TV</AdSystem>';
        $xml .= '<VASTAdTagURI><![CDATA[' . $vastTagUrl . ']]></VASTAdTagURI>';
        
        if ($videoAd->impression_url) {
            $xml .= '<Impression><![CDATA[' . $videoAd->impression_url . ']]></Impression>';
        }
        
        $xml .= '</Wrapper>';
        $xml .= '</Ad>';
        $xml .= '</VAST>';
        
        return $xml;
    }
    
    /**
     * Generate VMAP XML for multiple ads at different time offsets
     */
    public function generateVmap(array $videoAds): string
    {
        $xml = $this->getXmlHeader();
        
        $xml .= '<vmap:VMAP xmlns:vmap="http://www.iab.net/vmap-1.0" version="1.0">';
        
        foreach ($videoAds as $ad) {
            $timeOffset = $ad['time_offset'] ?? 'start'; // start, end, 00:00:15, etc.
            $breakType = $ad['break_type'] ?? 'linear'; // linear, nonlinear, display
            
            $xml .= '<vmap:AdBreak timeOffset="' . $timeOffset . '" breakType="' . $breakType . '" breakId="' . $ad['id'] . '">';
            $xml .= '<vmap:AdSource id="' . $ad['id'] . '" allowMultipleAds="false" followRedirects="true">';
            $xml .= '<vmap:VASTAdData>';
            
            // Embed inline VAST
            $videoAd = VideoAd::find($ad['id']);
            if ($videoAd) {
                // Strip XML declaration from nested VAST
                $vastXml = $this->generate($videoAd);
                $vastXml = preg_replace('/<\?xml.*?\?>/', '', $vastXml);
                $xml .= $vastXml;
            }
            
            $xml .= '</vmap:VASTAdData>';
            $xml .= '</vmap:AdSource>';
            $xml .= '</vmap:AdBreak>';
        }
        
        $xml .= '</vmap:VMAP>';
        
        return $xml;
    }
    
    /**
     * Get XML header
     */
    protected function getXmlHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>';
    }
    
    /**
     * Format skip offset to proper format
     */
    protected function formatSkipOffset($offset): string
    {
        // If already in HH:MM:SS format, return as is
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $offset)) {
            return $offset;
        }
        
        // If it's just seconds, convert to HH:MM:SS
        if (is_numeric($offset)) {
            $hours = floor($offset / 3600);
            $minutes = floor(($offset % 3600) / 60);
            $seconds = $offset % 60;
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return $offset;
    }
}
