<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $seo = \Modules\SEO\Models\Seo::first();
        $meta = isset($entertainment) ? (object) $entertainment : (object) [];
        $siteName = config('app.name', 'eZWay TV');
        $title = $meta->meta_title ?? optional($seo)->meta_title ?? $siteName;
        $description = $meta->short_description ?? optional($seo)->short_description ?? optional($seo)->meta_description ?? 'Watch live TV, on-demand channels, movies, and videos on eZWay TV.';
        $keywords = $meta->meta_keywords ?? optional($seo)->meta_keywords ?? null;
        $canonical = $meta->canonical_url ?? url()->current();
        $ogImage = $meta->seo_image ?? optional($seo)->seo_image ?? asset('img/logo/logo.png');

        if (! filter_var($ogImage, FILTER_VALIDATE_URL)) {
            $seoPath = 'storage/uploads/seo/' . basename((string) $ogImage);
            $ogImage = file_exists(public_path($seoPath))
                ? asset($seoPath)
                : asset(ltrim($ogImage, '/'));
        }
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(!empty($meta->google_site_verification ?? optional($seo)->google_site_verification ?? null))
        <meta name="google-site-verification" content="{{ $meta->google_site_verification ?? optional($seo)->google_site_verification }}">
    @endif
    <title>{{ $title }}</title>
    <meta name="title" content="{{ $title }}">
    <meta name="description" content="{{ $description }}">
    @if(!empty($keywords))
        <meta name="keywords" content="{{ $keywords }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <script src="https://imasdk.googleapis.com/js/sdkloader/ima3.js"></script>
    @vite('resources/react/main.tsx')
</head>
<body>
    <div id="react-modernization-root"></div>
</body>
</html>
