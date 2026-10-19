<?php

namespace App\Filament\Support\Presenters;

use App\Enums\BackupKind;
use App\Services\Backup\Backup;
use App\Support\Dates;
use App\Support\FileSize;

/**
 * How a backup is shown in the panel.
 */
final class BackupPresenter
{
    /**
     * The row of a backup in the list. Its key is the file name without
     * its fixed beginning and end.
     *
     * @return array<string, mixed>
     */
    public static function row(Backup $backup): array
    {
        return [
            'name' => $backup->name,
            'taken_at' => $backup->takenAt->format(Dates::DISPLAY_FORMAT_WITH_TIME),
            'kind' => $backup->kind->label(),
            'kind_color' => self::color($backup->kind),
            'size' => FileSize::arabic($backup->bytes),
        ];
    }

    public static function key(Backup $backup): string
    {
        return substr($backup->name, strlen('rawda-backup-'), -strlen('.zip'));
    }

    /**
     * The backups the system took to guard a restore or an update stand out.
     */
    public static function color(BackupKind $kind): string
    {
        return match ($kind) {
            BackupKind::Automatic => 'gray',
            BackupKind::Manual => 'primary',
            BackupKind::BeforeRestore, BackupKind::BeforeUpdate => 'warning',
            BackupKind::Uploaded => 'info',
            BackupKind::Evening => 'success',
        };
    }
}
