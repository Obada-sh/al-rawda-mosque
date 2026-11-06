<?php

namespace Tests\Feature\English\Panel;

use App\Actions\English\Attendance\RecordEnglishScan;
use App\Enums\English\PaymentState;
use App\Enums\Weekday;
use App\Filament\English\Pages\EnglishMoneyReport;
use App\Filament\English\Pages\EnglishPaymentsReport;
use App\Filament\English\Pages\ScanEnglishAttendance;
use App\Filament\English\Resources\EnglishAttendances\EnglishAttendanceResource;
use App\Filament\English\Resources\EnglishAttendances\Pages\ManageEnglishAttendances;
use App\Filament\English\Resources\EnglishCourses\Pages\EditEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\PapersRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\SessionsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\TestsRelationManager;
use App\Filament\English\Resources\EnglishDepartments\EnglishDepartmentResource;
use App\Filament\English\Resources\EnglishDepartments\Pages\ManageEnglishDepartments;
use App\Filament\English\Resources\EnglishExpenses\EnglishExpenseResource;
use App\Filament\English\Resources\EnglishExpenses\Pages\ManageEnglishExpenses;
use App\Filament\English\Resources\EnglishPayments\EnglishPaymentResource;
use App\Filament\English\Resources\EnglishPayments\Pages\ManageEnglishPayments;
use App\Filament\English\Widgets\Money\BalanceStats;
use App\Filament\English\Widgets\Money\StudentsMoneyTable;
use App\Models\English\EnglishAttendance;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishDepartment;
use App\Models\English\EnglishExpense;
use App\Models\English\EnglishPaper;
use App\Models\English\EnglishPayment;
use App\Models\English\EnglishTest;
use App\Models\English\EnglishTestResult;
use App\Models\Expense;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

class EnglishDailyAndMoneyPanelTest extends EnglishPanelTestCase
{
    /**
     * @return array{ownerRecord: EnglishCourse, pageClass: class-string}
     */
    private function on(EnglishCourse $course): array
    {
        return ['ownerRecord' => $course, 'pageClass' => EditEnglishCourse::class];
    }

    /**
     * @return array<string, array{0: callable(): string}>
     */
    public static function screens(): array
    {
        return [
            'scan' => [fn (): string => ScanEnglishAttendance::getUrl(panel: 'english')],
            'attendance' => [fn (): string => EnglishAttendanceResource::getUrl(panel: 'english')],
            'payments' => [fn (): string => EnglishPaymentResource::getUrl(panel: 'english')],
            'payments report' => [fn (): string => EnglishPaymentsReport::getUrl(panel: 'english')],
            'money report' => [fn (): string => EnglishMoneyReport::getUrl(panel: 'english')],
            'expenses' => [fn (): string => EnglishExpenseResource::getUrl(panel: 'english')],
            'departments' => [fn (): string => EnglishDepartmentResource::getUrl(panel: 'english')],
        ];
    }

    #[DataProvider('screens')]
    public function test_the_screen_opens(callable $url): void
    {
        $enrollment = $this->enrollInEnglish();
        $this->alreadyPaid($enrollment, 1000);
        app(RecordEnglishScan::class)->handle($enrollment->card_code);

        $this->get($url())->assertOk();
    }

    public function test_the_desk_records_an_english_card_and_tells_a_mosque_card(): void
    {
        $enrollment = $this->enrollInEnglish();
        $mosque = $this->enroll();

        Livewire::test(ScanEnglishAttendance::class)
            ->assertSee('بانتظار أول بطاقة')
            ->set('cardCode', $enrollment->card_code)
            ->call('scan')
            ->assertSet('cardCode', '')
            ->assertSet('result.tone', 'success')
            ->assertSee('تم تسجيل الحضور')
            ->assertSee($enrollment->student->full_name)
            ->set('cardCode', $mosque->card_code)
            ->call('scan')
            ->assertSet('result.tone', 'danger')
            ->assertSee('هذه بطاقة من دورات المسجد');

        $this->assertSame(1, EnglishAttendance::query()->count());
    }

    public function test_the_desk_offers_an_exceptional_session_on_a_day_without_class(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->meetingOn([Weekday::Saturday])->create());

        Livewire::test(ScanEnglishAttendance::class)
            ->set('cardCode', $enrollment->card_code)
            ->call('scan')
            ->assertSet('refusedCard', $enrollment->card_code)
            ->assertSee('فتح جلسة استثنائية اليوم وتسجيل الحضور')
            ->call('openExceptionalSession')
            ->assertSet('result.tone', 'success');

