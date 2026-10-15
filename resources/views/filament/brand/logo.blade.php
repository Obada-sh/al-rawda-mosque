{{--
    The brand of the panel: the mosque's logo and its name. Filament puts
    it at the top of the menu and above the sign-in form.
--}}
<span class="rawda-brand">
    <img src="{{ \App\Support\Brand::logoUrl() }}" alt="" class="rawda-brand__logo">
    <span class="rawda-brand__name">{{ $name }}</span>
</span>
