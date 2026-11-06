<?php

namespace App\Services\Students;

use App\Enums\Section;

/**
 * Decides whether a student profile is complete, in a section.
 *
 * Only the name is required to register a student; the profile counts as
 * complete once every field of the section's list has a value. The two
 * lists differ only in the level: the mosque asks for the tajweed level
 * and what the student memorized before, the English section for the
 * English level. Zero is a value: a student who has memorized nothing yet
 * has prior_memorized_pages = 0.
 */
final class ProfileCompleteness
{
    /** @var array<string, string> field => Arabic label: the mosque's list */
    public const REQUIRED_FIELDS = [
        'birth_date' => 'تاريخ الميلاد',
        'phone' => 'رقم الطالب',
        'guardian_phone' => 'رقم ولي الأمر',
        'address' => 'مكان السكن',
        'tajweed_level_id' => 'مستوى التجويد',
        'prior_memorized_pages' => 'المحفوظ السابق',
    ];

    /** @var array<string, string> field => Arabic label: the English section's list */
    public const ENGLISH_REQUIRED_FIELDS = [
        'birth_date' => 'تاريخ الميلاد',
        'phone' => 'رقم الطالب',
        'guardian_phone' => 'رقم ولي الأمر',
        'address' => 'مكان السكن',
        'english_level_id' => 'مستوى اللغة الانجليزية',
    ];

    /**
     * @return array<string, string> field => Arabic label
     */
    public static function requiredFields(Section $section = Section::Mosque): array
    {
        return match ($section) {
            Section::Mosque => self::REQUIRED_FIELDS,
            Section::English => self::ENGLISH_REQUIRED_FIELDS,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string> the missing field names
     */
    public function missingFields(array $attributes, Section $section = Section::Mosque): array
    {
        $missing = [];

        foreach (array_keys(self::requiredFields($section)) as $field) {
            $value = $attributes[$field] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string> Arabic labels of the missing fields
     */
    public function missingLabels(array $attributes, Section $section = Section::Mosque): array
    {
        $labels = self::requiredFields($section);

        return array_map(
            fn (string $field): string => $labels[$field],
            $this->missingFields($attributes, $section),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function isComplete(array $attributes, Section $section = Section::Mosque): bool
    {
        return $this->missingFields($attributes, $section) === [];
    }
}
