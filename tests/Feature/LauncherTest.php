<?php

namespace Tests\Feature;

use App\Http\Requests\Backups\BackupUploadRequest;
use App\Services\Demo\DemoMode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * start.bat, demo.bat and update.bat cannot be run by the tests, but what
 * they promise can be read in them. A mistake there once served the real
 * data at the address of the demo, so their promises are checked like any
 * other rule of the system.
 */
class LauncherTest extends TestCase
{
    private static function script(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /**
     * The script without its comments: what Windows runs.
     */
    private static function commands(string $path): string
    {
        return implode("\n", array_filter(
            preg_split('/\r?\n/', self::script($path)) ?: [],
            fn (string $line): bool => preg_match('/^\s*rem(\s|$)/i', $line) !== 1,
        ));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function scripts(): array
    {
        return [
            'start.bat' => ['start.bat'],
            'demo.bat' => ['demo.bat'],
            'update.bat' => ['update.bat'],
            'deploy/serve.bat' => ['deploy/serve.bat'],
            'deploy/after-update.bat' => ['deploy/after-update.bat'],
            'deploy/make-package.bat' => ['deploy/make-package.bat'],
        ];
    }

    #[DataProvider('scripts')]
    public function test_a_script_is_text_that_windows_runs_as_it_is(string $path): void
    {
        $script = self::script($path);

        $this->assertNotSame('', $script);
        // The command interpreter reads a script in the code page of the computer: only ASCII means the same everywhere.
        $this->assertSame(1, preg_match('/\A[\x09\x0A\x0D\x20-\x7E]+\z/', $script), "{$path} holds something else than ASCII.");
        // And it loses its way in a script whose lines end the Unix way.
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $script), "{$path} has a line that does not end with CR LF.");
    }

    public function test_git_hands_the_scripts_out_with_windows_line_endings(): void
    {
        // The project keeps its text files with Unix line endings. Without this
        // line a clone would get its scripts that way too.
        $this->assertSame(1, preg_match('/^\*\.bat\s+text\s+eol=crlf$/m', self::script('.gitattributes')));
    }

    public function test_the_demo_is_served_at_its_own_address_on_a_demo_database(): void
    {
        $demo = self::commands('demo.bat');

        $this->assertSame(1, preg_match('/^call "%~dp0deploy\\\\serve\.bat" (\d+) demo$/m', $demo, $served));
        $this->assertSame((int) config('rawda.demo.port'), (int) $served[1]);

        $this->assertSame(1, preg_match('/^set "DB_DATABASE=([^"]+)"$/m', $demo, $database));
        $this->assertTrue(DemoMode::isDemoDatabase($database[1]));

        // Its backups, its uploaded files and its sign-in are its own as well.
        foreach (['RAWDA_BACKUPS_PATH', 'RAWDA_FILES_PATH', 'SESSION_COOKIE', 'CACHE_PREFIX'] as $setting) {
            $this->assertSame(1, preg_match('/^set "'.$setting.'=[^"]*demo[^"]*"$/m', $demo), "demo.bat does not give the demo a {$setting} of its own.");
        }
    }

    public function test_the_real_system_is_served_elsewhere_on_the_database_of_its_settings_file(): void
    {
        $start = self::commands('start.bat');

        $this->assertSame(1, preg_match('/^call "%~dp0deploy\\\\serve\.bat" (\d+)$/m', $start, $served));
        $this->assertNotSame((int) config('rawda.demo.port'), (int) $served[1]);

        foreach (['DB_DATABASE', 'RAWDA_BACKUPS_PATH', 'RAWDA_FILES_PATH'] as $setting) {
            $this->assertStringNotContainsString($setting, $start);
        }

        // Where the system really runs, nobody is shown a debug page.
        $this->assertStringContainsString('set "APP_ENV=production"', $start);
        $this->assertStringContainsString('set "APP_DEBUG=false"', $start);
    }

