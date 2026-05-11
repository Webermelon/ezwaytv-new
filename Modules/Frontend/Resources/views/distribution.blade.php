{{-- eZWay TV Distribution Page --}}
@extends('frontend::layouts.master')

@section('title', 'eZWay TV Distribution')

@push('after-styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">

<style>
  :root {
    --gold:       #D4A843;
    --gold-light: #F0C96A;
    --gold-dim:   #8A6B28;
    --charcoal:   #0D0D0D;
    --surface:    #161616;
    --surface-2:  #1E1E1E;
    --surface-3:  #272727;
    --border:     rgba(212,168,67,0.18);
    --text:       #E8E2D9;
    --text-muted: #7A7468;
    --red:        #C0392B;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--charcoal);
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-weight: 300;
    line-height: 1.6;
    overflow-x: hidden;
  }

  /* ── HERO ─────────────────────────────────────────── */
  .dist-hero {
    position: relative;
    padding: 90px 0 70px;
    text-align: center;
    overflow: hidden;
  }

  .dist-hero::before {
    content: '';
    position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 50% 0%, rgba(212,168,67,0.10) 0%, transparent 70%),
      repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(212,168,67,0.04) 40px),
      repeating-linear-gradient(90deg, transparent, transparent 39px, rgba(212,168,67,0.04) 40px);
    pointer-events: none;
  }

  .dist-hero .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 22px;
  }

  .dist-hero .eyebrow::before,
  .dist-hero .eyebrow::after {
    content: '';
    display: block;
    width: 32px; height: 1px;
    background: var(--gold);
    opacity: 0.6;
  }

  .dist-hero h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(54px, 8vw, 96px);
    letter-spacing: 0.04em;
    line-height: 0.92;
    color: #fff;
    margin-bottom: 28px;
  }

  .dist-hero h1 span { color: var(--gold); }

  .dist-hero .hero-lead {
    max-width: 680px;
    margin: 0 auto 36px;
    font-size: 15px;
    color: #9E9890;
    line-height: 1.75;
  }

  .hero-badge-row {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 10px;
  }

  .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border: 1px solid var(--border);
    border-radius: 2px;
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    color: var(--gold-light);
    background: rgba(212,168,67,0.06);
  }

  .hero-badge svg { width: 14px; height: 14px; opacity: 0.8; }

  /* ── REACH BAR ────────────────────────────────────── */
  .reach-bar {
    background: var(--gold);
    padding: 14px 24px;
    text-align: center;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--charcoal);
  }

  /* ── SECTION WRAPPER ──────────────────────────────── */
  .dist-section {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 32px;
  }

  /* ── SECTION HEADING ──────────────────────────────── */
  .section-head {
    display: flex;
    align-items: baseline;
    gap: 18px;
    margin-bottom: 48px;
    padding-top: 72px;
  }

  .section-head h2 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(32px, 4vw, 52px);
    letter-spacing: 0.05em;
    color: #fff;
  }

  .section-head .rule {
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, var(--gold-dim), transparent);
  }

  /* (removed duplicate Ad Reach banner styles) */

  /* ── LA STATION CARD ──────────────────────────────── */
  .la-station-card {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 4px solid var(--gold);
    border-radius: 4px;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    margin-bottom: 80px;
  }

  @media (max-width: 768px) {
    .la-station-card { grid-template-columns: 1fr; }
  }

  .la-station-info {
    padding: 48px 44px;
  }

  .la-station-info .tag {
    display: inline-block;
    padding: 4px 12px;
    background: var(--gold);
    color: var(--charcoal);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    border-radius: 2px;
    margin-bottom: 20px;
  }

  .la-station-info h3 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 46px;
    letter-spacing: 0.04em;
    line-height: 1;
    color: #fff;
    margin-bottom: 8px;
  }

  .la-station-info .channel-num {
    font-family: 'DM Serif Display', serif;
    font-style: italic;
    font-size: 20px;
    color: var(--gold-light);
    margin-bottom: 24px;
    letter-spacing: 0.02em;
  }

  .la-station-info p {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.75;
    max-width: 360px;
  }

  .la-station-info .signal-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 28px;
    padding: 11px 22px;
    background: transparent;
    border: 1px solid var(--gold);
    border-radius: 2px;
    color: var(--gold-light);
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    transition: background 0.2s, color 0.2s;
  }

  .la-station-info .signal-link:hover {
    background: var(--gold);
    color: var(--charcoal);
  }

  .la-station-map {
    position: relative;
    min-height: 360px;
    background: var(--surface-2);
    overflow: hidden;
  }

  .la-station-map iframe {
    width: 100%;
    height: 100%;
    min-height: 360px;
    border: none;
    filter: saturate(0.6) contrast(1.1);
    opacity: 0.85;
  }

  .la-station-map .map-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(22,22,22,0.55) 0%, transparent 30%);
    pointer-events: none;
  }

  /* ── NETWORK GRID ─────────────────────────────────── */
  .network-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 2px;
    background: var(--surface-3);
    border: 1px solid var(--surface-3);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 80px;
  }

  .network-card {
    background: var(--surface);
    padding: 36px 32px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: background 0.2s;
    position: relative;
    overflow: hidden;
  }

  .network-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 3px;
    height: 100%;
    background: var(--gold);
    transform: scaleY(0);
    transform-origin: bottom;
    transition: transform 0.3s ease;
  }

  .network-card:hover { background: var(--surface-2); }
  .network-card:hover::after { transform: scaleY(1); }

  .card-logo-area {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .card-logo-box {
    width: 72px; height: 46px;
    background: var(--surface-3);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
  }

  .card-logo-box img {
    max-width: 64px;
    max-height: 40px;
    object-fit: contain;
  }

  .card-name {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 22px;
    letter-spacing: 0.05em;
    color: #fff;
    line-height: 1.1;
  }

  .card-body {
    font-size: 13px;
    color: var(--text);
    line-height: 1.7;
    flex: 1;
  }

  .card-tag {
    align-self: flex-start;
    padding: 3px 9px;
    border: 1px solid var(--border);
    border-radius: 2px;
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold-light);
  }

  /* ── PLATFORM STRIP ───────────────────────────────── */
  .platforms-strip {
    background: var(--surface);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    padding: 40px 0;
    text-align: center;
    margin-bottom: 0;
  }

  .platforms-strip .plat-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 20px;
  }

  .platforms-list {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 10px 18px;
    max-width: 860px;
    margin: 0 auto;
  }

  .plat-chip {
    padding: 8px 20px;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 2px;
    font-size: 12px;
    font-weight: 500;
    color: #9E9890;
    letter-spacing: 0.04em;
    background: var(--surface-2);
    white-space: nowrap;
  }

  /* ── ANIMATE ON SCROLL ────────────────────────────── */
  [data-reveal] {
    opacity: 0;
    transform: translateY(22px);
    transition: opacity 0.55s ease, transform 0.55s ease;
  }

  [data-reveal].visible {
    opacity: 1;
    transform: none;
  }
