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
    @php
      $jsonPath = base_path('Modules/Frontend/Resources/data/distribution.json');
      $data = null;
      if (file_exists($jsonPath)) {
          $raw = file_get_contents($jsonPath);
          $data = json_decode($raw);
      }
    @endphp

    @if(!$data || empty($data->networks))
      <div class="network-card" data-reveal>
        <div class="card-body">No distribution data available.</div>
      </div>
    @else
      @foreach($data->networks as $network)
        <div class="network-card" data-reveal>
          <div class="card-logo-area">
            <div class="card-logo-box">
              <img src="{{ $network->image }}" alt="{{ $network->name }}" onerror="this.style.display='none'">
            </div>
            <div class="card-name">{{ $network->name }}</div>
          </div>
          <div class="card-body">{{ $network->description }}</div>
          @if(isset($network->tag))
            <span class="card-tag">{{ $network->tag }}</span>
          @endif
        </div>
      @endforeach
    @endif

  </div>
</div>

{{-- ── PLATFORMS STRIP ──────────────────────────────── --}}
<div class="platforms-strip">
  <div class="plat-label">Available On All Major Platforms</div>
  <div class="platforms-list">
    @foreach([
      'EZWAY.TV', 'XOTV', 'BVC TV', 'NATIONAL BIZ TV', 'EZWAY MUSIC',
      'BE SPIRE TV', 'XPN TV', 'FAN TV GLOBAL', 'EZWAY MOVIES',
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