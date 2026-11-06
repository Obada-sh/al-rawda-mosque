<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\ScanPoints;
use App\Models\English\EnglishEnrollment;
use App\Services\Teachers\TeacherCards;
use Livewire\Livewire;
use Tests\Concerns\SeedsReportWeek;

/**
 * The points desk in the panel: one box for the scanner, and the
 * student's points in large letters. On the week of SeedsReportWeek.
 */
class PointsDeskPanelTest extends PanelTestCase
{
    use SeedsReportWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReportWeek();
    }

    public function test_a_scanned_card_shows_the_students_points_and_the_box_is_ready_for_the_next(): void
    {
        $this->get(ScanPoints::getUrl())
            ->assertOk()
            ->assertSee('رصيد النقاط')
            ->assertSee('امسح بطاقة الطالب لمعرفة نقاطه')
            ->assertSee('بانتظار أول بطاقة');

        Livewire::test(ScanPoints::class)
            ->set('cardCode', $this->bilal->card_code)
            ->call('lookUp')
            ->assertSet('cardCode', '')
            ->assertSet('result.tone', 'success')
            ->assertSet('result.available', 6)
            ->assertDispatched('scan-processed', tone: 'success')
            ->assertSee('رصيد الطالب')
            ->assertSeeHtmlInOrder(['بلال', 'حلقة أ — دورة الخريف', 'الرصيد', '6', 'نقاط', 'كسب في الدورة', '6', 'صرف في السوق', '0'])
            ->assertSeeHtmlInOrder(['من الحفظ', '+8', 'من الحضور', '+1', 'مكافآت وخصومات', '-3'])
            ->assertSeeHtmlInOrder(['آخر نقاط الطالب', 'يدوي — مسابقة', '06/10/2026', '-3', 'حفظ', '05/10/2026', '+8'])
            ->assertSee('rawda-points__line is-penalty', escape: false);
    }

    public function test_other_cards_are_refused_in_red_and_an_empty_box_does_nothing(): void
    {
        $page = Livewire::test(ScanPoints::class)
            ->call('lookUp')
            ->assertSet('result', null)
            ->assertNotDispatched('scan-processed');

        $teacherCard = app(TeacherCards::class)->of($this->yaser, $this->course)->card_code;

        foreach ([
            $teacherCard => 'هذه بطاقة أستاذ، والنقاط للطلاب',
            EnglishEnrollment::factory()->create()->card_code => 'هذه بطاقة دورة انجليزي، ولا نقاط في دورات الانجليزي',
            '12345678' => 'بطاقة غير معروفة',
        ] as $card => $message) {
            $page->set('cardCode', (string) $card)
                ->call('lookUp')
                ->assertSet('result.tone', 'danger')
                ->assertSet('result.available', null)
                ->assertSee($message)
                ->assertDontSee('كسب في الدورة')
                ->assertDispatched('scan-processed', tone: 'danger');
        }
    }
}