    public function test_what_the_caller_sets_reaches_the_system_that_is_served(): void
    {
        $serve = self::commands('deploy/serve.bat');

        // "artisan serve" hides the environment of its caller from the system it
        // serves, which then reads the database of the settings file instead.
        $this->assertStringNotContainsString('artisan serve', $serve);

        // PHP's own web server is started directly, for this computer only...
        $this->assertSame(1, preg_match('/^call "%PHP%" %WEB_INI% %WEB_LIMITS% -S 127\.0\.0\.1:%PORT% (\S+) > "%LOG%" 2>&1$/m', $serve, $server));
        $this->assertSame(1, preg_match('/^cd \/d "%ROOT%\\\\public"$/m', $serve));

        // ...with the router of the framework that is installed.
        $this->assertFileExists(public_path(str_replace('\\', '/', $server[1])));
    }

    public function test_the_php_that_comes_with_the_system_is_told_where_its_settings_are(): void
    {
        $serve = self::commands('deploy/serve.bat');

        // Left to look for php.ini itself, PHP does not find it when the folder of the
        // system has letters that are not English: every start names the file, by a
        // path that does not go through that folder.
        $this->assertStringContainsString('set "CLI_INI=-c php\\php.ini"', $serve);
        $this->assertStringContainsString('set "WEB_INI=-c ..\\php\\php.ini"', $serve);

        $this->assertSame(
            substr_count($serve, 'call "%PHP%" '),
            substr_count($serve, 'call "%PHP%" %CLI_INI% ') + substr_count($serve, 'call "%PHP%" %WEB_INI% ') + substr_count($serve, 'call "%PHP%" -v '),
            'Every start of PHP in deploy/serve.bat names its settings file (the first probe, "-v", needs none).',
        );

        // And the file it names is the one the package ships.
        $this->assertFileExists(base_path('deploy/php.ini'));
    }

    public function test_the_web_server_gets_room_for_a_backup_file(): void
    {
        $serve = self::commands('deploy/serve.bat');

        $this->assertSame(1, preg_match('/^set "WEB_LIMITS=(.*)"$/m', $serve, $limits));
        $this->assertSame(1, preg_match('/-d upload_max_filesize=(\S+)/', $limits[1], $upload));
        $this->assertSame(1, preg_match('/-d post_max_size=(\S+)/', $limits[1], $post));

        $largest = BackupUploadRequest::MAX_KILOBYTES * 1024;

        $this->assertGreaterThanOrEqual($largest, ini_parse_quantity($upload[1]));
        // The request that carries the file is a little larger than the file.
        $this->assertGreaterThan(ini_parse_quantity($upload[1]), ini_parse_quantity($post[1]));
    }

    public function test_the_update_waits_until_the_system_and_the_demo_are_closed(): void
    {
        $update = self::commands('update.bat');

        $this->assertSame(1, preg_match('/^for %%P in \(([\d ]+)\) do \($/m', $update, $checked));
        $ports = array_map('intval', explode(' ', $checked[1]));

        foreach (['start.bat', 'demo.bat'] as $launcher) {
            $this->assertSame(1, preg_match('/serve\.bat" (\d+)/', self::commands($launcher), $served));
            $this->assertContains((int) $served[1], $ports, "update.bat does not check the port {$launcher} serves at.");
        }
    }

    public function test_the_update_never_overwrites_what_was_changed_on_the_computer(): void
    {
        $update = self::commands('update.bat');
        $download = strpos($update, 'git pull');

        $this->assertNotFalse($download);
        // A changed file stops the update before anything is downloaded...
        $this->assertStringContainsString("git status --porcelain -uno", substr($update, 0, $download));
        // ...the download only ever moves forward...
        $this->assertSame(substr_count($update, 'git pull'), substr_count($update, 'git pull --ff-only'));
        // ...and the only way back is to the version noted before the download.
        $this->assertSame(substr_count($update, 'git reset'), substr_count($update, 'git reset --hard %BEFORE%'));
        $this->assertStringContainsString("('git rev-parse HEAD') do set \"BEFORE=%%H\"", substr($update, 0, $download));
    }

