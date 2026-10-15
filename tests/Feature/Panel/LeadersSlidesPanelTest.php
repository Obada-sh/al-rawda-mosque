<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\ManageSettings;
use App\Filament\Pages\Reports\KnightRanking;
use App\Filament\Pages\Reports\Statistics;
use App\Filament\Support\Export\LeadersSlidesAction;
use App\Services\Export\LeadersTemplates;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;

/**
 * Exporting the slides of the leaders from the report pages, and managing
 * their template from the settings page. The week is the one described in
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

    private static function template(int $leaders, string $name = 'my-design.pptx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(resource_path("pptx/leaders-{$leaders}.pptx")));
    }

    public function test_the_export_window_opens_on_the_course_and_the_period_of_the_page(): void
    {
        // The Quran Knight page opens on this month; the only active course is offered.
        Livewire::test(KnightRanking::class)
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertActionDataSet([
                'course_id' => $this->course->getKey(),
                'criterion' => 'pages',
                'from' => '2026-10-01',
                'until' => '2026-10-07',
                'top' => 3,
                'seconds_per_slide' => 8,
                'opens_as_show' => false,
            ]);

        // On the statistics page the students are ranked by points, and so are the leaders.
        Livewire::test(Statistics::class)
            ->fillForm(['from' => '2026-10-03', 'rank_by' => 'points'], 'filtersForm')
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertActionDataSet([
                'course_id' => $this->course->getKey(),
                'criterion' => 'points',
                'from' => '2026-10-03',
                'until' => null,
            ]);

        // Leaders are not ranked by attendance: the window falls back to pages.
        Livewire::test(Statistics::class)
            ->fillForm(['rank_by' => 'attendance'], 'filtersForm')
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertActionDataSet(['criterion' => 'pages']);
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
    }

    public function test_a_wrong_choice_is_explained_under_its_field_and_nothing_is_downloaded(): void
    {
        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['top' => 11, 'seconds_per_slide' => 0, 'course_id' => null])
            ->assertHasActionErrors(['top', 'seconds_per_slide', 'course_id'])
            ->assertNoFileDownloaded();
    }

    public function test_a_period_without_leaders_is_explained_and_nothing_is_downloaded(): void
    {
        Livewire::test(KnightRanking::class)
            ->callAction(LeadersSlidesAction::NAME, data: ['from' => '2026-10-06'])
            ->assertNotified('لا يوجد ما يُعرض: لم يسمّع أي طالب في هذه الدورة صفحات جديدة في الفترة المختارة.')
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
            ->callAction('uploadLeadersTemplate', data: ['template' => self::template(2)])
            ->assertHasNoActionErrors()
            ->assertNotified('تم اعتماد قالبك')
            ->assertSee('المستعمل الآن: قالبك الخاص، ويتسع لـ 2 من الأوائل في الشريحة.')
            ->assertActionVisible('downloadCustomLeadersTemplate')
            ->assertActionVisible('restoreDefaultLeadersTemplate');

        $this->assertSame(2, $templates->customSlots());

        // The export window warns that their slide has two places only.
        Livewire::test(KnightRanking::class)
            ->mountAction(LeadersSlidesAction::NAME)
            ->assertFormFieldExists('top', fn (TextInput $field): bool => str_contains(
                (string) $field->getChildSchema(Field::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString(),
                'قالبك الخاص يتسع لـ 2 من الأوائل في الشريحة',
            ));

        Livewire::test(ManageSettings::class)
            ->callAction('downloadCustomLeadersTemplate')
            ->assertFileDownloaded('قالب شرائح الأوائل - قالبي.pptx', contentType: self::PPTX);

        Livewire::test(ManageSettings::class)
            ->callAction('restoreDefaultLeadersTemplate')
            ->assertNotified('عاد التصميم الافتراضي')
            ->assertSee('المستعمل الآن: التصميم الافتراضي.');

        $this->assertFalse($templates->hasCustom());
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

    public function test_the_default_design_is_downloaded_for_a_number_of_leaders(): void
    {
        Livewire::test(ManageSettings::class)
            ->mountAction('downloadDefaultLeadersTemplate')
            ->assertActionDataSet(['leaders' => 3])
            ->unmountAction();

        Livewire::test(ManageSettings::class)
            ->callAction('downloadDefaultLeadersTemplate', data: ['leaders' => '٥'])
            ->assertFileDownloaded(
                'قالب شرائح الأوائل - 5.pptx',
                file_get_contents(resource_path('pptx/leaders-5.pptx')),
                self::PPTX,
            );

        Livewire::test(ManageSettings::class)
            ->callAction('downloadDefaultLeadersTemplate', data: ['leaders' => 11])
            ->assertHasActionErrors(['leaders'])
            ->assertNoFileDownloaded();
    }
}
