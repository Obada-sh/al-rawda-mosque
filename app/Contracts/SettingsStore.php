<?php

namespace App\Contracts;

interface SettingsStore
{
    public function get(string $key, ?string $default = null): ?string;

    public function set(string $key, ?string $value): void;
}
