<?php

namespace Tests\Unit;

use App\Enums\BackupKind;
use App\Services\Backup\Backup;
use App\Services\Backup\BackupArchive;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\BackupStore;
use App\Services\Backup\InvalidBackup;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The backup files themselves: their folder, their names, and what an
 * archive must hold to be opened.
 */
class BackupFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rawda-backups-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);

        parent::tearDown();
    }

    private function store(): BackupStore
    {
        return new BackupStore($this->directory);
    }

    private function put(string $name, string $contents = 'x'): string
    {
        $this->store()->isWritable();
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.$name, $contents);

        return $name;
    }

    private function manifest(string $databaseSha256, array $files = []): BackupManifest
    {
        return new BackupManifest(
            takenAt: CarbonImmutable::parse('2026-10-07T17:00:00+03:00'),
            kind: BackupKind::Manual,
            databaseSha256: $databaseSha256,
            tables: ['students' => 3, 'users' => 1],
            migrations: ['0001_01_01_000000_create_users_table'],
            files: $files,
        );
    }

    /**
     * Writes an archive of the given entries, as another program would.
     *
     * @param  array<string, string>  $entries
     */
    private function zip(array $entries): string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'made-by-hand-'.bin2hex(random_bytes(3)).'.zip';
        $this->store()->isWritable();

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    public function test_the_folder_is_made_when_it_is_missing(): void
    {
        $this->assertDirectoryDoesNotExist($this->directory);
        $this->assertSame([], $this->store()->all());

        $this->assertTrue($this->store()->isWritable());
        $this->assertDirectoryExists($this->directory);
    }

    public function test_a_folder_that_cannot_be_made_is_not_writable(): void
    {
        $this->put('a-file');

        $this->assertFalse((new BackupStore($this->directory.DIRECTORY_SEPARATOR.'a-file'.DIRECTORY_SEPARATOR.'backups'))->isWritable());
    }

    public function test_the_name_of_a_file_says_when_and_why_the_backup_was_taken(): void
    {
        $this->put('rawda-backup-2026-10-07_170000-auto.zip', '12345');

        $backup = $this->store()->find('rawda-backup-2026-10-07_170000-auto.zip');

        $this->assertInstanceOf(Backup::class, $backup);
        $this->assertSame(BackupKind::Automatic, $backup->kind);
        $this->assertSame('2026-10-07 17:00:00', $backup->takenAt->format('Y-m-d H:i:s'));
        $this->assertSame(5, $backup->bytes);
        $this->assertSame($this->directory.DIRECTORY_SEPARATOR.'rawda-backup-2026-10-07_170000-auto.zip', $backup->path);

        $this->assertSame(
            'rawda-backup-2026-10-07_170000-before-restore.zip',
            BackupStore::nameOf(BackupKind::BeforeRestore, CarbonImmutable::parse('2026-10-07 17:00:00')),
        );
    }

    public function test_only_files_named_like_a_backup_are_listed_the_newest_first(): void
    {
        $this->put('rawda-backup-2026-10-05_080000-auto.zip');
        $this->put('rawda-backup-2026-10-07_170000-manual.zip');
        $this->put('rawda-backup-2026-10-07_170001-before-restore.zip');
        $this->put('rawda-backup-2026-10-06_090000-uploaded.zip');

        foreach ([
            'notes.txt',
            'rawda-backup-2026-10-07_170000-auto.zip.part',
            'rawda-backup-2026-10-07_170000-unknown.zip',
            'rawda-backup-2026-13-45_170000-auto.zip',
            'rawda-backup-2026-10-07_250000-auto.zip',
            'my-rawda-backup-2026-10-07_170000-auto.zip',
        ] as $other) {
            $this->put($other);
            $this->assertNull($this->store()->find($other), $other);
        }

        $this->assertSame([
            'rawda-backup-2026-10-07_170001-before-restore.zip',
            'rawda-backup-2026-10-07_170000-manual.zip',
            'rawda-backup-2026-10-06_090000-uploaded.zip',
            'rawda-backup-2026-10-05_080000-auto.zip',
        ], array_map(fn (Backup $backup): string => $backup->name, $this->store()->all()));

        $this->assertSame('rawda-backup-2026-10-05_080000-auto.zip', $this->store()->takenOn('2026-10-05', BackupKind::Automatic)?->name);
        $this->assertNull($this->store()->takenOn('2026-10-07', BackupKind::Automatic));
    }

    public function test_a_name_never_points_outside_the_folder(): void
    {
        $this->put('rawda-backup-2026-10-07_170000-auto.zip');

        foreach (['../rawda-backup-2026-10-07_170000-auto.zip', 'sub/rawda-backup-2026-10-07_170000-auto.zip', '..\\x.zip', '', '.'] as $name) {
            $this->assertNull($this->store()->find($name));
        }
    }

    public function test_two_backups_of_the_same_second_get_two_names(): void
    {
        $at = CarbonImmutable::parse('2026-10-07 17:00:00');

        $first = $this->store()->pathFor(BackupKind::Manual, $at);
        $this->assertSame('rawda-backup-2026-10-07_170000-manual.zip', basename($first));

        $this->put(basename($first));

        $this->assertSame('rawda-backup-2026-10-07_170001-manual.zip', basename($this->store()->pathFor(BackupKind::Manual, $at)));
        $this->assertSame('rawda-backup-2026-10-07_170000-auto.zip', basename($this->store()->pathFor(BackupKind::Automatic, $at)));
    }

    public function test_deleting_a_backup_removes_its_file_only(): void
    {
        $this->put('rawda-backup-2026-10-07_170000-manual.zip');
        $this->put('notes.txt');

        $backup = $this->store()->find('rawda-backup-2026-10-07_170000-manual.zip');
        $this->store()->delete($backup);

        $this->assertFileDoesNotExist($backup->path);
        $this->assertFileExists($this->directory.DIRECTORY_SEPARATOR.'notes.txt');

        // Deleting it again, or something that is not a backup, does nothing.
        $this->store()->delete($backup);
        $this->store()->delete(new Backup('notes.txt', $this->directory.DIRECTORY_SEPARATOR.'notes.txt', BackupKind::Manual, CarbonImmutable::now(), 1));
        $this->assertFileExists($this->directory.DIRECTORY_SEPARATOR.'notes.txt');
    }

    public function test_the_manifest_survives_being_written_and_read(): void
    {
        $manifest = BackupManifest::fromJson($this->manifest('abc', ['slide-templates/leaders.pptx' => 'def'])->toJson());

        $this->assertSame('2026-10-07T17:00:00+03:00', $manifest->takenAt->toIso8601String());
        $this->assertSame(BackupKind::Manual, $manifest->kind);
        $this->assertSame('abc', $manifest->databaseSha256);
        $this->assertSame(['students' => 3, 'users' => 1], $manifest->tables);
        $this->assertSame(['0001_01_01_000000_create_users_table'], $manifest->migrations);
        $this->assertSame(['slide-templates/leaders.pptx' => 'def'], $manifest->files);
    }

    public function test_a_manifest_of_something_else_is_refused(): void
    {
        $good = json_decode($this->manifest('abc')->toJson(), true);

        $cases = [
            InvalidBackup::NOT_A_BACKUP => ['not json at all', '[]', json_encode(['app' => 'another program'] + $good)],
            InvalidBackup::NEWER_VERSION => [json_encode(['format' => 2] + $good)],
            InvalidBackup::DAMAGED => [
                json_encode(['taken_at' => ''] + $good),
                json_encode(['taken_at' => 'not a date'] + $good),
                json_encode(['kind' => 'weekly'] + $good),
                json_encode(['database' => ['sha256' => 'abc']] + $good),
                json_encode(array_diff_key($good, ['database' => true])),
            ],
        ];

        foreach ($cases as $reason => $documents) {
            foreach ($documents as $json) {
                try {
                    BackupManifest::fromJson((string) $json);
                    $this->fail("A manifest was accepted: {$json}");
                } catch (InvalidBackup $exception) {
                    $this->assertSame($reason, $exception->reason, (string) $json);
                }
            }
        }
    }

    public function test_an_archive_is_written_and_checked_against_its_fingerprints(): void
    {
        $this->store()->isWritable();
        $sql = $this->directory.DIRECTORY_SEPARATOR.'database.sql';
        file_put_contents($sql, "-- rawda backup, format 1\nSET NAMES utf8mb4;\n");
        $path = $this->directory.DIRECTORY_SEPARATOR.'rawda-backup-2026-10-07_170000-manual.zip';

        BackupArchive::write(
            $path,
            fn (string $databaseSha256, array $files): BackupManifest => $this->manifest($databaseSha256, $files),
            $sql,
            ['slide-templates/leaders.pptx' => 'the template'],
        );

        $archive = BackupArchive::open($path);
        $archive->verify();

        $this->assertSame(hash_file('sha256', $sql), $archive->manifest->databaseSha256);
        $this->assertSame(file_get_contents($sql), stream_get_contents($archive->database()));
        $this->assertSame(['slide-templates/leaders.pptx' => 'the template'], $archive->files());
    }

    public function test_an_archive_that_is_not_a_backup_cannot_be_opened(): void
    {
        $text = $this->directory.DIRECTORY_SEPARATOR.$this->put('plain.zip', 'just text');
        $manifest = $this->manifest(hash('sha256', 'sql'))->toJson();

        foreach ([
            $text,
            $this->directory.DIRECTORY_SEPARATOR.'missing.zip',
            $this->zip(['hello.txt' => 'hello']),
            $this->zip([BackupArchive::MANIFEST => $manifest]),
            $this->zip([BackupArchive::DATABASE => 'sql']),
        ] as $path) {
            try {
                BackupArchive::open($path);
                $this->fail('An archive that is not a backup was opened.');
            } catch (InvalidBackup $exception) {
                $this->assertSame(InvalidBackup::NOT_A_BACKUP, $exception->reason);
            }
        }
    }

    public function test_an_altered_archive_does_not_pass_the_check(): void
    {
        $manifest = $this->manifest(hash('sha256', 'sql'), ['slide-templates/leaders.pptx' => hash('sha256', 'template')])->toJson();

        $whole = BackupArchive::open($this->zip([
            BackupArchive::MANIFEST => $manifest,
            BackupArchive::DATABASE => 'sql',
            'files/slide-templates/leaders.pptx' => 'template',
        ]));
        $whole->verify();
        $this->addToAssertionCount(1);

        $altered = [
            [BackupArchive::MANIFEST => $manifest, BackupArchive::DATABASE => 'sql; DROP TABLE x', 'files/slide-templates/leaders.pptx' => 'template'],
            [BackupArchive::MANIFEST => $manifest, BackupArchive::DATABASE => 'sql', 'files/slide-templates/leaders.pptx' => 'another template'],
            [BackupArchive::MANIFEST => $manifest, BackupArchive::DATABASE => 'sql'],
        ];

        foreach ($altered as $entries) {
            try {
                BackupArchive::open($this->zip($entries))->verify();
                $this->fail('An altered archive passed the check.');
            } catch (InvalidBackup $exception) {
                $this->assertSame(InvalidBackup::DAMAGED, $exception->reason);
            }
        }
    }
}
