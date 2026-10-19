<?php

namespace App\Services\Backup;

use App\Enums\BackupKind;
use Carbon\CarbonImmutable;

/**
 * The folder on this computer that the Google Drive program ("Google
 * Drive for desktop") sends to Drive. The system only puts files in it
 * and takes them out; sending them to Drive (when the internet is there)
 * is the program's work.
 *
 * The system only ever touches its own evening copies there: files named
 * like a backup of the kind "evening" (BackupStore::NAME_PATTERN), and the
 * unfinished pieces of those (the same name and ".part"). Anything else in
 * the folder is left alone.
 */
final class DriveFolder
{
    /** A copy is written under this ending, and takes its name when it is whole. */
    public const PART = '.part';

    public function __construct(public readonly string $path) {}

    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Writes a small file and removes it, to know that copies can be put here.
     */
    public function canWrite(): bool
    {
        $probe = $this->path.DIRECTORY_SEPARATOR.'.rawda-check-'.bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, 'rawda') === 5;

        @unlink($probe);

        return $written;
    }

    /**
     * Whether this folder is that folder or inside it.
     */
    public function isWithin(string $folder): bool
    {
        $mine = self::comparable($this->path);
        $theirs = self::comparable($folder);

        return $mine === $theirs || str_starts_with($mine.'/', $theirs.'/');
    }

    /**
     * Puts a copy of the backup in the folder under the backup's name.
     *
     * The copy is written under a name of its own first, read back and
     * compared with the backup, and only then given the backup's name: the
     * program never sends a half-written file to Drive under that name.
     *
     * @return bool false when the copy could not be written, or does not equal the backup
     */
    public function put(Backup $backup): bool
    {
        $target = $this->path.DIRECTORY_SEPARATOR.$backup->name;
        $part = $target.self::PART;

        @unlink($part);

        if (! @copy($backup->path, $part) || ! self::sameContent($backup->path, $part)) {
            @unlink($part);

            return false;
        }

        if (is_file($target)) {
            @unlink($target);
        }

        if (! @rename($part, $target)) {
            @unlink($part);

            return false;
        }

        clearstatcache(true, $target);

        return is_file($target) && filesize($target) === $backup->bytes;
    }

    /**
     * @return list<Backup> the evening copies in the folder, the newest first
     */
    public function copies(): array
    {
        return array_values(array_filter(
            (new BackupStore($this->path))->all(),
            fn (Backup $backup): bool => $backup->kind === BackupKind::Evening,
        ));
    }

    /**
     * Removes the evening copies taken before that moment, and the pieces
     * of copies that were never finished. On Drive they go to its bin,
     * which empties itself after thirty days.
     *
     * @return array{removed: list<string>, notRemoved: list<string>} the names of the files
     */
    public function removeTakenBefore(CarbonImmutable $moment): array
    {
        $old = array_map(
            fn (Backup $backup): string => $backup->name,
            array_filter($this->copies(), fn (Backup $backup): bool => $backup->takenAt->lessThan($moment)),
        );

        $removed = [];
        $notRemoved = [];

        foreach ([...$old, ...$this->unfinishedPieces()] as $name) {
            $path = $this->path.DIRECTORY_SEPARATOR.$name;

            @unlink($path);
            clearstatcache(true, $path);

            if (file_exists($path)) {
                $notRemoved[] = $name;
            } else {
                $removed[] = $name;
            }
        }

        return ['removed' => $removed, 'notRemoved' => $notRemoved];
    }

    /**
     * @return list<string> names of evening copies that were being written when something stopped
     */
    private function unfinishedPieces(): array
    {
        $pieces = [];

        foreach (scandir($this->path) ?: [] as $name) {
            if (! str_ends_with($name, self::PART)) {
                continue;
            }

            $copy = substr($name, 0, -strlen(self::PART));

            if (preg_match(BackupStore::NAME_PATTERN, $copy, $parts) === 1 && $parts[3] === BackupKind::Evening->value && is_file($this->path.DIRECTORY_SEPARATOR.$name)) {
                $pieces[] = $name;
            }
        }

        return $pieces;
    }

    private static function sameContent(string $one, string $other): bool
    {
        clearstatcache(true, $other);

        return filesize($one) === filesize($other) && hash_file('sha256', $one) === hash_file('sha256', $other);
    }

    /**
     * A path as it can be compared with another: the real path when the
     * folder exists, forward slashes, no slash at the end; and, on Windows,
     * where "E:\Rawda" and "e:\rawda" are the same folder, in small letters.
     */
    private static function comparable(string $path): string
    {
        $real = realpath($path);
        $path = rtrim(str_replace('\\', '/', $real === false ? $path : $real), '/');

        return DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($path) : $path;
    }
}
