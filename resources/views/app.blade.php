<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#171717">

    <link rel="icon" href="/images/brand/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/images/brand/app-icon.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Anton + Oswald draw the logo, Inter runs the interface, Newsreader stands in for Canela. --}}
    <link href="https://fonts.bunny.net/css?family=anton:400|oswald:500,600|inter:400,500,600|newsreader:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-paper text-ink antialiased">
    @inertia
</body>
</html>
