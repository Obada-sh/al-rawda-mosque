<?php

namespace Tests\Feature\English;

use App\Actions\English\Finance\DeleteEnglishDepartment;
use App\Actions\English\Finance\SaveEnglishDepartment;
use App\Actions\English\Finance\SaveEnglishExpense;
use App\Actions\English\Payments\DeleteEnglishPayment;
use App\Actions\English\Payments\RecordEnglishPayment;
use App\Enums\English\PaymentState;
use App\Enums\ExpenseKind;
use App\Exceptions\BusinessRuleViolation;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishDepartment;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use App\Services\English\EnglishBalanceFilter;
use App\Services\English\EnglishFinanceFilter;
use App\Services\English\EnglishFinanceReport;
use App\Services\English\EnglishMoneyReport;
use App\Services\Finance\ExpenseFilter;
use App\Services\Finance\ExpenseReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\TestCase;

/**
 * The money of the English section: what each student pays for a course,
 * the reports of who paid, and the section's own expenses, kept apart from
 * the mosque's.
 */
class EnglishMoneyTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
    }

    private function pay(EnglishEnrollment $enrollment, int|string $amount, string $paidOn = '2026-10-07', ?string $notes = null): EnglishPayment
    {
        return app(RecordEnglishPayment::class)->handle([
            'english_enrollment_id' => $enrollment->getKey(),
            'amount' => $amount,
            'paid_on' => $paidOn,
            'notes' => $notes,
        ]);
    }

    private function department(string $name = 'إيجار'): EnglishDepartment
    {
        return app(SaveEnglishDepartment::class)->handle(['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function spendInEnglish(array $overrides = []): void
    {
        $overrides['english_department_id'] ??= $this->department('قسم '.uniqid())->getKey();

        app(SaveEnglishExpense::class)->handle($overrides + [
            'kind' => ExpenseKind::LumpSum->value,
            'item_name' => 'إيجار القاعة',
            'amount' => '50000',
            'expense_date' => '2026-10-01',
        ]);
    }

    public function test_a_student_pays_in_parts_and_never_more_than_the_price(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 1000]));

        $first = $this->pay($enrollment, '٤٠٠', notes: 'القسط الأول');
        $this->pay($enrollment, 600);

        $this->assertSame(400, $first->amount);
        $this->assertSame('القسط الأول', $first->notes);
        $this->assertViolates('english_payment.more_than_remaining', fn () => $this->pay($enrollment, 1));
        $this->assertSame(1000, (int) EnglishPayment::query()->sum('amount'));
    }

    public function test_the_remainder_is_said_when_a_payment_is_too_large(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 150000]), price: 100000, priceNote: 'خصم');
        $this->pay($enrollment, 30000);

        try {
            $this->pay($enrollment, 80000);
            $this->fail('A payment above the remainder was accepted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('المتبقي على الطالب 70,000 ل.س فقط.', $violation->getMessage());
        }
    }

    public function test_payment_input_rules(): void
    {
        $enrollment = $this->enrollInEnglish();

        foreach ([0, -5, '1,000', '10.5', null] as $amount) {
            $this->assertThrows(fn () => $this->pay($enrollment, $amount ?? ''), ValidationException::class);
        }

        $this->assertThrows(fn () => $this->pay($enrollment, 100, '2026-10-08'), ValidationException::class);
        $this->assertSame(0, EnglishPayment::query()->count());
    }

    public function test_a_payment_is_corrected_within_the_price_and_stays_with_its_student(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 1000]));
        $payment = $this->pay($enrollment, 700);
        $this->pay($enrollment, 300);

        app(RecordEnglishPayment::class)->handle(['english_enrollment_id' => $enrollment->getKey(), 'amount' => 600, 'paid_on' => '2026-10-06'], $payment);
        $this->assertSame(600, $payment->fresh()->amount);
        $this->assertSame('2026-10-06', $payment->fresh()->paid_on->toDateString());

        $this->assertViolates('english_payment.more_than_remaining', fn () => app(RecordEnglishPayment::class)->handle(['english_enrollment_id' => $enrollment->getKey(), 'amount' => 800, 'paid_on' => '2026-10-06'], $payment));
        $this->assertViolates('english_payment.other_enrollment', fn () => app(RecordEnglishPayment::class)->handle(['english_enrollment_id' => $this->enrollInEnglish()->getKey(), 'amount' => 10, 'paid_on' => '2026-10-06'], $payment));

        app(DeleteEnglishPayment::class)->handle($payment);
        $this->assertSame(300, (int) EnglishPayment::query()->sum('amount'));
    }

    public function test_the_payments_report_by_student_by_course_and_for_everything(): void
    {
        $a1 = EnglishCourse::factory()->create(['name' => 'A1', 'price' => 100000, 'start_date' => '2026-09-01']);
        $b1 = EnglishCourse::factory()->create(['name' => 'B1', 'price' => 200000, 'start_date' => '2026-08-01']);
        $paid = $this->enrollInEnglish(course: $a1);
        $partly = $this->enrollInEnglish(course: $a1);
        $unpaid = $this->enrollInEnglish(course: $b1);
        $this->pay($paid, 100000);
        $this->pay($partly, 25000);

        $report = app(EnglishMoneyReport::class);
        $all = $report->students(new EnglishBalanceFilter);

        $this->assertEqualsCanonicalizing([$paid->getKey(), $partly->getKey(), $unpaid->getKey()], $all->pluck('enrollmentId')->all());
        // The newest course comes first.
        $this->assertSame(['A1', 'A1', 'B1'], $all->pluck('courseName')->all());

        $total = $report->total($all);
        $this->assertSame([3, 400000, 125000, 275000, 1, 1, 1], [$total->students, $total->due, $total->paid, $total->remaining, $total->paidInFull, $total->partly, $total->unpaid]);

        $byCourse = $report->byCourse($all);
        $this->assertSame(['A1', 'B1'], $byCourse->pluck('courseName')->all());
        $this->assertSame([200000, 125000, 75000], [$byCourse[0]->due, $byCourse[0]->paid, $byCourse[0]->remaining]);

        $notPaid = $report->students(EnglishBalanceFilter::fromInput(['state' => PaymentState::Unpaid->value]));
        $this->assertSame([$unpaid->getKey()], $notPaid->pluck('enrollmentId')->all());

        $ofA1 = $report->students(EnglishBalanceFilter::fromInput(['english_course_id' => $a1->getKey()]));
        $this->assertSame(2, $ofA1->count());

        $this->assertThrows(fn () => EnglishBalanceFilter::fromInput(['state' => 'nonsense']), ValidationException::class);
    }

    public function test_the_money_report_says_income_expenses_and_net_month_by_month(): void
    {
        $enrollment = $this->enrollInEnglish(course: EnglishCourse::factory()->create(['price' => 500000, 'start_date' => '2026-08-01']));
        $this->pay($enrollment, 100000, '2026-09-15');
        $this->pay($enrollment, 50000, '2026-10-02');
        $this->spendInEnglish(['amount' => '30000', 'expense_date' => '2026-09-20']);
        $this->spendInEnglish(['amount' => '80000.50', 'expense_date' => '2026-10-03']);

        $report = app(EnglishFinanceReport::class);
        $totals = $report->totals(new EnglishFinanceFilter);

        $this->assertSame(150000, $totals['income']);
        $this->assertSame('110000.50', $totals['expenses']);
        $this->assertSame('39999.50', $totals['net']);

        $months = $report->byMonth(new EnglishFinanceFilter);
        $this->assertSame([[2026, 10, 50000, '80000.50', '-30000.50'], [2026, 9, 100000, '30000.00', '70000.00']], $months->map(fn (array $m): array => [$m['year'], $m['month'], $m['income'], $m['expenses'], $m['net']])->all());

        $october = $report->totals(EnglishFinanceFilter::fromInput(['from' => '2026-10-01', 'until' => '2026-10-31']));
        $this->assertSame([50000, '80000.50', '-30000.50'], [$october['income'], $october['expenses'], $october['net']]);

        $this->assertSame(150000, $report->incomeByCourse(new EnglishFinanceFilter)->sole()['income']);
        $this->assertSame(2, $report->expensesByDepartment(new EnglishFinanceFilter)->count());
    }

    public function test_the_two_sections_never_count_each_others_money(): void
    {
        $this->spend(['kind' => ExpenseKind::LumpSum->value, 'amount' => '1000', 'expense_date' => '2026-10-01']);
        $this->spendInEnglish(['amount' => '5000']);

        $this->assertSame('1000.00', app(ExpenseReport::class)->totals(new ExpenseFilter)->total);
        $this->assertSame('5000.00', app(EnglishFinanceReport::class)->totals(new EnglishFinanceFilter)['expenses']);
    }

    public function test_english_departments_follow_the_mosque_rules(): void
    {
        $department = $this->department('كتب');

        $this->assertThrows(fn () => $this->department('كتب'), ValidationException::class);

        $this->spendInEnglish(['english_department_id' => $department->getKey()]);
        $this->assertViolates('department.has_expenses', fn () => app(DeleteEnglishDepartment::class)->handle($department));

        $department->update(['is_active' => false]);
        $this->assertViolates('department.inactive', fn () => $this->spendInEnglish(['english_department_id' => $department->getKey()]));

        $empty = $this->department('قرطاسية');
        app(DeleteEnglishDepartment::class)->handle($empty);
        $this->assertNull($empty->fresh());
    }
}
