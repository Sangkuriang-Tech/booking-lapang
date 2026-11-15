<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title inertia>{{ config('app.name', 'ArenaKita') }}</title>
        <link
            rel="preload"
            href="/fonts/barlow-condensed-700-latin.woff2"
            as="font"
            type="font/woff2"
            crossorigin="anonymous"
        />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-canvas text-ink font-sans antialiased">
        @inertia
    </body>
</html>
