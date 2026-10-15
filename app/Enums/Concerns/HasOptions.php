<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums that expose an Arabic label().
 */
trait HasOptions
{
    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string|int, string> value => label, ready for a select input
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
