@use('App\Filament\English\Support\EnglishPresenter')
@use('App\Filament\Support\Presenters\CardPresenter')
@use('App\Filament\Support\Presenters\StudentPresenter')
@use('App\Services\Cards\CardDesign')
@use('App\Support\CardFont')
{{--
    One attendance card of the English section, 54 × 86 mm upright, drawn
    in the pixels of a 638 × 1016 picture (300 ppi), like the mosque's card.

    The whole face is the design of the course's level: the picture the
    admin uploaded for it, edges and all. A level without a design gets a
    plain card: white, with a blue frame. Over it:

      photo   a circle in the upper half (#rawda-english-photo), with a
              white ring, or a grey figure
      panel   a white band in the middle, so the texts read on any design:
              the name, the hall and the level, the course
      QR code at the bottom, in the middle, on a white square inside the frame

    Texts too long for the band are made smaller by the page's script
    (data-fit = the room in the band).
--}}
@php
    $photoUrl = StudentPresenter::photoUrlOf($card->studentId, $card->photo);
    $backgroundUrl = EnglishPresenter::cardBackgroundUrlOf($card->levelId, $card->background);
    $ink = '#111827';
    $blue = '#1d4ed8';
@endphp
<svg class="rawda-badge rawda-badge--english" data-code="{{ $card->code }}" viewBox="0 0 638 1016" width="{{ CardDesign::WIDTH_MM }}mm" height="{{ CardDesign::HEIGHT_MM }}mm"
     xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $card->name }} — {{ $card->hallName }} — {{ $card->levelName }}">
    @if ($backgroundUrl !== null)
        <image class="rawda-badge__design" href="{{ $backgroundUrl }}" x="0" y="0" width="638" height="1016" preserveAspectRatio="xMidYMid slice" />
    @else
        <rect x="0" y="0" width="638" height="1016" fill="#ffffff" />
        <rect x="18" y="18" width="602" height="980" rx="36" fill="none" stroke="{{ $blue }}" stroke-width="14" />
        <text x="319" y="130" text-anchor="middle" font-size="50" fill="{{ $blue }}" direction="ltr">English Course</text>
    @endif

    <circle cx="319" cy="360" r="162" fill="#ffffff" />
    <g clip-path="url(#rawda-english-photo)">
        <rect x="169" y="210" width="300" height="300" fill="#f3f4f6" />
        @if ($photoUrl !== null)
            <image class="rawda-badge__photo" href="{{ $photoUrl }}" x="169" y="210" width="300" height="300" preserveAspectRatio="xMidYMid slice" />
        @else
            <g class="rawda-badge__nobody" fill="{{ $ink }}" fill-opacity="0.22">
                <circle cx="319" cy="330" r="58" />
                <path d="M205 510 C205 436 256 398 319 398 C382 398 433 436 433 510 Z" />
            </g>
        @endif
    </g>
    <circle cx="319" cy="360" r="156" fill="none" stroke="{{ $blue }}" stroke-width="5" />

    <rect x="56" y="552" width="526" height="200" rx="28" fill="#ffffff" fill-opacity="0.94" />
    <text class="rawda-badge__name" x="319" y="626" text-anchor="middle" direction="rtl"
          font-size="54" fill="{{ $ink }}" data-fit="490">{{ CardFont::withLatinDigits($card->name) }}</text>
    <text class="rawda-badge__hall" x="319" y="690" text-anchor="middle" direction="rtl"
          font-size="40" fill="{{ $blue }}" data-fit="490">{{ CardFont::withLatinDigits($card->hallName.' · '.$card->levelName) }}</text>
    <text class="rawda-badge__course" x="319" y="734" text-anchor="middle" direction="rtl"
          font-size="28" fill="#4b5563" data-fit="490">{{ CardFont::withLatinDigits($card->courseName) }}</text>

    <rect x="219" y="770" width="200" height="200" rx="20" fill="#ffffff" />
    <image class="rawda-badge__qr" href="{{ CardPresenter::svgDataUri($card->qrSvg) }}" x="234" y="785" width="170" height="170" />
</svg>
