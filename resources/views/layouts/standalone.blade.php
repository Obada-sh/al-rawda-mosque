<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ \App\Support\Brand::DEFAULT_NAME }}</title>
    <link rel="icon" href="{{ \App\Support\Brand::faviconUrl() }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('fonts/ibm-plex-sans-arabic/font.css') }}">
    <link rel="stylesheet" href="{{ asset('css/standalone.css') }}">
</head>
<body>
    <main class="card">
        <img src="{{ \App\Support\Brand::logoUrl() }}" alt="" class="logo">
        @yield('content')
    </main>
</body>
</html>
