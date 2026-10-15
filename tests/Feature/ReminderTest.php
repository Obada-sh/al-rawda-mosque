<?php

namespace Tests\Feature;

use App\Actions\Settings\RemindAboutIncompleteProfiles;
use App\Contracts\SettingsStore;
use App\Models\Student;
use App\Support\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');
    }

    private function remind(): bool
    {
        return app(RemindAboutIncompleteProfiles::class)->handle();
    }

    public function test_the_admin_is_reminded_about_incomplete_profiles(): void
    {
        Student::factory()->count(3)->create();
        Student::factory()->complete()->create();
        Student::factory()->archived()->create();

        $this->assertTrue($this->remind());
        $this->assertSame([3], $this->adminNotifier->incompleteProfileCounts);
    }

    public function test_the_reminder_is_not_repeated_before_the_interval_passes(): void
    {
        Student::factory()->create();

        $this->assertTrue($this->remind());
        $this->assertFalse($this->remind());

        $this->travelTo('2026-10-13 09:00:00');
        $this->assertFalse($this->remind());

        $this->travelTo('2026-10-14 09:00:00');
        $this->assertTrue($this->remind());

        $this->assertCount(2, $this->adminNotifier->incompleteProfileCounts);
    }

    public function test_the_interval_can_be_changed_in_the_settings(): void
    {
        Student::factory()->create();
        app(SettingsStore::class)->set(SettingKey::INCOMPLETE_PROFILES_REMINDER_DAYS, '2');

        $this->assertTrue($this->remind());

        $this->travelTo('2026-10-09 09:00:00');
        $this->assertTrue($this->remind());
    }

    public function test_nothing_is_sent_when_every_profile_is_complete(): void
    {
        Student::factory()->complete()->create();

        $this->assertFalse($this->remind());
        $this->assertSame([], $this->adminNotifier->incompleteProfileCounts);
    }

    public function test_settings_are_stored_and_read_back(): void
    {
        $settings = app(SettingsStore::class);

        $this->assertNull($settings->get(SettingKey::MOSQUE_NAME));
        $this->assertSame('الجامع', $settings->get(SettingKey::MOSQUE_NAME, 'الجامع'));

        $settings->set(SettingKey::MOSQUE_NAME, 'جامع الروضة');
        $settings->set(SettingKey::MOSQUE_NAME, 'جامع الروضة الكبير');

        $this->assertSame('جامع الروضة الكبير', $settings->get(SettingKey::MOSQUE_NAME));
        $this->assertDatabaseCount('settings', 1);
    }
}
