<?php

namespace App\Services\Backup;

use RuntimeException;
use ZipArchive;

/**
 * A backup file: a ZIP archive that holds
 *
 *   manifest.json   what the backup is, and the fingerprints of its parts
 *   database.sql    the whole database (see DatabaseDumper)
 *   files/...       the files kept with the data (the admin's slide template)
 */
final class BackupArchive
{
    public const MANIFEST = 'manifest.json';

    public const DATABASE = 'database.sql';

    private const FILES = 'files/';

    private function __construct(
        private readonly ZipArchive $zip,
        public readonly BackupManifest $manifest,
    ) {}

    public function __destruct()
    {
        @$this->zip->close();
    }

    /**
     * Writes a backup file.
     *
     * @param  callable(string $databaseSha256, array<string, string> $fileFingerprints): BackupManifest  $manifest
     * @param  string  $databaseFile  the SQL of the database, on disk
     * @param  array<string, string>  $files  name inside the archive (under "files/") => contents
     */
    public static function write(string $path, callable $manifest, string $databaseFile, array $files): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("The backup file [{$path}] cannot be created.");
        }

        $fingerprints = array_map(fn (string $contents): string => hash('sha256', $contents), $files);
        $label = $manifest((string) hash_file('sha256', $databaseFile), $fingerprints);

        $written = $zip->addFromString(self::MANIFEST, $label->toJson())
            && $zip->addFile($databaseFile, self::DATABASE);

        foreach ($files as $name => $contents) {
            $written = $written && $zip->addFromString(self::FILES.$name, $contents);
        }

        // The archive is really written when it is closed.
        if (! $zip->close() || ! $written) {
            @unlink($path);

            throw new RuntimeException("The backup file [{$path}] could not be written (is the disk full?).");
        }
    }

    /**
     * @throws InvalidBackup when the file is not a backup of this system
     */
    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw InvalidBackup::notABackup('the file is not a ZIP archive');
        }

        $manifest = $zip->getFromName(self::MANIFEST);

        if ($manifest === false || $zip->locateName(self::DATABASE) === false) {
            $zip->close();

            throw InvalidBackup::notABackup('the archive has no manifest or no database file');
        }

        try {
            return new self($zip, BackupManifest::fromJson($manifest));
        } catch (InvalidBackup $exception) {
            $zip->close();

            throw $exception;
        }
    }

    /**
     * Reads every part and compares it with its fingerprint in the manifest.
     *
     * @throws InvalidBackup when a part is missing, cut short or altered
     */
    public function verify(): void
    {
        $hash = hash_init('sha256');
        hash_update_stream($hash, $this->database());

        if (! hash_equals($this->manifest->databaseSha256, hash_final($hash))) {
            throw InvalidBackup::damaged('the database file does not match its fingerprint');
        }

        foreach ($this->manifest->files as $name => $fingerprint) {
            $contents = $this->zip->getFromName(self::FILES.$name);

            if ($contents === false || ! hash_equals($fingerprint, hash('sha256', $contents))) {
                throw InvalidBackup::damaged("the file [{$name}] does not match its fingerprint");
            }
        }
    }

    /**
     * @return resource the SQL of the database, read from its beginning
     */
    public function database()
    {
        $stream = $this->zip->getStream(self::DATABASE);

        if ($stream === false) {
            throw InvalidBackup::damaged('the database file cannot be read');
        }

        return $stream;
    }

    /**
     * The files kept with the data.
     *
     * @return array<string, string> name => contents
     */
    public function files(): array
    {
        $files = [];

        foreach (array_keys($this->manifest->files) as $name) {
            $contents = $this->zip->getFromName(self::FILES.$name);

            if ($contents === false) {
                throw InvalidBackup::damaged("the file [{$name}] is missing");
            }

            $files[$name] = $contents;
        }

        return $files;
    }
}
