{{--
    The switch between the two sections, at the top of every page
    (see SectionSwitcher).

    @var list<array{label: string, url: string, current: bool}> $sections
--}}
<nav class="rawda-sections" aria-label="أقسام النظام">
    @foreach ($sections as $section)
        <a href="{{ $section['url'] }}" class="rawda-sections__item{{ $section['current'] ? ' is-current' : '' }}"{!! $section['current'] ? ' aria-current="page"' : '' !!}>{{ $section['label'] }}</a>
    @endforeach
</nav>
