<?php

namespace Database\Seeders;

use App\Actions\Activities\SaveActivity;
use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Attendance\RecordManualTeacherAttendance;
use App\Actions\Auth\SaveAdminUser;
use App\Actions\Circles\SaveCircle;
use App\Actions\Courses\SaveCourse;
use App\Actions\Enrollments\EnrollStudent;
use App\Actions\Enrollments\TransferStudent;
use App\Actions\Enrollments\UpdateEnrollmentDates;
use App\Actions\Exams\RecordJuzExamResult;
use App\Actions\Finance\SaveExpense;
use App\Actions\Market\SaveMarket;
use App\Actions\Market\SaveMarketItem;
use App\Actions\Market\SellToCard;
use App\Actions\Points\AwardManualPoints;
use App\Actions\Recitations\RecordRecitation;
use App\Actions\Settings\SaveSettings;
use App\Actions\Students\ArchiveStudent;
use App\Actions\Students\SaveStudent;
use App\Actions\Teachers\SaveTeacher;
use App\Enums\ActivityAudience;
use App\Enums\CourseStatus;
use App\Enums\ExamGrade;
use App\Enums\ExpenseKind;
use App\Enums\Weekday;
use App\Models\Circle;
use App\Models\Course;
use App\Models\CourseSchedule;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\JuzExam;
use App\Models\Student;
use App\Models\TajweedLevel;
use App\Models\Teacher;
use App\Services\Demo\DemoAccount;
use App\Support\Dates;
use App\Support\QuranPages;
use App\Support\SettingKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Invented data to try the system with, or to train someone on it: a
 * finished course and a running one, circles, teachers (with their own
 * attendance), students with weeks of attendance, recitations, exams and
 * points, a few activities, and months of expenses.
 *
 * Everything is recorded through the same Actions the panel uses, so the
 * demo obeys every rule of the system. It is the same demo every time.
 *
 * It is seeded by "php artisan rawda:demo", which only ever works on a
 * database whose name ends with "_demo": never on the real data.
 */
class DemoSeeder extends Seeder
{
    public const MOSQUE_NAME = 'جامع الروضة (نسخة تجريبية)';

    private const STUDENTS = 36;

    private const TEACHERS = [
        'الشيخ ياسر الحموي', 'الشيخ عمر الخطيب', 'الأستاذ خالد الشامي', 'الأستاذ معاذ الحلبي',
        'الأستاذ أنس النجار', 'الأستاذ بلال العلي', 'الأستاذ زيد المصري', 'الأستاذ حمزة السيد',
    ];

    private const CIRCLES = ['حلقة أبي بكر الصديق', 'حلقة عمر بن الخطاب', 'حلقة عثمان بن عفان', 'حلقة علي بن أبي طالب'];

    private const FIRST_NAMES = [
        'أحمد', 'محمد', 'عبد الله', 'عمر', 'علي', 'خالد', 'يوسف', 'إبراهيم', 'حمزة', 'أنس',
        'معاذ', 'بلال', 'زيد', 'سعد', 'مصعب', 'عمار', 'ياسر', 'طارق', 'حسن', 'حسين',
        'سليمان', 'داود', 'يحيى', 'زكريا', 'إلياس', 'إدريس', 'نوح', 'صالح', 'أيوب', 'يونس',
        'هارون', 'عثمان', 'سلمان', 'أسامة', 'جعفر', 'مالك', 'ثابت', 'رياض', 'وليد', 'ماهر',
    ];

    private const FATHER_NAMES = ['محمد', 'أحمد', 'محمود', 'عبد الرحمن', 'مصطفى', 'حسن', 'إبراهيم', 'يوسف', 'خليل', 'سعيد'];

    private const FAMILY_NAMES = [
        'الحموي', 'الشامي', 'الحلبي', 'الخطيب', 'النجار', 'العلي', 'المصري', 'السيد',
        'القاسم', 'الحسن', 'البيطار', 'الصباغ', 'الدباغ', 'العطار', 'الحداد',
    ];

    private const DISTRICTS = ['حي الروضة', 'حي الميدان', 'حي المزة', 'حي المهاجرين', 'حي ركن الدين', 'حي القابون'];

