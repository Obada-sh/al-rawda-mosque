<?php

namespace Tests\Feature\Panel;

use App\Filament\Pages\Backups;
use App\Filament\Pages\ManageSettings;
use App\Http\Requests\Backups\BackupUploadRequest;
use App\Http\Requests\Export\SlideTemplateRequest;
use App\Support\FileSize;
use App\Support\UploadLimit;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Livewire\Livewire;

/**
 * A file larger than PHP allows never reaches the system, so every window
 * that takes a file says how large it may be on this computer.
 */
class UploadWindowsTest extends PanelTestCase
{
    /**
     * @param  int  $maxKilobytes  the largest file the system itself accepts there
     */
    private static function says(int $maxKilobytes): Closure
    {
        $limit = 'أقصى حجم للملف: '.FileSize::arabic(UploadLimit::kilobytes($maxKilobytes) * 1024).'.';

        return fn (FileUpload $field): bool => str_contains(
            (string) $field->getChildSchema(Field::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString(),
            $limit,
        );
    }

    public function test_the_window_that_takes_a_backup_file_says_how_large_it_may_be(): void
    {
        Livewire::test(Backups::class)
            ->mountAction('import')
            ->assertFormFieldExists('backup', self::says(BackupUploadRequest::MAX_KILOBYTES));
    }

    public function test_the_window_that_takes_a_slide_template_says_how_large_it_may_be(): void
    {
        Livewire::test(ManageSettings::class)
            ->mountAction('uploadLeadersTemplate')
            ->assertFormFieldExists('template', self::says(SlideTemplateRequest::MAX_KILOBYTES));
    }
}
