<?php

namespace App\Filament\Support;

use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Opens a page of the system in a new window of the browser, from the
 * end of an action (after a choice made in its window): the print pages
 * of the cards, the files and the sheets.
 */
final class NewWindow
{
    public static function open(Component $livewire, string $url): void
    {
        $livewire->js('window.open('.Js::from($url).", '_blank')");
    }
}
