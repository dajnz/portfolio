<!DOCTYPE html>
@php use App\Features\Shared\Infra\Middleware\CspNonce; @endphp
@php
    // Server-side SEO meta (seo.md:47) — the Blade layout renders the
    // controller-provided SeoProps during SSR; useSeoUpdater takes over on
    // client-side navigations. The meta tags carry `data-inertia` (empty
    // value) so Inertia's head manager treats them as managed and replaces
    // them with identical client-rendered elements after hydration (no
    // duplicates, per @inertiajs/core Renderer semantics).
    $seo = $page['props']['seo'] ?? null;
    $seoTitle = $seo['title'] ?? '';
    $seoDescription = $seo['description'] ?? '';
    $ogTitle = $seo['og_title'] ?? $seoTitle;
    $ogDescription = $seo['og_description'] ?? $seoDescription;
    $ogImage = $seo['og_image'] ?? '/images/og-default.png';
    $ogImageWidth = $seo['og_image_width'] ?? 1200;
    $ogImageHeight = $seo['og_image_height'] ?? 630;
    $ogUrl = $seo['og_url'] ?? null;
    $ogType = $seo['og_type'] ?? 'website';
    $canonicalUrl = $seo['canonical_url'] ?? null;
    $robots = $seo['robots'] ?? null;
    $jsonLd = $seo['json_ld'] ?? [];
@endphp
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="{{ ($page['props']['theme'] ?? 'light') === 'dark' ? 'dark' : '' }}"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ $seoTitle !== '' ? $seoTitle : config('app.name', 'Portfolio') }}</title>
    <script nonce="{{ app(CspNonce::class)->getValue() }}">
        (function () {
            var c = document.cookie.match(/(?:^|;\s*)theme=(light|dark)/);
            var t = c ? c[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (t === 'dark') document.documentElement.classList.add('dark');
            document.cookie = 'theme=' + t + ';path=/;max-age=31536000;SameSite=Lax';
        })();
    </script>
    @if ($seoDescription !== '')
        <meta name="description" content="{{ $seoDescription }}" data-inertia="">
    @endif
    <meta property="og:title" content="{{ $ogTitle }}" data-inertia="">
    <meta property="og:description" content="{{ $ogDescription }}" data-inertia="">
    <meta property="og:image" content="{{ $ogImage }}" data-inertia="">
    <meta property="og:image:width" content="{{ $ogImageWidth }}" data-inertia="">
    <meta property="og:image:height" content="{{ $ogImageHeight }}" data-inertia="">
    <meta property="og:type" content="{{ $ogType }}" data-inertia="">
    <meta name="twitter:card" content="summary_large_image" data-inertia="">
    <meta name="twitter:title" content="{{ $ogTitle }}" data-inertia="">
    <meta name="twitter:description" content="{{ $ogDescription }}" data-inertia="">
    <meta name="twitter:image" content="{{ $ogImage }}" data-inertia="">
    @if ($ogUrl)
        <meta property="og:url" content="{{ $ogUrl }}" data-inertia="">
    @endif
    @if ($canonicalUrl)
        <link rel="canonical" href="{{ $canonicalUrl }}" data-inertia="">
    @endif
    @if ($robots)
        <meta name="robots" content="{{ $robots }}" data-inertia="">
    @endif
    @foreach ($jsonLd as $jsonLdBlock)
        <script type="application/ld+json" data-seo-jsonld nonce="{{ app(CspNonce::class)->getValue() }}">{!! json_encode($jsonLdBlock, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endforeach
    @inertiaHead
    @vite(['resources/js/app.ts', 'resources/css/app.css'])
</head>
<body>
    @inertia
</body>
</html>
