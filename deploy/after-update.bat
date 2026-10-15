@echo off
rem ---------------------------------------------------------------------
rem  The second half of an update, and the way to finish a fresh clone:
rem  makes this folder match the code that is in it now.
rem
rem      1. installs the libraries that composer.lock names (vendor);
rem      2. builds again what is generated from them: the list of the
rem         packages, and the scripts and styles of the panel
rem         (public\css\filament, public\js\filament, ...);
rem      3. prepares the database the way start.bat does: a backup first,
rem         then the changes the new version brings.
rem
rem  update.bat calls it after "git pull". It can also be started by
rem  itself, as often as needed: a step that has nothing to do does
rem  nothing.
rem
rem  It ends with 0 when all is done, with 2 when the libraries could not
rem  be installed (update.bat then goes back to the version before), and
rem  with 1 for anything else.
rem ---------------------------------------------------------------------
setlocal

rem update.bat says "called": it keeps the window open itself.
set "CALLED=%~1"

rem The folder of the system: the parent of the folder this file is in.
for %%I in ("%~dp0..") do set "ROOT=%%~fI"
cd /d "%ROOT%"

rem PHP: the copy in the "php" folder of the system, otherwise the PHP
rem installed on the computer. The same choice as deploy\serve.bat, and
rem for the same reason that copy is told where its settings are.
set "PHP=%ROOT%\php\php.exe"
set "CLI_INI="
if exist "%PHP%" (
    xcopy /d /y /q "%ROOT%\deploy\php.ini" "%ROOT%\php\" >nul 2>&1
    set "CLI_INI=-c php\php.ini"
) else (
    set "PHP=php"
)

call "%PHP%" -v >nul 2>&1
if errorlevel 1 goto nophp

rem Composer: the copy next to that PHP, otherwise the Composer installed
rem on the computer.
set "COMPOSER_PHAR="
if exist "%ROOT%\php\composer.phar" set "COMPOSER_PHAR=php\composer.phar"
if not defined COMPOSER_PHAR (
    where composer >nul 2>&1
    if errorlevel 1 goto nocomposer
)

rem On a slow line Composer restarts downloads, and too many at once make
rem that worse: fewer at a time, more patience, and a failed run is tried
rem again. What an earlier try downloaded is kept by Composer.
set "COMPOSER_MAX_PARALLEL_HTTP=3"
set "COMPOSER_PROCESS_TIMEOUT=1800"

echo  Libraries...

rem Most updates change no library. Composer can say so without touching
rem a file, and then there is nothing to install. An installation reads
rem the whole "vendor" folder again, which takes minutes on a slow disk.
if not exist "vendor\autoload.php" goto install
if defined COMPOSER_PHAR (
    call "%PHP%" %CLI_INI% %COMPOSER_PHAR% install --dry-run --no-interaction --no-scripts 2>&1 | findstr /c:"Nothing to install, update or remove" >nul
) else (
    call composer install --dry-run --no-interaction --no-scripts 2>&1 | findstr /c:"Nothing to install, update or remove" >nul
)
if not errorlevel 1 (
    echo  They are the ones this version needs.
    goto generated
)

:install
echo  Installing the ones this version needs. This can take several
echo  minutes; the lines below show that it is working.
echo.
set /a ATTEMPT=0

:libraries
set /a ATTEMPT+=1
rem The libraries are installed exactly as composer.lock lists them, the
rem ones for development included: that is the set the tests ran with.
rem Composer's own scripts are left out here and done below, by a PHP
rem that knows where its settings are.
if defined COMPOSER_PHAR (
    call "%PHP%" %CLI_INI% %COMPOSER_PHAR% install --no-interaction --no-scripts --prefer-dist --no-progress
) else (
    call composer install --no-interaction --no-scripts --prefer-dist --no-progress
)
if not errorlevel 1 goto generated
if %ATTEMPT% GEQ 3 goto nolibraries
echo.
echo  Trying again...
ping -n 6 127.0.0.1 >nul
goto libraries

:generated
echo.
echo  Preparing...

rem What the framework remembers about the libraries it had before: it is
rem built again from the ones that are installed now.
if exist "bootstrap\cache\*.php" del /q "bootstrap\cache\*.php" >nul 2>&1

rem The scripts composer.json runs after an installation. Their long
rem lists are not shown; what goes wrong still is.
call "%PHP%" %CLI_INI% artisan package:discover >nul
if errorlevel 1 goto failed
call "%PHP%" %CLI_INI% artisan filament:upgrade >nul
if errorlevel 1 goto failed

rem The database: a backup, then the changes of the new version.
call "%PHP%" %CLI_INI% -d memory_limit=512M artisan rawda:prepare
if errorlevel 1 goto failed

echo.
echo  This folder is up to date.
git log -1 --date=short --format="  Version %%h of %%cd" 2>nul
echo.
if not defined CALLED pause
exit /b 0

:nophp
echo.
echo  PHP was not found. Put PHP 8.4 in the "php" folder of the system.
echo  See docs\deployment.md.
goto failed

:nocomposer
echo.
echo  Composer was not found. Put composer.phar in the "php" folder of the
echo  system, or install Composer. See docs\deployment.md.
goto nolibraries

:nolibraries
echo.
echo  The libraries could not be installed. The lines above say why; most
echo  often the computer is not online.
echo.
if not defined CALLED pause
exit /b 2

:failed
echo.
echo  This folder could not be brought up to date. Read the lines above,
echo  then see docs\deployment.md.
echo.
if not defined CALLED pause
exit /b 1
