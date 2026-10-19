<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\Reports\KnightRanking;
use App\Filament\Pages\Reports\Statistics;
use App\Filament\Support\Export\LeadersSlidesAction;
use App\Services\Export\LeadersTemplates;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;

/**
 * Exporting the slides of the students of each circle from the report
 * pages, and managing their template from the settings page. The week is the one described in
 * SeedsReportWeek.
 */
class LeadersSlidesPanelTest extends PanelTestCase
{
    use SeedsReportWeek;

    private const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReportWeek();
    }

    private static function design(string $path, string $name = 'my-design.pptx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path));
    }

    public function test_the_export_window_opens_on_the_course_the_circle_and_the_period_of_the_page(): void
    {
        // The Quran Knight page opens on this month; the only active course is offered, with all its circles and students.
        Livewire::test(KnightRanking::class)
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertActionDataSet([
                'course_id' => $this->course->getKey(),
                'circle_id' => null,
                'criterion' => 'pages',
                'from' => '2026-10-01',
                'until' => '2026-10-07',
                'top' => null,
                'seconds_per_slide' => 8,
                'opens_as_show' => false,
            ]);

        // On the statistics page the students are ranked by points, and so are the slides.
        Livewire::test(Statistics::class)
            ->fillForm(['from' => '2026-10-03', 'rank_by' => 'points'], 'filtersForm')
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertActionDataSet([
                'course_id' => $this->course->getKey(),
                'criterion' => 'points',
                'from' => '2026-10-03',
                'until' => null,
            ]);

        // Attendance and exams rank the slides too; a page narrowed to a circle exports that circle.
        foreach (['attendance', 'exams'] as $criterion) {
            Livewire::test(Statistics::class)
                ->fillForm(['course_id' => $this->course->getKey(), 'circle_id' => $this->circleB->getKey(), 'rank_by' => $criterion], 'filtersForm')
                ->mountAction(LeadersSlidesAction::NAME)
                ->assertActionDataSet(['criterion' => $criterion, 'circle_id' => $this->circleB->getKey()]);
        }
    }

    public function test_the_slides_are_downloaded_as_a_powerpoint_file(): void
    {
        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME)
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('أوائل الحلقات - دورة الخريف.pptx', contentType: self::PPTX);

        Livewire::test(Statistics::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['criterion' => 'points', 'top' => '٥', 'opens_as_show' => true])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('أوائل الحلقات - دورة الخريف.ppsx');

        // One circle alone, by the days its students attended.
        Livewire::test(Statistics::class)
            ->callAction(LeadersSlidesAction::NAME, data: [
                'course_id' => $this->course->getKey(),
                'circle_id' => $this->circleB->getKey(),
                'criterion' => 'attendance',
            ])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('أوائل حلقة ب - دورة الخريف.pptx', contentType: self::PPTX);
    }

    public function test_a_wrong_choice_is_explained_under_its_field_and_nothing_is_downloaded(): void
    {
        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['top' => 101, 'seconds_per_slide' => 0, 'course_id' => null])
            ->assertHasActionErrors(['top', 'seconds_per_slide', 'course_id'])
            ->assertNoFileDownloaded();
    }

    public function test_a_period_without_anything_to_show_is_explained_and_nothing_is_downloaded(): void
    {
        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['from' => '2026-10-06'])
            ->assertNotified('لا يوجد ما يُعرض: لم يسمّع أي طالب في هذه الدورة صفحات جديدة في الفترة المختارة.')
            ->assertNoFileDownloaded();

        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['criterion' => 'exams', 'circle_id' => $this->circleA->getKey()])
            ->assertNotified('لا يوجد ما يُعرض: لم ينجح أي طالب في هذه الحلقة في سبر جزء في الفترة المختارة.')
            ->assertNoFileDownloaded();
    }

    public function test_the_settings_page_says_which_design_is_in_use(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertOk()
            ->assertSee('شرائح أوائل الحلقات')
            ->assertSee('المستعمل الآن: التصميم الافتراضي.')
            ->assertActionVisible('uploadLeadersTemplate')
            ->assertActionVisible('downloadDefaultLeadersTemplate')
            ->assertActionHidden('downloadCustomLeadersTemplate')
            ->assertActionHidden('restoreDefaultLeadersTemplate');
    }

    public function test_the_admin_uploads_a_design_of_their_own_and_can_go_back_to_the_default(): void
    {
        $templates = app(LeadersTemplates::class);

        Livewire::test(ManageSettings::class)
            ->callAction('uploadLeadersTemplate', data: ['template' => self::design(base_path('tests/Fixtures/slides/numbered-3.pptx'))])
            ->assertHasNoActionErrors()
            ->assertNotified('تم اعتماد قالبك')
            ->assertSee('المستعمل الآن: قالبك الخاص. أماكنه المرقّمة تتسع لـ 3 طلاب في الشريحة، ويُكمَل بقية الطلاب في شرائح تالية.')
            ->assertActionVisible('downloadCustomLeadersTemplate')
            ->assertActionVisible('restoreDefaultLeadersTemplate');

        $this->assertSame(3, $templates->custom()?->itemsPerSlide());

        Livewire::test(ManageSettings::class)
            ->callAction('downloadCustomLeadersTemplate')
            ->assertFileDownloaded('قالب شرائح الأوائل - قالبي.pptx', contentType: self::PPTX);

        Livewire::test(ManageSettings::class)
            ->callAction('restoreDefaultLeadersTemplate')
            ->assertNotified('عاد التصميم الافتراضي')
            ->assertSee('المستعمل الآن: التصميم الافتراضي.');

        $this->assertFalse($templates->hasCustom());

        // A board of their own: its students are laid out to fit.
        Livewire::test(ManageSettings::class)
            ->callAction('uploadLeadersTemplate', data: ['template' => self::design($templates->defaultPath())])
            ->assertHasNoActionErrors()
            ->assertSee('المستعمل الآن: قالبك الخاص. يرتّب طلاب الحلقة في الشريحة بحجم يناسب عددهم');
    }

    public function test_a_file_that_is_not_a_slide_template_is_refused_with_a_reason(): void
    {
        // Not a .pptx by its name: the message goes under the field.
        Livewire::test(ManageSettings::class)
            ->callAction('uploadLeadersTemplate', data: ['template' => UploadedFile::fake()->create('design.pdf', 50)])
            ->assertHasActionErrors(['template']);

        Livewire::test(ManageSettings::class)
            ->callAction('uploadLeadersTemplate', data: [])
            ->assertHasActionErrors(['template']);

        // A .pptx by its name only: the reason is given, and the window stays open.
        Livewire::test(ManageSettings::class)
            ->callAction('uploadLeadersTemplate', data: [
                'template' => UploadedFile::fake()->createWithContent('design.pptx', 'not a presentation'),
            ])
            ->assertNotified('الملف ليس عرض PowerPoint صالحاً بصيغة ‎.pptx.')
            ->assertActionMounted('uploadLeadersTemplate');

        $this->assertFalse(app(LeadersTemplates::class)->hasCustom());
    }

    public function test_the_default_design_is_downloaded_in_one_click(): void
    {
        Livewire::test(ManageSettings::class)
            ->callAction('downloadDefaultLeadersTemplate')
            ->assertFileDownloaded(
                'قالب شرائح الأوائل.pptx',
                file_get_contents(resource_path('pptx/leaders.pptx')),
                self::PPTX,
            );
    }
}
