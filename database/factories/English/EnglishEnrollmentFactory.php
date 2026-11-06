<?php

namespace Database\Factories\English;

use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An English-only student in a course from its first day, in a hall of the
 * course, paying the course's price.
 *
 * @extends Factory<EnglishEnrollment>
 */
class EnglishEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory()->english(),
            'english_course_id' => EnglishCourse::factory(),
            'english_hall_id' => fn (array $attributes) => EnglishHall::factory()->create([
                'english_course_id' => $attributes['english_course_id'],
            ])->getKey(),
            // The same unique() pool as the mosque's enrollments: no code is shared.
            'card_code' => fake()->unique()->numerify('##########'),
            'enrolled_on' => fn (array $attributes) => EnglishCourse::query()
                ->findOrFail($attributes['english_course_id'])
                ->start_date
                ->toDateString(),
            'price' => null,
            'price_note' => null,
        ];
    }

    public function withPrice(int $price, string $note = 'خصم'): static
    {
        return $this->state(['price' => $price, 'price_note' => $note]);
    }
}