    public function test_the_update_survives_being_replaced_by_the_version_it_downloads(): void
    {
        $lines = preg_split('/\r?\n/', self::commands('update.bat')) ?: [];

        // The block: from the line that only opens it to the line that only closes it.
        $open = array_search('(', $lines, true);
        $this->assertNotFalse($open);

        $close = array_search(')', array_slice($lines, $open, preserve_keys: true), true);
        $this->assertNotFalse($close);

        $block = implode("\n", array_slice($lines, $open, $close - $open + 1));

        // Everything that follows the download is in one block, which the command
        // interpreter reads whole before the file can change under it...
        $this->assertStringContainsString('git pull --ff-only', $block);
        $this->assertStringContainsString('call "%~dp0deploy\\after-update.bat" called', $block);
        $this->assertStringNotContainsString('git pull', implode("\n", array_slice($lines, 0, $open)));
        // ...nothing in it looks for another place of the file...
        $this->assertSame(0, preg_match('/\bgoto\b/i', $block));
        // ...and it always ends the script itself.
        $this->assertSame('exit /b 0', trim($lines[$close - 1]));
    }

    public function test_the_update_installs_the_libraries_the_tests_ran_with(): void
    {
        $after = self::commands('deploy/after-update.bat');

        $this->assertSame(2, preg_match_all('/\binstall --no-interaction --no-scripts --prefer-dist --no-progress$/m', $after));
        // Leaving out the libraries for development would give the mosque a set
        // of libraries that no test has ever run on.
        $this->assertStringNotContainsString('--no-dev', $after);
        // Nothing is installed when Composer says the libraries are already the
        // ones the version needs: the question is asked first, and only its
        // exact answer skips the installation.
        $this->assertLessThan(
            strpos($after, ' install --no-interaction --no-scripts --prefer-dist'),
            strpos($after, 'install --dry-run --no-interaction --no-scripts 2>&1 | findstr /c:"Nothing to install, update or remove" >nul'),
        );
        // update.bat goes back to the version before when they cannot be installed.
        $this->assertStringContainsString('exit /b 2', $after);
        $this->assertStringContainsString('if errorlevel 2 (', self::commands('update.bat'));
    }

    public function test_the_update_runs_what_composer_would_run_after_an_installation(): void
    {
        $after = self::commands('deploy/after-update.bat');
        $composer = json_decode(self::script('composer.json'), true, flags: JSON_THROW_ON_ERROR);

        // Composer's scripts are left out (--no-scripts) so that the PHP that runs
        // them is told where its settings are: each of them is run by the update.
        $commands = array_filter(
            $composer['scripts']['post-autoload-dump'],
            fn (string $script): bool => str_starts_with($script, '@php artisan '),
        );

        $this->assertNotSame([], $commands);

        foreach ($commands as $script) {
            $command = explode(' ', substr($script, strlen('@php artisan ')))[0];

            $this->assertSame(1, preg_match('/^call "%PHP%" %CLI_INI% artisan '.preg_quote($command, '/').' >nul$/m', $after), "deploy/after-update.bat does not run \"artisan {$command}\".");
        }

        // The one script that is not a command forgets what the framework cached
        // about the libraries; the update does the same.
        $this->assertStringContainsString('del /q "bootstrap\\cache\\*.php"', $after);

        // And the database is prepared the way a start prepares it.
        $this->assertSame(1, preg_match('/^call "%PHP%" %CLI_INI% .*artisan rawda:prepare$/m', $after));

        $this->assertSame(
            substr_count($after, 'call "%PHP%" '),
            substr_count($after, 'call "%PHP%" %CLI_INI% ') + substr_count($after, 'call "%PHP%" -v '),
            'Every start of PHP in deploy/after-update.bat names its settings file (the first probe, "-v", needs none).',
        );
    }

    public function test_the_package_is_a_clone_and_never_carries_the_settings_file(): void
    {
        $package = self::commands('deploy/make-package.bat');

        // The laptop updates itself with git: its folder starts as a clone...
        $this->assertStringContainsString('git clone --no-hardlinks --branch %BRANCH% "%SOURCE%" "%TARGET%"', $package);
        // ...that asks for updates where the project pushes to.
        $this->assertStringContainsString('git -C "%TARGET%" remote set-url origin "%ORIGIN%"', $package);
        // The settings file holds the password of the database: copied by hand only.
        $this->assertStringNotContainsString('.env', $package);
    }
}
