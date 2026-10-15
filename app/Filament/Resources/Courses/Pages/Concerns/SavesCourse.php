<?php

namespace App\Filament\Resources\Courses\Pages\Concerns;

use App\Actions\Courses\SaveCourse;
use App\Filament\Support\Attempt;
use App\Models\Course;

/**
 * Saving the course form, shared by the create and the edit page.
 */
trait SavesCourse
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveCourse(array $data, ?Course $course = null): Course
    {
        return Attempt::form(
            fn (): Course => app(SaveCourse::class)->handle($data, $course),
            $this->form,
            $this->scheduleRowField(...),
        );
    }

    /**
     * The request numbers the timetable rows 0, 1, 2...; the form identifies
     * each row by a generated key. A message about "schedules.1.end_time"
     * must land on the second row of the form.
     */
    private function scheduleRowField(string $field): string
    {
        $rowKeys = array_keys($this->data['schedules'] ?? []);

        return (string) preg_replace_callback(
            '/^schedules\.(\d+)\./',
            fn (array $matches): string => 'schedules.'.($rowKeys[(int) $matches[1]] ?? $matches[1]).'.',
            $field,
        );
    }
}
