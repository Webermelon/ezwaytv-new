<div class="detail-page-banner">
    <div class="video-player-wrapper">
        <!-- Video.js core -->
        <link rel="stylesheet" href="{{ asset('css/video-js.css') }}" />
        <script src="{{ asset('js/videojs/video.min.js') }}"></script>

        <!-- YouTube Support -->
        <script src="{{ asset('js/videojs/videojs-youtube.min.js') }}"></script>

        <!-- IMA SDK -->
        <script src="{{ asset('js/videojs/ima3.js') }}"></script>

        <!-- Video.js Ads & IMA plugins -->
        <script src="{{ asset('js/videojs/videojs-contrib-ads.min.js') }}"></script>
        <script src="{{ asset('js/videojs/videojs.ima.min.js') }}"></script>
        <link href="{{ asset('css/videojs.ima.css') }}" rel="stylesheet">

        @php
            $userAgent = request()->header('User-Agent');
            $isIphone = stripos($userAgent, 'iPhone') !== false;
        @endphp

        <div class="video-player">
            <video id="videoPlayer" class="video-js vjs-default-skin vjs-ima" controls width="560" height="315" muted
                poster="{{ $thumbnail_image }}" data-type="{{ $type }}"

                @unless($isIphone)
                    data-setup='{"muted": true}'
                @endunless

                content-video-type="{{ $content_video_type }}"
                data-continue-watch="{{ isset($continue_watch) && $continue_watch ? 'true' : 'false' }}"
                data-movie-access="{{ $dataAccess ?? '' }}" data-plan-id="{{ $plan_id ?? '' }}"
                data-watch-time="{{ $watched_time ?? 0 }}"
                @if ($type != 'Local') data-encrypted="{{ $data }}" @endif
                @if (isset($content_type) && isset($content_id)) data-contentType="{{ $content_type }}"
                    data-contentId="{{ $content_id }}" @endif
                data-forward-seconds="{{ setting('forward_seconds', 30) }}"
                data-backward-seconds="{{ setting('backward_seconds', 30) }}" playsinline webkit-playsinline
                x-webkit-airplay="allow" preload="metadata">
                @if ($type == 'Local')
                    <source src="{{ $data }}" type="video/mp4" id="videoSource">
                @endif
            </video>

            <!-- Vimeo iframe for Vimeo videos -->
            <div id="vimeoContainer">
                <iframe id="vimeoIframe" frameborder="0" allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>

            <!-- Custom Ad Modal -->
            <div id="customAdModal">
                <div id="customAdContent">
                    <!-- Ad content will be injected here -->
                    <button id="customAdCloseBtn">&times;</button>
                </div>
            </div>

            <!-- Live TV Schedule: mini Now/Next + hidden full panel -->
            <div id="livetv-schedule-mini" class="livetv-schedule-mini" aria-hidden="false">
                <div class="livetv-schedule-mini__now">
                    <div class="livetv-schedule-mini__label">Now</div>
                    <div class="livetv-schedule-mini__title" id="livetv-schedule-current-title">-</div>
                    <div class="livetv-schedule-mini__time" id="livetv-schedule-current-time">-</div>
                </div>
                <div class="livetv-schedule-mini__next">
                    <div class="livetv-schedule-mini__label">Next</div>
                    <div class="livetv-schedule-mini__title" id="livetv-schedule-next-title">-</div>
                    <div class="livetv-schedule-mini__time" id="livetv-schedule-next-time">-</div>
                </div>
                <!-- Schedule toggle hidden for now; only show Now/Next mini bar -->
            </div>

            <div id="livetv-schedule-panel" class="livetv-schedule" style="display:none;" aria-hidden="true">
                <button id="livetv-schedule-close" class="livetv-schedule__close" aria-label="Close schedule">&times;</button>
                <div class="livetv-schedule__current">
                    <div class="livetv-schedule__label">Now</div>
                    <div class="livetv-schedule__title" id="livetv-schedule-panel-current-title">-</div>
                    <div class="livetv-schedule__time" id="livetv-schedule-panel-current-time">-</div>
                </div>
                <div id="livetv-schedule-loading" style="display:none;color:#9aa3ad;font-size:12px;margin-bottom:6px;">Loading schedule…</div>
                <div class="livetv-schedule__upcoming" id="livetv-schedule-upcoming">
                    <!-- upcoming items injected here -->
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Include Video.js script if not already -->
<script src="{{ mix('js/videoplayer.min.js') }}"></script>
<script>
    var isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};
    var loginUrl = "{{ route('login') }}";
    var skipTrailerText = "{{ __('messages.skip_trailer') }}";
    var skipIntroText = "{{ __('messages.skip_intro') }}";
    var previousEpisodeText = "{{ __('messages.previous_episode') }}";
    var nextEpisodeText = "{{ __('messages.next_episode') }}";
    var backwardButtonText = "{{ __('messages.backward_button') }}";
    var forwardButtonText = "{{ __('messages.forward_button') }}";
    var defaultText = "{{ __('messages.default') }}";
    var errorLoadingAdText = "{{ __('messages.error_loading_ad') }}";
    var nextText = "{{ __('messages.next') }}";