</style>
@endpush

@push('ezstats-meta')
<meta property="og:title" content="eZWay TV Distribution" />
<meta property="og:description" content="Global TV distribution across major platforms — reach and organic viewership." />
<meta property="og:image" content="/public/img/distribution/distribution-og.jpg" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:image" content="/public/img/distribution/distribution-og.jpg" />
@endpush

@section('content')
 
{{-- HERO --}}
<section class="dist-hero">
  <div class="dist-section">
    <div class="eyebrow">eZWay Network</div>
    <h1>eZWay <span>TV</span><br>Distribution</h1>
    <p class="hero-lead">
      eZWay.TV is an interactive streaming platform with worldwide reach — powered by a massive distribution network across AVOD, linear, mobile, smart TVs, and cable-connected devices.
    </p>
    <div class="hero-badge-row">
      <span class="hero-badge" style="font-weight:700;">
        Potential reach: 100,000,000 ·
      </span>
      <span class="hero-badge" style="background:transparent;border:none;color:var(--text);font-weight:600;">
        Organic viewers: tens of thousands
      </span>
    </div>
  </div>
</section>
 
<div class="reach-bar">
  Potential reach: 100,000,000 · Organic viewers: tens of thousands
</div>

<!-- Duplicate reach banner removed -->
 
{{-- ── LOS ANGELES STATION ─────────────────────────── --}}
<div class="dist-section">
  <div class="section-head" data-reveal>
    <h2>Los Angeles Station</h2>
    <div class="rule"></div>
  </div>
 
  <div class="la-station-card" data-reveal>
    <div class="la-station-info">
      <span class="tag">OTA · Over-the-Air</span>
      <h3>eZWay<br>Los Angeles</h3>
      <div class="channel-num">Channel 27.3</div>
      <p>
        eZWay Network is now broadcasting over-the-air in the Los Angeles metro area on <strong style="color:var(--text)">Channel 27.3</strong>. The station reaches millions of households across Greater LA, delivering purpose-driven content, live events, interviews, and original series — completely free over the air.
      </p>
      <p style="margin-top:14px;">
        View the full FCC contour map for signal coverage, propagation details, and licensing information for K21AC-D, the station powering this broadcast.
      </p>
      <a
        href="https://www.rabbitears.info/contour.php?appid=25076f917915d2290179276a691b1937&site=1&dma=N&map=N&contour=Y&lppc=N&int=N&pop=Y&incpop=k21ac-d&excpop=&z1=N&nrqz=N&lprw=N&head=Y&asrn=&extras=&cir=&circen="
        target="_blank"
        rel="noopener"
        class="signal-link"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M1 6c0 0 4-4 11-4s11 4 11 4"/>
          <path d="M5 10c0 0 2.5-2.5 7-2.5s7 2.5 7 2.5"/>
          <path d="M9 14c0 0 .9-1 3-1 2.1 0 3 1 3 1"/>
          <circle cx="12" cy="18" r="1" fill="currentColor"/>
        </svg>
        View Signal Coverage Map
      </a>
    </div>
    <div class="la-station-map">
      <iframe
        src="https://www.rabbitears.info/contour.php?appid=25076f917915d2290179276a691b1937&site=1&dma=N&map=N&contour=Y&lppc=N&int=N&pop=Y&incpop=k21ac-d&excpop=&z1=N&nrqz=N&lprw=N&head=Y&asrn=&extras=&cir=&circen="
        title="eZWay LA Station Signal Coverage — Channel 27.3 K21AC-D"
        loading="lazy"
      ></iframe>
      <div class="map-overlay"></div>
    </div>
  </div>
