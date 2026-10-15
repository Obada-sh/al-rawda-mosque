<?php

namespace App\Actions\Cards;

use App\Contracts\QrCodeRenderer;
use App\Http\Requests\Cards\PrintCardsRequest;
use App\Models\Enrollment;
use App\Services\Cards\CardDesign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the attendance cards of a course, ready to print.
 *
 * A card belongs to an enrollment (a student in a course), so a student
 * gets a new card for every course. Students who left the course get none.
 */
final class PrepareAttendanceCards
{
    public function __construct(private readonly QrCodeRenderer $qrCodes) {}

    /**
     * @param  array<string, mixed>  $input
     * @return Collection<int, AttendanceCard> ordered by circle, then by student name
     *
     * @throws ValidationException
     */
    public function handle(array $input): Collection
    {
        $data = PrintCardsRequest::validateData($input);

        return Enrollment::query()
            ->with(['student', 'circle'])
            ->where('course_id', $data['course_id'])
            ->whereNull('left_on')
            ->when($data['circle_id'] ?? null, fn (Builder $query, mixed $circleId) => $query->where('circle_id', $circleId))
            ->when($data['enrollment_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereKey($ids))
            ->get()
            ->sortBy(fn (Enrollment $enrollment): array => [$enrollment->circle->name, $enrollment->student->full_name])
            ->values()
            ->map(fn (Enrollment $enrollment): AttendanceCard => new AttendanceCard(
                name: $enrollment->student->full_name,
                circleName: $enrollment->circle->name,
                code: $enrollment->card_code,
                qrSvg: $this->qrCodes->svg($enrollment->card_code, CardDesign::INK, quietZone: false),
                studentId: $enrollment->student_id,
                photo: $enrollment->student->photo_path,
                circleId: $enrollment->circle_id,
                circleLogo: $enrollment->circle->logo_path,
            ));
    }
}
