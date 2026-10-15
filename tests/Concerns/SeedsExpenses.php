<?php

namespace Tests\Concerns;

use App\Models\Department;

/**
 * Four expenses in two departments over three months (the clock of the
 * test must stand after 5 October 2026):
 *
 *   مشتريات: ورق 2 × 1,500 on 5 October; ورق 1 × 1,500 on 10 September   = 4,500.00
 *   إطعام:   تمر 2.5 × 12,000 on 1 October; ماء 10 × 250.50 on 20 August = 32,505.00
 *   لوجستي:  nothing
 *
 * Total 37,005.00. Requires BuildsDomain.
 */
trait SeedsExpenses
{
    protected Department $purchases;

    protected Department $food;

    protected Department $logistics;

    protected function seedExpenses(): void
    {
        $this->purchases = Department::factory()->create(['name' => 'مشتريات']);
        $this->food = Department::factory()->create(['name' => 'إطعام']);
        $this->logistics = Department::factory()->create(['name' => 'لوجستي']);

        $this->spend(['department_id' => $this->purchases->getKey(), 'item_name' => 'ورق', 'quantity' => '2', 'unit_price' => '1500', 'expense_date' => '2026-10-05']);
        $this->spend(['department_id' => $this->purchases->getKey(), 'item_name' => 'ورق', 'quantity' => '1', 'unit_price' => '1500', 'expense_date' => '2026-09-10']);
        $this->spend(['department_id' => $this->food->getKey(), 'item_name' => 'تمر', 'quantity' => '2.5', 'unit_price' => '12000', 'expense_date' => '2026-10-01']);
        $this->spend(['department_id' => $this->food->getKey(), 'item_name' => 'ماء', 'quantity' => '10', 'unit_price' => '250.50', 'expense_date' => '2026-08-20']);
    }
}
