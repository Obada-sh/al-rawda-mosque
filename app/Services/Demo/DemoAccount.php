<?php

namespace App\Services\Demo;

/**
 * The account everybody signs in to the demo with.
 *
 * It only ever exists in a demo database, which holds invented data: its
 * password protects nothing, and the sign-in page of the demo shows it.
 * The admin of a real installation is created by the person who installs
 * it, on first use.
 */
final class DemoAccount
{
    public const NAME = 'مدير تجريبي';

    public const USERNAME = 'demo';

    public const PASSWORD = 'rawda-demo-1448';
}
