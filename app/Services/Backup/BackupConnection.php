<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * A connection to the system's database that backups have to themselves.
 *
 * Copying and restoring must not disturb the connection the rest of the
 * system works through, and they need two settings of their own:
 *
 *  - rows arrive one at a time instead of a whole table at once, so the
 *    copy needs little memory however large a table grows;
 *  - one command may carry one statement only: a line of a restored file
 *    can never bring a second statement with it.
 */
final class BackupConnection
{
    public const NAME = 'rawda_backup';

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly Config $config,
    ) {}

    public function open(): Connection
    {
        $default = (string) $this->config->get('database.default');
        $settings = $this->config->get("database.connections.{$default}");

        if (! is_array($settings) || ! in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backups are made for a MySQL database.');
        }

        $settings['options'] = [
            self::option('USE_BUFFERED_QUERY') => false,
            self::option('MULTI_STATEMENTS') => false,
        ] + ($settings['options'] ?? []);

        $this->config->set('database.connections.'.self::NAME, $settings);
        $this->databases->purge(self::NAME);

        return $this->databases->connection(self::NAME);
    }

    public function close(): void
    {
        $this->databases->purge(self::NAME);
    }

    /**
     * PHP 8.5 moved the MySQL options of PDO to a class of their own.
     */
    private static function option(string $name): int
    {
        return (int) constant(PHP_VERSION_ID >= 80500 ? "Pdo\\Mysql::ATTR_{$name}" : "PDO::MYSQL_ATTR_{$name}");
    }
}
