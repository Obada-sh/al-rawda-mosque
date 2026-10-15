<?php

namespace Tests\Feature\Panel;

use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\TajweedLevels\Pages\ManageTajweedLevels;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Models\Department;
use App\Models\Student;
use App\Models\TajweedLevel;
use App\Models\Teacher;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Teachers, tajweed levels and departments: three short lists managed in
 * the same way (add, edit and delete in a window).
 */
class SimpleListsTest extends PanelTestCase
{
    public function test_a_teacher_is_added_edited_and_listed(): void
    {
        Livewire::test(ManageTeachers::class)
            ->callAction('create', data: ['full_name' => '  الشيخ أحمد  ', 'phone' => '0944 123 456'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $teacher = Teacher::query()->sole();
        $this->assertSame('الشيخ أحمد', $teacher->full_name);
        $this->assertTrue($teacher->is_active);

        Livewire::test(ManageTeachers::class)
            ->assertCanSeeTableRecords([$teacher])
            ->callAction(TestAction::make('edit')->table($teacher), data: ['full_name' => 'الشيخ أحمد الحلبي', 'is_active' => false])
            ->assertHasNoActionErrors();

        $teacher->refresh();
        $this->assertSame('الشيخ أحمد الحلبي', $teacher->full_name);
        $this->assertFalse($teacher->is_active);
    }

    public function test_teacher_validation_messages_appear_under_the_fields_of_the_window(): void
    {
        Livewire::test(ManageTeachers::class)
            ->callAction('create', data: ['full_name' => '', 'phone' => 'not-a-number'])
            ->assertHasActionErrors(['full_name', 'phone'])
            ->assertActionMounted('create');

        $this->assertSame(0, Teacher::query()->count());
    }

    public function test_a_teacher_with_records_is_not_deleted_and_the_reason_is_shown(): void
    {
        $enrollment = $this->enroll();
        $used = Teacher::factory()->create();
        $unused = Teacher::factory()->create();
        $this->recite($enrollment, 1, 2, teacher: $used);

        Livewire::test(ManageTeachers::class)
            ->callAction(TestAction::make('delete')->table($used))
            ->assertNotified('لا يمكن حذف أستاذ له سجلات. يمكنك تعطيله بدلاً من ذلك.');

        Livewire::test(ManageTeachers::class)
            ->callAction(TestAction::make('delete')->table($unused))
            ->assertNotified('تم الحذف');

        $this->assertNotNull($used->fresh());
        $this->assertNull(Teacher::query()->find($unused->getKey()));
    }

    public function test_tajweed_levels_are_managed_and_a_level_in_use_is_kept(): void
    {
        Livewire::test(ManageTajweedLevels::class)
            ->callAction('create', data: ['name' => 'مبتدئ', 'sort_order' => '1'])
            ->assertHasNoActionErrors();

        $level = TajweedLevel::query()->where('name', 'مبتدئ')->sole();
        $this->assertSame(1, $level->sort_order);

        Livewire::test(ManageTajweedLevels::class)
            ->callAction('create', data: ['name' => 'مبتدئ', 'sort_order' => '2'])
            ->assertHasActionErrors(['name']);

        Livewire::test(ManageTajweedLevels::class)
            ->callAction(TestAction::make('edit')->table($level), data: ['name' => 'المستوى التمهيدي', 'sort_order' => '0'])
            ->assertHasNoActionErrors();

        $this->assertSame('المستوى التمهيدي', $level->refresh()->name);

        Student::factory()->create(['tajweed_level_id' => $level->getKey()]);

        Livewire::test(ManageTajweedLevels::class)
            ->callAction(TestAction::make('delete')->table($level))
            ->assertNotified('لا يمكن حذف مستوى مرتبط بطلاب.');

        $this->assertNotNull($level->fresh());
    }

    public function test_departments_are_managed_and_one_with_expenses_is_kept(): void
    {
        Livewire::test(ManageDepartments::class)
            ->callAction('create', data: ['name' => 'صيانة'])
            ->assertHasNoActionErrors();

        $department = Department::query()->where('name', 'صيانة')->sole();
        $this->assertTrue($department->is_active);

        $this->spend(['department_id' => $department->getKey()]);

        Livewire::test(ManageDepartments::class)
            ->assertCanSeeTableRecords([$department])
            ->callAction(TestAction::make('delete')->table($department))
            ->assertNotified('لا يمكن حذف قسم له مصاريف. يمكنك تعطيله بدلاً من ذلك.');

        Livewire::test(ManageDepartments::class)
            ->callAction(TestAction::make('edit')->table($department), data: ['name' => 'صيانة', 'is_active' => false])
            ->assertHasNoActionErrors();

        $this->assertFalse($department->refresh()->is_active);
    }
}
