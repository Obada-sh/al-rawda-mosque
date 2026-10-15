{{--
    A document printed on A4 paper under the mosque's logo (a student's file,
    the sheets of a circle). The page gives: $heading, $homeUrl, $backUrl,
    and the section "content" (one <article class="document"> per document).
--}}
@use('App\Support\Brand')
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
    <link rel="icon" href="{{ Brand::faviconUrl() }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('fonts/ibm-plex-sans-arabic/font.css') }}">
    <link rel="stylesheet" href="{{ asset('css/print-toolbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/documents.css') }}">
</head>
<body>
    <header class="toolbar">
        <div class="toolbar-title">
            <a href="{{ $backUrl }}" class="toolbar-back">→ رجوع</a>
            <a href="{{ $homeUrl }}" title="الرئيسية"><img src="{{ Brand::logoUrl() }}" alt="الرئيسية" class="toolbar-logo"></a>
            <div>
                <strong>{{ $heading }}</strong>
                @hasSection('count')
                    <span>— @yield('count')</span>
                @endif
            </div>
        </div>

        <div class="toolbar-actions">
            <button type="button" onclick="window.print()">طباعة</button>
        </div>
    </header>

    <p class="advice">في نافذة الطباعة: الورق A4 والمقياس 100%. يمكن أيضاً اختيار «حفظ بصيغة PDF».</p>

    @yield('content')
</body>
</html>