        $this->assertSame(1, EnglishAttendance::query()->count());
    }

    public function test_manual_and_bulk_attendance_from_the_register(): void
    {
        $course = EnglishCourse::factory()->create();
        $first = $this->enrollInEnglish(course: $course);
        $second = $this->enrollInEnglish(course: $course);

        Livewire::test(ManageEnglishAttendances::class)
            ->callAction('manual', data: ['english_enrollment_id' => $first->getKey(), 'attended_on' => '2026-10-05'])
            ->assertHasNoActionErrors()
            ->callAction('bulkAttendance', data: [
                'english_course_id' => $course->getKey(),
                'attended_on' => '2026-10-07',
                'english_enrollment_ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('سُجّل حضور طالبان ليوم 07/10/2026');

        $this->assertSame(3, EnglishAttendance::query()->count());

        Livewire::test(SessionsRelationManager::class, $this->on($course))
            ->assertCanSeeTableRecords($course->sessions()->get());
    }

    public function test_a_payment_is_recorded_from_the_course_and_refused_above_the_remainder(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 100000]));

        Livewire::test(EnrollmentsRelationManager::class, $this->on($enrollment->course))
            ->mountAction(TestAction::make('pay')->table($enrollment))
            ->assertSchemaStateSet(['amount' => 100000, 'paid_on' => '2026-10-07'])
            ->fillForm(['amount' => '150000'])
            ->callMountedAction()
            ->assertNotified('المتبقي على الطالب 100,000 ل.س فقط.');

        Livewire::test(EnrollmentsRelationManager::class, $this->on($enrollment->course))
            ->callAction(TestAction::make('pay')->table($enrollment), data: ['amount' => '40000', 'notes' => 'القسط الأول'])
            ->assertHasNoActionErrors();

        $this->assertSame(40000, (int) EnglishPayment::query()->sum('amount'));

        Livewire::test(EnrollmentsRelationManager::class, $this->on($enrollment->course))
            ->assertTableColumnStateSet('remaining', '60,000 ل.س', $enrollment->fresh());
    }

    public function test_payments_are_listed_and_recorded_from_their_page(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 100000]));

        Livewire::test(ManageEnglishPayments::class)
            ->callAction('pay', data: ['english_enrollment_id' => $enrollment->getKey(), 'amount' => '25000', 'paid_on' => '2026-10-06'])
            ->assertHasNoActionErrors();

        $payment = EnglishPayment::query()->sole();

        Livewire::test(ManageEnglishPayments::class)
            ->assertCanSeeTableRecords([$payment])
            ->callAction(TestAction::make('edit')->table($payment), data: ['amount' => '30000'])
            ->assertHasNoActionErrors();

        $this->assertSame(30000, $payment->fresh()->amount);
    }

    public function test_the_payments_report_lists_who_has_not_paid(): void
    {
        $course = EnglishCourse::factory()->create(['price' => 100000]);
        $paid = $this->enrollInEnglish(course: $course);
        $unpaid = $this->enrollInEnglish(course: $course);
        $this->alreadyPaid($paid, 100000);

        Livewire::test(StudentsMoneyTable::class, ['pageFilters' => ['state' => PaymentState::Unpaid->value]])
            ->assertSee($unpaid->student->full_name)
            ->assertDontSee($paid->student->full_name);

        Livewire::test(BalanceStats::class, ['pageFilters' => ['english_course_id' => $course->getKey()]])
            ->assertSee('200,000 ل.س')
            ->assertSee('100,000 ل.س');
    }

    public function test_marks_are_written_for_the_students_of_the_test_day(): void
    {
        $course = EnglishCourse::factory()->create(['start_date' => '2026-09-01']);
        $first = $this->enrollInEnglish(course: $course, enrolledOn: '2026-09-01');
        $second = $this->enrollInEnglish(course: $course, enrolledOn: '2026-09-01');

        Livewire::test(TestsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'Quiz 1', 'kind' => 'exam', 'held_on' => '2026-10-05', 'max_mark' => '20'])
            ->assertHasNoActionErrors();

        $test = EnglishTest::query()->sole();

        Livewire::test(TestsRelationManager::class, $this->on($course))
            ->mountAction(TestAction::make('results')->table($test))
            ->assertFormFieldExists("marks.{$first->getKey()}.mark")
            ->assertFormFieldExists("marks.{$second->getKey()}.is_absent")
            ->fillForm(['marks' => [$first->getKey() => ['mark' => '25']]])
            ->callMountedAction()
            ->assertHasActionErrors(["marks.{$first->getKey()}.mark"]);

        Livewire::test(TestsRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('results')->table($test), data: [
                'marks' => [
                    $first->getKey() => ['mark' => '18.5', 'is_absent' => false, 'notes' => null],
                    $second->getKey() => ['mark' => null, 'is_absent' => true, 'notes' => 'مريض'],
                ],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(2, EnglishTestResult::query()->count());

        Livewire::test(TestsRelationManager::class, $this->on($course))
            ->assertTableColumnStateSet('results_count', 2, $test);
    }

    public function test_a_paper_is_added_to_the_course_and_opened(): void
    {
        $course = EnglishCourse::factory()->create();

        Livewire::test(PapersRelationManager::class, $this->on($course))
            ->callAction(TestAction::make('create')->table(), data: [
                'title' => 'Vocabulary',
                'file' => UploadedFile::fake()->createWithContent('words.pdf', '%PDF-1.4 words'),
            ])
            ->assertHasNoActionErrors();

        $paper = EnglishPaper::query()->sole();

        $this->get(route('filament.english.papers.file', ['paper' => $paper]))
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_departments_and_expenses_of_the_english_section(): void
    {
        Livewire::test(ManageEnglishDepartments::class)
            ->callAction('create', data: ['name' => 'كتب'])
            ->assertHasNoActionErrors();

        Livewire::test(ManageEnglishExpenses::class)
            ->callAction('create', data: [
                'kind' => 'lump_sum',
                'english_department_id' => EnglishDepartment::query()->sole()->getKey(),
                'item_name' => 'كتب المستوى الأول',
                'amount' => '75000',
                'expense_date' => '2026-10-07',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('75000.00', EnglishExpense::query()->sole()->total);
        $this->assertSame(0, Expense::query()->count());
    }
}
