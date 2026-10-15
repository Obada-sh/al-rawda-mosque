<x-filament-widgets::widget class="rawda-letterhead-widget">
    <header class="rawda-letterhead">
        <img src="{{ \App\Support\Brand::logoUrl() }}" alt="شعار {{ $name }}" class="rawda-letterhead__logo">
        <div class="rawda-letterhead__text">
            <p class="rawda-letterhead__mosque">{{ $name }}</p>
            <h2 class="rawda-letterhead__title">{{ $heading }}</h2>
            <p class="rawda-letterhead__scope">{{ implode(' · ', $scope) }}</p>
        </div>
        <p class="rawda-letterhead__date">{{ $today }}</p>
    </header>
</x-filament-widgets::widget>
