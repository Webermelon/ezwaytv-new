@php
    $seo = Modules\SEO\Models\Seo::first();
    $siteName = config('app.name', 'eZWay TV');
    $homeShareImage = 'https://ezwayott.sfo3.digitaloceanspaces.com/livetv/image/caa4d6ec_3f9c_4f51_8e9c_95153c5d2b98_(1)_6a16d2697b16e_6a21ca461f0e2.jpg';
    // Route-specific Open Graph image overrides
    if(request()->is('topchannel-list')){
        // Use absolute URL so social validators can reach the image
        $ogImage = rtrim(config('app.url'), '/') . '/images/topchannel-og.png';
    } else {
        $ogImage = $entertainment->seo_image ?? $seo->seo_image ?? $homeShareImage;
    }
    if (empty($ogImage)) {
        $ogImage = $homeShareImage;
    }
    if (! filter_var($ogImage, FILTER_VALIDATE_URL)) {
        $seoPath = 'storage/uploads/seo/' . basename((string) $ogImage);
        $ogImage = file_exists(public_path($seoPath))
            ? asset($seoPath)
            : asset(ltrim($ogImage, '/'));
    }
    $ogImageHost = parse_url((string) $ogImage, PHP_URL_HOST);
    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
    $ogImagePath = parse_url((string) $ogImage, PHP_URL_PATH) ?: '';
    if ($ogImageHost === $appHost && ! file_exists(public_path(ltrim($ogImagePath, '/')))) {
        $ogImage = $homeShareImage;
    }
    $ogImagePath = parse_url((string) $ogImage, PHP_URL_PATH) ?: '';
    $ogImageExtension = strtolower(pathinfo($ogImagePath, PATHINFO_EXTENSION));
    $ogImageType = match ($ogImageExtension) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/jpeg',
    };
    $ogTitle = (isset($entertainment) && !empty($entertainment->meta_title)) ? $entertainment->meta_title : ($seo->meta_title ?? $siteName);
    $ogDescription = (isset($entertainment) && !empty($entertainment->short_description)) ? $entertainment->short_description : ($seo->short_description ?? '');
    $ogUrl = (isset($entertainment) && !empty($entertainment->canonical_url)) ? $entertainment->canonical_url : ($seo->canonical_url ?? url()->current());
    $ogType = request()->is('video-details/*') ? 'video.other' : 'website';
@endphp


<!-- Default Meta Information -->
    <meta name="seo_image" id="dynamicSeoImage" content="{{ $ogImage }}">
    <meta name="title" id="dynamicMetaTitle" content="{{ $ogTitle }}">
    <meta name="description" id="dynamicMetaDescription" content="{{ $ogDescription }}">
    <title id="pageTitle">{{ $ogTitle }}</title>
    <meta name="google-site-verification" id="dynamicGoogleVerification" content="{{ $entertainment->google_site_verification ?? $seo->google_site_verification ?? 'fKAs1MD3Q8uM4dMJBlWoIvevhysIO9B4vtJaAkxKYMA' }}">
    <meta name="keywords" id="dynamicMetaKeywords" content="{{ isset($entertainment->meta_keywords) ? (is_array($meta_keywords = json_decode($entertainment->meta_keywords)) ? implode(',', $meta_keywords) : $entertainment->meta_keywords) : (isset($seo->meta_keywords) ? (is_array($meta_keywords = json_decode($seo->meta_keywords)) ? implode(',', $meta_keywords) : $seo->meta_keywords) : '') }}">
    <link rel="canonical" id="dynamicCanonicalUrl" href="{{ $ogUrl }}">

    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:url" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="{{ $ogImageType }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogTitle }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta itemprop="image" content="{{ $ogImage }}">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $ogUrl }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogTitle }}">
