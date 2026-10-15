<?php

namespace Tests;

use App\Contracts\AdminNotifier;
use App\Services\Export\LeadersTemplates;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\RecordingAdminNotifier;

abstract class TestCase extends BaseTestCase
{
    protected RecordingAdminNotifier $adminNotifier;

    /**
     * The tests rebuild the database from nothing and write backup files:
     * they must only ever do so in places of their own.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $database = (string) $config->get('database.connections.'.$config->get('database.default').'.database');

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "The tests refuse to run on the database [{$database}]: its name must end with \"_testing\". "
                .'If the configuration is cached, run "php artisan config:clear" first.',
            );
        }

        $config->set('rawda.backups.path', storage_path('framework/testing/backups'));
        // No backup is taken behind the back of a test that opens a page.
        $config->set('rawda.backups.automatic', false);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Business rules are tested against the notifier contract; the
        // real panel notifier has its own test.
        $this->adminNotifier = new RecordingAdminNotifier;
        $this->app->instance(AdminNotifier::class, $this->adminNotifier);

        // Tests never read or replace the files the admin uploaded (their
        // slide template): each test gets an empty disk of its own.
        Storage::fake(LeadersTemplates::DISK);
    }
}
