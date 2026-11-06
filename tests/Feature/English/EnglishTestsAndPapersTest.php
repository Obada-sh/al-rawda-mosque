<?php

namespace Tests\Feature\English;

use App\Actions\English\Enrollments\UpdateEnglishEnrollmentDates;
use App\Actions\English\Papers\DeleteEnglishPaper;
use App\Actions\English\Papers\SaveEnglishPaper;
use App\Actions\English\Tests\DeleteEnglishTest;
use App\Actions\English\Tests\SaveEnglishTest;
use App\Actions\English\Tests\SaveEnglishTestResults;
use App\Enums\English\TestKind;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishTest;
use App\Models\English\EnglishTestResult;
use App\Services\Backup\KeptFiles;
use App\Services\English\EnglishPapers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\BuildsEnglish;
use Tests\Support\Pictures;
use Tests\TestCase;

/**
 * The exams and recitations of an English course with each student's
 * mark, and the course's papers.
 */
class EnglishTestsAndPapersTest extends TestCase
{
    use BuildsDomain;
    use BuildsEnglish;
    use RefreshDatabase;

    private EnglishCourse $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->course = EnglishCourse::factory()->create(['start_date' => '2026-09-01', 'end_date' => '2026-12-31']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function test(array $overrides = [], ?EnglishTest $test = null): EnglishTest
    {
        return app(SaveEnglishTest::class)->handle($this->course, $overrides + [
            'title' => 'امتحان الوحدة الأولى',
            'kind' => TestKind::Exam->value,
            'held_on' => '2026-10-05',
            'max_mark' => '٥٠',
        ], $test);
    }

    public function test_a_test_is_added_inside_the_course(): void
    {
        $test = $this->test(['kind' => TestKind::Recitation->value]);

        $this->assertSame(50, $test->max_mark);
        $this->assertSame(TestKind::Recitation, $test->kind);
        $this->assertViolates('english_test.outside_course', fn () => $this->test(['held_on' => '2027-01-05']));
        $this->assertThrows(fn () => $this->test(['max_mark' => '0']), ValidationException::class);
        $this->assertThrows(fn () => $this->test(['kind' => 'quiz']), ValidationException::class);
    }

    public function test_marks_are_written_out_of_the_full_mark_or_absent(): void
    {
        $test = $this->test();
        $ahmad = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');
        $lina = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');
        $omar = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');

        $count = app(SaveEnglishTestResults::class)->handle($test, ['marks' => [
            $ahmad->getKey() => ['mark' => '٤٥.٥', 'notes' => 'ممتاز'],
            $lina->getKey() => ['is_absent' => true],
            $omar->getKey() => ['mark' => null],
        ]]);

        $this->assertSame(2, $count);
        $this->assertSame('45.50', EnglishTestResult::query()->where('english_enrollment_id', $ahmad->getKey())->value('mark'));
        $this->assertTrue((bool) EnglishTestResult::query()->where('english_enrollment_id', $lina->getKey())->value('is_absent'));
        $this->assertNull(EnglishTestResult::query()->where('english_enrollment_id', $lina->getKey())->value('mark'));

        // A row emptied again removes its result.
        app(SaveEnglishTestResults::class)->handle($test, ['marks' => [$ahmad->getKey() => ['mark' => null]]]);
        $this->assertSame(1, $test->results()->count());

        // The student's marks are found from their file.
        $this->assertSame(1, $lina->student->englishTestResults()->count());
    }

    public function test_marks_rules(): void
    {
        $test = $this->test();
        $student = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');
        $lateComer = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-10-06');
        $other = $this->enrollInEnglish(enrolledOn: now()->subDays(30)->toDateString());

        $results = app(SaveEnglishTestResults::class);

        $this->assertThrows(fn () => $results->handle($test, ['marks' => [$student->getKey() => ['mark' => '51']]]), ValidationException::class);
        $this->assertThrows(fn () => $results->handle($test, ['marks' => [$student->getKey() => ['mark' => '40', 'is_absent' => true]]]), ValidationException::class);
        $this->assertThrows(fn () => $results->handle($test, ['marks' => [$student->getKey() => ['mark' => '40.555']]]), ValidationException::class);
        $this->assertViolates('english_test.student_not_in_course', fn () => $results->handle($test, ['marks' => [$lateComer->getKey() => ['mark' => '40']]]));
        $this->assertViolates('english_test.student_not_in_course', fn () => $results->handle($test, ['marks' => [$other->getKey() => ['mark' => '40']]]));

        $this->assertEqualsCanonicalizing([$student->getKey()], $results->candidates($test)->modelKeys());

        $future = $this->test(['title' => 'الامتحان النهائي', 'held_on' => '2026-12-20']);
        $this->assertViolates('english_test.not_held_yet', fn () => $results->handle($future, ['marks' => [$student->getKey() => ['mark' => '40']]]));

        $this->assertSame(0, EnglishTestResult::query()->count());
    }

    public function test_a_student_who_left_keeps_the_marks_of_the_tests_before(): void
    {
        $test = $this->test();
        $student = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');
        app(SaveEnglishTestResults::class)->handle($test, ['marks' => [$student->getKey() => ['mark' => '30']]]);

        // Leaving before the test is refused: the mark is on its day.
        $this->assertViolates('english_enrollment.records_after_left_on', fn () => app(UpdateEnglishEnrollmentDates::class)->handle($student, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-10-01']));

        app(UpdateEnglishEnrollmentDates::class)->handle($student, ['enrolled_on' => '2026-09-01', 'left_on' => '2026-10-06']);
        $this->assertEqualsCanonicalizing([$student->getKey()], app(SaveEnglishTestResults::class)->candidates($test)->modelKeys());
    }

    public function test_the_full_mark_never_goes_below_a_mark_given_and_a_test_goes_with_its_marks(): void
    {
        $test = $this->test();
        $student = $this->enrollInEnglish(course: $this->course, enrolledOn: '2026-09-01');
        app(SaveEnglishTestResults::class)->handle($test, ['marks' => [$student->getKey() => ['mark' => '45']]]);

        $this->assertViolates('english_test.mark_above_max', fn () => $this->test(['max_mark' => '40'], $test));
        $this->assertViolates('english_test.results_before_day', fn () => $this->test(['held_on' => '2026-12-01'], $test));
        $this->assertSame(100, $this->test(['max_mark' => '100'], $test)->max_mark);

        app(DeleteEnglishTest::class)->handle($test);
        $this->assertSame(0, EnglishTestResult::query()->count());
    }

    public function test_papers_are_kept_replaced_opened_and_deleted_with_their_files(): void
    {
        $disk = Storage::disk(EnglishPapers::DISK);

        $paper = app(SaveEnglishPaper::class)->handle($this->course, [
            'title' => 'كلمات الدرس الأول',
            'file' => UploadedFile::fake()->createWithContent('lesson 1.pdf', '%PDF-1.4 test'),
        ]);

        $this->assertMatchesRegularExpression('#^english-papers/'.$this->course->getKey().'-[a-z0-9]{10}\.pdf$#', $paper->file_path);
        $this->assertSame('lesson 1.pdf', $paper->original_name);
        $disk->assertExists($paper->file_path);
        $this->assertArrayHasKey($paper->file_path, app(KeptFiles::class)->all());

        $first = $paper->file_path;
        app(SaveEnglishPaper::class)->handle($this->course, ['title' => 'كلمات الدرس الأول', 'file' => Pictures::png('board.png')], $paper);
        $paper->refresh();
        $this->assertStringEndsWith('.png', $paper->file_path);
        $this->assertTrue($paper->isImage());
        $disk->assertMissing($first);

        // The title changes without a new file.
        app(SaveEnglishPaper::class)->handle($this->course, ['title' => 'السبورة'], $paper);
        $this->assertSame('السبورة', $paper->fresh()->title);
        $disk->assertExists($paper->file_path);

        // A new paper needs its file, of a kind that is allowed.
        $this->assertThrows(fn () => app(SaveEnglishPaper::class)->handle($this->course, ['title' => 'بلا ملف']), ValidationException::class);
        $this->assertThrows(fn () => app(SaveEnglishPaper::class)->handle($this->course, [
            'title' => 'برنامج',
            'file' => UploadedFile::fake()->createWithContent('run.exe', 'MZ'),
        ]), ValidationException::class);

        app(DeleteEnglishPaper::class)->handle($paper);
        $disk->assertMissing($paper->file_path);
        $this->assertSame(0, $this->course->papers()->count());
    }
}
