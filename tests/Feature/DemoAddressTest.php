<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Demo\DemoAccount;
use App\Services\Demo\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo is known by its database, says on every page that it is the
 * demo, and has an address of its own where the real data never shows.
 *
 * The database of the tests is not a demo database: as it is, the system
 * under test is "the real one". Where a test needs the demo, the database
 * of the tests plays it.
 */
class DemoAddressTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_ADDRESS = 'http://127.0.0.1:8010';

    private const REAL_ADDRESS = 'http://127.0.0.1:8000';

    private const SETUP = [
        'name' => 'المدير',
        'username' => 'admin',
        'password' => 'a-long-test-password',
        'password_confirmation' => 'a-long-test-password',
    ];

    private function thisIsTheDemo(): void
    {
        $this->app->instance(DemoMode::class, new DemoMode('rawda_demo', (int) config('rawda.demo.port')));
    }

    public function test_the_address_of_the_demo_is_the_one_demo_bat_serves(): void
    {
        $this->assertSame(self::DEMO_ADDRESS, 'http://127.0.0.1:'.config('rawda.demo.port'));
        $this->assertFalse(app(DemoMode::class)->isOn());
        $this->assertSame('rawda_testing', app(DemoMode::class)->database());
    }

    public function test_the_real_data_never_shows_at_the_address_of_the_demo(): void
    {
        foreach (['/', '/admin', '/admin/login', '/setup', '/up'] as $path) {
            $this->get(self::DEMO_ADDRESS.$path)
                ->assertStatus(503)
                ->assertSee('هذا عنوان النسخة التجريبية')
                ->assertSee('demo.bat')
                ->assertSee('start.bat');
        }
    }

    public function test_nothing_can_be_done_to_the_real_data_from_the_address_of_the_demo(): void
    {
        // Not even the first-run setup, which needs no account.
        $this->post(self::DEMO_ADDRESS.'/setup', self::SETUP)->assertStatus(503);

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();

        // And being signed in changes nothing.
        $this->actingAs(User::factory()->create())
            ->get(self::DEMO_ADDRESS.'/admin')
            ->assertStatus(503)
            ->assertDontSee('الرئيسية');
    }

    public function test_the_real_system_answers_at_every_other_address(): void
    {
        $this->get(self::REAL_ADDRESS.'/setup')->assertOk()->assertSee('إعداد النظام لأول مرة');
        $this->get('/setup')->assertOk();

        $this->post(self::REAL_ADDRESS.'/setup', self::SETUP)->assertRedirect();
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_demo_answers_at_its_address(): void
    {
        $this->thisIsTheDemo();
        User::factory()->create();

        $this->get(self::DEMO_ADDRESS.'/admin/login')->assertOk()->assertSee('اسم المستخدم');
        // And at any other: it holds nothing that needs guarding.
        $this->get(self::REAL_ADDRESS.'/admin/login')->assertOk();
    }

    public function test_the_sign_in_page_of_the_demo_says_what_it_is_and_how_to_get_in(): void
    {
        $this->thisIsTheDemo();
        User::factory()->create(['username' => DemoAccount::USERNAME, 'password' => DemoAccount::PASSWORD]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('نسخة تجريبية')
            ->assertSee('بيانات وهمية')
            ->assertSee('<bdi dir="ltr">'.DemoAccount::USERNAME.'</bdi>', false)
            ->assertSee('<bdi dir="ltr">'.e(DemoAccount::PASSWORD).'</bdi>', false);

        // Showing the account signs nobody in.
        $this->assertGuest();
    }

    public function test_the_note_never_shows_an_account_that_no_longer_opens_the_demo(): void
    {
        $this->thisIsTheDemo();
        // Someone tried the "my account" page and changed the password.
        User::factory()->create(['username' => DemoAccount::USERNAME, 'password' => 'changed-while-trying-things']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('نسخة تجريبية')
            ->assertSee('بيانات وهمية')
            ->assertSee('غُيّر من صفحة «حسابي»')
            ->assertDontSee(DemoAccount::PASSWORD);

        $this->assertGuest();
    }

    public function test_every_page_of_the_demo_carries_its_mark(): void
    {
        $this->thisIsTheDemo();

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('<span class="rawda-demo-badge"', false)
            ->assertSee('نسخة تجريبية')
            // The account is for the sign-in page only.
            ->assertDontSee(DemoAccount::PASSWORD);
    }

    public function test_the_real_system_shows_no_mark_of_the_demo_and_no_account(): void
    {
        $admin = User::factory()->create();

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('نسخة تجريبية')
            ->assertDontSee('rawda-demo-sign-in')
            ->assertDontSee(DemoAccount::PASSWORD);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('نسخة تجريبية')
            ->assertDontSee('rawda-demo-badge');
    }

    public function test_the_account_of_the_demo_opens_nothing_in_the_real_system(): void
    {
        User::factory()->create(['username' => 'admin']);

        $this->assertFalse(auth()->attempt(['username' => DemoAccount::USERNAME, 'password' => DemoAccount::PASSWORD]));
        $this->assertGuest();
    }
}
