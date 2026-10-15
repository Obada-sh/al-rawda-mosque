<?php

namespace App\Actions\Settings;

use App\Contracts\SettingsStore;
use App\Http\Requests\Settings\SettingsRequest;
use App\Support\SettingKey;
use Illuminate\Validation\ValidationException;

final class SaveSettings
{
    public function __construct(private readonly SettingsStore $settings) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): void
    {
        $data = SettingsRequest::validateData($input);

        $this->settings->set(SettingKey::MOSQUE_NAME, $data['mosque_name'] ?? null);
        $this->settings->set(
            SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS,
            (string) $data['incomplete_profiles_reminder_days'],
        );
    }
}
