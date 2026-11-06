<?php

namespace App\Actions\English\Levels;

use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishLevel;
use App\Services\English\EnglishCardBackgrounds;

/**
 * Deletes a level that no course and no student has, with its card design.
 */
final class DeleteEnglishLevel
{
    public function __construct(private readonly EnglishCardBackgrounds $backgrounds) {}

    /**
     * @throws BusinessRuleViolation english_level.in_use
     */
    public function handle(EnglishLevel $level): void
    {
        if ($level->courses()->exists() || $level->students()->exists()) {
            throw BusinessRuleViolation::because(
                'english_level.in_use',
                'لا يمكن حذف مستوى له دورات أو طلاب.',
            );
        }

        $level->delete();

        $this->backgrounds->remove($level->card_background_path);
    }
}
