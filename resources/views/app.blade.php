<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Aura') }}</title>

    {{-- Favicon: PNG first (HostGator often serves empty favicon.ico); SVG light/dark --}}
    <link rel="icon" href="/favicon-32.png?v=2" type="image/png" sizes="32x32">
    <link rel="icon" href="/favicon-light.svg?v=2" type="image/svg+xml" media="(prefers-color-scheme: light)">
    <link rel="icon" href="/favicon-dark.svg?v=2" type="image/svg+xml" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
    <meta name="theme-color" content="#FCFDFC" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#151716" media="(prefers-color-scheme: dark)">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