    /** What the mosque buys, by department: [item, quantity, unit price]. */
    private const PURCHASES = [
        'مشتريات' => [['مصاحف', 20, 15000], ['دفاتر متابعة', 40, 2500], ['أقلام', 50, 800], ['بطاقات حضور', 100, 350], ['حبر طابعة', 2, 85000]],
        'إطعام' => [['تمر', 6, 22000], ['عصير', 48, 3500], ['معجنات', 80, 2000], ['ماء شرب', 30, 4000], ['وجبة تكريم', 45, 18000]],
        'لوجستي' => [['صيانة مكيف', 1, 250000], ['كراسي', 12, 65000], ['إضاءة', 8, 12000], ['أجرة نقل', 3, 40000], ['سجاد', 2, 320000]],
    ];

    /** name, price in points, what is left (null: no limit), description */
    private const MARKET_ITEMS = [
        ['قلم حبر', 15, null, 'قلم أزرق للدرس'],
        ['دفتر ملاحظات', 30, null, null],
        ['ميدالية', 25, 20, 'ميدالية الحلقة'],
        ['علبة ألوان', 60, null, '12 لوناً'],
        ['مصحف جيب', 120, 6, 'مصحف صغير بغلاف جلدي'],
        ['كرة قدم', 200, 2, null],
    ];

    private CarbonImmutable $today;

    /** @var array<int, int> student id => the next page the student will recite */
    private array $nextPage = [];

    /** @var array<int, array{int, int}> circle id => its supervisor and its assistant */
    private array $teachersOf = [];

    /**
     * @param  int  $students  how many students the demo has (the tests use a handful)
     */
    public function run(int $students = self::STUDENTS): void
    {
        // The same demo every time.
        mt_srand(1448);

        $this->today = Dates::today();

        $this->call([TajweedLevelSeeder::class, DepartmentSeeder::class]);

        app(SaveAdminUser::class)->handle([
            'name' => DemoAccount::NAME,
            'username' => DemoAccount::USERNAME,
            'password' => DemoAccount::PASSWORD,
        ]);

        app(SaveSettings::class)->handle([
            'mosque_name' => self::MOSQUE_NAME,
            'incomplete_profiles_reminder_days' => SettingKey::DEFAULT_REMINDER_DAYS,
        ]);

        $teachers = array_map(
            fn (string $name, int $index): Teacher => app(SaveTeacher::class)->handle([
                'full_name' => $name,
                'phone' => '0933'.str_pad((string) (100200 + $index * 7), 6, '0', STR_PAD_LEFT),
            ]),
            self::TEACHERS,
            array_keys(self::TEACHERS),
        );

        $everyone = $this->students(max(4, $students));

        // A summer course that is over, with the first students only...
        $summer = $this->course('الدورة الصيفية', $this->today->subDays(150), $this->today->subDays(80), [Weekday::Saturday, Weekday::Monday, Weekday::Wednesday], '10:00', '12:00');
        $summerCircles = $this->circles($summer, array_slice(self::CIRCLES, 0, 2), $teachers);
        $this->studyTogether($summer, $summerCircles, array_slice($everyone, 0, (int) ceil(count($everyone) * 0.4)));
        $this->finish($summer);

        // ...and the autumn course everybody is in now.
        $autumn = $this->course('دورة الخريف', $this->today->subDays(42), null, [Weekday::Saturday, Weekday::Sunday, Weekday::Tuesday, Weekday::Thursday], '16:30', '18:30');
        $autumnCircles = $this->circles($autumn, self::CIRCLES, $teachers);
        $enrollments = $this->studyTogether($autumn, $autumnCircles, $everyone);

        $this->moveAndLeave($enrollments, $autumnCircles);
        $this->holdActivities($enrollments, $autumnCircles);
        $this->examine($teachers);
        $this->spend();
        $this->holdMarket($autumn, $enrollments);

        // One student who never came back is kept in the archive.
        app(ArchiveStudent::class)->handle(app(SaveStudent::class)->handle(['full_name' => 'طالب مؤرشف قديم']));
    }

