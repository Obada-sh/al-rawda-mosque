<?php

namespace App\Actions\English\Halls;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\English\EnglishHallRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishTeacher;
use Illuminate\Validation\ValidationException;

/**
 * Creates a hall in an English course, or updates one. Changing the
 * teacher or the assistant is a plain replacement.
 */
final class SaveEnglishHall
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation english_teacher.inactive
     */
    public function handle(EnglishCourse $course, array $input, ?EnglishHall $hall = null): EnglishHall
    {
        $data = EnglishHallRequest::validateData($input, [
            'english_course_id' => $course->getKey(),
            'ignore_id' => $hall?->getKey(),
        ]);

        $hall ??= new EnglishHall(['english_course_id' => $course->getKey()]);

        foreach (['teacher_id', 'assistant_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->assertAssignable($data[$field], $hall->getAttribute($field));
            }
        }

        $hall->fill($data)->save();

        return $hall;
    }

    /**
     * A newly chosen teacher must be active; one who was already on the
     * hall may stay even after being deactivated.
     */
    private function assertAssignable(mixed $teacherId, mixed $currentTeacherId): void
    {
        if ($teacherId === null || (int) $teacherId === (int) $currentTeacherId) {
            return;
        }

        if (! EnglishTeacher::query()->whereKey($teacherId)->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('english_teacher.inactive', 'الأستاذ المختار غير نشط.');
        }
    }
}
