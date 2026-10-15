<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'المدير',
            'username' => 'admin',
            'password' => 'the-current-password',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_the_profile_page_shows_the_account_without_any_password(): void
    {
        Livewire::test(EditProfile::class)
            ->assertOk()
            ->assertSchemaStateSet(['name' => 'المدير', 'username' => 'admin'])
            ->assertDontSee($this->admin->password);
    }

    public function test_the_admin_changes_the_name_and_username_without_touching_the_password(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'أبو أحمد', 'username' => 'abu.ahmad'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('تم حفظ بيانات الحساب');

        $this->admin->refresh();
        $this->assertSame('أبو أحمد', $this->admin->name);
        $this->assertSame('abu.ahmad', $this->admin->username);
        $this->assertTrue(Hash::check('the-current-password', $this->admin->password));
    }

    public function test_changing_the_password_needs_the_current_one_and_the_new_one_twice(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm([
                'current_password' => 'the-current-password',
                'new_password' => 'a-brand-new-password',
                'new_password_confirmation' => 'a-brand-new-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet([
                'current_password' => null,
                'new_password' => null,
                'new_password_confirmation' => null,
            ]);

        $this->assertTrue(Hash::check('a-brand-new-password', $this->admin->refresh()->password));
    }

    public function test_a_wrong_current_password_or_a_mismatch_changes_nothing(): void
    {
        Livewire::test(EditProfile::class)
            ->fillForm([
                'current_password' => 'not-the-current-password',
                'new_password' => 'a-brand-new-password',
                'new_password_confirmation' => 'a-brand-new-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'new_password' => 'a-brand-new-password',
                'new_password_confirmation' => 'a-brand-new-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'current_password' => 'the-current-password',
                'new_password' => 'a-brand-new-password',
                'new_password_confirmation' => 'a-different-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['new_password']);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'current_password' => 'the-current-password',
                'new_password' => 'short',
                'new_password_confirmation' => 'short',
            ])
            ->call('save')
            ->assertHasFormErrors(['new_password']);

        $this->assertTrue(Hash::check('the-current-password', $this->admin->refresh()->password));
    }

    public function test_the_username_must_stay_unique(): void
    {
        User::factory()->create(['username' => 'second']);

        Livewire::test(EditProfile::class)
            ->fillForm(['username' => 'second'])
            ->call('save')
            ->assertHasFormErrors(['username']);

        $this->assertSame('admin', $this->admin->refresh()->username);
    }
}
