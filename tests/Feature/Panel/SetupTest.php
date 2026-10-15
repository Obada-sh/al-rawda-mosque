<?php

namespace Tests\Feature\Panel;

use App\Actions\Setup\CreateFirstAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    private const VALID = [
        'name' => 'المدير',
        'username' => 'admin',
        'password' => 'a-long-test-password',
        'password_confirmation' => 'a-long-test-password',
    ];

    public function test_until_an_admin_exists_the_panel_leads_to_the_setup_page(): void
    {
        $this->get('/admin')->assertRedirect('/setup');
        $this->get('/admin/login')->assertRedirect('/setup');

        $this->get('/setup')->assertOk()->assertSee('إعداد النظام لأول مرة');
    }

    public function test_the_setup_page_creates_the_admin_and_signs_them_in(): void
    {
        $this->post('/setup', self::VALID)->assertRedirect('/admin');

        $admin = User::query()->sole();
        $this->assertSame('admin', $admin->username);
        $this->assertSame('المدير', $admin->name);
        $this->assertTrue(Hash::check('a-long-test-password', $admin->password));
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin')->assertOk();
    }

    public function test_the_setup_form_is_validated(): void
    {
        $this->post('/setup', [...self::VALID, 'password_confirmation' => 'something-else'])
            ->assertSessionHasErrors(['password']);

        $this->post('/setup', [...self::VALID, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['password']);

        $this->post('/setup', [...self::VALID, 'username' => 'اسم عربي'])
            ->assertSessionHasErrors(['username']);

        $this->post('/setup', [...self::VALID, 'name' => ''])
            ->assertSessionHasErrors(['name']);

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_the_setup_page_is_closed_once_an_admin_exists(): void
    {
        User::factory()->create(['username' => 'first']);

        $this->get('/setup')->assertRedirect('/admin');
        $this->post('/setup', self::VALID)->assertRedirect('/admin');

        $this->assertSame(1, User::query()->count());
        $this->assertGuest();

        $this->assertViolates('setup.already_done', fn () => app(CreateFirstAdmin::class)->handle(self::VALID));
    }
}
