<?php

namespace Tests\Feature\Panel;

use App\Contracts\SettingsStore;
use App\Enums\ExpenseKind;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Department;
use App\Models\Expense;
use App\Support\SettingKey;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

/**
 * Expenses and the general settings.
 */
class FinanceAndSettingsPanelTest extends PanelTestCase
{
    public function test_an_expense_is_recorded_and_its_total_is_computed(): void
    {
        $department = Department::factory()->create();

        Livewire::test(ManageExpenses::class)
            ->callAction('create', data: [
                'department_id' => $department->getKey(),
                'item_name' => 'تمر',
                'quantity' => '2.5',
                'unit_price' => '12000',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $expense = Expense::query()->sole();
        $this->assertSame(['2.500', '12000.00', '30000.00'], [$expense->quantity, $expense->unit_price, $expense->total]);
        $this->assertSame('2026-10-07', $expense->expense_date->toDateString());

        Livewire::test(ManageExpenses::class)
            ->assertCanSeeTableRecords([$expense])
            ->assertSee('30,000 ل.س');
    }

    public function test_the_total_is_shown_while_the_admin_types(): void
    {
        Livewire::test(ManageExpenses::class)
            ->mountAction('create')
            ->assertSchemaComponentStateSet('total_preview', '—')
            ->fillForm(['quantity' => '3', 'unit_price' => '1250.50'])
            ->assertSchemaComponentStateSet('total_preview', '3,751.50 ل.س');
    }

    public function test_an_amount_paid_as_a_whole_is_recorded_with_a_description_only(): void
    {
        $department = Department::factory()->create();

        // Choosing "amount paid as a whole" leaves only the description and the amount.
        Livewire::test(ManageExpenses::class)
            ->mountAction('create')
            ->assertSchemaStateSet(['kind' => ExpenseKind::Itemized->value])
            ->fillForm(['kind' => ExpenseKind::LumpSum->value])
            ->assertFormFieldHidden('quantity')
            ->assertFormFieldHidden('unit_price')
            ->assertFormFieldVisible('amount');

        Livewire::test(ManageExpenses::class)
            ->callAction('create', data: [
                'kind' => ExpenseKind::LumpSum->value,
                'department_id' => $department->getKey(),
                'item_name' => 'مشتريات متنوعة لحفل الختام',
                'amount' => '75000',
            ])
            ->assertHasNoActionErrors();

        $expense = Expense::query()->sole();
        $this->assertSame([null, null, '75000.00'], [$expense->quantity, $expense->unit_price, $expense->total]);

        Livewire::test(ManageExpenses::class)
            ->assertCanSeeTableRecords([$expense])
            ->assertSee('75,000 ل.س')
            ->assertSee(ExpenseKind::LumpSum->label())
            // Corrected as it was written.
            ->mountAction(TestAction::make('edit')->table($expense))
            ->assertSchemaStateSet(['kind' => ExpenseKind::LumpSum->value, 'amount' => '75000.00', 'item_name' => 'مشتريات متنوعة لحفل الختام'])
            ->fillForm(['amount' => '80000.50'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('80000.50', $expense->fresh()->total);

        Livewire::test(ManageExpenses::class)
            ->callAction('create', data: [
                'kind' => ExpenseKind::LumpSum->value,
                'department_id' => $department->getKey(),
                'item_name' => 'ضيافة',
                'amount' => '',
            ])
            ->assertHasActionErrors(['amount']);
    }

    public function test_an_expense_is_corrected_and_deleted(): void
    {
        $expense = $this->spend(['quantity' => '2', 'unit_price' => '1500', 'expense_date' => '2026-10-05']);

        Livewire::test(ManageExpenses::class)
            ->mountAction(TestAction::make('edit')->table($expense))
            ->assertSchemaStateSet(['item_name' => 'مياه شرب', 'expense_date' => '2026-10-05'])
            ->fillForm(['quantity' => '4'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $expense->refresh();
        $this->assertSame('6000.00', $expense->total);
        $this->assertSame('2026-10-05', $expense->expense_date->toDateString());

        Livewire::test(ManageExpenses::class)
            ->callAction(TestAction::make('delete')->table($expense));

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_expense_validation_messages_appear_under_the_fields(): void
    {
        Livewire::test(ManageExpenses::class)
            ->callAction('create', data: [
                'department_id' => null,
                'item_name' => '',
                'quantity' => '0',
                'unit_price' => 'abc',
            ])
            ->assertHasActionErrors(['department_id', 'item_name', 'quantity', 'unit_price']);

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_an_inactive_department_is_offered_only_to_the_expense_that_already_has_it(): void
    {
        $active = Department::factory()->create(['name' => 'مشتريات عامة']);
        $inactive = Department::factory()->create(['name' => 'قسم متوقف']);
        $old = $this->spend(['department_id' => $inactive->getKey()]);
        $inactive->update(['is_active' => false]);

        Livewire::test(ManageExpenses::class)
            ->mountAction('create')
            ->assertFormFieldExists('department_id', fn (Select $field): bool => $field->getOptions() === [
                $active->getKey() => 'مشتريات عامة',
            ]);

        Livewire::test(ManageExpenses::class)
            ->mountAction(TestAction::make('edit')->table($old))
            ->assertFormFieldExists('department_id', fn (Select $field): bool => $field->getOptions() === [
                $inactive->getKey() => 'قسم متوقف',
                $active->getKey() => 'مشتريات عامة',
            ])
            ->fillForm(['item_name' => 'مياه معدنية'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('مياه معدنية', $old->refresh()->item_name);
        $this->assertSame($inactive->getKey(), $old->department_id);
    }

    public function test_the_settings_are_saved_and_the_mosque_name_becomes_the_title_of_the_system(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['mosque_name' => null, 'incomplete_profiles_reminder_days' => '7'])
            ->fillForm(['mosque_name' => '  جامع الروضة  ', 'incomplete_profiles_reminder_days' => '٣'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('تم حفظ الإعدادات');

        $settings = app(SettingsStore::class);
        $this->assertSame('جامع الروضة', $settings->get(SettingKey::MOSQUE_NAME));
        $this->assertSame('3', $settings->get(SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS));
        $this->assertSame('جامع الروضة', Filament::getDefaultPanel()->getBrandName());

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['mosque_name' => 'جامع الروضة', 'incomplete_profiles_reminder_days' => '3']);
    }

    public function test_the_reminder_interval_must_be_a_sensible_number_of_days(): void
    {
        foreach (['', '0', '366', 'abc'] as $days) {
            Livewire::test(ManageSettings::class)
                ->fillForm(['incomplete_profiles_reminder_days' => $days])
                ->call('save')
                ->assertHasFormErrors(['incomplete_profiles_reminder_days']);
        }

        $this->assertNull(app(SettingsStore::class)->get(SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS));
    }
}