    /**
     * @return list<Student> most with a complete profile, some with a name only
     */
    private function students(int $count): array
    {
        $levels = TajweedLevel::query()->orderBy('sort_order')->pluck('id')->all();
        $students = [];

        for ($index = 0; $index < $count; $index++) {
            $name = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)]
                .' '.self::FATHER_NAMES[($index * 3) % count(self::FATHER_NAMES)]
                .' '.self::FAMILY_NAMES[($index * 7) % count(self::FAMILY_NAMES)];

            // Every sixth student was registered in a hurry: a name, and nothing else yet.
            $profile = $index % 6 === 5 ? [] : [
                'birth_date' => $this->today->subYears(mt_rand(8, 16))->subDays(mt_rand(0, 364))->toDateString(),
                'phone' => $index % 4 === 0 ? null : '09'.mt_rand(30000000, 99999999),
                'guardian_phone' => '09'.mt_rand(30000000, 99999999),
                'address' => self::DISTRICTS[$index % count(self::DISTRICTS)],
                'tajweed_level_id' => $levels[$index % max(1, count($levels))] ?? null,
                'prior_memorized_pages' => [0, 0, 20, 23, 44, 60][$index % 6],
            ];

            $student = app(SaveStudent::class)->handle(['full_name' => $name] + $profile);

            // The young ones begin with the last juz, the others with the first.
            $this->nextPage[$student->getKey()] = $index % 2 === 0 ? QuranPages::JUZ_START_PAGES[30] : QuranPages::FIRST;

