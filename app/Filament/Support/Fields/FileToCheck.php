<?php

namespace App\Filament\Support\Fields;

use App\Support\FileSize;
use App\Support\UploadLimit;
use Filament\Forms\Components\FileUpload;

/**
 * The field of a file that the action checks and then stores itself (a
 * backup, a slide template): the form keeps nothing of it. It says how
 * large the file may be on this computer.
 */
final class FileToCheck
{
    /**
     * @param  int  $maxKilobytes  the largest file the system accepts here (the rule of the Request class)
     */
    public static function make(string $name, int $maxKilobytes): FileUpload
    {
        return FileUpload::make($name)
            ->storeFiles(false)
            ->markAsRequired()
            ->helperText(fn (): string => 'أقصى حجم للملف: '.FileSize::arabic(UploadLimit::kilobytes($maxKilobytes) * 1024).'.');
    }
}
