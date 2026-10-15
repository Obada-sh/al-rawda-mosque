<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An enrollment that started with the course, in a circle of that course.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'circle_id' => fn (array $attributes) => Circle::factory()->create([
                'course_id' => $attributes['course_id'],
            ])->getKey(),
            'card_code' => fake()->unique()->numerify('##########'),
            'enrolled_on' => fn (array $attributes) => Course::query()
                ->findOrFail($attributes['course_id'])
                ->start_date
                ->toDateString(),
        ];
    }
}
