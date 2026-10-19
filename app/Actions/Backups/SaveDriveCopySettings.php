<?php

namespace App\Actions\Backups;

use App\Exceptions\BusinessRuleViolation;
use App\Http\Requests\Backups\DriveCopyRequest;
use App\Services\Backup\BackupStore;
use App\Services\Backup\DriveCopySettings;
use App\Services\Backup\DriveCopySetup;
use App\Services\Backup\DriveFolder;
use App\Services\Demo\DemoMode;
use Illuminate\Validation\ValidationException;

/**
 * Sets up the evening copy for Google Drive, or turns it off (no folder).
 *
 * The folder must be one this computer has now, that copies can be
 * written in, and that is not part of the system: a copy kept inside the
 * system's folder could be reached through it, and one in the backups
 * folder would be taken for a backup of this computer.
 */
final class SaveDriveCopySettings
{
    public function __construct(
        private readonly DriveCopySetup $setup,
        private readonly BackupStore $backups,
        private readonly DemoMode $demo,
    ) {}

    /**
     * @param  array<string, mixed>  $input  folder, link, time, keep_days
     *
     * @throws ValidationException
     * @throws BusinessRuleViolation drive.demo, drive.folder_not_a_full_path, drive.folder_missing, drive.folder_inside_system, drive.folder_not_writable
     */
    public function handle(array $input): DriveCopySettings
    {
        $data = DriveCopyRequest::validateData($input);
        $folder = self::folderFrom($data['folder'] ?? null);

        if ($folder !== null) {
            $this->check(new DriveFolder($folder));
        }

        $settings = new DriveCopySettings(
            folder: $folder,
            link: $data['link'] ?? null,
            time: substr((string) $data['time'], 0, 5),
            keepDays: (int) $data['keep_days'],
        );

        $this->setup->save($settings);

        return $settings;
    }

    private function check(DriveFolder $folder): void
    {
        if ($this->demo->isOn()) {
            throw DriveCopyViolation::demo();
        }

        if (! self::isFullPath($folder->path)) {
            throw DriveCopyViolation::notAFullPath();
        }

        if (! $folder->exists()) {
            throw DriveCopyViolation::folderMissing($folder->path);
        }

        if ($folder->isWithin(base_path()) || $folder->isWithin($this->backups->directory())) {
            throw DriveCopyViolation::insideSystem();
        }

        if (! $folder->canWrite()) {
            throw DriveCopyViolation::notWritable($folder->path);
        }
    }

    /**
     * The folder as Windows gives it: "Copy as path" puts it between
     * quotation marks, and a folder may be written with a slash at its end.
     */
    private static function folderFrom(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $folder = trim(trim($value), "\"'");

        // "G:\" stays as it is; "G:\My Drive\" loses its last slash.
        if (preg_match('~^[A-Za-z]:[\\\\/]$~', $folder) !== 1) {
            $folder = rtrim($folder, '\\/');
        }

        return $folder === '' ? null : $folder;
    }

    /**
     * "G:\My Drive\…", "\\server\share\…", or, on other systems, "/…".
     */
    private static function isFullPath(string $folder): bool
    {
        return preg_match('~^([A-Za-z]:[\\\\/]|\\\\\\\\[^\\\\]+\\\\|/)~', $folder) === 1;
    }
}
