<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_signs_in_with_a_username(): void
    {
        $admin = User::factory()->create(['username' => 'admin', 'password' => 'a-long-test-password']);

        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'a-long-test-password'], 'form')
            ->call('authenticate')
            ->assertHasNoFormErrors([], 'form')
            ->assertRedirect();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_spaces_around_the_username_are_ignored_but_the_password_is_used_as_typed(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'a-long-test-password']);

        Livewire::test(Login::class)
            ->fillForm(['username' => '  admin ', 'password' => 'a-long-test-password '], 'form')
            ->call('authenticate')
            ->assertHasFormErrors(['username'], 'form');

        $this->assertGuest();

        Livewire::test(Login::class)
            ->fillForm(['username' => '  admin ', 'password' => 'a-long-test-password'], 'form')
            ->call('authenticate')
            ->assertHasNoFormErrors([], 'form');

        $this->assertAuthenticated();
    }

    public function test_a_wrong_password_is_rejected_without_saying_which_part_is_wrong(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'a-long-test-password']);

        Livewire::test(Login::class)
            ->fillForm(['username' => 'admin', 'password' => 'not-the-password'], 'form')
            ->call('authenticate')
            ->assertHasFormErrors(['username'], 'form')
            ->assertHasNoFormErrors(['password'], 'form');

        Livewire::test(Login::class)
            ->fillForm(['username' => 'nobody', 'password' => 'a-long-test-password'], 'form')
            ->call('authenticate')
            ->assertHasFormErrors(['username'], 'form');

        $this->assertGuest();
    }

    public function test_the_username_and_the_password_are_required(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['username' => '', 'password' => ''], 'form')
            ->call('authenticate')
            ->assertHasFormErrors(['username', 'password'], 'form');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        User::factory()->create();

        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('اسم المستخدم');
    }

    public function test_the_admin_sees_the_home_page_after_signing_in(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertOk()->assertSee('الرئيسية');
    }
}
