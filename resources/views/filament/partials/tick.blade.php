{{--
    The tick of an open page: every five minutes it asks the system to do
    what is due at a time of day (see TickController). Nothing is shown.
--}}
<script data-tick="{{ route('filament.admin.tick') }}">
    (() => {
        const url = document.currentScript.dataset.tick;

        setInterval(() => fetch(url, { credentials: 'same-origin', cache: 'no-store' }).catch(() => {}), 5 * 60 * 1000);
    })();
</script>
