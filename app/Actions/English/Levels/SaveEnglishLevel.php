<?php

namespace App\Actions\English\Levels;

use App\Http\Requests\English\EnglishLevelRequest;
use App\Models\English\EnglishLevel;
use App\Services\English\EnglishCardBackgrounds;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates a level of the English section, or updates one. The card design
 * is optional: a new picture replaces the one the level had;
 * "remove_card_background" takes it away (the cards are then drawn plain).
 */
final class SaveEnglishLevel
{
    public function __construct(private readonly EnglishCardBackgrounds $backgrounds) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?EnglishLevel $level = null): EnglishLevel
    {
        $data = EnglishLevelRequest::validateData($input, ['ignore_id' => $level?->getKey()]);

        /** @var UploadedFile|null $background */
        $background = $data['card_background'] ?? null;
        $remove = (bool) ($data['remove_card_background'] ?? false);
        unset($data['card_background'], $data['remove_card_background']);

        $level ??= new EnglishLevel;
        $level->fill($data)->save();

        if ($background !== null) {
            $this->changeBackground($level, $this->backgrounds->keep($level, $background));
        } elseif ($remove) {
            $this->changeBackground($level, null);
        }

        return $level;
    }

    /**
     * The name is stored first and the old file removed last: whatever
     * goes wrong in between, the level never points at a missing file.
     */
    private function changeBackground(EnglishLevel $level, ?string $name): void
    {
        $previous = $level->card_background_path;

        $level->forceFill(['card_background_path' => $name])->save();

        if ($previous !== $name) {
            $this->backgrounds->remove($previous);
        }
    }
}