</script>
<style>
    .video-player-wrapper {
        position: relative;
    }

    #vimeoContainer {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        display: none;
    }

    #vimeoIframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: none;
    }

    #customAdModal {
        display: none;
        position: absolute;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        align-items: center;
        justify-content: center;
    }

    #customAdContent {
        position: relative;
        background: rgba(0, 0, 0, 0.0);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }

    #customAdCloseBtn {
        position: absolute;
        top: -20px;
        right: -20px;
        background: #f00;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        font-size: 20px;
        cursor: pointer;
        z-index: 2;
    }
</style>
<style>
    /* Schedule overlay */
    .livetv-schedule {
        /* place the schedule under the player in normal flow */
        position: relative;
        width: 100%;
        max-width: 900px;
        max-height: 60vh;
        background: rgba(0,0,0,0.85);
        color: #fff;
        border-radius: 12px;
        padding: 12px;
        margin: 12px auto 0;
        z-index: 2;
        overflow: auto;
        font-size: 13px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.6);
    }

    .livetv-schedule__label { font-size: 11px; color: #9aa3ad; }
    .livetv-schedule__current { margin-bottom: 10px; }
    .livetv-schedule__title { font-weight: 600; margin-top: 4px; }
    .livetv-schedule__time { color: #9aa3ad; font-size: 12px; }
    .livetv-schedule__upcoming { border-top: 1px solid rgba(255,255,255,0.06); padding-top: 8px; }
    .livetv-schedule__item { padding: 6px 0; border-bottom: 1px dashed rgba(255,255,255,0.03); }
    .livetv-schedule__item:last-child { border-bottom: none; }
    .livetv-schedule__item.current { background: rgba(63, 132, 255, 0.08); }

    .livetv-schedule__close {
        position: absolute;
        top: 8px;
        right: 10px;
        background: transparent;
        border: none;
        color: #fff;
        font-size: 20px;
        line-height: 1;
        cursor: pointer;
        padding: 4px 8px;
    }

    /* Mini bar */
    .livetv-schedule-mini {
        /* mini bar sits below the player, inline with schedule panel */
        position: relative;
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 8px;
        z-index: 3;
        justify-content: flex-end;
    }
    .livetv-schedule-mini__now, .livetv-schedule-mini__next {
        background: rgba(0,0,0,0.6);
        padding: 8px 10px;
        border-radius: 8px;
        color: #fff;
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        width: auto; /* shrink to content */
        max-width: 400px; /* cap at 400px */
        box-sizing: border-box;
    }
    /* Mobile: stack Now/Next vertically (top and bottom) and allow titles to wrap */
    @media (max-width: 768px) {
        .livetv-schedule-mini {
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 8px;
        }
        .livetv-schedule-mini__now, .livetv-schedule-mini__next {
            width: 100%;
            max-width: none;
            display: flex;
            align-items: flex-start;
        }
        .livetv-schedule-mini__title {
            white-space: normal; /* allow wrapping on small screens */
            overflow: visible;
        }
        .livetv-schedule-mini__time { font-size: 12px; }
    }
    .livetv-schedule-mini__label { font-size: 11px; color: #9aa3ad; }
    .livetv-schedule-mini__title { font-weight:600; margin-top:4px; max-width:100%; overflow:hidden; white-space:nowrap; display:block; }
    .livetv-schedule-mini__time { color:#9aa3ad; font-size:12px; }
    .livetv-schedule-toggle {
        background: rgba(255,255,255,0.06);
        color: #fff;
        border: none;
        padding: 8px 10px;
        border-radius: 8px;
        cursor: pointer;
    }

    /* Marquee (duplicate-track technique) */
    .marquee-clip { display:inline-block; vertical-align:top; overflow:hidden; }
    .marquee-track { display:inline-block; white-space:nowrap; }
    .marquee-track .marquee-item { display:inline-block; padding-right:40px; }
    .marquee-active .marquee-track { animation: marquee-scroll linear infinite; }
    @keyframes marquee-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
</style>

<script>
    (function(){
        try {
            var schedules = @json($schedules ?? []);
        } catch(e) {
            var schedules = [];
        }
        // schedules_api_key passed from server when channel mapping has an api key
        try {
            var schedulesApiKey = @json($schedules_api_key ?? null);
        } catch(e) {
            var schedulesApiKey = null;
        }

        // Normalize if backend accidentally provided the full URL instead of the raw key.
        try{
            if(schedulesApiKey && /https?:\/\//.test(schedulesApiKey)){
                try{
                    var parts = schedulesApiKey.split('/');
                    var last = parts[parts.length-1] || '';
                    // if URL ended with an encoded value, decode it
                    schedulesApiKey = decodeURIComponent(last);
                }catch(e){}
            }
        }catch(e){}

        function fmtTime(iso){
            try{
                // Parse ISO (expected UTC) and display in viewer's local timezone
                var d = new Date(iso);
                return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', timeZoneName: 'short'});
            }catch(e){
                return iso;
            }
        }

        function fmtDateTime(iso){
            try{
                var d = new Date(iso);
                return d.toLocaleString([], {year:'numeric', month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit', second:'2-digit', timeZoneName: 'short'});
            }catch(e){
                return iso;
            }
        }

        function cleanTitle(title){
            // Remove "_converted" suffix from schedule titles
            if(!title) return '-';
            return title.replace(/_converted$/i, '').trim() || '-';
        }

        function renderSchedules(){
            var now = new Date();
            // update mini bar current/next
            var miniCurTitle = document.getElementById('livetv-schedule-current-title');
            var miniCurTime = document.getElementById('livetv-schedule-current-time');
            var miniNextTitle = document.getElementById('livetv-schedule-next-title');
            var miniNextTime = document.getElementById('livetv-schedule-next-time');

            var panelCurTitle = document.getElementById('livetv-schedule-panel-current-title');
            var panelCurTime = document.getElementById('livetv-schedule-panel-current-time');
            var upcoming = document.getElementById('livetv-schedule-upcoming');
            if(!miniCurTitle || !miniCurTime || !miniNextTitle || !miniNextTime || !panelCurTitle || !panelCurTime || !upcoming) return;

            upcoming.innerHTML = '';

            var currentFound = null;
            var nextFound = null;

            schedules.sort(function(a,b){ return new Date(a.start_at) - new Date(b.start_at); });

            schedules.forEach(function(s){
                var start = s.start_at ? new Date(s.start_at) : null;
                var end = s.end_at ? new Date(s.end_at) : null;

                var item = document.createElement('div');
                item.className = 'livetv-schedule__item';
                var title = document.createElement('div'); title.textContent = cleanTitle(s.title);
                var time = document.createElement('div'); time.className = 'livetv-schedule__time';
                time.textContent = (start ? fmtTime(start) : '-') + ' — ' + (end ? fmtTime(end) : '-');

                // find current
                if(!currentFound && start && end && now >= start && now <= end){
                    currentFound = s;
                    item.className += ' current';
                }

                // find next (first start > now)
                if(!nextFound && start && now < start){
                    nextFound = s;
                }

                item.appendChild(title);
                item.appendChild(time);
                upcoming.appendChild(item);
            });

            // populate mini and panel current
            var displayCurrent = currentFound || schedules[0] || null;
            var displayNext = nextFound || (schedules.length > 1 ? schedules[1] : null);

            // helper: set title with marquee if overflow
            function setTitleWithMarquee(containerEl, text){
                if(!containerEl) return;
                containerEl.innerHTML = '';
                var clip = document.createElement('span'); clip.className='marquee-clip';
                var track = document.createElement('span'); track.className='marquee-track';
                var item1 = document.createElement('span'); item1.className='marquee-item'; item1.textContent = text || '-';
                // append only single item initially; duplicate only when overflow is detected
                track.appendChild(item1);
                // ensure clip fills the available container width so overflow detection works
                clip.style.display = 'inline-block';
                clip.style.width = '100%';
                clip.style.boxSizing = 'border-box';
                clip.appendChild(track);
                containerEl.appendChild(clip);
                // after render, detect overflow and enable animation
                requestAnimationFrame(function(){
                    try{
                        var clipW = clip.clientWidth;
                        var itemW = item1.scrollWidth;
                        // if single item wider than clip, enable marquee by duplicating the item
                        if(itemW > clipW + 6){
                            // append duplicate if not already present
                            if(track.children.length < 2){
                                var item2 = document.createElement('span'); item2.className='marquee-item'; item2.textContent = text || '-';
                                track.appendChild(item2);
                            }
                            // set duration proportional to single item length
                            var duration = Math.max(6, Math.min(40, Math.round(itemW/30)));
                            // ensure track contains two copies and is wide enough
                            track.style.display = 'inline-block';
                            track.style.width = (itemW * 2) + 'px';
                            // explicit animation: use existing keyframes which animate -50% (half the track)
                            track.style.animationName = 'marquee-scroll';
                            track.style.animationTimingFunction = 'linear';
                            track.style.animationIterationCount = 'infinite';
                            track.style.animationDuration = duration + 's';
                            // play normal so text moves right-to-left
                            track.style.animationDirection = 'normal';
                            containerEl.classList.add('marquee-active');
                        } else {
                            // remove duplicate if exists
                            if(track.children.length > 1){
                                while(track.children.length > 1) track.removeChild(track.lastChild);
                            }
                            containerEl.classList.remove('marquee-active');
                            track.style.animationName = '';
                            track.style.animationDuration = '';
                            track.style.animationDirection = '';
                            track.style.width = '';
                        }
                    }catch(e){}
                });
            }

            setTitleWithMarquee(miniCurTitle, displayCurrent ? cleanTitle(displayCurrent.title) : '-');
            miniCurTime.textContent = displayCurrent ? ((displayCurrent.start_at?fmtTime(new Date(displayCurrent.start_at)):'-') + ' — ' + (displayCurrent.end_at?fmtTime(new Date(displayCurrent.end_at)):'-')) : '-';

            // panel current can reuse plain text but allow marquee as well
            setTitleWithMarquee(panelCurTitle, displayCurrent ? cleanTitle(displayCurrent.title) : '-');
            panelCurTime.textContent = displayCurrent && displayCurrent.start_at ? fmtDateTime(displayCurrent.start_at) + (displayCurrent.end_at ? ' — ' + fmtDateTime(displayCurrent.end_at) : '') : miniCurTime.textContent;

            setTitleWithMarquee(miniNextTitle, displayNext ? cleanTitle(displayNext.title) : '-');
            miniNextTime.textContent = displayNext ? ((displayNext.start_at?fmtTime(new Date(displayNext.start_at)):'-') + ' — ' + (displayNext.end_at?fmtTime(new Date(displayNext.end_at)):'-')) : '-';
        }

        // render once on load and refresh every 30s to update 'Now' highlight
        document.addEventListener('DOMContentLoaded', function(){
            var loadingEl = document.getElementById('livetv-schedule-loading');
            var miniBar = document.getElementById('livetv-schedule-mini');
            var panel = document.getElementById('livetv-schedule-panel');

            // If no schedules API key for this channel, hide Now/Next UI entirely
            if(!schedulesApiKey){
                if(miniBar) miniBar.style.display = 'none';
                if(panel) panel.style.display = 'none';
                return;
            }

            // When API key exists: ignore embedded local schedules and fetch external feed
            schedules = [];
            if(loadingEl) loadingEl.style.display = 'block';
            fetch('https://stream.ezway.tv/api/public/schedules/' + encodeURIComponent(schedulesApiKey))
                .then(function(r){ return r.json(); })
                .then(function(json){
                    if(json && json.schedules && Array.isArray(json.schedules)){
                        function normalizeIso(ts){
                            if(!ts) return null;
                            if(/T.*(Z|[+\-]\d{2}:?\d{2})$/.test(ts)) return ts;
                            if(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(ts)){
                                return ts.replace(' ', 'T') + 'Z';
                            }
                            if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(ts)){
                                return ts + 'Z';
                            }
                            return ts;
                        }
                        schedules = json.schedules.map(function(it){
                            return {
                                id: it.id,
                                title: (it.media && it.media.title) ? it.media.title : (it.title || '-'),
                                start_at: normalizeIso(it.start_time || it.start_at),
                                end_at: normalizeIso(it.end_time || it.end_at),
                                meta: it.meta || null
                            };
                        });
                    }
                })
                .catch(function(){
                    // on fetch error, hide Now/Next UI to avoid showing stale local schedules
                    if(miniBar) miniBar.style.display = 'none';
                    if(panel) panel.style.display = 'none';
                })
                .finally(function(){ if(loadingEl) loadingEl.style.display = 'none'; renderSchedules(); setInterval(renderSchedules, 30000); });

            // Close button inside panel to 'untoggle'
            var closeBtn = document.getElementById('livetv-schedule-close');
            if(closeBtn && panel){
                closeBtn.addEventListener('click', function(){
                    panel.style.display = 'none';
                    panel.setAttribute('aria-hidden','true');
                    if(miniBar) miniBar.style.display = 'flex';
                    try{ miniBar && miniBar.focus(); }catch(e){}
                });
            }
        });
    })();
</script>
<style>
    /* Hide ALL IMA Skip Elements */
    .ima-skip-container,
    .ima-skip-button,
    div[class*="ima-ad-skip"],
    div[class*="ima_skip"],
    div[class*="ima-skip"],
    button[class*="ima-skip"],
    button[class*="ima_skip"],
    div[class*="skip-button"],
    .ima-skip-button-container,
    [class*="skip-container"],
    [data-skip-button],
    [data-skip-container] {
        opacity: 0 !important;
        visibility: hidden !important;
        display: none !important;
        pointer-events: none !important;
        width: 0 !important;
        height: 0 !important;
        position: absolute !important;
        top: -9999px !important;
        left: -9999px !important;
    }

    /* Keep IMA Progress and Countdown Visible */
    .ima-controls-div,
    .ima-progress-div,
    .ima-countdown-div,
    .ima-seek-bar-div,
    .ima-progress-bar-div {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    .vjs-texttrack-settings {
        display: none !important;
    }

    /* Subtitle Responsive Font Size */
    .video-js .vjs-text-track-cue,
    .video-js .vjs-text-track-cue div {
        font-size: 1.4em !important;
        line-height: normal !important;
    }

    /* iOS FIX: Prevent caption size explosion */
    @supports (-webkit-touch-callout: none) {

        /* iOS-specific styles */
        .video-js .vjs-text-track-display {
            font-size: 1.4em !important;
            line-height: normal !important;
            transform: none !important;
            -webkit-transform: none !important;
        }

        .video-js .vjs-text-track-cue,
        .video-js .vjs-text-track-cue div {
            font-size: 1.4em !important;
            line-height: normal !important;
            transform: none !important;
            -webkit-transform: none !important;
            max-font-size: 1.4em !important;
        }

        /* iOS FIX: Ensure play button is always clickable */
        .video-js .vjs-big-play-button {
            pointer-events: auto !important;
            opacity: 1 !important;
        }

        .video-js .vjs-big-play-button.vjs-hidden {
            display: block !important;
            opacity: 1 !important;
        }
    }

    @media (max-width: 768px) {

        .video-js .vjs-text-track-cue,
        .video-js .vjs-text-track-cue div {
            font-size: 16px !important;
        }
    }

    @media (max-width: 480px) {

        .video-js .vjs-text-track-cue,
        .video-js .vjs-text-track-cue div {
            font-size: 13px !important;
        }

        video::-webkit-media-text-track-display {
            font-size: 13px !important;
        }
    }

    .video-js.vjs-ima {
        overflow: visible;
    }

    .video-js.vjs-ima .vjs-ima-ad-container {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        pointer-events: none;
    }

    .video-js.vjs-ima .vjs-ima-ad-container>div {
        pointer-events: auto;
    }

    .vjs-ad-cue {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 4px;
        z-index: 10;
        pointer-events: none;
        transition: all 0.3s ease;
    }

    .vjs-ad-cue:hover {
        width: 6px;
        opacity: 0.8;
    }

    .vjs-ad-cue[title*="Mid-roll"] {
        background-color: orange;
    }

    .vjs-ad-cue[title*="Post-roll"] {
        background-color: orange;
    }

    .vjs-ad-cue[title*="Overlay"] {
        background-color: orange;
    }

    /* Enhanced Skip Button Styling */
    .vjs-skip-ad-button {
        position: absolute;
        bottom: 80px;
        right: 20px;
        background: rgba(15, 15, 15, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: #fff;
        padding: 12px 24px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 4px;
        cursor: pointer;
        z-index: 10001 !important;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .vjs-skip-ad-button:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: #fff;
        transform: scale(1.05);
    }

    .vjs-skip-ad-button::after {
        content: "";
        display: inline-block;
        width: 0;
        height: 0;
        border-top: 6px solid transparent;
        border-bottom: 6px solid transparent;
        border-left: 10px solid #fff;
    }

    @media (max-width: 768px) {
        .vjs-skip-ad-button {
            bottom: 70px;
            padding: 8px 16px;
            font-size: 14px;
        }
    }

    @media (max-width: 575px) {
        .vjs-skip-ad-button {
            bottom: 110px;
            padding: 6px 12px;
            font-size: 12px;
        }
    }

    .overlay-ad {
        position: absolute;
        bottom: 80px;
        left: 20px;
        z-index: 1000;
        background: rgba(15, 15, 15, 0.9);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        padding: 8px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    }

    @media (max-width: 575px) {
        .overlay-ad {
            bottom: 110px;
            left: 10px;
            right: 10px;
            text-align: center;
        }
    }

    .video-player {
        position: relative;
        z-index: 0;
    }

    #customAdModal {
        display: none;
        position: absolute;
        z-index: 10000;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    #customAdModal.show {
        opacity: 1;
    }

    #customAdContent {
        max-width: 900px;
        width: 90%;
        max-height: 85vh;
        position: relative;
        background: #000;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    #customAdContent img,
    #customAdContent video,
    #customAdContent iframe {
        width: 100%;
        height: auto;
        max-height: 85vh;
        object-fit: contain;
        display: block;
    }

    #customAdCloseBtn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(255, 0, 0, 0.85);
        color: #fff;
        border: 2px solid #fff;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        font-size: 22px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10001;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        padding: 0;
        padding-bottom: 4px; // Align the × character
    }

    #customAdCloseBtn:hover {
        background: #f00;
        transform: scale(1.1);
        box-shadow: 0 0 15px rgba(255, 0, 0, 0.5);
    }

    @media (max-width: 768px) {
        #customAdContent {
            width: 95%;
            border-radius: 12px;
        }

        #customAdCloseBtn {
            width: 32px;
            height: 32px;
            top: 10px;
            right: 10px;
            font-size: 18px;
        }
    }

    @media (max-width: 480px) {
        #customAdContent {
            width: 98%;
            max-height: 70vh; // Avoid obscuring too much on very small screens
        }
    }
</style>
