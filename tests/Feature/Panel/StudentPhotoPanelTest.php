<?php

namespace Tests\Feature\Panel;

use App\Actions\Students\SaveStudent;
use App\Filament\Pages\ScanAttendance;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use App\Services\Students\StudentPhotos;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Pictures;

class StudentPhotoPanelTest extends PanelTestCase
{
    private function withPhoto(string $name = 'أحمد الحموي'): Student
    {
        return app(SaveStudent::class)->handle(['full_name' => $name, 'photo' => Pictures::png()]);
    }

    public function test_a_student_is_registered_with_a_photo_or_without_one(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm(['full_name' => 'بلال الشامي'])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateStudent::class)
            ->fillForm(['full_name' => 'أحمد الحموي', 'photo' => Pictures::png()])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Student::query()->where('full_name', 'بلال الشامي')->sole()->photo_path);

        $withPhoto = Student::query()->where('full_name', 'أحمد الحموي')->sole();
        $this->assertNotNull($withPhoto->photo_path);
        Storage::disk(StudentPhotos::DISK)->assertExists($withPhoto->photo_path);
    }

    public function test_a_file_that_is_not_a_picture_is_refused_under_its_field(): void
    {
        Livewire::test(CreateStudent::class)
            ->fillForm(['full_name' => 'أحمد الحموي', 'photo' => Pictures::pretender()])
            ->call('create')
            ->assertHasFormErrors(['photo']);

        $this->assertSame(0, Student::query()->count());
    }

    public function test_the_photo_is_replaced_or_removed_from_the_edit_page(): void
    {
        $student = $this->withPhoto();
        $first = $student->photo_path;

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->assertFormFieldVisible('remove_photo')
            ->fillForm(['photo' => Pictures::png('new.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $second = $student->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk(StudentPhotos::DISK)->assertMissing($first);

        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->fillForm(['remove_photo' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($student->refresh()->photo_path);
        Storage::disk(StudentPhotos::DISK)->assertMissing($second);

        // Nothing to remove any more: the switch is gone.
        Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
            ->assertFormFieldHidden('remove_photo');
    }

    public function test_the_list_gets_a_column_of_photos_once_a_student_has_one(): void
    {
        Student::factory()->create();

        Livewire::test(ListStudents::class)->assertTableColumnHidden('photo');

        $student = $this->withPhoto();

        Livewire::test(ListStudents::class)
            ->assertTableColumnVisible('photo')
            ->assertSee((string) StudentPresenter::photoUrl($student), escape: false)
            // Whoever has no photo gets the same grey figure, so the names stay in line.
            ->assertSee(asset('img/student.svg'), escape: false);
    }

    public function test_the_file_of_the_student_shows_the_photo(): void
    {
        $student = $this->withPhoto();

        $this->get(StudentResource::getUrl('view', ['record' => $student]))
            ->assertOk()
            ->assertSee((string) StudentPresenter::photoUrl($student), escape: false);
    }

    public function test_scanning_a_card_shows_the_face_of_its_owner(): void
    {
        $withPhoto = $this->enroll($this->withPhoto());
        $without = $this->enroll(Student::factory()->create());

        Livewire::test(ScanAttendance::class)
            ->set('cardCode', $withPhoto->card_code)
            ->call('scan')
            ->assertSet('result.photo', StudentPresenter::photoUrl($withPhoto->student))
            ->assertSeeHtml('rawda-scan__photo')
            ->set('cardCode', $without->card_code)
            ->call('scan')
            ->assertSet('result.photo', null)
            ->assertDontSeeHtml('rawda-scan__photo');
    }
}
