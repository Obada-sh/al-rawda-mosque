<?php

namespace App\Actions\Documents;

use App\Contracts\QrCodeRenderer;
use App\Http\Requests\Documents\CircleSheetRequest;
use App\Models\Circle;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Statistics\ReportFilter;
use App\Services\Statistics\StudentStanding;
use App\Services\Statistics\StudentStandings;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the sheets of the circles of a course: for each circle, its
 * current students with their phone, their guardian's phone and the pages
 * they memorized in the course; and, for the sheet of codes, the QR of
 * each one's attendance card.
 *
 * The pages come from the same figures as the statistics, so the sheet
 * and the reports always agree. Students who left the course are not on
 * it; a student who moved to the circle is, with everything they did in
 * the course.
 */
final class PrepareCircleSheets
{
    public function __construct(
        private readonly StudentStandings $standings,
        private readonly QrCodeRenderer $qrCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $input  circle_id (one circle) or course_id (all its circles)
     * @param  bool  $withCodes  whether each student's QR is drawn
     * @return Collection<int, CircleSheet> by circle name; without circle_id, the circles that have students
     *
     * @throws ValidationException
     */
    public function handle(array $input, bool $withCodes = false): Collection
    {
        $data = CircleSheetRequest::validateData($input);
        $oneCircle = isset($data['circle_id']);

        $circles = Circle::query()
            ->with(['course', 'supervisor', 'assistant'])
            ->when(
                $oneCircle,
                fn ($query) => $query->whereKey($data['circle_id']),
                fn ($query) => $query->where('course_id', $data['course_id']),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($circles->isEmpty()) {
            return collect();
        }

        $standings = $this->standings
            ->for(new ReportFilter(
                courseId: $circles->first()->course_id,
                circleId: $oneCircle ? (int) $data['circle_id'] : null,
            ))
            ->reject(fn (StudentStanding $standing): bool => $standing->hasLeft());

        $students = Student::query()->whereKey($standings->pluck('studentId')->unique()->all())->get()->keyBy('id');
        $codes = Enrollment::query()->whereKey($standings->pluck('enrollmentId')->all())->pluck('card_code', 'id');
        $byCircle = $standings->groupBy('circleId');

        return $circles
            ->map(fn (Circle $circle): CircleSheet => new CircleSheet(
                circle: $circle,
                lines: $byCircle->get($circle->getKey(), collect())
                    ->map(fn (StudentStanding $standing): CircleSheetLine => $this->line($standing, $students->get($standing->studentId), (string) $codes->get($standing->enrollmentId), $withCodes))
                    ->values()
                    ->all(),
            ))
            ->reject(fn (CircleSheet $sheet): bool => ! $oneCircle && $sheet->isEmpty())
            ->values();
    }

    private function line(StudentStanding $standing, Student $student, string $cardCode, bool $withCodes): CircleSheetLine
    {
        return new CircleSheetLine(
            name: $standing->studentName,
            phone: $student->phone,
            guardianPhone: $student->guardian_phone,
            newPages: $standing->newPages,
            cardCode: $cardCode,
            qrSvg: $withCodes ? $this->qrCodes->svg($cardCode) : null,
        );
    }
}
