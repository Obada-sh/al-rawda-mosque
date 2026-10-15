<?php

namespace Tests\Unit;

use App\Services\Backup\DatabaseDumper;
use App\Services\Backup\DatabaseRestorer;
use App\Services\Backup\InvalidBackup;
use PHPUnit\Framework\TestCase;

/**
 * What the system accepts as the SQL of a backup, before anything of it
 * is run.
 */
class DatabaseRestorerTest extends TestCase
{
    private const CREATE = 'CREATE TABLE `circles` ( `id` bigint unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL, '
        .'PRIMARY KEY (`id`), CONSTRAINT `circles_chk` CHECK ((`id` >= 1)) ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

    private const INSERT = "INSERT INTO `circles` (`id`, `name`) VALUES (1, 'حلقة أ'), (2, 'حلقة \\'ب\\'; DROP TABLE x; --\\nسطر');";

    /**
     * @param  list<string>  $lines
     * @return resource
     */
    private static function dump(array $lines)
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, implode("\n", $lines)."\n");
        rewind($stream);

        return $stream;
    }

    /**
     * A whole dump around the given statements.
     *
     * @param  list<string>  $statements
     * @return list<string>
     */
    private static function around(array $statements): array
    {
        return [
            DatabaseDumper::HEADER,
            ...DatabaseDumper::FIRST_STATEMENTS,
            ...$statements,
            DatabaseDumper::LAST_STATEMENT,
        ];
    }

    private function assertRefused(string $reason, array $lines): void
    {
        try {
            (new DatabaseRestorer)->inspect(self::dump($lines));
            $this->fail('The dump was accepted: '.implode(' | ', $lines));
        } catch (InvalidBackup $exception) {
            $this->assertSame($reason, $exception->reason, $exception->getMessage());
        }
    }

    public function test_the_statements_the_dumper_writes_are_accepted(): void
    {
        $statements = (new DatabaseRestorer)->inspect(self::dump(self::around([
            'DROP TABLE IF EXISTS `circles`;',
            self::CREATE,
            self::INSERT,
            '-- a comment is not a statement',
            '',
        ])));

        // Two at the beginning, three of the table, one at the end.
        $this->assertSame(6, $statements);
    }

    public function test_a_file_without_the_header_is_not_a_backup(): void
    {
        $this->assertRefused(InvalidBackup::NOT_A_BACKUP, ['-- MySQL dump 10.13', 'SET NAMES utf8mb4;', DatabaseDumper::LAST_STATEMENT]);
        $this->assertRefused(InvalidBackup::NOT_A_BACKUP, ['DROP TABLE IF EXISTS `circles`;']);
        $this->assertRefused(InvalidBackup::NOT_A_BACKUP, []);
    }

    public function test_a_file_cut_short_is_refused(): void
    {
        $this->assertRefused(InvalidBackup::DAMAGED, [DatabaseDumper::HEADER, ...DatabaseDumper::FIRST_STATEMENTS, self::CREATE]);
        $this->assertRefused(InvalidBackup::DAMAGED, [DatabaseDumper::HEADER]);
    }

    public function test_any_other_statement_is_refused(): void
    {
        $foreign = [
            'DELETE FROM `circles`;',
            'UPDATE `users` SET `password` = \'x\';',
            'DROP DATABASE `rawda`;',
            'DROP TABLE `circles`;',
            'GRANT ALL ON *.* TO \'someone\'@\'%\';',
            'SET GLOBAL general_log = 1;',
            "SELECT * FROM `users` INTO OUTFILE 'C:/x.txt';",
            'INSERT INTO circles (id) VALUES (1);',
            "INSERT INTO `circles` SELECT * FROM `mysql`.`user`;",
            'INSERT INTO `circles` (`id`) VALUES (1)',
            "LOAD DATA INFILE 'x' INTO TABLE `circles`;",
            // A table may only be created plainly: not from a query, not in another engine, not with a path.
            'CREATE TABLE `x` (`id` int) ENGINE=InnoDB SELECT * FROM `users`;',
            'CREATE TABLE `x` AS SELECT * FROM `users`;',
            "CREATE TABLE `x` (`id` int) ENGINE=InnoDB DATA DIRECTORY='C:/';",
            'CREATE TABLE `x` (`id` int) ENGINE=FEDERATED CONNECTION=mysql;',
            'CREATE TABLE `x` (`id` int);',
            'CREATE TABLE `other`.`x` (`id` int) ENGINE=InnoDB;',
            'CREATE TRIGGER `t` BEFORE INSERT ON `circles` FOR EACH ROW SET NEW.id = 1;',
        ];

        foreach ($foreign as $statement) {
            $this->assertRefused(InvalidBackup::DAMAGED, self::around([$statement]));
        }
    }
}
