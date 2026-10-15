<?php

namespace App\Services\Backup;

use App\Enums\BackupKind;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The folder the backup files are kept in.
 *
 * The name of a file says when the backup was taken and why:
 * "rawda-backup-2026-10-07_170000-auto.zip". A file with any other name
 * is not listed.
 */
final class BackupStore
{
    public const NAME_PATTERN = '/^rawda-backup-(\d{4}-\d{2}-\d{2})_(\d{6})-([a-z]+(?:-[a-z]+)*)\.zip$/';

    public function __construct(private readonly string $directory) {}

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Makes the folder when it is missing, and says whether files can be written in it.
     */
    public function isWritable(): bool
    {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        return is_dir($this->directory) && is_writable($this->directory);
    }

    /**
     * @return list<Backup> the newest first
     */
    public function all(): array
    {
        $backups = [];

        foreach (is_dir($this->directory) ? (scandir($this->directory) ?: []) : [] as $name) {
            $backup = $this->read($name);

            if ($backup !== null) {
                $backups[] = $backup;
            }
        }

        usort($backups, fn (Backup $a, Backup $b): int => [$b->takenAt->getTimestamp(), $b->name] <=> [$a->takenAt->getTimestamp(), $a->name]);

        return $backups;
    }

    public function find(string $name): ?Backup
    {
        return $this->read($name);
    }

    /**
     * The newest backup of a kind taken on a day, if any.
     *
     * @param  string  $day  Y-m-d
     */
    public function takenOn(string $day, BackupKind $kind): ?Backup
    {
        foreach ($this->all() as $backup) {
            if ($backup->kind === $kind && $backup->takenAt->toDateString() === $day) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * The path of a new backup taken at that moment. When a file of the
     * same second and kind exists, the next free second is used.
     */
    public function pathFor(BackupKind $kind, CarbonImmutable $takenAt): string
    {
        do {
            $path = $this->directory.DIRECTORY_SEPARATOR.self::nameOf($kind, $takenAt);
            $takenAt = $takenAt->addSecond();
        } while (file_exists($path));

        return $path;
    }

    public static function nameOf(BackupKind $kind, CarbonImmutable $takenAt): string
    {
        return 'rawda-backup-'.$takenAt->format('Y-m-d_His').'-'.$kind->value.'.zip';
    }

    public function delete(Backup $backup): void
    {
        // Only a file of this folder that is named like a backup is ever removed.
        if ($this->read($backup->name) !== null) {
            @unlink($this->directory.DIRECTORY_SEPARATOR.$backup->name);
        }
    }

    private function read(string $name): ?Backup
    {
        if (preg_match(self::NAME_PATTERN, $name, $parts) !== 1) {
            return null;
        }

        $path = $this->directory.DIRECTORY_SEPARATOR.$name;
        $kind = BackupKind::tryFrom($parts[3]);

        if ($kind === null || ! is_file($path)) {
            return null;
        }

        try {
            $takenAt = CarbonImmutable::createFromFormat('!Y-m-d His', "{$parts[1]} {$parts[2]}");
        } catch (Throwable) {
            return null;
        }

        if (! $takenAt instanceof CarbonImmutable || $takenAt->format('Y-m-d His') !== "{$parts[1]} {$parts[2]}") {
            return null;
        }

        return new Backup(
            name: $name,
            path: $path,
            kind: $kind,
            takenAt: $takenAt,
            bytes: (int) filesize($path),
        );
    }
}
