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
use Illuminate\Validation\ValidationException;

/**
 * Builds the sheets of the circles of a course: for each circle, its
 * current students with their phones and their records: the pages they
 * memorized (how many, and which), their juz exams and their attendance;
 * and, for the sheet of codes, the QR of each one's attendance card.
 *
 * The records are those of the course of the circle, or, when asked
 * (SheetOptions::ALL_COURSES), everything the students did in every
 * course since they joined the system: the sheet a teacher is given for
 * the new students of a new course.
 *
 * The records are built by SheetRecords, from the same source as the
 * statistics. Students who left the course are not on it; a student who
 * moved to the circle is, with everything they did in the course.
 */
final class PrepareCircleSheets
{
    public function __construct(
        private readonly StudentStandings $standings,
        private readonly SheetRecords $records,
        private readonly QrCodeRenderer $qrCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $input  circle_id (one circle) or course_id (all its circles), and the options of SheetOptions
     * @param  bool  $withCodes  whether each student's QR is drawn
     * @return PrintedCircleSheets by circle name; without circle_id, only the circles that have students
     *
     * @throws ValidationException
     */
    public function handle(array $input, bool $withCodes = false): PrintedCircleSheets
    {
        $data = CircleSheetRequest::validateData($input);
        $options = SheetOptions::from($data);
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
            return new PrintedCircleSheets($options, collect());
        }

        $standings = $this->standings
            ->for(new ReportFilter(
                courseId: $circles->first()->course_id,
                circleId: $oneCircle ? (int) $data['circle_id'] : null,
            ))
            ->reject(fn (StudentStanding $standing): bool => $standing->hasLeft());

        $students = Student::query()->whereKey($standings->pluck('studentId')->unique()->all())->get()->keyBy('id');
        $codes = Enrollment::query()->whereKey($standings->pluck('enrollmentId')->all())->pluck('card_code', 'id');
        $records = $options->allCourses ? $this->records->sinceJoining($students) : $this->records->inCourse($standings);
        $byCircle = $standings->groupBy('circleId');

        $sheets = $circles
            ->map(fn (Circle $circle): CircleSheet => new CircleSheet(
                circle: $circle,
                lines: $byCircle->get($circle->getKey(), collect())
                    ->map(function (StudentStanding $standing) use ($students, $records, $codes, $options, $withCodes): SheetLine {
                        $code = (string) $codes->get($standing->enrollmentId);

                        return SheetLine::of(
                            $students->get($standing->studentId),
                            $records[$options->allCourses ? $standing->studentId : $standing->enrollmentId],
                            cardCode: $code,
                            qrSvg: $withCodes ? $this->qrCodes->svg($code) : null,
                        );
                    })
                    ->values()
                    ->all(),
            ))
            ->reject(fn (CircleSheet $sheet): bool => ! $oneCircle && $sheet->isEmpty())
            ->values();

        return new PrintedCircleSheets($options, $sheets);
    }
}
