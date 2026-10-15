<?php

namespace Tests\Feature;

use App\Actions\Students\DeleteStudent;
use App\Actions\Students\SaveStudent;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Pictures;
use Tests\TestCase;

class StudentPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function photos(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(StudentPhotos::DISK);
    }

    private function withPhoto(?Student $student = null): Student
    {
        return app(SaveStudent::class)->handle(['full_name' => 'أحمد الحموي', 'photo' => Pictures::png()], $student);
    }

    public function test_a_student_needs_no_photo(): void
    {
        $student = app(SaveStudent::class)->handle(['full_name' => 'أحمد الحموي']);

        $this->assertNull($student->photo_path);
        $this->assertNull(StudentPresenter::photoUrl($student));
        $this->assertSame([], $this->photos()->files(StudentPhotos::FOLDER));
    }

    public function test_a_photo_is_kept_in_the_private_folder_under_a_name_of_its_own(): void
    {
        $student = $this->withPhoto();

        $this->assertMatchesRegularExpression('#^student-photos/'.$student->getKey().'-[a-z0-9]{10}\.png$#', (string) $student->photo_path);
        $this->assertSame(Pictures::pngBytes(), $this->photos()->get($student->photo_path));
        $this->assertSame($student->photo_path, $student->fresh()->photo_path);

        // A photo says nothing about whether the profile is complete.
        $this->assertFalse($student->is_profile_complete);
    }

    public function test_a_new_photo_replaces_the_old_one_and_gets_a_new_address(): void
    {
        $student = $this->withPhoto();
        $firstName = $student->photo_path;
        $firstUrl = StudentPresenter::photoUrl($student);

        $student = $this->withPhoto($student);

        $this->assertNotSame($firstName, $student->photo_path);
        $this->assertNotSame($firstUrl, StudentPresenter::photoUrl($student));
        $this->assertSame([$student->photo_path], $this->photos()->files(StudentPhotos::FOLDER));
    }

    public function test_saving_the_student_again_leaves_the_photo_alone(): void
    {
        $student = $this->withPhoto();
        $name = $student->photo_path;

        $student = app(SaveStudent::class)->handle(['full_name' => 'أحمد محمد الحموي', 'remove_photo' => false], $student);

        $this->assertSame('أحمد محمد الحموي', $student->full_name);
        $this->assertSame($name, $student->photo_path);
        $this->photos()->assertExists($name);
    }

    public function test_the_photo_can_be_removed(): void
    {
        $student = $this->withPhoto();
        $name = $student->photo_path;

        $student = app(SaveStudent::class)->handle(['full_name' => $student->full_name, 'remove_photo' => true], $student);

        $this->assertNull($student->fresh()->photo_path);
        $this->photos()->assertMissing($name);
    }

    public function test_only_a_picture_of_a_reasonable_size_is_accepted(): void
    {
        $refused = [
            'text that calls itself a picture' => Pictures::pretender(),
            'a document' => UploadedFile::fake()->create('card.pdf', 20, 'application/pdf'),
            'a drawing that can carry scripts' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'a huge picture' => UploadedFile::fake()->create('photo.png', 3000, 'image/png'),
        ];

        foreach ($refused as $case => $file) {
            try {
                app(SaveStudent::class)->handle(['full_name' => 'أحمد', 'photo' => $file]);
                $this->fail("Accepted: {$case}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('photo', $exception->errors(), $case);
            }
        }

        $this->assertSame(0, Student::query()->count());
        $this->assertSame([], $this->photos()->files(StudentPhotos::FOLDER));
    }

    public function test_deleting_the_student_deletes_the_photo(): void
    {
        $student = $this->withPhoto();
        $name = $student->photo_path;

        app(DeleteStudent::class)->handle($student);

        $this->photos()->assertMissing($name);
    }

    public function test_only_the_signed_in_admin_is_handed_a_photo(): void
    {
        $admin = User::factory()->create();
        $student = $this->withPhoto();
        $url = (string) StudentPresenter::photoUrl($student);

        $this->get($url)->assertRedirect('/admin/login');

        $this->actingAs($admin);

        $response = $this->get($url)->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame(Pictures::pngBytes(), (string) file_get_contents($response->baseResponse->getFile()->getPathname()));

        // No photo, or a photo whose file is gone: there is nothing to hand out.
        $without = app(SaveStudent::class)->handle(['full_name' => 'بلا صورة']);
        $this->get(route('filament.admin.students.photo', ['student' => $without->getKey()]))->assertNotFound();

        $this->photos()->delete($student->photo_path);
        $this->get($url)->assertNotFound();
    }
}
