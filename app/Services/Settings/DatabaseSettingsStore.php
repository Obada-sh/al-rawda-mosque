<?php

namespace App\Services\Settings;

use App\Contracts\SettingsStore;
use App\Models\Setting;

final class DatabaseSettingsStore implements SettingsStore
{
    /** @var array<string, string|null> values already read in this request */
    private array $loaded = [];

    public function get(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, $this->loaded)) {
            $this->loaded[$key] = Setting::query()->where('key', $key)->value('value');
        }

        return $this->loaded[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->loaded[$key] = $value;
    }
}
