<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <title>eZWay TV API Docs</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: #000; color: #f5f5f5; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { display: flex; justify-content: center; padding: 48px 20px; }
        main { width: min(960px, 100%); }
        a { color: inherit; }
        .eyebrow { color: #d3a221; font-size: 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; }
        h1 { margin: 10px 0 12px; font-size: clamp(34px, 6vw, 64px); line-height: .95; letter-spacing: 0; }
        h2 { margin: 34px 0 12px; font-size: 22px; }
        h3 { margin: 24px 0 8px; font-size: 17px; }
        p { color: #b7b7b7; font-size: 16px; line-height: 1.7; }
        .grid { display: grid; gap: 14px; margin-top: 28px; }
        .card { display: block; border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 20px; text-decoration: none; background: #090909; }
        .card strong { display: block; font-size: 18px; margin-bottom: 6px; }
        .card span, li { color: #b7b7b7; line-height: 1.7; }
        .panel { border: 1px solid rgba(255,255,255,.12); border-radius: 12px; background: #080808; padding: 22px; margin-top: 18px; }
        code { color: #fff; background: #151515; border: 1px solid rgba(255,255,255,.1); border-radius: 6px; padding: 2px 6px; }
        pre { margin: 14px 0 0; padding: 16px; overflow: auto; color: #f4f4f4; background: #050505; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; line-height: 1.55; font-size: 13px; }
        pre code { display: block; padding: 0; background: transparent; border: 0; color: inherit; }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">eZWay TV</div>
    <h1>TV Access API Docs</h1>
    <p>API contracts used by eZWay Core and the TV frontend. Private access endpoints are server-to-server only. The checkout bridge endpoints are authenticated TV web routes that proxy the logged-in viewer to Core without exposing Core API credentials to the browser.</p>

    <div class="panel" id="index">
        <h2>Index</h2>
        <div class="grid">
            <a class="card" href="#auth"><strong>Authentication</strong><span>Bearer token and HMAC request signature.</span></a>
            <a class="card" href="#mapping"><strong>Access Mapping</strong><span>How Core capability access.premium.4 maps to TV plan 4.</span></a>
            <a class="card" href="#sync-user"><strong>Sync User</strong><span>Create or update TV user from Core/Connect identity.</span></a>
            <a class="card" href="#activate"><strong>Activate Subscription</strong><span>Grant TV plan access after Core checkout succeeds.</span></a>
            <a class="card" href="#cancel"><strong>Cancel Subscription</strong><span>Revoke TV plan access when subscription ends.</span></a>
            <a class="card" href="#status"><strong>Check Access</strong><span>Read active TV access status by Core user ID.</span></a>
            <a class="card" href="#checkout-bridge"><strong>TV Checkout Bridge</strong><span>Authenticated TV web endpoints for saved cards, checkout, and payment history.</span></a>
        </div>
    </div>

    <div class="panel" id="auth">
        <h2>Authentication</h2>
        <p>Every private Core request must include a bearer token. TV accepts the legacy <code>CORE_PRIVATE_API_TOKEN</code> from <code>.env</code> and active database keys generated from the backend page <code>/app/core-api-keys</code>.</p>
        <pre><code>Authorization: Bearer &lt;generated_token_or_CORE_PRIVATE_API_TOKEN&gt;
X-Core-Signature: &lt;hmac_sha256(raw_body, signing_secret)&gt;
X-Request-Id: &lt;uuid&gt;
Content-Type: application/json</code></pre>
        <p>Generated API keys include a bearer token and signing secret. The token is stored hashed, the secret is stored encrypted, and both raw values are only shown once after generation.</p>
        <p>Invalid token returns <code>401</code>. Invalid signature also returns <code>401</code>.</p>
    </div>

    <div class="panel" id="mapping">
        <h2>Access Mapping</h2>
        <ul>
            <li>Current Core package slug: <code>tv-subscription-monthly</code>.</li>
            <li>Current Core package ID in staging: <code>17</code>.</li>
            <li>Core access package is resolved from the package settings in Core, not hardcoded by TV.</li>
            <li>Connect/Core capability: <code>access.premium.4</code>.</li>
            <li>The trailing <code>4</code> is used as the TV plan ID.</li>
            <li>TV stores the Core user ID in <code>users.network_user_id</code>.</li>
            <li>TV stores access in the existing <code>subscriptions</code> table, so current content plan checks continue to work.</li>
        </ul>
    </div>

    <div class="panel" id="sync-user">
        <h2>Sync User</h2>
        <p><code>POST /api/private/core/users/sync</code></p>
        <p>Creates or updates a TV user from Core/Connect identity. TV first matches by <code>network_user_id</code>, then by email.</p>
        <h3>Request</h3>
        <pre><code>{
  "core_user_id": 1,
  "connect_user_id": 1,
  "email": "trimatric@example.com",
  "username": "trimatric",
  "first_name": "Tri",
  "last_name": "Matric",
  "avatar": "https://example.com/avatar.jpg",
  "phone": "+15555555555"
}</code></pre>
        <h3>Response</h3>
        <pre><code>{
  "success": true,
  "tv_user_id": 456,
  "network_user_id": 1
}</code></pre>
    </div>

    <div class="panel" id="activate">
        <h2>Activate Subscription</h2>
        <p><code>POST /api/private/core/subscriptions/activate</code></p>
        <p>Activates a TV subscription after Core confirms payment. The endpoint is idempotent for the same <code>core_subscription_id</code>.</p>
        <h3>Request</h3>
        <pre><code>{
  "core_user_id": 1,
  "connect_user_id": 1,
  "email": "trimatric@example.com",
  "username": "trimatric",
  "first_name": "Tri",
  "last_name": "Matric",
  "core_subscription_id": 77,
  "core_invoice_id": "in_abc123",
  "core_package_id": 6,
  "access_package_id": 7,
  "capability": "access.premium.4",
  "tv_plan_id": 4,
  "starts_at": "2026-06-24T00:00:00Z",
  "ends_at": "2026-07-24T00:00:00Z",
  "amount": 499,
  "total_amount": 499,
  "status": "active"
}</code></pre>
        <h3>Response</h3>
        <pre><code>{
  "success": true,
  "tv_user_id": 456,
  "network_user_id": 1,
  "tv_subscription_id": 9001,
  "tv_plan_id": 4,
  "status": "active",
  "ends_at": "2026-07-24 00:00:00"
}</code></pre>
    </div>

    <div class="panel" id="cancel">
        <h2>Cancel Subscription</h2>
        <p><code>POST /api/private/core/subscriptions/cancel</code></p>
        <p>Revokes active access for a Core subscription and TV plan.</p>
        <h3>Request</h3>
        <pre><code>{
  "core_user_id": 1,
  "core_subscription_id": 77,
  "capability": "access.premium.4",
  "tv_plan_id": 4,
  "cancelled_at": "2026-07-24T00:00:00Z",
  "reason": "subscription_cancelled"
}</code></pre>
        <h3>Response</h3>
        <pre><code>{
  "success": true,
  "tv_user_id": 456,
  "tv_plan_id": 4,
  "status": "cancelled"
}</code></pre>
    </div>

    <div class="panel" id="status">
        <h2>Check Access</h2>
        <p><code>GET /api/private/core/users/{core_user_id}/access?capability=access.premium.4</code></p>
        <h3>Response</h3>
        <pre><code>{
  "success": true,
  "has_access": true,
  "tv_user_id": 456,
  "tv_plan_id": 4,
  "status": "active",
  "ends_at": "2026-07-24 00:00:00"
}</code></pre>
    </div>



    <div class="panel" id="checkout-bridge">
        <h2>TV Checkout Bridge</h2>
        <p>These routes are authenticated TV web routes. They are called by the TV React app and then proxied by TV to Core using <code>CORE_API_BASE_URL</code>, <code>CORE_API_TOKEN</code>, and optional <code>CORE_API_HOST</code>. The browser never sends a Core token or chooses the Core user ID.</p>

        <h3>Load Saved Payment Methods</h3>
        <p><code>GET /core/payment-methods</code></p>
        <p>TV resolves the logged-in user, maps to <code>users.network_user_id</code> when available, and calls Core <code>GET /api/users/{core_user_id}/payment-methods</code>.</p>
        <pre><code>{
  "data": [
    {
      "id": "pm_abc123",
      "brand": "visa",
      "last4": "4242",
      "exp_month": 12,
      "exp_year": 2028
    }
  ]
}</code></pre>

        <h3>Create Checkout</h3>
        <p><code>POST /core/checkouts</code></p>
        <p>Creates a Core checkout for the logged-in TV user. If <code>payment_method_id</code> is provided, Core can try direct charge first. If no card exists or direct charge cannot complete, Core returns a hosted checkout URL.</p>
        <pre><code>{
  "package_slug": "tv-subscription-monthly",
  "payment_method_id": "pm_abc123"
}</code></pre>
        <p>TV sends Core metadata for scoping payment history and access sync:</p>
        <pre><code>{
  "platform": { "slug": "ezway-tv" },
  "subject_type": "tv_subscription",
  "subject_id": "{core_user_id}",
  "metadata": {
    "source_app": "ezway_tv",
    "tv_user_id": "{tv_user_id}",
    "network_user_id": "{core_user_id}"
  }
}</code></pre>
        <h3>Response</h3>
        <pre><code>{
  "success": true,
  "redirect_url": "https://sandbox.ezwaypay.com/subscription-checkout/...",
  "provider_subscription_id": "sub_..."
}</code></pre>
        <p>Success, cancel, and failed redirects return to:</p>
        <pre><code>/subscription-plan?checkout_status=success
/subscription-plan?checkout_status=cancelled
/subscription-plan?checkout_status=failed</code></pre>

        <h3>Payment History</h3>
        <p><code>GET /core/payment-history</code></p>
        <p>Returns payment history for the logged-in TV user only. TV calls Core <code>GET /api/payments/status</code> with all of the following metadata filters together:</p>
        <pre><code>{
  "platform_slug": "ezway-tv",
  "subject_type": "tv_subscription",
  "subject_id": "{core_user_id}"
}</code></pre>
        <p>Core must match these metadata values together. This prevents unrelated membership payments for the same user ID from showing in TV payment history.</p>
        <p>The TV page currently displays only <code>subscriptions</code> and <code>invoices</code>. If Core has no local invoice rows for a TV subscription, the TV frontend displays invoice-style rows derived from the subscription records so the invoice tab is still useful.</p>
        <pre><code>{
  "data": {
    "subscriptions": [],
    "invoices": [],
    "service_orders": [],
    "transactions": []
  }
}</code></pre>
    </div>


    <div class="panel" id="errors">
        <h2>Error Examples</h2>
        <pre><code>{
  "message": "Unauthorized."
}</code></pre>
        <pre><code>{
  "message": "Invalid signature."
}</code></pre>
        <pre><code>{
  "message": "The given data was invalid.",
  "errors": {
    "capability": [
      "The capability field is required."
    ]
  }
}</code></pre>
    </div>
</main>
</body>
</html>
