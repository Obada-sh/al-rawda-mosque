<?php

namespace App\Filament\Support\Fields;

/**
 * Text typed in a search box, made safe to use inside a SQL LIKE pattern.
 */
final class Like
{
    public static function escape(string $text): string
    {
        return addcslashes(trim($text), '\\%_');
    }
}
