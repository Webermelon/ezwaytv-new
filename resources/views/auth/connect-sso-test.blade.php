<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Connect Sign-in Test | eZWay TV</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #080808; color: #fff; font-family: system-ui, sans-serif; }
        main { width: 100%; max-width: 460px; padding: 32px; border: 1px solid #333; border-radius: 12px; background: #141414; }
        .label { color: #edc342; font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 12px 0; font-size: 28px; }
        p { color: #bcbcbc; line-height: 1.6; }
        .status { padding: 12px 16px; border: 1px solid #38573d; border-radius: 6px; background: #142319; color: #c9eed0; }
        .button { display: block; margin-top: 24px; padding: 14px 18px; border-radius: 6px; background: #edc342; color: #111; text-align: center; text-decoration: none; font-weight: 750; }
        .button:hover, .button:focus-visible { background: #f4ce4d; }
        .back { display: inline-block; margin-top: 24px; color: #bcbcbc; font-size: 14px; }
    </style>
</head>
<body>
    <main>
        <span class="label">Temporary test page</span>
        <h1>eZWay Connect sign-in</h1>
        <p>Sign in with Connect. Your TV account will be created automatically or your existing account will be used. Successful sign-in returns here.</p>

        @auth
            <p class="status" role="status">
                @if (auth()->user()->connect_user_id !== null)
                    Signed in to TV with a linked Connect account.
                @else
                    Signed in to TV. Continue with Connect to sign in using your Connect account.
                @endif
            </p>
        @endauth

        @if ((string) config('connect_sso.secret') !== '')
            <a class="button" href="{{ route('connect-sso.start', ['redirect_to' => route('connect-sso.test', [], false)]) }}">Continue with eZWay Connect</a>
        @else
            <p role="status">Connect sign-in is not configured yet.</p>
        @endif

        <a class="back" href="/">Back to eZWay TV</a>
    </main>
</body>
</html>
