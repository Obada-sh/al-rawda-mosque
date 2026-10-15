<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Reports\KnightRanking;
use App\Filament\Pages\Reports\Statistics;
use App\Filament\Widgets\OverviewStats;
use App\Filament\Widgets\Reports\CirclesReport;
use App\Filament\Widgets\Reports\CoursesReport;
use App\Filament\Widgets\Reports\ExamsReport;
use App\Filament\Widgets\Reports\KnightRankingTable;
use App\Filament\Widgets\Reports\ScopeTotalsStats;
use App\Filament\Widgets\Reports\StudentsReport;
use App\Filament\Widgets\Reports\TeachersReport;
use App\Models\Student;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;

/**
 * The report pages, on the week described in SeedsReportWeek.
 */
class ReportsPanelTest extends PanelTestCase
{
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReportWeek();
    }

    /**
     * @return array{pageFilters: array<string, mixed>}
     */
    private function on(array $filters = []): array
    {
        return ['pageFilters' => $filters];
    }

    public function test_the_statistics_page_starts_on_the_only_active_course(): void
    {
        Livewire::test(Statistics::class)
            ->assertOk()
            ->assertSchemaStateSet([
                'course_id' => $this->course->getKey(),
                'circle_id' => null,
                'from' => null,
                'until' => null,
                'rank_by' => 'points',
            ], 'filtersForm')
            ->assertSee('ترتيب الطلاب')
            ->assertSee('أساتذة التسميع');
    }

    public function test_a_period_that_ends_before_it_starts_is_explained_under_the_field(): void
    {
        Livewire::test(Statistics::class)
            ->set('filters.from', '2026-10-05')
            ->assertHasNoErrors()
            ->set('filters.until', '2026-10-01')
            ->assertHasErrors(['filters.until'])
            ->set('filters.until', '2026-10-06')
            ->assertHasNoErrors();
    }

    public function test_choosing_another_course_clears_the_circle(): void
    {
        $other = $this->enroll();

        Livewire::test(Statistics::class)
            ->fillForm(['circle_id' => $this->circleA->getKey()], 'filtersForm')
            ->assertSchemaStateSet(['circle_id' => $this->circleA->getKey()], 'filtersForm')
            ->fillForm(['course_id' => $other->course_id], 'filtersForm')
            ->assertSchemaStateSet(['course_id' => $other->course_id, 'circle_id' => null], 'filtersForm');
    }

    public function test_the_totals_of_the_scope_are_shown(): void
    {
        Livewire::test(ScopeTotalsStats::class, $this->on(['course_id' => $this->course->getKey()]))
            ->assertSee('66.7%')
            ->assertSee('6 حضور من 9')
            ->assertSee('من 22 صفحة مسمَّعة')
            ->assertSee('المتوسط للطالب: 14');

        Livewire::test(ScopeTotalsStats::class, $this->on(['from' => '2026-10-05', 'until' => '2026-10-01']))
            ->assertDontSee('نسبة الحضور');
    }

    public function test_students_are_listed_in_the_order_of_the_chosen_ranking(): void
    {
        $ahmad = (string) $this->ahmad->getKey();
        $bilal = (string) $this->bilal->getKey();
        $khalid = (string) $this->khalid->getKey();

        Livewire::test(StudentsReport::class, $this->on(['course_id' => $this->course->getKey()]))
            ->assertCanSeeTableRecords([$ahmad, $khalid, $bilal], inOrder: true)
            ->assertSee('الترتيب حسب: النقاط')
            ->assertTableColumnStateSet('place', 1, $ahmad)
            ->assertTableColumnStateSet('points', 27, $ahmad)
            ->assertTableColumnStateSet('new_pages', 12, $ahmad)
            ->assertTableColumnStateSet('attendance', '3 / 3', $ahmad)
            ->assertTableColumnFormattedStateSet('attendance_rate', '33.3%', $bilal)
            ->assertTableColumnStateSet('place', 3, $bilal);

        Livewire::test(StudentsReport::class, $this->on(['course_id' => $this->course->getKey(), 'rank_by' => 'pages']))
            ->assertCanSeeTableRecords([$ahmad, $bilal, $khalid], inOrder: true)
            ->assertSee('الترتيب حسب: الصفحات الجديدة')
            ->assertTableColumnStateSet('place', 3, $khalid);

        Livewire::test(StudentsReport::class, $this->on(['circle_id' => $this->circleB->getKey()]))
            ->assertCanSeeTableRecords([$khalid])
            ->assertCanNotSeeTableRecords([$ahmad, $bilal])
            ->assertTableColumnStateSet('place', 1, $khalid);
    }

    public function test_the_students_report_is_searched_by_name_and_paged(): void
    {
        $ahmad = (string) $this->ahmad->getKey();
        $bilal = (string) $this->bilal->getKey();
        $khalid = (string) $this->khalid->getKey();

        Livewire::test(StudentsReport::class, $this->on())
            ->searchTable('بلا')
            ->assertCanSeeTableRecords([$bilal])
            ->assertCanNotSeeTableRecords([$ahmad, $khalid]);

        foreach (range(1, 12) as $number) {
            $this->enroll(Student::factory()->create(['full_name' => "طالب {$number}"]), $this->course, $this->circleB);
        }

        Livewire::test(StudentsReport::class, $this->on())
            ->set('tableRecordsPerPage', 10)
            ->assertCanSeeTableRecords([$ahmad, $khalid, $bilal], inOrder: true)
            ->call('gotoPage', 2, 'studentsPage')
            ->assertCanNotSeeTableRecords([$ahmad]);
    }

    public function test_a_filter_that_is_not_valid_shows_no_figures(): void
    {
        Livewire::test(StudentsReport::class, $this->on(['from' => '2026-10-05', 'until' => '2026-10-01']))
            ->assertCanNotSeeTableRecords([(string) $this->ahmad->getKey()])
            ->assertSee('لا يوجد طلاب في هذا النطاق');
    }

    public function test_circles_show_the_average_next_to_the_total_and_can_be_sorted(): void
    {
        $first = (string) $this->circleA->getKey();
        $second = (string) $this->circleB->getKey();

        Livewire::test(CirclesReport::class, $this->on(['course_id' => $this->course->getKey()]))
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->assertTableColumnStateSet('students', 2, $first)
            ->assertTableColumnStateSet('points', 33, $first)
            ->assertTableColumnFormattedStateSet('average_points', '16.5', $first)
            ->assertTableColumnFormattedStateSet('attendance_rate', '66.7%', $first)
            ->assertTableColumnFormattedStateSet('average_points', '9', $second)
            ->sortTable('points')
            ->assertCanSeeTableRecords([$second, $first], inOrder: true)
            ->sortTable('points', 'desc')
            ->assertCanSeeTableRecords([$first, $second], inOrder: true);
    }

    public function test_the_courses_teachers_and_exams_reports_show_their_figures(): void
    {
        $this->recite($this->khalid, 582, 604, '2026-10-05', $this->omar);

        Livewire::test(CoursesReport::class, $this->on())
            ->assertTableColumnStateSet('students', 3, (string) $this->course->getKey())
            ->assertTableColumnStateSet('new_pages', 39, (string) $this->course->getKey());

        Livewire::test(TeachersReport::class, $this->on())
            ->assertCanSeeTableRecords([(string) $this->omar->getKey(), (string) $this->yaser->getKey()], inOrder: true)
            ->assertTableColumnStateSet('pages', 27, (string) $this->omar->getKey())
            ->assertTableColumnStateSet('new_pages', 12, (string) $this->yaser->getKey())
            ->assertTableColumnStateSet('recitations', 2, (string) $this->yaser->getKey());

        Livewire::test(ExamsReport::class, $this->on())
            ->assertTableColumnStateSet('pending', 1, 'circle-'.$this->circleB->getKey())
            ->assertTableColumnStateSet('done', 0, 'circle-'.$this->circleB->getKey())
            ->assertTableColumnStateSet('grade_excellent', 0, 'circle-'.$this->circleB->getKey());
    }

    public function test_the_quran_knight_page_starts_on_this_month_and_ranks_by_new_pages(): void
    {
        Livewire::test(KnightRanking::class)
            ->assertOk()
            ->assertSchemaStateSet(['from' => '2026-10-01', 'until' => '2026-10-07', 'course_id' => null], 'filtersForm')
            ->assertSee('الفارس القرآني');

        $ahmad = (string) $this->ahmad->student_id;
        $bilal = (string) $this->bilal->student_id;

        Livewire::test(KnightRankingTable::class, $this->on(['from' => '2026-10-01', 'until' => '2026-10-07']))
            ->assertCanSeeTableRecords([$ahmad, $bilal], inOrder: true)
            ->assertCanNotSeeTableRecords([(string) $this->khalid->student_id])
            ->assertTableColumnStateSet('place', 1, $ahmad)
            ->assertTableColumnStateSet('new_pages', 12, $ahmad)
            ->assertTableColumnStateSet('circles', ['حلقة أ (دورة الخريف)'], $ahmad)
            ->assertTableColumnStateSet('place', 2, $bilal);

        Livewire::test(KnightRankingTable::class, $this->on(['from' => '2026-10-04', 'until' => '2026-10-07']))
            ->assertCanSeeTableRecords([$bilal])
            ->assertCanNotSeeTableRecords([$ahmad])
            ->assertTableColumnStateSet('place', 1, $bilal);
    }

    public function test_the_home_page_shows_its_figures(): void
    {
        Livewire::test(OverviewStats::class)
            ->assertSee('حضور اليوم')
            ->assertSee('صفحات جديدة هذا الشهر')
            ->assertSee('المسجَّلون فيها: 3');
    }
}
