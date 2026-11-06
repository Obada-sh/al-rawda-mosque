<?php

namespace Database\Seeders;

use App\Actions\English\Attendance\RecordEnglishManualAttendance;
use App\Actions\English\Courses\SaveEnglishCourse;
use App\Actions\English\Enrollments\EnrollEnglishStudent;
use App\Actions\English\Enrollments\RegisterEnglishStudent;
use App\Actions\English\Finance\SaveEnglishDepartment;
use App\Actions\English\Finance\SaveEnglishExpense;
use App\Actions\English\Halls\SaveEnglishHall;
use App\Actions\English\Payments\RecordEnglishPayment;
use App\Actions\English\Teachers\SaveEnglishTeacher;
use App\Actions\English\Tests\SaveEnglishTest;
use App\Actions\English\Tests\SaveEnglishTestResults;
use App\Enums\CourseStatus;
use App\Enums\English\TestKind;
use App\Enums\ExpenseKind;
use App\Enums\Weekday;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishLevel;
use App\Models\Student;
use App\Services\English\EnglishBalances;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * The English section of the demo: its teachers, a finished course and a
 * running one with their halls, students of the English section only and
 * two of the mosque's, weeks of attendance, payments (in full, in part,
 * none), an exam and a recitation with their marks, and months of the
 * section's own expenses.
 *
 * Everything goes through the same Actions as the panel. It is called by
 * DemoSeeder, after the mosque, with the mosque's students.
 */
class EnglishDemoSeeder extends Seeder
{
    private const TEACHERS = ['Ms. Rana Haddad', 'Mr. Sami Khoury', 'Ms. Lina Saleh'];

    private const STUDENTS = [
        'جود سامر الحلبي', 'ليان ماهر العلي', 'كرم وسيم الشامي', 'تيم باسل الخطيب',
        'ماسة رامي الحسن', 'آدم نزار الأحمد', 'سيلين غسان يوسف', 'يزن فادي الصباغ',
    ];

    private CarbonImmutable $today;

    /**
     * @param  list<Student>  $mosqueStudents  two of them are enrolled in English too
     */
    public function run(array $mosqueStudents = []): void
    {
        $this->today = Dates::today();

        $teachers = array_map(
            fn (string $name, int $index): int => app(SaveEnglishTeacher::class)->handle([
                'full_name' => $name,
                'phone' => '0944'.str_pad((string) (300400 + $index * 11), 6, '0', STR_PAD_LEFT),
            ])->getKey(),
            self::TEACHERS,
            array_keys(self::TEACHERS),
        );

        // A summer A1 course that is over...
        $summer = $this->course('A1 الصيفي', 'A1', 125000, $this->today->subDays(140), $this->today->subDays(80), [Weekday::Sunday, Weekday::Tuesday]);
        $summerHall = app(SaveEnglishHall::class)->handle($summer, ['name' => 'القاعة 1', 'teacher_id' => $teachers[0]]);

        // ...and an A2 course running now, in two halls.
        $autumn = $this->course('A2 الخريف', 'A2', 150000, $this->today->subDays(35), null, [Weekday::Saturday, Weekday::Monday, Weekday::Wednesday]);
        $halls = [
            app(SaveEnglishHall::class)->handle($autumn, ['name' => 'القاعة الزرقاء', 'teacher_id' => $teachers[0], 'assistant_id' => $teachers[2]]),
            app(SaveEnglishHall::class)->handle($autumn, ['name' => 'القاعة الخضراء', 'teacher_id' => $teachers[1]]),
        ];

        $enrollments = [];

        foreach (self::STUDENTS as $index => $name) {
            $enrollments[] = app(RegisterEnglishStudent::class)->handle([
                'full_name' => $name,
                'birth_date' => $this->today->subYears(10 + $index % 6)->subDays(37 * $index)->toDateString(),
                'phone' => $index % 3 === 0 ? null : '0955'.str_pad((string) (210000 + $index * 13), 6, '0', STR_PAD_LEFT),
                'guardian_phone' => '0966'.str_pad((string) (410000 + $index * 17), 6, '0', STR_PAD_LEFT),
                'address' => ['حي الروضة', 'حي المزة', 'حي الميدان'][$index % 3],
                'english_level_id' => $autumn->english_level_id,
                'english_course_id' => $autumn->getKey(),
                'english_hall_id' => $halls[$index % 2]->getKey(),
                'enrolled_on' => Dates::day($autumn->start_date),
                // Two brothers pay less.
                'price' => in_array($index, [2, 3], true) ? 120000 : null,
                'price_note' => in_array($index, [2, 3], true) ? 'خصم أخوة' : null,
            ]);
        }

        // Two of the mosque's students study English too.
        foreach (array_slice($mosqueStudents, 0, 2) as $index => $student) {
            $enrollments[] = app(EnrollEnglishStudent::class)->handle([
                'student_id' => $student->getKey(),
                'english_course_id' => $autumn->getKey(),
                'english_hall_id' => $halls[$index % 2]->getKey(),
                'enrolled_on' => Dates::parse($autumn->start_date)->addDays(7)->toDateString(),
            ]);
        }

        // The summer course had a few of them, who paid it all.
        foreach (array_slice($enrollments, 0, 3) as $enrollment) {
            $summerEnrollment = app(EnrollEnglishStudent::class)->handle([
                'student_id' => $enrollment->student_id,
                'english_course_id' => $summer->getKey(),
                'english_hall_id' => $summerHall->getKey(),
                'enrolled_on' => Dates::day($summer->start_date),
            ]);
            $this->pay($summerEnrollment, $summer->price, Dates::parse($summer->start_date)->addDays(3));
        }

        $this->finish($summer);
        $this->attend($autumn, $enrollments);
        $this->payments($enrollments);
        $this->tests($autumn);
        $this->spend();
    }

