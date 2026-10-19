<?php

namespace Tests\Feature;

use App\Actions\Auth\SaveAdminUser;
use App\Contracts\AdminNotifier;
use App\Models\JuzExam;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifications\FilamentAdminNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_account_is_created_with_a_hashed_password(): void
    {
        $user = app(SaveAdminUser::class)->handle([
            'name' => 'المدير',
            'username' => 'admin',
            'password' => 'a-long-test-password',
        ]);

        $this->assertSame('admin', $user->username);
        $this->assertNotSame('a-long-test-password', $user->password);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
    }

    public function test_usernames_are_unique_and_passwords_have_a_minimum_length(): void
    {
        User::factory()->create(['username' => 'admin']);

        $invalid = [
            'taken username' => ['name' => 'مدير', 'username' => 'admin', 'password' => 'a-long-test-password'],
            'short password' => ['name' => 'مدير', 'username' => 'second', 'password' => 'short'],
            'spaces in username' => ['name' => 'مدير', 'username' => 'the admin', 'password' => 'a-long-test-password'],
        ];

        foreach ($invalid as $case => $input) {
            try {
                app(SaveAdminUser::class)->handle($input);
                $this->fail("Input was accepted: {$case}");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, User::query()->count());
    }

    public function test_the_panel_notifier_writes_to_the_admins_notification_bell(): void
    {
        $admin = User::factory()->create();
        $student = Student::factory()->create();
        $exam = JuzExam::query()->create([
            'student_id' => $student->getKey(),
            'juz_no' => 30,
            'triggered_on' => now()->toDateString(),
        ]);

        $notifier = new FilamentAdminNotifier;
        $notifier->juzExamPending($exam);
        $notifier->incompleteProfiles(4);

        $this->assertSame(2, $admin->notifications()->count());
        $this->assertStringContainsString($student->full_name, json_encode($admin->notifications()->pluck('data')->all(), JSON_UNESCAPED_UNICODE));

        // The evening copy for Google Drive: why it failed, and which old copies are left for the admin to remove.
        $notifier->driveCopyFailed('لم يُعثر على المجلد G:\\My Drive\\نسخ.');
        $notifier->driveCopiesNotRemoved(['rawda-backup-2026-10-01_200000-evening.zip'], 'G:\\My Drive\\نسخ');

        $bell = json_encode($admin->notifications()->pluck('data')->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSame(4, $admin->notifications()->count());
        $this->assertStringContainsString('تعذّر وضع النسخة المسائية في Google Drive', $bell);
        $this->assertStringContainsString('احذف النسخ القديمة من مجلد Google Drive', $bell);
        $this->assertStringContainsString('نسخة واحدة', $bell);
        $this->assertStringContainsString('rawda-backup-2026-10-01_200000-evening.zip', $bell);
    }

    public function test_the_application_uses_the_panel_notifier_by_default(): void
    {
        $this->app->forgetInstance(AdminNotifier::class);

        $this->assertInstanceOf(FilamentAdminNotifier::class, $this->app->make(AdminNotifier::class));
    }
}
