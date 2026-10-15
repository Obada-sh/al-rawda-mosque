<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;

/**
 * Puts the database back from the SQL written by DatabaseDumper.
 *
 * A file is first read through without running anything: it is refused
 * unless every line is one of the few statements the dumper writes. Only
 * then are the tables of the database replaced by those of the file.
 */
final class DatabaseRestorer
{
    private const NAME = '`[A-Za-z0-9_]+`';

    /**
     * Checks a dump without touching the database.
     *
     * @param  resource  $input
     * @return int the number of statements in the dump
     *
     * @throws InvalidBackup
     */
    public function inspect($input): int
    {
        $statements = 0;
        $last = null;

        foreach ($this->lines($input) as $number => $line) {
            if ($number === 1) {
                if ($line !== DatabaseDumper::HEADER) {
                    throw InvalidBackup::notABackup('the database file does not start with the header of this system');
                }

                continue;
            }

            if ($line === '' || str_starts_with($line, '-- ')) {
                continue;
            }

            if (! $this->isKnownStatement($line)) {
                throw InvalidBackup::damaged("line {$number} of the database file is not a statement this system writes");
            }

            $statements++;
            $last = $line;
        }

        if ($last !== DatabaseDumper::LAST_STATEMENT) {
            throw InvalidBackup::damaged('the database file is cut short');
        }

        return $statements;
    }

    /**
     * Replaces every table of the database by the tables of the dump.
     *
     * @param  Connection  $connection  a connection of its own (see BackupConnection); nothing else may hold the tables
     * @param  resource  $input  a dump that passed inspect()
     */
    public function restore(Connection $connection, $input): void
    {
        // Tables the dump does not know (added by a newer version) must go too.
        $connection->getSchemaBuilder()->dropAllTables();

        $pdo = $connection->getPdo();

        foreach ($this->lines($input) as $line) {
            if ($line !== '' && ! str_starts_with($line, '-- ')) {
                $pdo->exec($line);
            }
        }
    }

    /**
     * @param  resource  $input
     * @return iterable<int, string> line number (from 1) => the line without its end
     */
    private function lines($input): iterable
    {
        $number = 0;

        while (($line = fgets($input)) !== false) {
            yield ++$number => rtrim($line, "\r\n");
        }
    }

    private function isKnownStatement(string $line): bool
    {
        if (in_array($line, DatabaseDumper::FIRST_STATEMENTS, true) || $line === DatabaseDumper::LAST_STATEMENT) {
            return true;
        }

        if (preg_match('/^DROP TABLE IF EXISTS '.self::NAME.';$/', $line) === 1) {
            return true;
        }

        if (preg_match('/^INSERT INTO '.self::NAME.' \('.self::NAME.'(?:, '.self::NAME.')*\) VALUES \(/', $line) === 1) {
            return str_ends_with($line, ');');
        }

        return $this->isCreateTable($line);
    }

    /**
     * CREATE TABLE `name` ( ... ) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=...;
     *
     * What follows the last parenthesis may only be plain table options:
     * nothing there can read from, or write to, anything but the table.
     */
    private function isCreateTable(string $line): bool
    {
        if (preg_match('/^CREATE TABLE '.self::NAME.' \(/', $line) !== 1 || ! str_ends_with($line, ';')) {
            return false;
        }

        $options = preg_split('/ +/', trim(substr($line, (int) strrpos($line, ')') + 1, -1)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (($options[0] ?? null) !== 'ENGINE=InnoDB') {
            return false;
        }

        foreach ($options as $option) {
            if (preg_match('/^(?:DEFAULT|[A-Z_]+=[A-Za-z0-9_]+)$/', $option) !== 1) {
                return false;
            }
        }

        return true;
    }
}