    /**
     * @param  list<Weekday>  $days
     */
    private function course(string $name, string $level, int $price, CarbonImmutable $start, ?CarbonImmutable $end, array $days): EnglishCourse
    {
        return app(SaveEnglishCourse::class)->handle([
            'name' => $name,
            'english_level_id' => EnglishLevel::query()->where('name', $level)->value('id'),
            'price' => $price,
            'start_date' => $start->toDateString(),
            'end_date' => $end?->toDateString(),
            'schedules' => array_map(
                fn (Weekday $day): array => ['weekday' => $day->value, 'start_time' => '17:00', 'end_time' => '19:00'],
                $days,
            ),
        ]);
    }

    private function finish(EnglishCourse $course): void
    {
        $course->load('schedules');

        app(SaveEnglishCourse::class)->handle([
            'name' => $course->name,
            'english_level_id' => $course->english_level_id,
            'price' => $course->price,
            'start_date' => $course->start_date->toDateString(),
            'end_date' => $course->end_date?->toDateString(),
            'status' => CourseStatus::Finished->value,
            'schedules' => $course->schedules->map(fn ($schedule): array => [
                'weekday' => $schedule->weekday->value,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
            ])->all(),
        ], $course);
    }

    /**
     * Every class day of the course so far, most students come.
     *
     * @param  list<EnglishEnrollment>  $enrollments
     */
    private function attend(EnglishCourse $course, array $enrollments): void
    {
        $course->load('schedules');

        for ($day = Dates::parse($course->start_date); $day->lessThanOrEqualTo($this->today); $day = $day->addDay()) {
            if (! $course->isScheduledOn($day)) {
                continue;
            }

            foreach ($enrollments as $index => $enrollment) {
                if ($day->toDateString() < $enrollment->enrolled_on->toDateString() || ($index + $day->day) % 7 === 0) {
                    continue;
                }

                app(RecordEnglishManualAttendance::class)->handle([
                    'english_enrollment_id' => $enrollment->getKey(),
                    'attended_on' => $day->toDateString(),
                ]);
            }
        }
    }

    /**
     * Some paid it all, some in parts, a few nothing yet.
     *
     * @param  list<EnglishEnrollment>  $enrollments
     */
    private function payments(array $enrollments): void
    {
        foreach ($enrollments as $index => $enrollment) {
            $due = app(EnglishBalances::class)->of($enrollment)->due;

            match ($index % 4) {
                0 => $this->pay($enrollment, $due, $this->today->subDays(30)),
                1 => $this->pay($enrollment, intdiv($due, 2), $this->today->subDays(20), 'القسط الأول'),
                2 => [
                    $this->pay($enrollment, 50000, $this->today->subDays(28), 'القسط الأول'),
                    $this->pay($enrollment, 25000, $this->today->subDays(5), 'القسط الثاني'),
                ],
                default => null,
            };
        }
    }

    private function pay(EnglishEnrollment $enrollment, int $amount, CarbonImmutable $day, ?string $notes = null): void
    {
        app(RecordEnglishPayment::class)->handle([
            'english_enrollment_id' => $enrollment->getKey(),
            'amount' => $amount,
            'paid_on' => ($day->greaterThan($this->today) ? $this->today : $day)->toDateString(),
            'notes' => $notes,
        ]);
    }

    /**
     * An exam and a recitation, with the marks of the students of their day.
     */
    private function tests(EnglishCourse $course): void
    {
        $results = app(SaveEnglishTestResults::class);

        foreach ([
            ['امتحان الوحدة الأولى', TestKind::Exam, 12, 50],
            ['تسميع كلمات الوحدة الثانية', TestKind::Recitation, 4, 20],
        ] as [$title, $kind, $daysAgo, $max]) {
            $test = app(SaveEnglishTest::class)->handle($course, [
                'title' => $title,
                'kind' => $kind->value,
                'held_on' => $this->today->subDays($daysAgo)->toDateString(),
                'max_mark' => $max,
            ]);

            $marks = [];

            foreach ($results->candidates($test) as $index => $enrollment) {
                $marks[$enrollment->getKey()] = $index % 6 === 5
                    ? ['is_absent' => true]
                    : ['mark' => (string) max(0, $max - ($index * 3) % (int) ($max / 2))];
            }

            $results->handle($test, ['marks' => $marks]);
        }
    }

    /**
     * The rent of the halls every month, and books once.
     */
    private function spend(): void
    {
        $rent = app(SaveEnglishDepartment::class)->handle(['name' => 'إيجار القاعات']);
        $books = app(SaveEnglishDepartment::class)->handle(['name' => 'كتب وقرطاسية']);

        for ($monthsAgo = 4; $monthsAgo >= 0; $monthsAgo--) {
            $day = $this->today->startOfMonth()->subMonths($monthsAgo)->addDays(2);

            app(SaveEnglishExpense::class)->handle([
                'kind' => ExpenseKind::LumpSum->value,
                'english_department_id' => $rent->getKey(),
                'item_name' => 'إيجار القاعتين لشهر',
                'amount' => 200000,
                'expense_date' => ($day->greaterThan($this->today) ? $this->today : $day)->toDateString(),
            ]);
        }

        app(SaveEnglishExpense::class)->handle([
            'kind' => ExpenseKind::Itemized->value,
            'english_department_id' => $books->getKey(),
            'item_name' => 'كتاب المستوى A2',
            'quantity' => 10,
            'unit_price' => 30000,
            'expense_date' => $this->today->subDays(33)->toDateString(),
        ]);
    }
}
