<?php

namespace Tests\Feature;

use App\Actions\Circles\DeleteCircle;
use App\Actions\Circles\SaveCircle;
use App\Actions\Teachers\DeleteTeacher;
use App\Actions\Teachers\SaveTeacher;
use App\Filament\Support\Presenters\CirclePresenter;
use App\Filament\Support\Presenters\TeacherPresenter;
use App\Models\Circle;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Circles\CircleLogos;
use App\Services\Files\KeptPictures;
use App\Services\Students\StudentPhotos;
use App\Services\Teachers\TeacherPhotos;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * The teacher's photo (printed on their card) and the circle's logo
 * (printed on the cards of its students), kept like the students' photos.
 */
class TeacherPhotoAndCircleLogoTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(KeptPictures::DISK);
    }

    private function teacher(array $input = [], ?Teacher $teacher = null): Teacher
    {
        return app(SaveTeacher::class)->handle($input + ['full_name' => 'الشيخ ياسر'], $teacher);
    }

    private function circle(array $input = [], ?Circle $circle = null): Circle
    {
        return app(SaveCircle::class)->handle($this->course, $input + ['name' => 'تاج الوقار'], $circle);
    }

    public function test_a_teacher_photo_is_kept_replaced_and_removed(): void
    {
        $teacher = $this->teacher();
        $this->assertNull($teacher->photo_path);
        $this->assertNull(TeacherPresenter::photoUrl($teacher));

        $teacher = $this->teacher(['photo' => Pictures::png()], $teacher);
        $first = $teacher->photo_path;

        $this->assertMatchesRegularExpression('#^teacher-photos/'.$teacher->getKey().'-[a-z0-9]{10}\.png$#', (string) $first);
        $this->assertSame(Pictures::pngBytes(), $this->disk()->get($first));
        $firstUrl = TeacherPresenter::photoUrl($teacher);

        // A new photo gets a new name and a new address; the old file goes.
        $teacher = $this->teacher(['photo' => Pictures::png()], $teacher);
        $this->assertNotSame($first, $teacher->photo_path);
        $this->assertNotSame($firstUrl, TeacherPresenter::photoUrl($teacher));
        $this->assertSame([$teacher->photo_path], $this->disk()->files(TeacherPhotos::FOLDER));

        // Saving again without a photo leaves it alone; asking removes it.
        $kept = $this->teacher(['remove_photo' => false], $teacher)->photo_path;
        $this->assertSame($teacher->photo_path, $kept);

        $this->teacher(['remove_photo' => true], $teacher);
        $this->assertNull($teacher->fresh()->photo_path);
        $this->assertSame([], $this->disk()->files(TeacherPhotos::FOLDER));
    }

    public function test_a_circle_logo_is_kept_replaced_and_removed(): void
    {
        $circle = $this->circle();
        $this->assertNull($circle->logo_path);
        $this->assertNull(CirclePresenter::logoUrl($circle));

        $circle = $this->circle(['logo' => Pictures::png('logo.png')], $circle);
        $first = $circle->logo_path;

        $this->assertMatchesRegularExpression('#^circle-logos/'.$circle->getKey().'-[a-z0-9]{10}\.png$#', (string) $first);
        $this->assertSame(Pictures::pngBytes(), $this->disk()->get($first));

        $circle = $this->circle(['logo' => Pictures::png('logo.png')], $circle);
        $this->assertNotSame($first, $circle->logo_path);
        $this->assertSame([$circle->logo_path], $this->disk()->files(CircleLogos::FOLDER));

        // Renaming the circle leaves the logo alone.
        $renamed = $this->circle(['name' => 'تاج الوقار الجديد'], $circle);
        $this->assertSame($circle->logo_path, $renamed->logo_path);

        $this->circle(['name' => 'تاج الوقار الجديد', 'remove_logo' => true], $circle);
        $this->assertNull($circle->fresh()->logo_path);
        $this->assertSame([], $this->disk()->files(CircleLogos::FOLDER));
    }

    public function test_only_a_picture_of_a_reasonable_size_is_accepted(): void
    {
        $refused = [
            'text that calls itself a picture' => Pictures::pretender('logo.png'),
            'a drawing that can carry scripts' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'a huge picture' => UploadedFile::fake()->create('logo.png', 3000, 'image/png'),
        ];

        foreach ($refused as $case => $file) {
            foreach (['photo' => fn () => $this->teacher(['photo' => $file]), 'logo' => fn () => $this->circle(['logo' => $file])] as $field => $save) {
                try {
                    $save();
                    $this->fail("Accepted as {$field}: {$case}.");
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey($field, $exception->errors(), $case);
                }
            }
        }

        $this->assertSame(0, Teacher::query()->count());
        $this->assertSame(0, Circle::query()->count());
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_deleting_the_teacher_or_the_circle_deletes_its_picture(): void
    {
        $teacher = $this->teacher(['photo' => Pictures::png()]);
        $circle = $this->circle(['logo' => Pictures::png('logo.png')]);

        app(DeleteTeacher::class)->handle($teacher);
        app(DeleteCircle::class)->handle($circle);

        $this->disk()->assertMissing($teacher->photo_path);
        $this->disk()->assertMissing($circle->logo_path);
    }

    public function test_a_folder_never_removes_a_file_of_another_folder(): void
    {
        $teacher = $this->teacher(['photo' => Pictures::png()]);

        app(StudentPhotos::class)->remove($teacher->photo_path);
        app(CircleLogos::class)->remove($teacher->photo_path);
        app(TeacherPhotos::class)->remove('teacher-photos/../.env');

        $this->disk()->assertExists($teacher->photo_path);
        $this->assertNull(app(CircleLogos::class)->path($teacher->photo_path));
    }

    public function test_only_the_signed_in_admin_is_handed_a_teacher_photo_or_a_circle_logo(): void
    {
        $admin = User::factory()->create();
        $teacher = $this->teacher(['photo' => Pictures::png()]);
        $circle = $this->circle(['logo' => Pictures::png('logo.png')]);

        $urls = [(string) TeacherPresenter::photoUrl($teacher), (string) CirclePresenter::logoUrl($circle)];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->actingAs($admin);

        foreach ($urls as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame('image/png', $response->headers->get('Content-Type'));
        }

        // Without a picture there is nothing to hand out.
        $plain = $this->teacher(['full_name' => 'بلا صورة']);
        $this->get(route('filament.admin.teachers.photo', ['teacher' => $plain->getKey()]))->assertNotFound();

        $this->disk()->delete($circle->logo_path);
        $this->get($urls[1])->assertNotFound();
    }
}