            $students[] = $student;
        }

        return $students;
    }

    /**
     * @param  list<Weekday>  $days
     */
    private function course(string $name, CarbonImmutable $start, ?CarbonImmutable $end, array $days, string $from, string $until): Course
    {
        return app(SaveCourse::class)->handle([
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end?->toDateString(),
            'points_per_page' => 2,
            'points_per_attendance' => 1,
            'schedules' => array_map(
                fn (Weekday $day): array => ['weekday' => $day->value, 'start_time' => $from, 'end_time' => $until],
                $days,
            ),
        ]);
    }

    private function finish(Course $course): void
    {
        $course->load('schedules');

        app(SaveCourse::class)->handle([
            'name' => $course->name,
            'start_date' => $course->start_date->toDateString(),
            'end_date' => $course->end_date?->toDateString(),
            'points_per_page' => $course->points_per_page,
            'points_per_attendance' => $course->points_per_attendance,
            'status' => CourseStatus::Finished->value,
            'schedules' => $course->schedules
                ->map(fn (CourseSchedule $schedule): array => [
                    'weekday' => $schedule->weekday->value,
                    'start_time' => substr((string) $schedule->start_time, 0, 5),
                    'end_time' => substr((string) $schedule->end_time, 0, 5),
                ])
                ->all(),
        ], $course);
    }

    /**
     * @param  list<string>  $names
     * @param  list<Teacher>  $teachers
     * @return list<Circle> each with a supervisor and an assistant of its own
     */
    private function circles(Course $course, array $names, array $teachers): array
    {
        $circles = [];

        foreach ($names as $index => $name) {
            $supervisor = $teachers[($index * 2) % count($teachers)]->getKey();
            $assistant = $teachers[($index * 2 + 1) % count($teachers)]->getKey();

            $circle = app(SaveCircle::class)->handle($course, [
                'name' => $name,
                'supervisor_teacher_id' => $supervisor,
                'assistant_teacher_id' => $assistant,
            ]);

            $this->teachersOf[$circle->getKey()] = [$supervisor, $assistant];
            $circles[] = $circle;
        }

        return $circles;
    }

    /**
     * Enrolls the students in the circles of a course, then lives the
     * course day by day: who came, what each one recited, who won points.
     *
     * @param  list<Circle>  $circles
     * @param  list<Student>  $students
     * @return list<Enrollment>
     */
    private function studyTogether(Course $course, array $circles, array $students): array
    {
        $course->load('schedules');

        $enrollments = [];
        $habits = [];

        foreach ($students as $index => $student) {
            $enrollment = app(EnrollStudent::class)->handle([
                'student_id' => $student->getKey(),
                'course_id' => $course->getKey(),
                'circle_id' => $circles[$index % count($circles)]->getKey(),
                'enrolled_on' => $course->start_date->toDateString(),
            ]);

            $enrollments[] = $enrollment;

            // Each student has a way of their own: how often they come, how often they recite, how much.
            // The last one on the list stopped coming two weeks ago (see moveAndLeave).
            $habits[$enrollment->getKey()] = [
                'comes' => mt_rand(60, 97),
                'recites' => mt_rand(55, 95),
                'pages' => mt_rand(1, 3),
                'until' => $index === count($students) - 1 ? $this->leavingDay() : null,
            ];
        }

        $lastDay = $course->end_date !== null && $course->end_date->lessThan($this->today)
            ? CarbonImmutable::parse($course->end_date)
            : $this->today;

        $teachers = $this->teachersOfCircles($circles);

        for ($day = CarbonImmutable::parse($course->start_date); $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
            if (! $course->isScheduledOn($day)) {
                continue;
            }

            foreach ($enrollments as $enrollment) {
                $habit = $habits[$enrollment->getKey()];

                if (mt_rand(1, 100) > $habit['comes'] || ($habit['until'] !== null && $day->greaterThan($habit['until']))) {
                    continue;
                }

                $this->attend($enrollment, $day, $habit);
            }

            $this->teachersCome($course, $day, $teachers);
        }

        return $enrollments;
    }

    /**
     * @param  list<Circle>  $circles
     * @return array<int, int> teacher id => how many days in a row they come before missing one
     */
    private function teachersOfCircles(array $circles): array
    {
        $teachers = [];

        foreach ($circles as $circle) {
            [$supervisor, $assistant] = $this->teachersOf[$circle->getKey()];
            // A supervisor misses about one day in nine, an assistant one in five.
            $teachers[$supervisor] = max($teachers[$supervisor] ?? 0, 9);
            $teachers[$assistant] = max($teachers[$assistant] ?? 0, 5);
        }

        return $teachers;
    }

    /**
     * The supervisors and assistants who came on a day of class. Their
     * absences follow the calendar, not chance, so the students' days stay
     * the same as they were before teachers had an attendance.
     *
     * @param  array<int, int>  $teachers
     */
    private function teachersCome(Course $course, CarbonImmutable $day, array $teachers): void
    {
        foreach ($teachers as $teacherId => $every) {
            if (($day->dayOfYear + $teacherId * 3) % $every === 0) {
                continue;
            }

            app(RecordManualTeacherAttendance::class)->handle([
                'teacher_id' => $teacherId,
                'course_id' => $course->getKey(),
                'attended_on' => $day->toDateString(),
            ]);
        }
    }

    /**
     * One student on one day of class.
     *
     * @param  array{comes: int, recites: int, pages: int, until: CarbonImmutable|null}  $habit
     */
    private function attend(Enrollment $enrollment, CarbonImmutable $day, array $habit): void
    {
        $date = $day->toDateString();

        app(RecordManualAttendance::class)->handle(['enrollment_id' => $enrollment->getKey(), 'attended_on' => $date]);

        $from = $this->nextPage[$enrollment->student_id];

        if (mt_rand(1, 100) <= $habit['recites']) {
            $to = min(QuranPages::LAST, $from + mt_rand(1, $habit['pages']) - 1);
            [$supervisor, $assistant] = $this->teachersOf[$enrollment->circle_id];

            app(RecordRecitation::class)->handle([
                'enrollment_id' => $enrollment->getKey(),
                // The supervisor hears most recitations, the assistant the others.
                'teacher_id' => mt_rand(1, 4) === 1 ? $assistant : $supervisor,
                'recited_on' => $date,
                'from_page' => $from,
                'to_page' => $to,
            ]);

            // After the last page of the mushaf, a student who began with the last juz goes on from the first page.
            $this->nextPage[$enrollment->student_id] = $to === QuranPages::LAST ? QuranPages::FIRST : $to + 1;
        }

        if (mt_rand(1, 14) === 1) {
            app(AwardManualPoints::class)->handle([
                'enrollment_id' => $enrollment->getKey(),
                'points' => 5,
                'reason' => 'الفوز في مسابقة الأسبوع',
                'occurred_on' => $date,
            ]);
        } elseif (mt_rand(1, 40) === 1) {
            app(AwardManualPoints::class)->handle([
                'enrollment_id' => $enrollment->getKey(),
                'points' => -2,
                'reason' => 'الإخلال بنظام الحلقة',
                'occurred_on' => $date,
            ]);
        }
    }

    /**
     * Two things that happen in every course: a student is moved to
     * another circle, and a student stops coming.
     *
     * @param  list<Enrollment>  $enrollments
     * @param  list<Circle>  $circles
     */
    private function moveAndLeave(array $enrollments, array $circles): void
    {
        if (count($enrollments) < 4 || count($circles) < 2) {
            return;
        }

        $moved = $enrollments[1];
        $target = collect($circles)->first(fn (Circle $circle): bool => $circle->getKey() !== $moved->circle_id);

        app(TransferStudent::class)->handle($moved, [
            'to_circle_id' => $target->getKey(),
            'transferred_on' => $this->today->subDays(10)->toDateString(),
            'notes' => 'بطلب من ولي الأمر',
        ]);

        // The last student on the list has not come for two weeks: they left the course.
        $left = $enrollments[count($enrollments) - 1];

        app(UpdateEnrollmentDates::class)->handle($left, [
            'enrolled_on' => $left->enrolled_on->toDateString(),
            'left_on' => $this->leavingDay()->toDateString(),
        ]);
    }

    /**
     * What happened outside the lessons: a competition of one circle, a
     * trip of the whole mosque, and a meeting that gave no points.
     *
     * @param  list<Enrollment>  $enrollments
     * @param  list<Circle>  $circles
     */
    private function holdActivities(array $enrollments, array $circles): void
    {
        // Whoever is still in the course, in the circle they are in now.
        $present = Enrollment::query()
            ->whereKey(array_map(fn (Enrollment $enrollment): int => $enrollment->getKey(), $enrollments))
            ->whereNull('left_on')
            ->orderBy('id')
            ->get();

        $of = fn (Circle $circle): array => $present->where('circle_id', $circle->getKey())->modelKeys();
        // Not everybody comes: every fifth student stayed at home.
        $most = fn (array $ids): array => array_values(array_filter($ids, fn (int $index): bool => $index % 5 !== 4, ARRAY_FILTER_USE_KEY));

        $activities = [
            ['مسابقة الحفظ بين طلاب الحلقة', 20, $circles[0], 10, $most($of($circles[0])), 'فاز بها أكثر الطلاب حفظاً في الأسبوع.'],
            ['رحلة الجامع الخريفية', 7, null, 15, $most($present->modelKeys()), null],
            ['لقاء الطلاب مع أهاليهم', 3, $circles[1] ?? $circles[0], 0, $of($circles[1] ?? $circles[0]), 'لقاء تعارف، بلا نقاط.'],
        ];

        foreach ($activities as [$title, $daysAgo, $circle, $points, $attendees, $notes]) {
            app(SaveActivity::class)->handle([
                'title' => $title,
                'activity_date' => $this->today->subDays($daysAgo)->toDateString(),
                'audience' => ($circle === null ? ActivityAudience::Mosque : ActivityAudience::Circle)->value,
                'circle_id' => $circle?->getKey(),
                'points' => $points,
                'notes' => $notes,
                'enrollment_ids' => $attendees,
            ]);
        }
    }

    private function leavingDay(): CarbonImmutable
    {
        return $this->today->subDays(14);
    }

    /**
     * Most of the students who completed a juz have been examined; the
     * others are still waiting, as the admin's notifications say.
     *
     * @param  list<Teacher>  $teachers
     */
    private function examine(array $teachers): void
    {
        $grades = [ExamGrade::Excellent, ExamGrade::Excellent, ExamGrade::VeryGood, ExamGrade::VeryGood, ExamGrade::Good, ExamGrade::Acceptable, ExamGrade::Retake];

        foreach (JuzExam::query()->pending()->orderBy('id')->get() as $index => $exam) {
            // Every third one is left pending.
            if ($index % 3 === 2) {
                continue;
            }

            $examinedOn = CarbonImmutable::parse($exam->triggered_on)->addDays(mt_rand(1, 4));

            app(RecordJuzExamResult::class)->handle($exam, [
                'exam_date' => ($examinedOn->greaterThan($this->today) ? $this->today : $examinedOn)->toDateString(),
                'examiner_teacher_id' => $teachers[$index % 2]->getKey(),
                'grade' => $grades[$index % count($grades)]->value,
            ]);
        }
    }

    /**
     * Eight months of expenses in the three starting departments: items
     * bought by quantity, and once a month an amount paid as a whole.
     */
    private function spend(): void
    {
        $departments = Department::query()->pluck('id', 'name');

        for ($monthsAgo = 7; $monthsAgo >= 0; $monthsAgo--) {
            $month = $this->today->startOfMonth()->subMonths($monthsAgo);

            foreach (self::PURCHASES as $department => $items) {
                if (! isset($departments[$department])) {
                    continue;
                }

                // Two or three purchases of the department each month.
                foreach (array_slice($items, $monthsAgo % 3, mt_rand(2, 3)) as [$item, $quantity, $price]) {
                    $day = $month->addDays(mt_rand(0, 26));

                    app(SaveExpense::class)->handle([
                        'kind' => ExpenseKind::Itemized->value,
                        'department_id' => $departments[$department],
                        'item_name' => $item,
                        'quantity' => max(1, (int) round($quantity * mt_rand(60, 140) / 100)),
                        'unit_price' => $price,
                        'expense_date' => ($day->greaterThan($this->today) ? $this->today : $day)->toDateString(),
                    ]);
                }
            }

            // Once a month, a hospitality whose items nobody kept: only what was paid.
            if (isset($departments['إطعام'])) {
                $day = $month->addDays(mt_rand(0, 26));

                app(SaveExpense::class)->handle([
                    'kind' => ExpenseKind::LumpSum->value,
                    'department_id' => $departments['إطعام'],
                    'item_name' => 'ضيافة درس الجمعة (مشتريات متنوعة)',
                    'amount' => mt_rand(15, 60) * 5000,
                    'expense_date' => ($day->greaterThan($this->today) ? $this->today : $day)->toDateString(),
                ]);
            }
        }
    }

    /**
     * A points market of the autumn course, with its items, and a week of
     * purchases by the students whose points cover them.
     *
     * @param  list<Enrollment>  $enrollments
     */
    private function holdMarket(Course $course, array $enrollments): void
    {
        $market = app(SaveMarket::class)->handle(['course_id' => $course->getKey(), 'name' => 'سوق نهاية الشهر']);

        $items = array_map(
            fn (array $item): int => app(SaveMarketItem::class)->handle($market, [
                'name' => $item[0],
                'price' => $item[1],
                'stock' => $item[2],
                'description' => $item[3],
            ])->getKey(),
            self::MARKET_ITEMS,
        );

        $buyers = Enrollment::query()
            ->whereKey(array_map(fn (Enrollment $enrollment): int => $enrollment->getKey(), $enrollments))
            ->whereNull('left_on')
            ->orderBy('id')
            ->get();

        foreach ($buyers->take(10) as $index => $enrollment) {
            $basket = [['item_id' => $items[$index % count($items)], 'quantity' => 1 + $index % 2]];

            if ($index % 3 === 0) {
                $basket[] = ['item_id' => $items[($index + 2) % count($items)], 'quantity' => 1];
            }

            $at = $this->today->subDays($index % 6)->setTime(17, mt_rand(0, 59));

            // Who cannot pay for the basket buys nothing: SellToCard says so and writes nothing.
            app(SellToCard::class)->handle(
                ['market_id' => $market->getKey(), 'card_code' => $enrollment->card_code, 'items' => $basket],
                $at->greaterThan(now()) ? now() : $at,
            );
        }
    }
}
