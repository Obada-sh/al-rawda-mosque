<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\TestCase;

/**
 * Base of the tests that drive the admin panel as the signed-in admin,
 * on a fixed Wednesday afternoon.
 */
abstract class PanelTestCase extends TestCase
{
    use BuildsDomain;
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 17:00:00');

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }
}
