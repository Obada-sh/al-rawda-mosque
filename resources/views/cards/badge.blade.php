@use('App\Filament\Support\Presenters\CardPresenter')
@use('App\Services\Cards\CardDesign')
@use('App\Support\CardFont')
{{--
    One attendance card, exactly as the designer's Photoshop file draws it.

    The drawing is in the pixels of that file (638 × 1016 at 300 ppi) and
    printed at 54 × 86 mm. The fixed design is one picture
    (public/img/card/background.png); over it, at the places of the PSD:

      photo        cut to the scalloped frame (the mask is defined once by
                   the page: #rawda-badge-photo), or a grey figure
      name         13 pt, the green of the design, centred in the white band
      circle       10.8 pt, white, centred in the green band
      QR code      bottom left, 174 × 174, the green of the design
      circle logo  bottom right, fitted in 113 × 159, with the design's
                   drop shadow (#rawda-badge-shadow)

    A teacher's card (its own background, without the green band of the
    circle) has no circle and no circle logo: the photo, the name, the
    course the card is for (smaller, in the room of the band), the mosque's
    logo and the QR code, which then stands in the middle.

    The card's code is printed in its QR code only (data-code names it for
    whoever reads the page). The texts are SVG texts placed on their
    baseline, like Photoshop's. A name too long for its band is made
    smaller by the page's script (data-fit = the room in the band).
--}}
@php
    $photoUrl = CardPresenter::photoUrl($card);
    $logoUrl = $card->isTeacher() ? null : CardPresenter::logoUrl($card);
    $background = $card->isTeacher() ? 'img/card/background-teacher.png' : 'img/card/background.png';
    // Alone at the bottom of a teacher's card, the code stands in the middle.
    $qrX = $card->isTeacher() ? 232 : 107;
@endphp
<svg class="rawda-badge" data-code="{{ $card->code }}" viewBox="0 0 638 1016" width="{{ CardDesign::WIDTH_MM }}mm" height="{{ CardDesign::HEIGHT_MM }}mm"
     xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $card->name }} — {{ $card->isTeacher() ? $card->courseName : $card->circleName }}">
    <image href="{{ asset($background) }}" x="0" y="0" width="638" height="1016" preserveAspectRatio="none" />

    <g mask="url(#rawda-badge-photo)">
        <rect x="170" y="211" width="293" height="290" fill="#f7f7fa" />
        @if ($photoUrl !== null)
            <image class="rawda-badge__photo" href="{{ $photoUrl }}" x="170" y="211" width="293" height="290" preserveAspectRatio="xMidYMid slice" />
        @else
            <g class="rawda-badge__nobody" fill="{{ CardDesign::INK }}" fill-opacity="0.28">
                <circle cx="316.5" cy="328" r="54" />
                <path d="M210 501 C210 428 258 394 316.5 394 C375 394 423 428 423 501 Z" />
            </g>
        @endif
    </g>

    <text class="rawda-badge__name" x="318.07" y="611.25" text-anchor="middle" direction="rtl"
          font-size="54.17" fill="{{ CardDesign::INK }}" data-fit="413">{{ CardFont::withLatinDigits($card->name) }}</text>

    @if ($card->isTeacher())
        @if (filled($card->courseName))
            <text class="rawda-badge__course" x="318.07" y="688" text-anchor="middle" direction="rtl"
                  font-size="36" fill="{{ CardDesign::INK }}" data-fit="440">{{ CardFont::withLatinDigits($card->courseName) }}</text>
        @endif
    @else
        <text class="rawda-badge__circle" x="317.55" y="685.32" text-anchor="middle" direction="rtl"
              font-size="44.91" fill="#ffffff" data-fit="260">{{ CardFont::withLatinDigits($card->circleName) }}</text>
    @endif

    <image class="rawda-badge__qr" href="{{ CardPresenter::qrDataUri($card) }}" x="{{ $qrX }}" y="795" width="174" height="174" />

    @if ($logoUrl !== null)
        <image class="rawda-badge__logo" href="{{ $logoUrl }}" x="441" y="801" width="113" height="159"
               preserveAspectRatio="xMidYMid meet" filter="url(#rawda-badge-shadow)" />
    @endif
</svg>
