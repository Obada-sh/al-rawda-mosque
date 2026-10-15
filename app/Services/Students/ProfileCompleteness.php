<?php

namespace App\Services\Students;

/**
 * Decides whether a student profile is complete.
 *
 * Only the name is required to register a student; the profile counts as
 * complete once every field below has a value. Zero is a value: a student
 * who has memorized nothing yet has prior_memorized_pages = 0.
 */
final class ProfileCompleteness
{
    /** @var array<string, string> field => Arabic label */
    public const REQUIRED_FIELDS = [
        'birth_date' => 'تاريخ الميلاد',
        'phone' => 'رقم الطالب',
        'guardian_phone' => 'رقم ولي الأمر',
        'address' => 'مكان السكن',
        'tajweed_level_id' => 'مستوى التجويد',
        'prior_memorized_pages' => 'المحفوظ السابق',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<string> the missing field names
     */
    public function missingFields(array $attributes): array
    {
        $missing = [];

        foreach (array_keys(self::REQUIRED_FIELDS) as $field) {
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
    public function missingLabels(array $attributes): array
    {
        return array_map(
            fn (string $field): string => self::REQUIRED_FIELDS[$field],
            $this->missingFields($attributes),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function isComplete(array $attributes): bool
    {
        return $this->missingFields($attributes) === [];
    }
}
