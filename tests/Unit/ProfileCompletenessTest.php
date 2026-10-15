<?php

namespace Tests\Unit;

use App\Services\Students\ProfileCompleteness;
use PHPUnit\Framework\TestCase;

class ProfileCompletenessTest extends TestCase
{
    /** @return array<string, mixed> */
    private function completeProfile(): array
    {
        return [
            'full_name' => 'طالب',
            'birth_date' => '2012-05-01',
            'phone' => '0999111222',
            'guardian_phone' => '0999333444',
            'address' => 'دمشق',
            'tajweed_level_id' => 1,
            'prior_memorized_pages' => 40,
        ];
    }

    public function test_a_profile_with_every_field_is_complete(): void
    {
        $this->assertTrue((new ProfileCompleteness)->isComplete($this->completeProfile()));
    }

    public function test_zero_memorized_pages_counts_as_filled_in(): void
    {
        $profile = ['prior_memorized_pages' => 0] + $this->completeProfile();

        $this->assertTrue((new ProfileCompleteness)->isComplete($profile));
    }

    public function test_each_missing_field_is_reported(): void
    {
        $completeness = new ProfileCompleteness;

        foreach (array_keys(ProfileCompleteness::REQUIRED_FIELDS) as $field) {
            $profile = [$field => null] + $this->completeProfile();

            $this->assertSame([$field], $completeness->missingFields($profile));
            $this->assertFalse($completeness->isComplete($profile));
        }
    }

    public function test_blank_text_counts_as_missing(): void
    {
        $profile = ['address' => '   '] + $this->completeProfile();

        $this->assertSame(['مكان السكن'], (new ProfileCompleteness)->missingLabels($profile));
    }

    public function test_a_profile_with_only_a_name_misses_everything_else(): void
    {
        $missing = (new ProfileCompleteness)->missingFields(['full_name' => 'طالب']);

        $this->assertSame(array_keys(ProfileCompleteness::REQUIRED_FIELDS), $missing);
    }
}