</div>
 
{{-- ── NETWORK PARTNERS ────────────────────────────── --}}
<div class="dist-section">
  <div class="section-head" data-reveal>
    <h2>Network Partners</h2>
    <div class="rule"></div>
  </div>
 
  <div class="network-grid">

  {{-- EZWAY.TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/Frame_14_69bffc354fd95.jpg" alt="EZWAY.TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">EZWAY.TV</div>
    </div>
    <div class="card-body">
      Official flagship streaming platform of the eZWay ecosystem delivering curated entertainment, interviews, and creator-driven content across multiple devices and audiences.
    </div>
    <span class="card-tag">Flagship · Network Hub</span>
  </div>

  {{-- XOTV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/IMG_20260426_WA0016_69ee16da67dc3.jpg" alt="XOTV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">XOTV</div>
    </div>
    <div class="card-body">
      Streaming and media distribution channel within the eZWay ecosystem focusing on entertainment and creator content.
    </div>
    <span class="card-tag">Streaming · Entertainment</span>
  </div>

  {{-- BVC TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/6c799fe3_043c_4094_86b9_fabe4b3a8eec_300x222_69afe52fe2b7e.jpeg" alt="BVC TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">BVC TV</div>
    </div>
    <div class="card-body">
      BVC TV is a nonprofit supported by the eZWay Family community, which includes eZWay TV. The partnership includes fundraising events, such as the eZWay Awards Golden Gala, to support eye care and education for children. Eric Zuley leads the eZWay community, which features 24/7, promoting BVC's mission.
    </div>
    <span class="card-tag">Broadcast · Digital TV</span>
  </div>

  {{-- NATIONAL BIZ TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/1000154509_69defe5ac06b2.jpg" alt="National Biz TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">National Biz TV</div>
    </div>
    <div class="card-body">
      Business-focused media channel featuring entrepreneurship, interviews, and educational content.
    </div>
    <span class="card-tag">Business · Media</span>
  </div>

  {{-- EZWAY MUSIC --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/WhatsApp_Image_2026_04_14_at_13_00_04_69ddeed88c92e.jpeg" alt="EZWAY MUSIC" onerror="this.style.display='none'">
      </div>
      <div class="card-name">EZWAY MUSIC</div>
    </div>
    <div class="card-body">
      Music-focused distribution channel showcasing artists, performances, and curated audio-visual content.
    </div>
    <span class="card-tag">Music · Entertainment</span>
  </div>

  {{-- THE WOMEN’S CHANNEL --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/the_womans_channel_69d0a0d4083c3.jpeg" alt="The Women’s Channel" onerror="this.style.display='none'">
      </div>
      <div class="card-name">The Women’s Channel</div>
    </div>
    <div class="card-body">
      Content platform focused on empowering stories, leadership, lifestyle, and women-centered programming.
    </div>
    <span class="card-tag">Lifestyle · Empowerment</span>
  </div>

  {{-- BE SPIRE TV (existing) --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/bespire-tv.jpeg" alt="Be Spire TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Be Spire TV</div>
    </div>
    <div class="card-body">
      Inspirational and purpose-driven content platform aligned with empowerment and positive storytelling.
    </div>
    <span class="card-tag">Inspirational · Purpose-Driven</span>
  </div>

  {{-- XPN TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/xpn_69eef82b59e28.jpeg" alt="XPN TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">XPN TV</div>
    </div>
    <div class="card-body">
      Multimedia broadcast channel delivering entertainment, interviews, and creator-driven programming.
    </div>
    <span class="card-tag">Broadcast · Network</span>
  </div>

  {{-- FAN TV GLOBAL (existing) --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/Untitled_69e138d4675c9.jpeg" alt="Fan TV Global" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Fan TV Global</div>
    </div>
    <div class="card-body">
      Global creator-focused streaming and distribution platform amplifying independent voices worldwide.
    </div>
    <span class="card-tag">Web3 · Global · Creator</span>
  </div>

  {{-- EZWAY MOVIES --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/movies_69dcf6ff8ca84.jpeg" alt="EZWAY Movies" onerror="this.style.display='none'">
      </div>
      <div class="card-name">EZWAY Movies</div>
    </div>
    <div class="card-body">
      Film-focused distribution channel featuring movies, specials, and cinematic content from creators.
    </div>
    <span class="card-tag">Movies · VOD</span>
  </div>

  {{-- KATE LINDER TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/kate_69e7220ad0de4.jpeg" alt="Kate Linder TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Kate Linder TV</div>
    </div>
    <div class="card-body">
      Branded content channel featuring personality-driven media and curated entertainment programming.
    </div>
    <span class="card-tag">Branded · Entertainment</span>
  </div>

  {{-- POWER TV NETWORK (existing) --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/power.jpg" alt="Power TV Network" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Power TV Network</div>
    </div>
    <div class="card-body">
      Broadcast and streaming network delivering impactful storytelling and diverse programming.
    </div>
    <span class="card-tag">Broadcast · Network</span>
  </div>

  {{-- I&C TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/Untitled_69e138d4675c9.jpeg" alt="I&C TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">I&C TV</div>
    </div>
    <div class="card-body">
      International content channel within the eZWay distribution ecosystem focused on curated programming and global reach.
    </div>
    <span class="card-tag">International · Content</span>
  </div>

  {{-- EXISTING ORIGINAL ITEMS (kept unchanged) --}}

  {{-- Tubi --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/tubi.png" alt="Tubi" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Tubi</div>
    </div>
    <div class="card-body">
      One of the largest free streaming platforms in the US with over 75 million monthly active users. eZWay content reaches a massive cord-cutter audience across smart TVs, mobile, web, and connected devices — completely free and ad-supported.
    </div>
    <span class="card-tag">AVOD · Free Streaming</span>
  </div>

  {{-- Pluto TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/pluto-logo.png" alt="Pluto TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Pluto TV</div>
    </div>
    <div class="card-body">
      A leading free, ad-supported streaming service owned by Paramount with over 80 million monthly active users worldwide. eZWay programming is distributed across Pluto TV's live channel lineup and on-demand library, reaching audiences across the US and globally.
    </div>
    <span class="card-tag">FAST · Free · Paramount</span>
  </div>

  {{-- Apple TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/apple-tv.png" alt="Apple TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Apple TV</div>
    </div>
    <div class="card-body">
      eZWay content is available on Apple TV, putting the network in front of hundreds of millions of Apple device users worldwide — distributed through the Apple TV app ecosystem across iPhones, iPads, Macs, and Apple TV hardware.
    </div>
    <span class="card-tag">Premium · Apple Ecosystem</span>
  </div>

  {{-- Roku --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/Roku-Logo.png" alt="Roku" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Roku</div>
    </div>
    <div class="card-body">
      The #1 TV streaming platform in the US with over 80 million active accounts. eZWay reaches Roku's massive audience through the Roku Channel Store — available on Roku streaming sticks, smart TVs, and the Roku mobile app on iOS and Android.
    </div>
    <span class="card-tag">Streaming · #1 Platform</span>
  </div>

  {{-- Fire TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/fire-tv.png" alt="Amazon Fire TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Fire TV</div>
    </div>
    <div class="card-body">
      Amazon Fire TV reaches over 200 million devices sold worldwide. eZWay programming is distributed through the Fire TV app ecosystem — connecting with Amazon's vast customer base across Fire TV Sticks, Fire TV Cubes, and Fire TV Edition smart TVs.
    </div>
    <span class="card-tag">Amazon · 200M+ Devices</span>
  </div>

  {{-- Whale TV --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/whale-tv.png" alt="Whale TV" onerror="this.style.display='none'">
      </div>
      <div class="card-name">Whale TV</div>
    </div>
    <div class="card-body">
      Whale TV is an emerging streaming platform delivering curated independent content to audiences hungry for fresh, authentic programming outside the mainstream. eZWay's distribution on Whale TV connects the network's purpose-driven content with a growing community of engaged viewers.
    </div>
    <span class="card-tag">Independent · Streaming</span>
  </div>

  {{-- iVOD --}}
  <div class="network-card" data-reveal>
    <div class="card-logo-area">
      <div class="card-logo-box">
        <img src="/img/distribution/ivod-logo.jpg" alt="iVOD" onerror="this.style.display='none'">
      </div>
      <div class="card-name">iVOD</div>
    </div>
    <div class="card-body">
      iVOD delivers video-on-demand content to audiences across connected TV devices and digital platforms. eZWay's presence on iVOD extends the network's reach into on-demand viewing, giving audiences the flexibility to watch compelling content on their own schedule.
    </div>
    <span class="card-tag">VOD · Connected TV</span>
  </div>

</div>
</div>

{{-- ── PLATFORMS STRIP ──────────────────────────────── --}}
<div class="platforms-strip">
  <div class="plat-label">Available On All Major Platforms</div>
  <div class="platforms-list">
    @foreach([
      'EZWAY.TV', 'XOTV', 'BVC TV', 'NATIONAL BIZ TV', 'EZWAY MUSIC',
      'THE WOMEN\'S CHANNEL', 'BE SPIRE TV', 'XPN TV', 'FAN TV GLOBAL', 'EZWAY MOVIES',
      'KATE LINDER TV', 'POWER TV NETWORK', 'I&C TV'
    ] as $platform)
      <span class="plat-chip">{{ $platform }}</span>
    @endforeach
  </div>
</div>

@endsection

@push('after-scripts')
<script>
  // Scroll reveal
  (function(){
    const els = document.querySelectorAll('[data-reveal]');
    if (!els || els.length === 0) return;
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) {
          setTimeout(() => e.target.classList.add('visible'), i * 60);
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.08 });
    els.forEach(el => io.observe(el));
  })();
</script>
@endpush