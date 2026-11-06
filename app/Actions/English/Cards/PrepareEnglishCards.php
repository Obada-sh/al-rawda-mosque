<?php

namespace App\Actions\English\Cards;

use App\Contracts\QrCodeRenderer;
use App\Http\Requests\English\EnglishPrintCardsRequest;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the attendance cards of an English course, ready to print, in the
 * design of the course's level. A card belongs to an enrollment (a student
 * in a course); students who left the course get none.
 */
final class PrepareEnglishCards
{
    /** The ink of the QR code: dark, for any design behind its white square. */
    public const INK = '#111827';

    public function __construct(private readonly QrCodeRenderer $qrCodes) {}

    /**
     * @param  array<string, mixed>  $input  see EnglishPrintCardsRequest
     * @return Collection<int, EnglishCard> ordered by hall, then by student name
     *
     * @throws ValidationException
     */
    public function handle(array $input): Collection
    {
        $data = EnglishPrintCardsRequest::validateData($input);

        $course = EnglishCourse::query()->with('level')->findOrFail($data['english_course_id']);

        return EnglishEnrollment::query()
            ->with(['student', 'hall'])
            ->where('english_course_id', $course->getKey())
            ->whereNull('left_on')
            ->when($data['english_hall_id'] ?? null, fn (Builder $query, mixed $hallId) => $query->where('english_hall_id', $hallId))
            ->when($data['english_enrollment_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereKey($ids))
            ->get()
            ->sortBy(fn (EnglishEnrollment $enrollment): array => [$enrollment->hall->name, $enrollment->student->full_name])
            ->values()
            ->map(fn (EnglishEnrollment $enrollment): EnglishCard => new EnglishCard(
                name: $enrollment->student->full_name,
                hallName: $enrollment->hall->name,
                levelName: $course->level->name,
                courseName: $course->name,
                code: $enrollment->card_code,
                qrSvg: $this->qrCodes->svg($enrollment->card_code, self::INK, quietZone: false),
                studentId: $enrollment->student_id,
                levelId: $course->english_level_id,
                photo: $enrollment->student->photo_path,
                background: $course->level->card_background_path,
            ));
    }
}
