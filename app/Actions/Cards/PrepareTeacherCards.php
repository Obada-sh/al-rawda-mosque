<?php

namespace App\Actions\Cards;

use App\Contracts\QrCodeRenderer;
use App\Http\Requests\Cards\PrintTeacherCardsRequest;
use App\Models\Course;
use App\Models\Teacher;
use App\Services\Cards\CardDesign;
use App\Services\Teachers\TeacherCards;
use App\Services\Teachers\TeacherCircles;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the attendance cards of a course's teachers, ready to print.
 *
 * A teacher's card belongs to the course (its code counts there only),
 * so a teacher of two courses has two cards. It shows the teacher's photo
 * and name, the course's name, the mosque's logo and the code: no circle
 * and no circle logo.
 * The cards of the teachers who never had one in the course are given
 * here, once (Services\Teachers\TeacherCards).
 */
final class PrepareTeacherCards
{
    public function __construct(
        private readonly QrCodeRenderer $qrCodes,
        private readonly TeacherCircles $circles,
        private readonly TeacherCards $cards,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, AttendanceCard> ordered by name
     *
     * @throws ValidationException
     */
    public function handle(array $input): Collection
    {
        $data = PrintTeacherCardsRequest::validateData($input);
        $course = Course::query()->findOrFail($data['course_id']);
        $chosen = array_map('intval', $data['teacher_ids'] ?? []);

        return $this->circles->teachersOf($course)
            ->filter(fn (Teacher $teacher): bool => $chosen === [] || in_array((int) $teacher->getKey(), $chosen, true))
            ->values()
            ->map(fn (Teacher $teacher): AttendanceCard => $this->card($teacher, $course));
    }

    private function card(Teacher $teacher, Course $course): AttendanceCard
    {
        $code = $this->cards->of($teacher, $course)->card_code;

        return new AttendanceCard(
            name: $teacher->full_name,
            circleName: '',
            code: $code,
            qrSvg: $this->qrCodes->svg($code, CardDesign::INK, quietZone: false),
            teacherId: $teacher->getKey(),
            photo: $teacher->photo_path,
            courseName: $course->name,
        );
    }
}
