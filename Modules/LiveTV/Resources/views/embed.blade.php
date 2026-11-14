<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        html, body { width: 100%; height: 100%; margin: 0; background: #000; }
        .live-embed { position: relative; width: 100%; height: 100%; overflow: hidden; background: #000; }
        video { width: 100%; height: 100%; display: block; background: #000; object-fit: contain; }
        .live-brand {
            position: absolute;
            z-index: 2;
            top: 16px;
            left: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: calc(100% - 32px);
            padding: 8px 10px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 5px;
            background: rgba(0, 0, 0, .62);
            box-shadow: 0 8px 22px rgba(0, 0, 0, .34);
            pointer-events: none;
        }
        .live-brand img { display: block; width: auto; height: 24px; max-width: 118px; object-fit: contain; }
        .live-badge { display: inline-flex; align-items: center; gap: 6px; color: #fff; font: 700 11px/1 Arial, sans-serif; letter-spacing: .08em; }
        .live-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #ef233c; box-shadow: 0 0 10px rgba(239, 35, 60, .95); }
        @media (max-width: 480px) {
            .live-brand { top: 10px; left: 10px; padding: 6px 8px; gap: 8px; }
            .live-brand img { height: 19px; max-width: 92px; }
            .live-badge { font-size: 9px; }
        }
    </style>
</head>
<body>
    <div class="live-embed">
        <video id="live-player" controls playsinline preload="metadata" @if($posterUrl) poster="{{ $posterUrl }}" @endif></video>
        <div class="live-brand" aria-label="eZWay TV live">
            <img src="https://ezwayott.sfo3.digitaloceanspaces.com/logos/image/ezwaytv_white_6a26f75c71a3d.png" alt="eZWay TV">
            <span class="live-badge">LIVE</span>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <script>
        (() => {
            const player = document.getElementById('live-player');
            const source = @json($streamUrl);

            if (player.canPlayType('application/vnd.apple.mpegurl')) {
                player.src = source;
            } else if (window.Hls && Hls.isSupported()) {
                const hls = new Hls();
                hls.loadSource(source);
                hls.attachMedia(player);
            }
        })();
    </script>
</body>
</html>
