<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0b6b3a">

        <title inertia>{{ $page['props']['meta']['title'] ?? config('app.name', 'Laravel') }}</title>

        {{-- Social previews must be in the server HTML: crawlers (WhatsApp, Facebook) don't run JS. --}}
        @php($meta = $page['props']['meta'] ?? null)
        @if ($meta)
            <meta name="description" content="{{ $meta['description'] ?? '' }}">
            <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:title" content="{{ $meta['title'] ?? config('app.name') }}">
            <meta property="og:description" content="{{ $meta['description'] ?? '' }}">
            <meta property="og:url" content="{{ $meta['url'] ?? url()->current() }}">
            @isset($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
                @isset($meta['image_width'])<meta property="og:image:width" content="{{ $meta['image_width'] }}">@endisset
                @isset($meta['image_height'])<meta property="og:image:height" content="{{ $meta['image_height'] }}">@endisset
                <meta property="og:image:type" content="image/png">
            @endisset
            <meta name="twitter:card" content="{{ isset($meta['image']) ? 'summary_large_image' : 'summary' }}">
            <meta name="twitter:title" content="{{ $meta['title'] ?? config('app.name') }}">
            <meta name="twitter:description" content="{{ $meta['description'] ?? '' }}">
            @isset($meta['image'])<meta name="twitter:image" content="{{ $meta['image'] }}">@endisset
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="preload" href="/fonts/HindSiliguri-Regular.ttf" as="font" type="font/ttf" crossorigin>

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
