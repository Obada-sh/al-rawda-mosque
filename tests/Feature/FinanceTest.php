<?php

namespace Tests\Feature;

use App\Actions\Finance\DeleteDepartment;
use App\Actions\Finance\DeleteExpense;
use App\Actions\Finance\SaveDepartment;
use App\Actions\Finance\SaveExpense;
use App\Enums\ExpenseKind;
use App\Models\Department;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->department = Department::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function expense(array $overrides = [], ?Expense $existing = null): Expense
    {
        return app(SaveExpense::class)->handle($overrides + [
            'kind' => ExpenseKind::Itemized->value,
            'department_id' => $this->department->getKey(),
            'item_name' => 'مصاحف',
            'quantity' => '12',
            'unit_price' => '2500.50',
            'expense_date' => '2026-10-05',
        ], $existing);
    }

    public function test_an_expense_is_recorded_and_its_total_is_computed(): void
    {
        $expense = $this->expense()->fresh();

        $this->assertSame('مصاحف', $expense->item_name);
        $this->assertSame('12.000', $expense->quantity);
        $this->assertSame('2500.50', $expense->unit_price);
        $this->assertSame('30006.00', $expense->total);
        $this->assertSame('2026-10-05', $expense->expense_date->toDateString());
        $this->assertTrue($expense->department->is($this->department));
    }

    public function test_a_total_sent_in_the_input_is_ignored(): void
    {
        $expense = $this->expense(['quantity' => '2', 'unit_price' => '10', 'total' => '999999'])->fresh();

        $this->assertSame('20.00', $expense->total);
    }

    public function test_fractional_quantities_are_supported(): void
    {
        $expense = $this->expense(['quantity' => '1.5', 'unit_price' => '3.33'])->fresh();

        $this->assertSame('5.00', $expense->total);
    }

    public function test_correcting_an_expense_recomputes_its_total(): void
    {
        $expense = $this->expense();

        $this->expense(['quantity' => '3', 'unit_price' => '100'], $expense);

        $this->assertSame('300.00', $expense->fresh()->total);
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_invalid_expense_input_is_refused(): void
    {
        $invalid = [
            'no item' => ['item_name' => ''],
            'zero quantity' => ['quantity' => '0'],
            'negative price' => ['unit_price' => '-5'],
            'too many decimals in price' => ['unit_price' => '1.999'],
            'too many decimals in quantity' => ['quantity' => '1.0001'],
            'future date' => ['expense_date' => '2026-10-08'],
            'unknown department' => ['department_id' => 999999],
            'no department' => ['department_id' => null],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                $this->expense($overrides);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Expense::query()->count());
    }

    /** @param array<string, mixed> $overrides */
    private function lumpSum(array $overrides = [], ?Expense $existing = null): Expense
    {
        return $this->expense($overrides + [
            'kind' => ExpenseKind::LumpSum->value,
            'item_name' => 'مشتريات متنوعة لحفل الختام',
            'amount' => '125000',
        ], $existing);
    }

    public function test_an_amount_paid_as_a_whole_needs_only_a_description_and_the_amount(): void
    {
        $expense = app(SaveExpense::class)->handle([
            'kind' => ExpenseKind::LumpSum->value,
            'department_id' => $this->department->getKey(),
            'item_name' => 'مشتريات متنوعة لحفل الختام',
            'amount' => '١٢٥٠٠٠',
            'expense_date' => '2026-10-05',
        ])->fresh();

        $this->assertSame(ExpenseKind::LumpSum, $expense->kind);
        $this->assertSame('مشتريات متنوعة لحفل الختام', $expense->item_name);
        $this->assertNull($expense->quantity);
        $this->assertNull($expense->unit_price);
        $this->assertSame('125000.00', $expense->total);
        $this->assertSame(ExpenseKind::Itemized, $this->expense()->kind);
    }

    public function test_a_quantity_or_a_price_sent_with_an_amount_paid_as_a_whole_is_ignored(): void
    {
        // The defaults of expense() carry a quantity and a unit price.
        $expense = $this->lumpSum(['amount' => '90000.50'])->fresh();

        $this->assertNull($expense->quantity);
        $this->assertNull($expense->unit_price);
        $this->assertSame('90000.50', $expense->total);
    }

    public function test_an_item_becomes_an_amount_paid_as_a_whole_and_back(): void
    {
        $expense = $this->expense();

        $this->lumpSum(['amount' => '40000'], $expense);
        $expense->refresh();
        $this->assertSame([null, null, '40000.00'], [$expense->quantity, $expense->unit_price, $expense->total]);

        $this->expense(['quantity' => '2', 'unit_price' => '1500'], $expense);
        $expense->refresh();
        $this->assertSame(['2.000', '1500.00', '3000.00'], [$expense->quantity, $expense->unit_price, $expense->total]);
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_invalid_amounts_paid_as_a_whole_are_refused(): void
    {
        $invalid = [
            'no amount' => ['amount' => null],
            'zero' => ['amount' => '0'],
            'negative' => ['amount' => '-100'],
            'not a number' => ['amount' => 'كثير'],
            'too many decimals' => ['amount' => '10.555'],
            'too large' => ['amount' => '100000000000000'],
            'no description' => ['item_name' => ''],
            'no department' => ['department_id' => null],
        ];

        foreach ($invalid as $case => $overrides) {
            try {
                $this->lumpSum($overrides);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), $case);
            }
        }

        foreach (['no way' => null, 'unknown way' => 'gift'] as $case => $kind) {
            try {
                $this->expense(['kind' => $kind]);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('kind', $exception->errors(), $case);
            }
        }

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_spending_is_summed_per_department(): void
    {
        $food = Department::factory()->create();

        $this->expense(['quantity' => '1', 'unit_price' => '100']);
        $this->expense(['quantity' => '2', 'unit_price' => '50']);
        $this->expense(['department_id' => $food->getKey(), 'quantity' => '1', 'unit_price' => '75.25']);

        $this->assertSame('200.00', number_format((float) $this->department->expenses()->sum('total'), 2, '.', ''));
        $this->assertSame('75.25', number_format((float) $food->expenses()->sum('total'), 2, '.', ''));
    }

    public function test_the_admin_can_add_a_new_department(): void
    {
        $department = app(SaveDepartment::class)->handle(['name' => 'صيانة']);

        $this->assertTrue($department->fresh()->is_active);

        $this->expectException(ValidationException::class);

        app(SaveDepartment::class)->handle(['name' => 'صيانة']);
    }

    public function test_a_department_can_be_renamed_without_tripping_over_its_own_name(): void
    {
        $department = app(SaveDepartment::class)->handle(['name' => 'صيانة']);

        $renamed = app(SaveDepartment::class)->handle(['name' => 'صيانة', 'is_active' => false], $department);

        $this->assertFalse($renamed->fresh()->is_active);
    }

    public function test_an_inactive_department_takes_no_new_expenses_but_keeps_its_old_ones(): void
    {
        $expense = $this->expense();
        $this->department->update(['is_active' => false]);

        $this->assertViolates('department.inactive', fn () => $this->expense());

        // An old expense of that department can still be corrected.
        $this->expense(['quantity' => '1', 'unit_price' => '10'], $expense);
        $this->assertSame('10.00', $expense->fresh()->total);
    }

    public function test_a_department_with_expenses_cannot_be_deleted(): void
    {
        $this->expense();

        $this->assertViolates('department.has_expenses', fn () => app(DeleteDepartment::class)->handle($this->department));

        $empty = Department::factory()->create();
        app(DeleteDepartment::class)->handle($empty);
        $this->assertModelMissing($empty);
    }

    public function test_an_expense_can_be_deleted(): void
    {
        $expense = $this->expense();

        app(DeleteExpense::class)->handle($expense);

        $this->assertModelMissing($expense);
    }
}
