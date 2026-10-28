# eZWay Connect SSO on TV

TV implements Connect's one-time-code protocol using the session-enabled web
routes `/auth/connect/start` and `/auth/connect/callback`. The exchange is a
server-to-server HMAC-signed POST; the secret is never passed to React.

The login page shows **Continue with eZWay Connect** when `CONNECT_SSO_SECRET`
is configured and `CONNECT_SSO_SHOW_LOGIN=true`. The sign-in and account-linking
buttons are hidden by default. The existing `?redirect=/local/path` login parameter is passed
to the start route as `redirect_to`. `/auth/login?connect_status=failed`
redirects to TV's actual `/login` page for Connect's expiration fallback.

For testing while public buttons are hidden, open `/auth/connect/test` directly.
This temporary page is not linked from site navigation and sends `noindex`
and `no-store` headers. Its button returns successful sign-ins to the same page.
It uses the regular SSO routes and is available independently of `CONNECT_SSO_SHOW_LOGIN`.

## Mobile app return

Android/iOS can open Connect in an external browser while TV's login state
remains in the TV app's WebView. A mobile callback with no initiating TV session
now returns a small **Return to eZWay TV** page using the TV app's existing
`ezwaytv://open?url=...` deep link (Android package `tv.ezway.apps`). It forwards
the callback to the app instead of consuming the code in the external browser.

The original TV session must still match the saved state before any exchange
or login. The return page neither authenticates the external browser nor
relaxes state validation. It disables caching, indexing, and referrer sharing.
The `app_return=1` marker prevents a repeated app-return loop. If the original
app session was cleared, start a new sign-in; the callback remains rejected.

## Deployment

1. Apply the dedicated migration before configuring the secret:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_22_000001_add_connect_user_id_to_users_table.php --force
   ```

2. No bulk account import or manual mapping is needed for new members. The
   first successful Connect login creates the TV account and saves Connect's
   stable `user_id` in `users.connect_user_id`. The schema migration adds this
   unique nullable field once; it does not copy or modify existing accounts.
   It is separate from `network_user_id` and guarded against mass assignment.

3. Set TV's private environment values:

   ```dotenv
   CONNECT_SSO_URL=https://ezwayconnect.com
   CONNECT_SSO_CLIENT_ID=ezway-tv
   CONNECT_SSO_SECRET=<unique-shared-secret>
   CONNECT_SSO_TIMEOUT=10
   SESSION_SECURE_COOKIE=true
   ```

   Keep `SESSION_DOMAIN` unset/host-only. The session configuration already
   uses `SameSite=lax`. Use HTTPS for Connect and the callback. Never place
   the secret in `VITE_*` variables, browser storage, or committed files.

4. On Connect, set `EZWAY_TV_SSO_CLIENT_URL=https://ezway.tv`,
   `EZWAY_TV_SSO_CALLBACK_URL=https://ezway.tv/auth/connect/callback`, and
   `EZWAY_TV_SSO_SECRET` to the same private secret. If the public TV hostname
   differs, update both URLs to its actual HTTPS hostname.

5. Refresh Laravel configuration and route caches through the normal deployment
   process on both servers, and deploy the React assets using `npm run react:build`.

6. Verify first-time account creation and returning login, both with and without
   an existing Connect session. Check a local return path, cancellation, expired
   state/code, automatic existing-email sign-in, and banned, disabled, and deleted TV accounts. Confirm
   the callback retains HTTPS behind the proxy and both clocks are synchronized
   within 60 seconds. Connect's replay/nonce rejection and inactive-user checks
   require a live integration test against Connect. Multi-node Connect must use
   its shared atomic cache for authorization codes and nonces.

## Account behavior

Any identity accepted by Connect's signed one-time-code exchange can create a
TV account on first sign-in. The response must include a positive `user_id`
and valid email. New accounts receive only the regular `user` role, a random
password, and a default viewing profile. They do not receive paid subscriptions
or administrator access. An active Connect identity does not assert email
verification, so TV does not mark a new account's email verified.

Returning sign-ins use `connect_user_id`. Existing TV accounts must be
non-deleted with `status=1` and `is_banned=0`; SSO never reactivates them or
replaces their roles, subscriptions, profiles, or email.

Connect is the trusted identity authority for this integration. If no Connect
ID mapping exists, TV matches the email returned by the authenticated
server-to-server exchange, saves the Connect ID, and signs into the existing
TV account automatically. No separate TV OTP login or manual linking is needed.
The matching is case-insensitive and never uses email from browser parameters.
Existing TV subscriptions, roles, passwords, profiles, and history are retained.

An account already mapped to a different Connect ID is rejected with
`identity_conflict`, as are ambiguous case-insensitive email matches. SSO never
reactivates a disabled, banned, or deleted local account.

Account creation, role assignment, and profile creation run in one transaction.
The unique Connect ID and email constraints prevent duplicate accounts during
concurrent sign-ins. SSO regenerates the TV session, clears pending TV OTP
challenges, and records the normal TV device session.

The start route supports `register=1` for Connect registration continuation;
new Connect members are provisioned on TV through the same first-login flow.
TV logout remains local; global logout is outside this protocol.

An unsuccessful callback consumes the saved intent. Start a new sign-in attempt
to retry. The controller deliberately does not retry an exchange or follow
exchange HTTP redirects, and does not log the one-time code or signature.

## Tests

Run `php vendor/bin/phpunit tests/Feature/Auth/ConnectSsoTest.php --no-coverage`
with the PHP PDO SQLite extension enabled. The tests use an in-memory database
and fake all Connect HTTP calls; they never call the live provider. The tests
cover the exact signed bytes, local-account policies, state expiration and
consumption, unsafe destinations, network and provider failures, automatic
provisioning, transaction rollback, automatic existing-account sign-in, identity
conflicts, and migration uniqueness/rollback.

Rollback the feature by clearing `CONNECT_SSO_SECRET` and refreshing configuration.
The login button will disappear and new SSO attempts return 503. The nullable
mapping can remain in place; rolling back its migration discards reviewed links.
