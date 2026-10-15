<?php

namespace Tests\Feature;

use App\Actions\Export\DownloadLeadersTemplate;
use App\Actions\Export\ExportLeadersSlides;
use App\Actions\Export\RestoreDefaultLeadersTemplate;
use App\Actions\Export\SaveLeadersTemplate;
use App\Actions\Export\SlideTemplateViolation;
use App\Contracts\SettingsStore;
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
 * The PowerPoint file of the leaders of each circle, on the week
 * described in SeedsReportWeek, and the template it is written on.
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
            'top' => 3,
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

    private static function defaultTemplate(int $leaders): string
    {
        return resource_path("pptx/leaders-{$leaders}.pptx");
    }

    private static function upload(string $path, string $name = 'my-design.pptx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
    }

    /**
     * A copy of the default template for one leader, after an edit of its slide.
     *
     * @param  callable(string): string  $edit
     */
    private function editedTemplate(callable $edit): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tpl');
        copy(self::defaultTemplate(1), $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('ppt/slides/slide1.xml', $edit((string) $zip->getFromName('ppt/slides/slide1.xml')));
        $zip->close();

        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    public function test_the_file_has_one_slide_for_each_circle_that_has_leaders(): void
    {
        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');
        $this->circleA->update(['supervisor_teacher_id' => $this->yaser->getKey()]);

        $file = $this->export(['from' => '2026-10-01', 'until' => '2026-10-07']);

        $this->assertSame('أوائل الحلقات - دورة الخريف.pptx', $file->name);
        $this->assertSame('application/vnd.openxmlformats-officedocument.presentationml.presentation', $file->mimeType);

        $deck = new Deck($file->contents);

        // خالد recited no new page, so his circle has no slide.
        $this->assertCount(1, $deck->slides());

        $text = $deck->paragraphs(0);

        foreach (['جامع الروضة', 'الأوائل في الحفظ', 'حلقة أ', 'المشرف: الشيخ ياسر', 'دورة الخريف  من 01/10/2026 إلى 07/10/2026'] as $line) {
            $this->assertContains($line, $text);
        }

        // Place, name and score of each leader; nothing is left of the third, empty row.
        $this->assertSame(
            ['1', 'أحمد', '12 صفحة', '2', 'بلال', '4 صفحات'],
            array_values(array_intersect($text, ['1', '2', '3', 'أحمد', 'بلال', 'خالد', '12 صفحة', '4 صفحات'])),
        );
        $this->assertSame([], array_filter($deck->shapes(0), fn (string $name): bool => str_starts_with($name, 'slot3')));
        $this->assertStringNotContainsString('{{', implode(' ', $text));
        // The mosque's logo is on the slide, its picture inside the file.
        $this->assertContains('logo', $deck->shapes(0));
        $this->assertNotSame([], array_filter($deck->parts(), fn (string $part): bool => str_starts_with($part, 'ppt/media/')));
    }

    public function test_the_leaders_can_be_those_with_the_most_points(): void
    {
        $deck = $this->deck(['criterion' => 'points']);

        $this->assertCount(2, $deck->slides());

        $this->assertContains('الأوائل في النقاط', $deck->paragraphs(0));
        $this->assertContains('حلقة أ', $deck->paragraphs(0));
        $this->assertContains('27 نقطة', $deck->paragraphs(0));
        $this->assertContains('6 نقاط', $deck->paragraphs(0));

        $this->assertContains('حلقة ب', $deck->paragraphs(1));
        $this->assertContains('خالد', $deck->paragraphs(1));
        $this->assertContains('9 نقاط', $deck->paragraphs(1));

        // A circle without a supervisor shows no supervisor line; a file without a period shows none.
        $this->assertStringNotContainsString('المشرف', implode(' ', $deck->paragraphs(1)));
        $this->assertContains('دورة الخريف', array_map('trim', $deck->paragraphs(1)));
    }

    public function test_a_period_open_at_one_end_is_worded_as_such(): void
    {
        $this->assertContains('دورة الخريف  منذ 01/10/2026', $this->deck(['from' => '2026-10-01'])->paragraphs(0));
        $this->assertContains('دورة الخريف  حتى 06/10/2026', $this->deck(['until' => '2026-10-06'])->paragraphs(0));
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

    public function test_the_number_of_leaders_picks_the_design_made_for_it(): void
    {
        $this->assertSame(['slot1 card', 'slot1 medal', 'slot1 place', 'slot1 name and score'], array_values(array_filter(
            $this->deck(['top' => 1])->shapes(0),
            fn (string $name): bool => str_starts_with($name, 'slot'),
        )));

        // Ten places on the slide, two leaders in the circle: the eight other rows are removed.
        $rows = array_filter($this->deck(['top' => '١٠'])->shapes(0), fn (string $name): bool => str_ends_with($name, ' name'));
        $this->assertSame(['slot1 name', 'slot2 name'], array_values($rows));
    }

    public function test_tied_students_share_a_place_on_the_slide(): void
    {
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

    public function test_a_name_in_the_course_that_windows_refuses_does_not_break_the_file_name(): void
    {
        $this->course->update(['name' => 'دورة 2026/2027: الخريف']);

        $this->assertSame('أوائل الحلقات - دورة 2026-2027- الخريف.pptx', $this->export()->name);
    }

    public function test_the_choices_of_the_export_are_validated(): void
    {
        $this->assertRefused(['course_id' => null], ['course_id']);
        $this->assertRefused(['course_id' => 999999], ['course_id']);
        $this->assertRefused(['criterion' => 'attendance'], ['criterion']);
        $this->assertRefused(['criterion' => null], ['criterion']);
        $this->assertRefused(['top' => 0], ['top']);
        $this->assertRefused(['top' => 11], ['top']);
        $this->assertRefused(['top' => 'ثلاثة'], ['top']);
        $this->assertRefused(['seconds_per_slide' => 1], ['seconds_per_slide']);
        $this->assertRefused(['seconds_per_slide' => 301], ['seconds_per_slide']);
        $this->assertRefused(['seconds_per_slide' => null], ['seconds_per_slide']);
        $this->assertRefused(['from' => '2026-10-05', 'until' => '2026-10-01'], ['until']);
        $this->assertRefused(['from' => '2026-13-45'], ['from']);
        $this->assertRefused(['opens_as_show' => 'maybe'], ['opens_as_show']);
    }

    public function test_nothing_is_exported_when_no_circle_has_a_leader(): void
    {
        // Nobody recited a new page after the 5th.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export(['from' => '2026-10-06']));

        // By points, the 6th only took points away from بلال.
        $this->assertViolates(ExportLeadersSlides::NO_LEADERS, fn () => $this->export([
            'criterion' => 'points',
            'from' => '2026-10-06',
            'until' => '2026-10-06',
        ]));
    }

    public function test_the_admin_can_replace_the_design_by_a_file_of_their_own(): void
    {
        $templates = app(LeadersTemplates::class);

        $this->assertFalse($templates->hasCustom());
        $this->assertNull($templates->customSlots());

        $slots = app(SaveLeadersTemplate::class)->handle(['template' => self::upload(self::defaultTemplate(1))]);

        $this->assertSame(1, $slots);
        $this->assertTrue($templates->hasCustom());
        $this->assertSame(1, $templates->customSlots());
        Storage::disk(LeadersTemplates::DISK)->assertExists(LeadersTemplates::FOLDER.'/leaders.pptx');

        // Their slide has one place: one leader is shown, however many were asked for.
        $names = array_filter($this->deck(['top' => 5])->shapes(0), fn (string $name): bool => str_contains($name, 'name'));
        $this->assertSame(['slot1 name and score'], array_values($names));

        app(RestoreDefaultLeadersTemplate::class)->handle();

        $this->assertFalse($templates->hasCustom());
        $rows = array_filter($this->deck(['top' => 5])->shapes(0), fn (string $name): bool => str_ends_with($name, ' name'));
        $this->assertSame(['slot1 name', 'slot2 name'], array_values($rows));
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

        // A slide that names the circle but no leader.
        $withoutLeaders = $this->editedTemplate(fn (string $xml): string => str_replace(
            ['{{place1}}', '{{name1}}', '{{score1}}'],
            ['1', 'الاسم', 'الرقم'],
            $xml,
        ));
        $this->assertViolates(SlideTemplateViolation::NO_SLOTS, fn () => $save->handle(['template' => self::upload($withoutLeaders)]));

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
        $this->assertNull(app(LeadersTemplates::class)->customSlots());
        $this->assertViolates(ExportLeadersSlides::UNUSABLE_TEMPLATE, fn () => $this->export());
    }

    public function test_the_admin_can_take_a_copy_of_the_default_design_to_start_from(): void
    {
        $download = app(DownloadLeadersTemplate::class);

        $file = $download->default(['leaders' => '٤']);

        $this->assertSame('قالب شرائح الأوائل - 4.pptx', $file->name);
        $this->assertSame(file_get_contents(self::defaultTemplate(4)), $file->contents);

        foreach ([0, 11, null, 'x'] as $leaders) {
            try {
                $download->default(['leaders' => $leaders]);
                $this->fail('A number of leaders without a default design was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(['leaders'], array_keys($exception->errors()));
            }
        }
    }

    public function test_the_admin_can_take_their_own_design_back(): void
    {
        $download = app(DownloadLeadersTemplate::class);

        $this->assertViolates(DownloadLeadersTemplate::NO_CUSTOM_TEMPLATE, fn () => $download->custom());

        app(SaveLeadersTemplate::class)->handle(['template' => self::upload(self::defaultTemplate(2))]);

        $file = $download->custom();

        $this->assertSame('قالب شرائح الأوائل - قالبي.pptx', $file->name);
        $this->assertSame(file_get_contents(self::defaultTemplate(2)), $file->contents);
    }
}
