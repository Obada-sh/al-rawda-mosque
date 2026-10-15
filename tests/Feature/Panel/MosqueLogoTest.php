<?php

namespace Tests\Feature\Panel;

use App\Contracts\SettingsStore;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports\FinanceReport;
use App\Filament\Pages\Reports\KnightRanking;
use App\Filament\Pages\Reports\Statistics;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Course;
use App\Support\Brand;
use App\Support\SettingKey;
use Illuminate\Support\Facades\Auth;

/**
 * The mosque's logo is everywhere: on every page of the panel, above the
 * sign-in form, at the top of the home page, on the letterhead of every
 * report (on the screen and on paper), and on the setup page.
 */
class MosqueLogoTest extends PanelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsStore::class)->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');
    }

    public function test_every_page_of_the_panel_shows_the_logo_with_the_mosques_name(): void
    {
        foreach ([Dashboard::getUrl(), StudentResource::getUrl()] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<img src="'.Brand::logoUrl().'" alt="" class="rawda-brand__logo">', escape: false)
                ->assertSee('<span class="rawda-brand__name">جامع الروضة</span>', escape: false)
                ->assertSee(Brand::faviconUrl(), escape: false);
        }
    }

    public function test_the_sign_in_page_shows_the_logo(): void
    {
        Auth::logout();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('class="rawda-brand__logo"', escape: false)
            ->assertSee('جامع الروضة');
    }

    public function test_the_home_page_opens_on_the_logo_the_name_and_the_day_in_numbers(): void
    {
        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('class="rawda-banner__logo"', escape: false)
            ->assertSee('الأربعاء 07/10/2026')
            ->assertDontSee('تشرين');
    }

    public function test_every_report_has_a_letterhead_that_says_what_it_covers(): void
    {
        $course = Course::factory()->create(['name' => 'دورة الخريف']);

        $this->get(Statistics::getUrl(['filters' => ['course_id' => $course->getKey()]]))
            ->assertOk()
            ->assertSee('class="rawda-letterhead__logo"', escape: false)
            ->assertSee('<h2 class="rawda-letterhead__title">الإحصائيات</h2>', escape: false)
            ->assertSee('دورة الخريف · من البداية حتى اليوم')
            ->assertSee('07/10/2026');

        // The Quran Knight opens on this month.
        $this->get(KnightRanking::getUrl())
            ->assertOk()
            ->assertSee('<h2 class="rawda-letterhead__title">الفارس القرآني</h2>', escape: false)
            ->assertSee('من 01/10/2026 إلى 07/10/2026');

        $this->get(FinanceReport::getUrl())
            ->assertOk()
            ->assertSee('<h2 class="rawda-letterhead__title">تقرير المصاريف</h2>', escape: false)
            ->assertSee('كل الأقسام');
    }

    public function test_the_setup_page_shows_the_logo(): void
    {
        Auth::logout();
        $this->admin->delete();

        $this->get('/setup')
            ->assertOk()
            ->assertSee('<img src="'.Brand::logoUrl().'" alt="" class="logo">', escape: false);
    }
}
