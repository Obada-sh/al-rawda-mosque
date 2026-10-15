<?php

namespace App\Providers;

use App\Contracts\AdminNotifier;
use App\Contracts\CardCodeGenerator;
use App\Contracts\QrCodeRenderer;
use App\Contracts\SettingsStore;
use App\Http\Requests\Backups\BackupUploadRequest;
use App\Services\Backup\BackupStore;
use App\Services\Cards\BaconQrCodeRenderer;
use App\Services\Cards\NumericCardCodeGenerator;
use App\Services\Demo\DemoMode;
use App\Services\Memorization\JuzMap;
use App\Services\Notifications\FilamentAdminNotifier;
use App\Services\Settings\DatabaseSettingsStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Contracts and their implementations. The application layer depends
     * on the contracts only.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CardCodeGenerator::class => NumericCardCodeGenerator::class,
        QrCodeRenderer::class => BaconQrCodeRenderer::class,
        AdminNotifier::class => FilamentAdminNotifier::class,
    ];

    /**
     * Services that keep state for the duration of one request.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        SettingsStore::class => DatabaseSettingsStore::class,
        JuzMap::class => JuzMap::class,
    ];

    public function register(): void
    {
        // The backups folder belongs to the machine: it comes from the configuration.
        $this->app->bind(BackupStore::class, fn (): BackupStore => new BackupStore((string) config('rawda.backups.path')));

        // So does the folder of the uploaded files, when the machine names one.
        if (filled($files = config('rawda.files_path'))) {
            config(['filesystems.disks.local.root' => $files]);
        }

        // Whether this is the demo is read from the database the system works on.
        $this->app->bind(DemoMode::class, fn (Application $app): DemoMode => new DemoMode(
            (string) $app->make(ConnectionResolverInterface::class)->connection()->getDatabaseName(),
            (int) config('rawda.demo.port'),
        ));
    }

    public function boot(): void
    {
        // Fail loudly instead of silently dropping or inventing attributes.
        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes();

        // A backup file is larger than the uploads the panel allows by default.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.BackupUploadRequest::MAX_KILOBYTES]]);
    }
}
