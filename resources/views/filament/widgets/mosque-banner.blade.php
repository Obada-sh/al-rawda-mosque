<x-filament-widgets::widget class="rawda-banner-widget">
    <div class="rawda-banner">
        <img src="{{ \App\Support\Brand::logoUrl() }}" alt="شعار {{ $name }}" class="rawda-banner__logo">
        <div class="rawda-banner__text">
            <p class="rawda-banner__name">{{ $name }}</p>
            <p class="rawda-banner__date">{{ $today }}</p>
        </div>
    </div>
</x-filament-widgets::widget>
