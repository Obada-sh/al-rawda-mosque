<?php

namespace App\Services\Demo;

/**
 * The demo is the system running on invented data, to try it or to train
 * someone on it (demo.bat).
 *
 * What makes a running system the demo is its database, and nothing else:
 * a database whose name ends with "_demo". The system therefore never
 * believes it is the demo while it holds the real data, however it was
 * started.
 */
final class DemoMode
{
    public const DATABASE_SUFFIX = '_demo';

    /**
     * @param  string  $database  the name of the database this system works on
     * @param  int  $port  the port demo.bat serves the demo at
     */
    public function __construct(
        private readonly string $database,
        private readonly int $port,
    ) {}

    public static function isDemoDatabase(string $name): bool
    {
        return $name !== self::DATABASE_SUFFIX && str_ends_with($name, self::DATABASE_SUFFIX);
    }

    /**
     * Whether everything this system shows is invented.
     */
    public function isOn(): bool
    {
        return self::isDemoDatabase($this->database);
    }

    public function database(): string
    {
        return $this->database;
    }

    /**
     * People try things at the address of the demo that they would never
     * do to the real data. A system that holds the real data must
     * therefore never answer there.
     */
    public function wouldShowRealDataAt(int $port): bool
    {
        return $port === $this->port && ! $this->isOn();
    }
}
