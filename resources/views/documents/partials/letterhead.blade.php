{{--
    The top of a document: the mosque's logo at the top right, what the
    document is next to it, the day it was printed at the left.

    $title, and optionally $kicker (a small line above the title) and
    $lines (lines under it; a line given as ['text', 'small'] is smaller).
--}}
@use('App\Support\Brand')
@use('App\Support\Dates')
<header class="letterhead">
    <img src="{{ Brand::logoUrl() }}" alt="شعار {{ app(Brand::class)->name() }}" class="letterhead__logo">
    <div class="letterhead__text">
        @isset($kicker)
            <p class="letterhead__kicker">{{ $kicker }}</p>
        @endisset
        <h1 class="letterhead__title">{{ $title }}</h1>
        @foreach ($lines ?? [] as $line)
            @if (is_array($line))
                @if (filled($line[0] ?? null))
                    <p class="letterhead__line letterhead__line--small">{{ $line[0] }}</p>
                @endif
            @elseif (filled($line))
                <p class="letterhead__line">{{ $line }}</p>
            @endif
        @endforeach
    </div>
    <p class="letterhead__date">{{ Dates::show(Dates::today()) }}</p>
</header>
