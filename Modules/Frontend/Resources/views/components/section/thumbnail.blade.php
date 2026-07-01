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
                @if (isset($content_type) && isset($content_id)) data-contentType="{{ $stat_content_type ?? $content_type }}"
                    data-contentId="{{ $content_id }}" @endif
                @if (!empty($stat_channel_id)) data-stat-channel-id="{{ (int) $stat_channel_id }}" @endif
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

            <!-- Live TV Schedule with New Layout -->
            <div class="tv-wrap" id="livetv-schedule-wrap">
              <div class="tv-inner">
                <div class="screen-area">
                  <div class="screen" style="display:none;">
                    <div class="screen-bg"></div>
                    <div class="screen-overlay"></div>
                    <div class="play-btn"><div class="play-icon"></div></div>
                  </div>
                  <div class="now-next">
                    <div class="info-card">
                      <div class="info-label">Now</div>
                      <div class="info-title" id="sn-now-title">-</div>
                      <div class="info-time" id="sn-now-time">-</div>
                    </div>
                    <div class="info-card">
                      <div class="info-label">Next</div>
                      <div class="info-title" id="sn-next-title">-</div>
                      <div class="info-time" id="sn-next-time">-</div>
                    </div>
                  </div>
                </div>
              </div>
              <button class="schedule-btn" id="toggleSchedBtn">Full Schedule</button>

              <div class="schedule-panel" id="schedPanel">
                <div class="schedule-header">
                  <span class="schedule-header-title">Full Schedule</span>
                  <button class="close-btn" id="closeSchedBtn">&times;</button>
                </div>
                <div class="schedule-list" id="scheduleList">
                  <!-- items injected -->
                </div>
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
  @import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700&family=Barlow:wght@300;400;500;600&display=swap');
  .tv-wrap{background:#0d0d0f;border-radius:16px;padding:4px;font-family:'Barlow',sans-serif;color:#fff;margin-top:12px;}
  .tv-inner{display:flex;flex-direction:column;gap:14px}
  .screen-area{display:flex;flex-direction:column;gap:14px}
  .screen{background:#111;border-radius:10px;position:relative;overflow:hidden;aspect-ratio:16/9;max-height:260px;border:2px solid #1e1e22;cursor:pointer;display:flex;align-items:center;justify-content:center}
  .screen-bg{position:absolute;inset:0;background:linear-gradient(135deg,#0f0f14 0%,#1a1a24 50%,#111118 100%)}
  .screen-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.45)}
  .play-btn{position:relative;z-index:2;width:68px;height:68px;border-radius:50%;background:#f97316;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:transform 0.15s,box-shadow 0.15s;box-shadow:0 0 30px rgba(249,115,22,0.5)}
  .play-btn:hover{transform:scale(1.08);box-shadow:0 0 40px rgba(249,115,22,0.7)}
  .play-icon{width:0;height:0;border-style:solid;border-width:13px 0 13px 22px;border-color:transparent transparent transparent #fff;margin-left:5px}
  .now-next{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .info-card{background:#13131a;border-radius:8px;padding:12px 14px;border:1px solid #2a2a30}
  .info-label{font-size:10px;font-weight:700;letter-spacing:2px;color:#f97316;text-transform:uppercase;margin-bottom:6px}
  .info-title{font-size:18px;font-weight:600;color:#fff;line-height:1.3;margin-bottom:4px}
  .info-time{font-size:16px;color:rgba(255,255,255,0.45);font-weight:500}
  .schedule-btn{background:#13131a;border:1px solid #2a2a30;color:#f97316;font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:700;letter-spacing:2px;text-transform:uppercase;writing-mode:horizontal-tb;padding:14px 20px;border-radius:8px;cursor:pointer;transition:background 0.2s,border-color 0.2s,transform 0.05s;width:100%;margin-top:10px}
  .schedule-btn:hover{background:#1a1a24;border-color:#f97316;transform:scale(1.02)}
  .schedule-panel{background:#13131a;border-radius:12px;border:1px solid #2a2a30;overflow:hidden;margin-top:16px;max-height:0;transition:max-height 0.4s cubic-bezier(0.4,0,0.2,1),opacity 0.3s;opacity:0;pointer-events:none}
  .schedule-panel.open{max-height:420px;opacity:1;pointer-events:auto}
  .schedule-header{padding:14px 18px;border-bottom:1px solid #2a2a30;display:flex;align-items:center;justify-content:space-between}
  .schedule-header-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#f97316}
  .close-btn{background:none;border:none;color:rgba(255,255,255,0.4);font-size:20px;cursor:pointer;line-height:1;transition:color 0.2s}
  .close-btn:hover{color:#fff}
  .schedule-list{overflow-y:auto;max-height:360px;padding:8px 0}
  .schedule-list::-webkit-scrollbar{width:4px}
  .schedule-list::-webkit-scrollbar-track{background:#1a1a22}
  .schedule-list::-webkit-scrollbar-thumb{background:#f97316;border-radius:2px}
  .sched-item{padding:12px 18px;border-bottom:1px solid #1e1e26;transition:background 0.15s}
  .sched-item:last-child{border-bottom:none}
  .sched-item:hover{background:#1c1c26}
  .sched-item.now{background:#1e150a;border-left:3px solid #f97316;padding-left:15px}
  .sched-item.now .sched-name{color:#f97316}
  .sched-badge{display:inline-block;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;background:#f97316;color:#fff;border-radius:3px;padding:2px 6px;margin-bottom:5px}
  .sched-name{font-size:13px;font-weight:600;color:#fff;line-height:1.3;margin-bottom:3px}
  .sched-time{font-size:11px;color:rgba(255,255,255,0.4);font-weight:300}
  @media (max-width: 768px) {
    .now-next{grid-template-columns:1fr;gap:10px}
  }
</style>

<script>
  (function(){
    try {
      var schedules = @json($schedules ?? []);
    } catch(e) {
      var schedules = [];
    }
    try {
      var schedulesApiKey = @json($schedules_api_key ?? null);
    } catch(e) {
      var schedulesApiKey = null;
    }

    // Defensive: normalize API key - reject non-schedule URLs, extract token only if valid
    try{
      if(schedulesApiKey && /https?:\/\//.test(schedulesApiKey)){
        // If it's a public schedules URL, keep it; otherwise try extracting last segment
        if(/\/api\/public\/schedules\//.test(schedulesApiKey)){
          // keep as-is
        } else {
          var parts = schedulesApiKey.split('/');
          var last = decodeURIComponent(parts[parts.length-1] || '');
          // Only accept if last segment looks like a 32-hex token
          if(/^[a-f0-9]{32}$/i.test(last)){
            schedulesApiKey = last;
          } else {
            // Reject (e.g., logo URLs)
            schedulesApiKey = null;
          }
        }
      }
    }catch(e){ schedulesApiKey = null; }

    function cleanTitle(t){ return t ? t.replace(/_converted$/i, '').trim() : '-'; }

    function renderSchedules(){
      var now = new Date();
      var nowTitle = document.getElementById('sn-now-title');
      var nowTime = document.getElementById('sn-now-time');
      var nextTitle = document.getElementById('sn-next-title');
      var nextTime = document.getElementById('sn-next-time');
      var list = document.getElementById('scheduleList');
      if(!nowTitle || !nowTime || !nextTitle || !nextTime || !list) return;

      list.innerHTML = '';
      var current = null, next = null;
      var sorted = schedules.sort(function(a,b){ return new Date(a.start_at) - new Date(b.start_at); });

      // Filter to show only current and upcoming schedules
      var currentAndUpcoming = sorted.filter(function(it){
        var e = it.end_at ? new Date(it.end_at) : null;
        // Include if end time is in the future (still playing or upcoming)
        return e && e >= now;
      });

      currentAndUpcoming.forEach(function(it){
        var s = it.start_at ? new Date(it.start_at) : null;
        var e = it.end_at ? new Date(it.end_at) : null;
        var div = document.createElement('div'); div.className = 'sched-item';
        if(s && e && now >= s && now <= e){ div.classList.add('now'); if(!current) current = it; }
        if(s && now < s && !next){ next = it; }
        var badgeWrap = '';
        if(it.meta && (it.meta.type || it.meta.category)){
          badgeWrap = '<div class="sched-badge">'+(it.meta.type || it.meta.category)+'</div>';
        } else if(s && e && now >= s && now <= e){
          badgeWrap = '<div class="sched-badge">Now</div>';
        }
        var dStart = s ? s.toLocaleString() : '-';
        var dEnd = e ? e.toLocaleString() : '-';
        div.innerHTML = badgeWrap + '<div class="sched-name">'+cleanTitle(it.title)+'</div>'+
          '<div class="sched-time">'+dStart+' — '+dEnd+'</div>';
        list.appendChild(div);
      });

      if(currentAndUpcoming.length === 0){
        list.innerHTML = '<div style="padding:18px;color:#9aa3ad">No upcoming schedule</div>';
      }

      var displayCurrent = current || sorted[0] || null;
      var displayNext = next || (sorted.length > 1 ? sorted[1] : null);

      nowTitle.textContent = displayCurrent ? cleanTitle(displayCurrent.title) : '-';
      nowTime.textContent = displayCurrent && displayCurrent.start_at ? new Date(displayCurrent.start_at).toLocaleString() + (displayCurrent.end_at ? ' — ' + new Date(displayCurrent.end_at).toLocaleString() : '') : '-';
      nextTitle.textContent = displayNext ? cleanTitle(displayNext.title) : '-';
      nextTime.textContent = displayNext && displayNext.start_at ? new Date(displayNext.start_at).toLocaleString() : '-';
    }

    document.addEventListener('DOMContentLoaded', function(){
      var wrap = document.getElementById('livetv-schedule-wrap');
      var btn = document.getElementById('toggleSchedBtn');
      var panel = document.getElementById('schedPanel');
      var closeBtn = document.getElementById('closeSchedBtn');

      // Toggle button
      btn && btn.addEventListener('click', function(){ 
        panel.classList.toggle('open'); 
        if(panel.classList.contains('open')){
          panel.scrollTop = 0; // Reset to top to show current "now" item first
        }
      });
      closeBtn && closeBtn.addEventListener('click', function(){ panel.classList.remove('open'); });

      // If we have an API key, fetch external schedules
      if(schedulesApiKey){
        var originalSchedules = schedules; // backup embedded schedules
        schedules = [];
        // Check if schedulesApiKey is already a full URL or just a token
        var fetchUrl = /^https?:\/\//.test(schedulesApiKey) 
          ? schedulesApiKey 
          : 'https://stream.ezway.tv/api/public/schedules/' + encodeURIComponent(schedulesApiKey);
        fetch(fetchUrl)
          .then(function(r){ return r.json(); })
          .then(function(json){
            if(json && json.schedules && Array.isArray(json.schedules)){
              function normalizeIso(ts){
                if(!ts) return null;
                if(/T.*(Z|[+\-]\d{2}:?\d{2})$/.test(ts)) return ts;
                if(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(ts)) return ts.replace(' ', 'T') + 'Z';
                if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(ts)) return ts + 'Z';
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
            // On fetch error, fall back to embedded schedules
            schedules = originalSchedules;
          })
          .finally(function(){ 
            // Hide UI only if we have no schedules at all
            if(!schedules || schedules.length === 0){
              if(wrap) wrap.style.display = 'none';
            } else {
              renderSchedules(); 
              setInterval(renderSchedules, 30000);
            }
          });
      } else {
        // No API key - use embedded schedules if available
        if(!schedules || schedules.length === 0){
          if(wrap) wrap.style.display = 'none';
        } else {
          renderSchedules(); 
          setInterval(renderSchedules, 30000);
        }
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

    @media (max-width: 640px) {
        .video-player .video-js .vjs-control-bar {
            position: absolute !important;
            right: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            display: flex !important;
            align-items: flex-end !important;
            height: 5.4rem !important;
            padding: 2rem 0.45rem 0.55rem !important;
            background: linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.34) 32%, rgba(0, 0, 0, 0.86) 100%) !important;
        }

        .video-player .video-js .vjs-progress-control,
        .video-player .video-js .vjs-control.vjs-progress-control {
            position: absolute !important;
            top: 0.72rem !important;
            right: 0.85rem !important;
            left: 0.85rem !important;
            display: block !important;
            width: auto !important;
            min-width: 0 !important;
            height: 1.45rem !important;
            padding: 0.45rem 0 !important;
        }

        .video-player .video-js .vjs-progress-control .vjs-progress-holder {
            width: 100% !important;
            height: 0.42rem !important;
            margin: 0 !important;
            border-radius: 999px !important;
            overflow: visible !important;
            background: rgba(255, 255, 255, 0.26) !important;
        }

        .video-player .video-js .vjs-load-progress,
        .video-player .video-js .vjs-load-progress div {
            border-radius: inherit !important;
            background: rgba(255, 255, 255, 0.38) !important;
        }

        .video-player .video-js .vjs-play-progress {
            border-radius: inherit !important;
            background: #ff0033 !important;
        }

        .video-player .video-js .vjs-play-progress::before {
            top: 50% !important;
            right: -0.5rem !important;
            width: 1rem !important;
            height: 1rem !important;
            margin-top: -0.5rem !important;
            border-radius: 999px !important;
            color: transparent !important;
            opacity: 1 !important;
            background: #ff0033 !important;
            transform: scale(0.9) !important;
        }
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
