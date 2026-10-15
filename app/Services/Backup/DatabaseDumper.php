<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Writes the whole database as SQL: the structure of every table and
 * its rows.
 *
 * The file is ordinary SQL that MySQL Workbench or the mysql program can
 * import. It is also written in a strict shape, so that the system can
 * check a file line by line before restoring it:
 *
 *  - the first line is the header;
 *  - every other line is one complete statement (a text value never
 *    contains a line break: MySQL writes it as \n);
 *  - only DROP TABLE IF EXISTS, CREATE TABLE, INSERT INTO and three SET
 *    statements are used.
 *
 * It is done in PHP, without the mysqldump program, so that it works the
 * same on every machine and under a folder with an Arabic name.
 */
final class DatabaseDumper
{
    public const HEADER = '-- rawda backup, format 1';

    public const FIRST_STATEMENTS = ['SET NAMES utf8mb4;', 'SET FOREIGN_KEY_CHECKS=0;'];

    public const LAST_STATEMENT = 'SET FOREIGN_KEY_CHECKS=1;';

    /** Tables that only hold what the system rebuilds as it runs: their structure is kept, not their rows. */
    private const WITHOUT_ROWS = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    private const ROWS_PER_STATEMENT = 200;

    private const BYTES_PER_STATEMENT = 512 * 1024;

    /**
     * @param  Connection  $connection  a connection of its own (see BackupConnection)
     * @param  resource  $output  where the SQL is written
     * @return array<string, int> table name => rows written
     */
    public function dump(Connection $connection, $output): array
    {
        $pdo = $connection->getPdo();
        $rows = [];

        $this->write($output, self::HEADER);

        foreach (self::FIRST_STATEMENTS as $statement) {
            $this->write($output, $statement);
        }

        // One picture of the database for the whole copy, whatever is recorded while it is taken.
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        try {
            foreach ($this->tables($pdo) as $table) {
                $this->write($output, 'DROP TABLE IF EXISTS '.$this->quoteName($table).';');
                $this->write($output, $this->createStatement($pdo, $table));

                $rows[$table] = in_array($table, self::WITHOUT_ROWS, true) ? 0 : $this->writeRows($pdo, $table, $output);
            }
        } finally {
            $pdo->exec('COMMIT');
        }

        $this->write($output, self::LAST_STATEMENT);

        return $rows;
    }

    /**
     * @return list<string> in alphabetical order, so two backups of the same data are alike
     */
    private function tables(PDO $pdo): array
    {
        $tables = array_map(
            fn (array $row): string => (string) $row[0],
            $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM),
        );

        sort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * The CREATE TABLE statement as MySQL gives it, on one line.
     */
    private function createStatement(PDO $pdo, string $table): string
    {
        $row = $pdo->query('SHOW CREATE TABLE '.$this->quoteName($table))->fetchAll(PDO::FETCH_NUM)[0] ?? null;

        if (! is_array($row) || ! is_string($row[1] ?? null)) {
            throw new RuntimeException("The structure of the table [{$table}] cannot be read.");
        }

        return preg_replace('/\s*\R\s*/', ' ', trim($row[1])).';';
    }

    /**
     * @param  resource  $output
     * @return int the number of rows written
     */
    private function writeRows(PDO $pdo, string $table, $output): int
    {
        $columns = $this->storedColumns($pdo, $table);

        if ($columns === []) {
            return 0;
        }

        $names = implode(', ', array_map($this->quoteName(...), $columns));
        $insert = 'INSERT INTO '.$this->quoteName($table)." ({$names}) VALUES ";

        // The rows are read one at a time: the table is never held in memory as a whole.
        $statement = $pdo->query("SELECT {$names} FROM ".$this->quoteName($table));

        $count = 0;
        $tuples = [];
        $bytes = 0;

        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            $tuple = '('.implode(', ', array_map(fn (mixed $value): string => $this->literal($pdo, $value), $row)).')';

            $tuples[] = $tuple;
            $bytes += strlen($tuple);
            $count++;

            if (count($tuples) >= self::ROWS_PER_STATEMENT || $bytes >= self::BYTES_PER_STATEMENT) {
                $this->write($output, $insert.implode(', ', $tuples).';');
                $tuples = [];
                $bytes = 0;
            }
        }

        $statement->closeCursor();

        if ($tuples !== []) {
            $this->write($output, $insert.implode(', ', $tuples).';');
        }

        return $count;
    }

    /**
     * The columns that hold a value of their own (a generated column is
     * computed by MySQL and cannot be written).
     *
     * @return list<string>
     */
    private function storedColumns(PDO $pdo, string $table): array
    {
        $columns = [];

        foreach ($pdo->query('SHOW COLUMNS FROM '.$this->quoteName($table))->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (! str_contains(strtoupper((string) ($column['Extra'] ?? '')), 'GENERATED')) {
                $columns[] = (string) $column['Field'];
            }
        }

        return $columns;
    }

    /**
     * A value as it is written in SQL.
     */
    private function literal(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new RuntimeException('A number that is not finite cannot be backed up.');
            }

            return (string) json_encode($value);
        }

        $value = (string) $value;

        // Bytes that are not text (a picture kept in the database) are written as they are, in hexadecimal.
        if (! mb_check_encoding($value, 'UTF-8')) {
            return '0x'.strtoupper(bin2hex($value));
        }

        $quoted = $pdo->quote($value);

        if ($quoted === false || strpbrk($quoted, "\r\n") !== false) {
            throw new RuntimeException('A text value could not be written on one line; check the sql_mode of the MySQL server.');
        }

        return $quoted;
    }

    /**
     * The name of a table or a column, between backticks.
     */
    private function quoteName(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException("The name [{$name}] is not one this system gives to a table or a column.");
        }

        return "`{$name}`";
    }

    /**
     * @param  resource  $output
     */
    private function write($output, string $line): void
    {
        $line .= "\n";

        if (fwrite($output, $line) !== strlen($line)) {
            throw new RuntimeException('The backup could not be written (is the disk full?).');
        }
    }
}
