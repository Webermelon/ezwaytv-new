<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Return to eZWay TV</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #080808; color: white; font-family: system-ui, sans-serif; }
        main { width: 100%; max-width: 440px; padding: 30px; border: 1px solid #333; border-radius: 12px; background: #141414; }
        h1 { margin: 0 0 16px; font-size: 28px; }
        p { line-height: 1.6; color: #bcbcbc; }
        .button { display: block; margin-top: 24px; padding: 15px; border-radius: 6px; background: #edc342; color: #111; text-align: center; text-decoration: none; font-weight: 750; }
        .button:hover, .button:focus-visible { background: #f4ce4d; }
        .retry { display: block; margin-top: 24px; color: #bcbcbc; font-size: 14px; }
    </style>
</head>
<body>
    <main>
        <h1>Return to eZWay TV</h1>
        <p>Finish your Connect sign-in in the TV app where you started.</p>
        <a id="return-to-tv" class="button" href="{{ $appLink }}">Return to eZWay TV</a>
        <p>If the app does not open automatically, tap the button above.</p>
        <a class="retry" href="{{ $restartUrl }}">Or start a new sign-in in this browser</a>
    </main>
    <script>
        window.location.replace(@json($appLink));
    </script>
</body>
</html>
