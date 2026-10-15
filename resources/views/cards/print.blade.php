@use('App\Enums\CardPaper')
@use('App\Services\Cards\CardDesign')
@use('App\Support\CardFont')

@php
    $isSheet = $paper === CardPaper::Sheet;
    $count = $cards->count();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
    <link rel="icon" href="{{ \App\Support\Brand::faviconUrl() }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('fonts/ibm-plex-sans-arabic/font.css') }}">
    <link rel="stylesheet" href="{{ asset('css/print-toolbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/attendance-cards.css') }}">
    <style>
        {!! CardFont::faceCss() !!}

        @page {
            size: {{ $isSheet ? 'A4' : CardDesign::WIDTH_MM.'mm '.CardDesign::HEIGHT_MM.'mm' }};
            margin: 0;
        }

        :root {
            --card-w: {{ CardDesign::WIDTH_MM }}mm;
            --card-h: {{ CardDesign::HEIGHT_MM }}mm;
            --columns: {{ CardDesign::SHEET_COLUMNS }};
            --rows: {{ CardDesign::SHEET_ROWS }};
            --gap: {{ CardDesign::SHEET_GAP_MM }}mm;
        }

        .rawda-badge text {
            font-family: {!! CardFont::stack() !!};
        }

        /* Numbers are drawn 0-9, as everywhere in the system (see CardFont::withLatinDigits). */
        .rawda-badge .rawda-badge__digits {
            font-family: {!! CardFont::FALLBACK !!};
        }
    </style>
</head>
<body>
    {{-- Defined once, used by every card: the frame the photo is cut to, and the shadow of the circle logo. --}}
    <svg class="rawda-badge-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <mask id="rawda-badge-photo" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse" x="170" y="211" width="293" height="290">
                <image href="{{ asset('img/card/photo-mask.png') }}" x="170" y="211" width="293" height="290" preserveAspectRatio="none" />
            </mask>
            <filter id="rawda-badge-shadow" x="-10%" y="-10%" width="130%" height="130%" color-interpolation-filters="sRGB">
                <feDropShadow dx="1.36" dy="1.46" stdDeviation="0" flood-color="#171613" flood-opacity="0.84" />
            </filter>
        </defs>
    </svg>

    <header class="toolbar">
        <div class="toolbar-title">
            <a href="{{ $backUrl }}" class="toolbar-back">→ رجوع</a>
            <a href="{{ $homeUrl }}" title="الرئيسية"><img src="{{ \App\Support\Brand::logoUrl() }}" alt="الرئيسية" class="toolbar-logo"></a>
            <div>
                <strong>{{ $heading }}</strong>
                <span>— {{ $count }} بطاقة</span>
            </div>
        </div>

        <nav class="toolbar-choices" aria-label="طريقة الطباعة">
            @foreach (CardPaper::cases() as $choice)
                <a href="{{ $papers[$choice->value] }}" @class(['is-current' => $choice === $paper]) @if ($choice === $paper) aria-current="true" @endif>{{ $choice->label() }}</a>
            @endforeach
        </nav>

        <div class="toolbar-actions">
            @if ($count > 0)
                <button type="button" onclick="window.print()">طباعة</button>
            @endif
        </div>
    </header>

    @if ($count === 0)
        <p class="empty">{{ $emptyText }}</p>
    @elseif ($isSheet)
        <p class="advice">في نافذة الطباعة: الورق A4، الهوامش «بلا»، والمقياس 100%. كل ورقة فيها {{ CardDesign::cardsPerSheet() }} بطاقات بقياس {{ CardDesign::WIDTH_MM }} × {{ CardDesign::HEIGHT_MM }} مم.</p>
    @else
        <p class="advice">في نافذة الطباعة: اختر قياس ورق البطاقة ({{ CardDesign::WIDTH_MM }} × {{ CardDesign::HEIGHT_MM }} مم، بالطول) وهوامش «بلا»، والمقياس 100%.</p>
    @endif

    @if ($isSheet)
        @foreach ($cards->chunk(CardDesign::cardsPerSheet()) as $page)
            <section class="sheet">
                @foreach ($page as $card)
                    <div class="slot">@include('cards.badge', ['card' => $card])</div>
                @endforeach
            </section>
        @endforeach
    @else
        <main class="singles">
            @foreach ($cards as $card)
                <section class="single">@include('cards.badge', ['card' => $card])</section>
            @endforeach
        </main>
    @endif

    <script>
        (function () {
            var SVG = 'http://www.w3.org/2000/svg';

            // A name longer than its band is made smaller until it fits, on
            // its own baseline; it is never cut.
            function fitTexts() {
                document.querySelectorAll('.rawda-badge text[data-fit]').forEach(function (text) {
                    var room = parseFloat(text.dataset.fit);
                    var size = parseFloat(text.dataset.size || text.getAttribute('font-size'));
                    text.dataset.size = size;
                    text.setAttribute('font-size', size);
                    var width = text.getComputedTextLength();
                    if (width > room) {
                        text.setAttribute('font-size', (size * room / width).toFixed(2));
                    }
                });
            }

            // A logo usually comes with see-through margins. The design fills
            // the logo's box with what is drawn, so the margins are left out:
            // the logo is shown through a window around its visible part.
            function trimLogo(logo) {
                return new Promise(function (done) {
                    var picture = new Image();
                    picture.onerror = done;
                    picture.onload = function () {
                        try {
                            frameLogo(logo, picture);
                        } finally {
                            done();
                        }
                    };
                    picture.src = logo.getAttribute('href');
                });
            }

            function frameLogo(logo, picture) {
                var w = picture.naturalWidth;
                var h = picture.naturalHeight;
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var context = canvas.getContext('2d');
                context.drawImage(picture, 0, 0);
                var alpha = context.getImageData(0, 0, w, h).data;
                var left = w, top = h, right = -1, bottom = -1;

                for (var y = 0; y < h; y++) {
                    for (var x = 0; x < w; x++) {
                        if (alpha[(y * w + x) * 4 + 3] > 8) {
                            if (x < left) left = x;
                            if (x > right) right = x;
                            if (y < top) top = y;
                            if (y > bottom) bottom = y;
                        }
                    }
                }

                if (right < left) {
                    return;
                }

                var frame = document.createElementNS(SVG, 'svg');
                ['x', 'y', 'width', 'height'].forEach(function (name) {
                    frame.setAttribute(name, logo.getAttribute(name));
                });
                frame.setAttribute('viewBox', [left, top, right - left + 1, bottom - top + 1].join(' '));
                frame.setAttribute('preserveAspectRatio', 'xMidYMid meet');

                var inner = document.createElementNS(SVG, 'image');
                inner.setAttribute('href', logo.getAttribute('href'));
                inner.setAttribute('width', w);
                inner.setAttribute('height', h);
                frame.appendChild(inner);

                // The shadow is drawn in the card's measures, not the logo's.
                var shadow = document.createElementNS(SVG, 'g');
                shadow.setAttribute('class', 'rawda-badge__logo');
                shadow.setAttribute('filter', logo.getAttribute('filter'));
                shadow.appendChild(frame);
                logo.replaceWith(shadow);
            }

            var logos = Array.prototype.slice.call(document.querySelectorAll('image.rawda-badge__logo'));
            var fonts = document.fonts ? document.fonts.ready : Promise.resolve();

            Promise.all([Promise.all(logos.map(trimLogo)), fonts]).then(function () {
                fitTexts();
                document.body.dataset.ready = 'yes';
            });
            window.addEventListener('beforeprint', fitTexts);
        })();
    </script>
</body>
</html>
