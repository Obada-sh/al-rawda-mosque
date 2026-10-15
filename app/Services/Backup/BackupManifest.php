<?php

namespace App\Services\Backup;

use App\Enums\BackupKind;
use Carbon\CarbonImmutable;
use JsonException;
use Throwable;

/**
 * The label of a backup: what it holds and how to check that nothing in
 * it changed. It is the file "manifest.json" of the archive.
 */
final readonly class BackupManifest
{
    public const FORMAT = 1;

    public const APP = 'rawda';

    /**
     * @param  string  $databaseSha256  the SHA-256 of the database file
     * @param  array<string, int>  $tables  table name => number of rows
     * @param  list<string>  $migrations  the migrations the database had run
     * @param  array<string, string>  $files  path inside the archive => SHA-256, for the files kept with the data
     */
    public function __construct(
        public CarbonImmutable $takenAt,
        public BackupKind $kind,
        public string $databaseSha256,
        public array $tables,
        public array $migrations,
        public array $files,
    ) {}

    public function toJson(): string
    {
        return (string) json_encode([
            'app' => self::APP,
            'format' => self::FORMAT,
            'taken_at' => $this->takenAt->toIso8601String(),
            'kind' => $this->kind->value,
            'database' => [
                'sha256' => $this->databaseSha256,
                'tables' => $this->tables,
                'migrations' => $this->migrations,
            ],
            'files' => $this->files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @throws InvalidBackup when the label is missing something, or belongs to another program
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidBackup::notABackup('the manifest cannot be read');
        }

        if (! is_array($data) || ($data['app'] ?? null) !== self::APP) {
            throw InvalidBackup::notABackup('the manifest belongs to another program');
        }

        if (($data['format'] ?? null) !== self::FORMAT) {
            throw InvalidBackup::newerVersion('unknown backup format');
        }

        $database = $data['database'] ?? null;
        $files = $data['files'] ?? [];

        $takenAt = self::moment($data['taken_at'] ?? null);
        $kind = BackupKind::tryFrom(is_string($data['kind'] ?? null) ? $data['kind'] : '');

        if (
            $takenAt === null
            || $kind === null
            || ! is_array($database)
            || ! is_string($database['sha256'] ?? null)
            || ! is_array($database['tables'] ?? null)
            || ! is_array($database['migrations'] ?? null)
            || ! is_array($files)
        ) {
            throw InvalidBackup::damaged('the manifest is incomplete');
        }

        return new self(
            takenAt: $takenAt,
            kind: $kind,
            databaseSha256: $database['sha256'],
            tables: array_map('intval', $database['tables']),
            migrations: array_values(array_map('strval', $database['migrations'])),
            files: array_map('strval', $files),
        );
    }

    private static function moment(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
