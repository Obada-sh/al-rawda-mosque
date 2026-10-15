<?php

/*
| Run by deploy\serve.bat before anything else: says, in plain words, why
| the PHP of this computer cannot run the system. Exits with 0 when it can.
| It runs before the system is loaded, so it uses nothing of it.
*/

$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDOUT, '  The files of the system are incomplete: the "vendor" folder is missing.'.PHP_EOL);

    exit(1);
}

// The packages of the system know the oldest PHP they run on. Their own
// check stops here, with its own message, when this PHP is older.
$platformCheck = $root.'/vendor/composer/platform_check.php';

if (is_file($platformCheck)) {
    require $platformCheck;
}

$missing = array_values(array_filter(
    ['curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'zip'],
    static fn (string $extension): bool => ! extension_loaded($extension),
));

if ($missing === []) {
    exit(0);
}

fwrite(STDOUT, '  These parts of PHP are not loaded: '.implode(', ', $missing).'.'.PHP_EOL);

$settings = php_ini_loaded_file();

fwrite(STDOUT, $settings === false
    ? '  PHP read no settings file (php.ini). Start the system with start.bat, which gives'.PHP_EOL
        .'  PHP its settings; if it still fails, move the folder of the system to a plain'.PHP_EOL
        .'  path such as C:\rawda.'.PHP_EOL
    : '  Enable them in its settings file: '.$settings.PHP_EOL);

exit(1);
