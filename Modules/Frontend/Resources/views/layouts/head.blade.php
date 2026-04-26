@php
    $seo = Modules\SEO\Models\Seo::first();
    // Route-specific Open Graph image overrides
    if(request()->is('topchannel-list')){
        // Use absolute URL so social validators can reach the image
        $ogImage = rtrim(config('app.url'), '/') . '/images/topchannel-og.png';
    } else {
        $ogImage = $entertainment->seo_image ?? $seo->seo_image ?? asset('img/logo/favicon.png');
    }
    $ogTitle = (isset($entertainment) && !empty($entertainment->meta_title)) ? $entertainment->meta_title : ($seo->meta_title ?? config('app.name'));
    $ogDescription = (isset($entertainment) && !empty($entertainment->short_description)) ? $entertainment->short_description : ($seo->short_description ?? '');
    $ogUrl = (isset($entertainment) && !empty($entertainment->canonical_url)) ? $entertainment->canonical_url : ($seo->canonical_url ?? url()->current());
@endphp


<!-- Default Meta Information -->
    <meta name="seo_image" id="dynamicSeoImage" content="{{ $ogImage }}">
    <meta name="title" id="dynamicMetaTitle" content="{{ $ogTitle }}">
    <meta name="description" id="dynamicMetaDescription" content="{{ $ogDescription }}">
    <title id="pageTitle">{{ $ogTitle }}</title>
    <meta name="google-site-verification" id="dynamicGoogleVerification" content="{{ $entertainment->google_site_verification ?? $seo->google_site_verification ?? '' }}">
    <meta name="keywords" id="dynamicMetaKeywords" content="{{ isset($entertainment->meta_keywords) ? (is_array($meta_keywords = json_decode($entertainment->meta_keywords)) ? implode(',', $meta_keywords) : $entertainment->meta_keywords) : (isset($seo->meta_keywords) ? (is_array($meta_keywords = json_decode($seo->meta_keywords)) ? implode(',', $meta_keywords) : $seo->meta_keywords) : '') }}">
    <link rel="canonical" id="dynamicCanonicalUrl" href="{{ $ogUrl }}">

    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
