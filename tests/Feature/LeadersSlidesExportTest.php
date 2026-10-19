<?php

namespace Tests\Feature;

use App\Actions\Export\DownloadLeadersTemplate;
use App\Actions\Export\ExportLeadersSlides;
use App\Actions\Export\RestoreDefaultLeadersTemplate;
use App\Actions\Export\SaveLeadersTemplate;
use App\Actions\Export\SlideTemplateViolation;
use App\Contracts\SettingsStore;
use App\Enums\ExamGrade;
use App\Models\Circle;
use App\Models\Student;
use App\Services\Export\ExportedFile;
use App\Services\Export\LeadersTemplates;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\SeedsReportWeek;
use Tests\Support\Deck;
use Tests\TestCase;
use ZipArchive;

/**
 * The PowerPoint file of the students of each circle, on the week
 * described in SeedsReportWeek, and the template it is written on.
 *
 * The default design is a board: one cell (a name and its number) is
 * repeated for every student. A design of the admin may instead have
 * numbered places (tests/Fixtures/slides/numbered-*.pptx).
 */
class LeadersSlidesExportTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
        $this->seedReportWeek();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function export(array $overrides = []): ExportedFile
    {
        return app(ExportLeadersSlides::class)->handle($overrides + [
            'course_id' => $this->course->getKey(),
            'criterion' => 'pages',
            'seconds_per_slide' => 8,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function deck(array $overrides = []): Deck
    {
        return new Deck($this->export($overrides)->contents);
    }

    /**
     * What a slide of the board shows: its title, then each student's name and number, the first one first.
     *
     * @return array{string, list<array{string, string}>}
     */
    private static function board(Deck $deck, int $slide): array
    {
        return [
            $deck->named($slide, 'title')[0]['text'] ?? '',
            array_map(
                fn (array $name, array $score): array => [$name['text'], $score['text']],
                $deck->named($slide, 'cell name'),
                $deck->named($slide, 'cell score'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $fields
     */
    private function assertRefused(array $overrides, array $fields): void
    {
        try {
            $this->export($overrides);
            $this->fail('The input was accepted: '.json_encode($overrides, JSON_UNESCAPED_UNICODE));
        } catch (ValidationException $exception) {
            $this->assertSame($fields, array_keys($exception->errors()));
        }
    }

    /** A design with that many numbered places. */
    private static function numbered(int $places): string
    {
        return base_path("tests/Fixtures/slides/numbered-{$places}.pptx");
    }

    private static function upload(string $path, string $name = 'my-design.pptx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
    }

    private function useDesign(string $path): void
    {
        app(SaveLeadersTemplate::class)->handle(['template' => self::upload($path)]);
    }

    /**
     * A copy of a template after an edit of its slide.
     *
     * @param  callable(string): string  $edit
     */
    private function editedTemplate(string $template, callable $edit): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tpl');
        copy($template, $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('ppt/slides/slide1.xml', $edit((string) $zip->getFromName('ppt/slides/slide1.xml')));
        $zip->close();

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    public function test_each_circle_gets_a_slide_of_its_students_best_first(): void
    {
        $file = $this->export(['from' => '2026-10-01', 'until' => '2026-10-07']);

        $this->assertSame('أوائل الحلقات - دورة الخريف.pptx', $file->name);
        $this->assertSame('application/vnd.openxmlformats-officedocument.presentationml.presentation', $file->mimeType);

        $deck = new Deck($file->contents);

        // خالد recited no new page, so his circle has nothing to show and no slide.
        $this->assertCount(1, $deck->slides());
        $this->assertSame(['حفظ حلقة أ', [['أحمد', '12'], ['بلال', '4']]], self::board($deck, 0));
        $this->assertStringNotContainsString('{{', implode(' ', $deck->paragraphs(0)));
        // The design's background and the mosque's logo are inside the file.
        $this->assertCount(2, array_filter($deck->parts(), fn (string $part): bool => str_starts_with($part, 'ppt/media/')));
    }

    public function test_all_the_students_of_a_circle_are_listed_those_with_nothing_yet_last(): void
    {
        $this->enroll(Student::factory()->create(['full_name' => 'سامي']), $this->course, $this->circleA);

        $deck = $this->deck(['criterion' => 'points']);

        $this->assertCount(2, $deck->slides());
        $this->assertSame(['نقاط حلقة أ', [['أحمد', '27'], ['بلال', '6'], ['سامي', '0']]], self::board($deck, 0));
        $this->assertSame(['نقاط حلقة ب', [['خالد', '9']]], self::board($deck, 1));
    }

    public function test_asked_for_a_number_only_the_first_who_have_something_are_listed(): void
    {
        $this->enroll(Student::factory()->create(['full_name' => 'سامي']), $this->course, $this->circleA);

        $deck = $this->deck(['criterion' => 'points', 'top' => '١']);

        $this->assertSame(['نقاط حلقة أ', [['أحمد', '27']]], self::board($deck, 0));

        // Three asked for, two with points: the student with nothing is not added to make up the number.
        $this->assertSame(['نقاط حلقة أ', [['أحمد', '27'], ['بلال', '6']]], self::board($this->deck(['criterion' => 'points', 'top' => 3]), 0));
    }

    public function test_one_circle_can_be_exported_on_its_own(): void
    {
        $file = $this->export(['criterion' => 'points', 'circle_id' => $this->circleB->getKey()]);
        $deck = new Deck($file->contents);

        $this->assertSame('أوائل حلقة ب - دورة الخريف.pptx', $file->name);
        $this->assertCount(1, $deck->slides());
        $this->assertSame(['نقاط حلقة ب', [['خالد', '9']]], self::board($deck, 0));

        // A circle is named after the word «حلقة» once only.
        $this->circleB->update(['name' => 'الفرقان']);
        $this->assertSame('نقاط حلقة الفرقان', self::board($this->deck(['criterion' => 'points', 'circle_id' => $this->circleB->getKey()]), 0)[0]);
    }

    public function test_a_circle_of_another_course_is_refused(): void
    {
        $elsewhere = Circle::factory()->create();

        $this->assertViolates(ExportLeadersSlides::CIRCLE_OF_ANOTHER_COURSE, fn () => $this->export(['circle_id' => $elsewhere->getKey()]));
    }

    public function test_attendance_shows_the_days_attended_and_ranks_by_the_rate(): void
    {
        // سامي joined on the 4th and came to the one session held since: all of his sessions.
        $sami = $this->enroll(Student::factory()->create(['full_name' => 'سامي']), $this->course, $this->circleA, '2026-10-04');
        $this->attend($sami, '2026-10-05');

        $deck = $this->deck(['criterion' => 'attendance']);

        // أحمد came to all three and is ahead of سامي on points; بلال came to one of three.
        $this->assertSame(['حضور حلقة أ', [['أحمد', '3'], ['سامي', '1'], ['بلال', '1']]], self::board($deck, 0));
        $this->assertSame(['حضور حلقة ب', [['خالد', '2']]], self::board($deck, 1));
    }

    public function test_exams_show_the_juz_passed_in_the_period(): void
    {
        $this->examine($this->ahmad, 30, '2026-10-02', ExamGrade::Excellent);
        $this->examine($this->ahmad, 29, '2026-10-03', ExamGrade::Retake);
        $this->examine($this->ahmad, 28);
        $this->examine($this->bilal, 30, '2026-10-04', ExamGrade::Acceptable);
        $this->examine($this->bilal, 29, '2026-10-06', ExamGrade::VeryGood);

        // A retake and a waiting exam are not passes; خالد passed nothing, so his circle has no slide.
        $deck = $this->deck(['criterion' => 'exams']);
        $this->assertCount(1, $deck->slides());
        $this->assertSame(['سبر حلقة أ', [['بلال', '2'], ['أحمد', '1']]], self::board($deck, 0));

        // Until the 5th, both passed one juz: أحمد recited more new pages, so he comes first.
        $this->assertSame([['أحمد', '1'], ['بلال', '1']], self::board($this->deck(['criterion' => 'exams', 'until' => '2026-10-05']), 0)[1]);
    }

    public function test_a_large_circle_is_shared_evenly_between_slides(): void
    {
        foreach (range(1, 40) as $number) {
            $this->enroll(Student::factory()->create(['full_name' => "طالب {$number}"]), $this->course, $this->circleB);
        }

        $deck = $this->deck(['criterion' => 'points']);

        // حلقة أ, then حلقة ب: its 41 students on two slides of 21 and 20.
        $this->assertCount(3, $deck->slides());
        [$firstTitle, $first] = self::board($deck, 1);
        [$secondTitle, $second] = self::board($deck, 2);

        $this->assertSame('نقاط حلقة ب (1/2)', $firstTitle);
        $this->assertSame('نقاط حلقة ب (2/2)', $secondTitle);
        $this->assertCount(21, $first);
        $this->assertCount(20, $second);
        $this->assertSame(['خالد', '9'], $first[0]);
    }

    public function test_the_slides_follow_each_other_and_start_again_forever(): void
    {
        $deck = $this->deck(['criterion' => 'points', 'seconds_per_slide' => '١٢']);

        $this->assertMatchesRegularExpression('/<p:showPr[^>]*\bloop="1"/', $deck->part('ppt/presProps.xml'));

        foreach ([0, 1] as $slide) {
            $this->assertMatchesRegularExpression('/<p:transition[^>]*\badvTm="12000"/', $deck->slide($slide));
        }
    }

    public function test_the_file_can_open_as_a_running_show(): void
    {
        $file = $this->export(['opens_as_show' => true]);

        $this->assertSame('أوائل الحلقات - دورة الخريف.ppsx', $file->name);
        $this->assertSame('application/vnd.openxmlformats-officedocument.presentationml.slideshow', $file->mimeType);
        $this->assertStringContainsString('presentationml.slideshow.main+xml', (new Deck($file->contents))->part('[Content_Types].xml'));
    }

    public function test_a_design_with_numbered_places_is_filled_place_by_place(): void
    {
        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');
        $this->circleA->update(['supervisor_teacher_id' => $this->yaser->getKey()]);
        $this->useDesign(self::numbered(3));

        $deck = $this->deck(['from' => '2026-10-01', 'until' => '2026-10-07']);
        $text = $deck->paragraphs(0);

        $this->assertCount(1, $deck->slides());

        foreach (['جامع الروضة', 'الأوائل في الحفظ', 'حلقة أ', 'المشرف: الشيخ ياسر', 'دورة الخريف  من 01/10/2026 إلى 07/10/2026'] as $line) {
            $this->assertContains($line, $text);
        }

        // Place, name and score of each student; nothing is left of the third, empty row.
        $this->assertSame(
            ['1', 'أحمد', '12 صفحة', '2', 'بلال', '4 صفحات'],
            array_values(array_intersect($text, ['1', '2', '3', 'أحمد', 'بلال', 'خالد', '12 صفحة', '4 صفحات'])),
        );
        $this->assertSame([], array_filter($deck->shapes(0), fn (string $name): bool => str_starts_with($name, 'slot3')));
        $this->assertStringNotContainsString('{{', implode(' ', $text));
        $this->assertContains('logo', $deck->shapes(0));
    }

    public function test_a_period_open_at_one_end_is_worded_as_such(): void
    {
        $this->useDesign(self::numbered(3));

        $this->assertContains('دورة الخريف  منذ 01/10/2026', $this->deck(['from' => '2026-10-01'])->paragraphs(0));
        $this->assertContains('دورة الخريف  حتى 06/10/2026', $this->deck(['until' => '2026-10-06'])->paragraphs(0));
    }

    public function test_tied_students_share_a_place_on_the_slide(): void
    {
        $this->useDesign(self::numbered(3));
        $dawud = $this->enroll(Student::factory()->create(['full_name' => 'داود']), $this->course, $this->circleA);
        $this->recite($dawud, 1, 12, '2026-10-05');

        $deck = $this->deck();
        $text = $deck->paragraphs(0);

        $this->assertSame(
            ['1', 'أحمد', '12 صفحة', '1', 'داود', '12 صفحة', '3', 'بلال', '4 صفحات'],
            array_values(array_intersect($text, ['1', '2', '3', 'أحمد', 'داود', 'بلال', '12 صفحة', '4 صفحات'])),
        );

        // Both first places wear the gold medal; the third row holds a third place.
        $medals = array_values(array_filter($deck->shapes(0), fn (string $name): bool => str_ends_with($name, ' medal')));
        $this->assertSame(['slot1 medal', 'slot2@1 medal', 'slot3@3 medal'], $medals);
    }

    public function test_students_beyond_the_numbered_places_go_on_the_next_slides(): void
    {
        $this->useDesign(self::numbered(1));

        $deck = $this->deck(['criterion' => 'points']);

        // One place a slide: أحمد, then بلال (second place) for حلقة أ, then خالد for حلقة ب.
        $this->assertCount(3, $deck->slides());
        $this->assertContains('أحمد', $deck->paragraphs(0));
        $this->assertContains('بلال', $deck->paragraphs(1));
        $this->assertContains('2', $deck->paragraphs(1));
        $this->assertContains('خالد', $deck->paragraphs(2));
    }

    public function test_a_name_in_the_course_that_windows_refuses_does_not_break_the_file_name(): void
    {
        $this->course->update(['name' => 'دورة 2026/2027: الخريف']);

        $this->assertSame('أوائل الحلقات - دورة 2026-2027- الخريف.pptx', $this->export()->name);
    }

    public function test_the_choices_of_the_export_are_validated(): void
    {
        $this->assertRefused(['course_id' => null], ['course_id']);
        $this->assertRefused(['course_id' => 999999], ['course_id']);
        $this->assertRefused(['circle_id' => 999999], ['circle_id']);
        $this->assertRefused(['criterion' => 'rate'], ['criterion']);
        $this->assertRefused(['criterion' => null], ['criterion']);
        $this->assertRefused(['top' => 0], ['top']);
        $this->assertRefused(['top' => 101], ['top']);
        $this->assertRefused(['top' => 'ثلاثة'], ['top']);
        $this->assertRefused(['seconds_per_slide' => 1], ['seconds_per_slide']);
        $this->assertRefused(['seconds_per_slide' => 301], ['seconds_per_slide']);
        $this->assertRefused(['seconds_per_slide' => null], ['seconds_per_slide']);
        $this->assertRefused(['from' => '2026-10-05', 'until' => '2026-10-01'], ['until']);
        $this->assertRefused(['from' => '2026-13-45'], ['from']);
        $this->assertRefused(['opens_as_show' => 'maybe'], ['opens_as_show']);
    }

    public function test_nothing_is_exported_when_no_student_has_anything_to_show(): void
    {
        // Nobody recited a new page after the 5th.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export(['from' => '2026-10-06']));

        // By points, the 6th only took points away from بلال.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export([
            'criterion' => 'points',
            'from' => '2026-10-06',
            'until' => '2026-10-06',
        ]));

        // No session was held after the 5th, and nobody has passed an exam.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export(['criterion' => 'attendance', 'from' => '2026-10-06']));
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export(['criterion' => 'exams']));

        // One circle alone: خالد recited nothing.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export(['circle_id' => $this->circleB->getKey()]));
    }

    public function test_the_admin_can_replace_the_design_by_a_file_of_their_own(): void
    {
        $templates = app(LeadersTemplates::class);

        $this->assertFalse($templates->hasCustom());
        $this->assertNull($templates->custom());

        $template = app(SaveLeadersTemplate::class)->handle(['template' => self::upload(self::numbered(1))]);

        $this->assertFalse($template->isBoard());
        $this->assertSame(1, $template->itemsPerSlide());
        $this->assertTrue($templates->hasCustom());
        $this->assertSame(1, $templates->custom()?->itemsPerSlide());
        Storage::disk(LeadersTemplates::DISK)->assertExists(LeadersTemplates::FOLDER.'/leaders.pptx');
        $this->assertSame([], $this->deck()->named(0, 'cell name'));

        app(RestoreDefaultLeadersTemplate::class)->handle();

        $this->assertFalse($templates->hasCustom());
        $this->assertCount(2, $this->deck()->named(0, 'cell name'));

        // A board of their own is a design like any other.
        $board = app(SaveLeadersTemplate::class)->handle(['template' => self::upload($templates->defaultPath())]);
        $this->assertTrue($board->isBoard());
        $this->assertGreaterThanOrEqual(30, $board->itemsPerSlide());
    }

    public function test_a_file_that_cannot_give_slides_never_becomes_the_template(): void
    {
        $save = app(SaveLeadersTemplate::class);

        // Not a .pptx by its name.
        foreach ([UploadedFile::fake()->create('design.pdf', 100), UploadedFile::fake()->create('design.ppt', 100)] as $file) {
            try {
                $save->handle(['template' => $file]);
                $this->fail('A file that is not a .pptx was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(['template'], array_keys($exception->errors()));
            }
        }

        try {
            $save->handle([]);
            $this->fail('No file at all was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['template'], array_keys($exception->errors()));
        }

        // A .pptx by its name only.
        $this->assertViolates(SlideTemplateViolation::NOT_A_PRESENTATION, fn () => $save->handle([
            'template' => UploadedFile::fake()->createWithContent('design.pptx', 'this is not a presentation'),
        ]));

        // A finished presentation: nothing left to fill.
        $finished = (string) tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($finished, $this->export()->contents);
        $this->assertViolates(SlideTemplateViolation::NO_PLACEHOLDERS, fn () => $save->handle(['template' => self::upload($finished)]));
        @unlink($finished);

        // A slide that names the circle but no student.
        $withoutStudents = $this->editedTemplate(self::numbered(1), fn (string $xml): string => str_replace(
            ['{{place1}}', '{{name1}}', '{{score1}}'],
            ['1', 'الاسم', 'الرقم'],
            $xml,
        ));
        $this->assertViolates(SlideTemplateViolation::NO_SLOTS, fn () => $save->handle(['template' => self::upload($withoutStudents)]));

        // A board whose area is too small for even one cell.
        $cramped = $this->editedTemplate(resource_path('pptx/leaders.pptx'), fn (string $xml): string => (string) preg_replace(
            '#(name="cells".*?<a:ext cx=")\d+(" cy=")\d+#s',
            '${1}200000${2}200000',
            $xml,
        ));
        $this->assertViolates(SlideTemplateViolation::NO_ROOM, fn () => $save->handle(['template' => self::upload($cramped)]));

        $this->assertFalse(app(LeadersTemplates::class)->hasCustom());
    }

    public function test_a_template_that_is_too_heavy_is_refused(): void
    {
        try {
            app(SaveLeadersTemplate::class)->handle(['template' => UploadedFile::fake()->create('design.pptx', 10241)]);
            $this->fail('A file above the size limit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['template' => ['حجم القالب لا يتجاوز 10 ميغابايت.']], $exception->errors());
        }
    }

    public function test_an_uploaded_template_that_can_no_longer_be_read_stops_the_export_with_a_reason(): void
    {
        Storage::disk(LeadersTemplates::DISK)->put(LeadersTemplates::FOLDER.'/leaders.pptx', 'damaged');

        $this->assertTrue(app(LeadersTemplates::class)->hasCustom());
        $this->assertNull(app(LeadersTemplates::class)->custom());
        $this->assertViolates(ExportLeadersSlides::UNUSABLE_TEMPLATE, fn () => $this->export());
    }

    public function test_the_admin_can_take_a_copy_of_the_default_design_to_start_from(): void
    {
        $file = app(DownloadLeadersTemplate::class)->default();

        $this->assertSame('قالب شرائح الأوائل.pptx', $file->name);
        $this->assertSame(file_get_contents(resource_path('pptx/leaders.pptx')), $file->contents);
    }

    public function test_the_admin_can_take_their_own_design_back(): void
    {
        $download = app(DownloadLeadersTemplate::class);

        $this->assertViolates(DownloadLeadersTemplate::NO_CUSTOM_TEMPLATE, fn () => $download->custom());

        $this->useDesign(self::numbered(3));

        $file = $download->custom();

        $this->assertSame('قالب شرائح الأوائل - قالبي.pptx', $file->name);
        $this->assertSame(file_get_contents(self::numbered(3)), $file->contents);
    }
}
