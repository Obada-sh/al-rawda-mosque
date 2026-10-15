<?php

namespace App\Actions\Circles;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Courses\CircleRequest;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Teacher;
use App\Services\Circles\CircleLogos;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Creates a circle in a course, or updates one. Changing the supervisor or
 * the assistant is a plain replacement: no history is kept for teachers.
 *
 * The logo is optional. A new picture replaces the one the circle had;
 * "remove_logo" takes it away.
 */
final class SaveCircle
{
    public function __construct(private readonly CircleLogos $logos) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation teacher.inactive
     */
    public function handle(Course $course, array $input, ?Circle $circle = null): Circle
    {
        $data = CircleRequest::validateData($input, [
            'course_id' => $course->getKey(),
            'ignore_id' => $circle?->getKey(),
        ]);

        /** @var UploadedFile|null $logo */
        $logo = $data['logo'] ?? null;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);
        unset($data['logo'], $data['remove_logo']);

        $circle ??= new Circle(['course_id' => $course->getKey()]);

        foreach (['supervisor_teacher_id', 'assistant_teacher_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $this->assertAssignable($data[$field], $circle->getAttribute($field));
            }
        }

        $circle->fill($data)->save();

        if ($logo !== null) {
            $this->changeLogo($circle, $this->logos->keep($circle, $logo));
        } elseif ($removeLogo) {
            $this->changeLogo($circle, null);
        }

        return $circle;
    }

    /**
     * A newly chosen teacher must be active; a teacher who was already on
     * the circle may stay even after being deactivated.
     */
    private function assertAssignable(mixed $teacherId, mixed $currentTeacherId): void
    {
        if ($teacherId === null || (int) $teacherId === (int) $currentTeacherId) {
            return;
        }

        if (! Teacher::query()->whereKey($teacherId)->where('is_active', true)->exists()) {
            throw BusinessRuleViolation::because('teacher.inactive', 'الأستاذ المختار غير نشط.');
        }
    }

    private function changeLogo(Circle $circle, ?string $name): void
    {
        $previous = $circle->logo_path;

        $circle->forceFill(['logo_path' => $name])->save();

        if ($previous !== $name) {
            $this->logos->remove($previous);
        }
    }
}
