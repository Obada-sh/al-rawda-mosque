@echo off
rem ---------------------------------------------------------------------
rem  The part start.bat and demo.bat share: checks PHP, prepares the
rem  system and serves it on this computer. It is not meant to be started
rem  by itself.
rem
rem      call deploy\serve.bat PORT [demo]
rem
rem  PORT    the port the system is served at, on 127.0.0.1 only: other
rem          computers cannot reach it
rem  demo    builds the invented data of the demo before serving
rem
rem  The caller decides what the system runs on by setting environment
rem  variables first (APP_ENV, DB_DATABASE, ...). They reach the system
rem  because PHP's own web server is started directly from here.
rem  "php artisan serve" must not be used instead: it hides such variables
rem  from the system it serves, which then silently falls back to .env.
rem ---------------------------------------------------------------------
setlocal

set "PORT=%~1"
set "WHAT=system"
if /i "%~2"=="demo" set "WHAT=demo"
if "%PORT%"=="" (
    echo.
    echo  Start the system with start.bat.
    echo.
    pause
    exit /b 1
)

rem The folder of the system: the parent of the folder this file is in.
for %%I in ("%~dp0..") do set "ROOT=%%~fI"
cd /d "%ROOT%"

set "URL=http://127.0.0.1:%PORT%"
set "APP_URL=%URL%"
set "LOG=%ROOT%\storage\logs\server-%PORT%.log"

rem PHP: the copy in the "php" folder of the system, otherwise the PHP
rem installed on the computer.
set "PHP=%ROOT%\php\php.exe"
set "CLI_INI="
set "WEB_INI="
if exist "%PHP%" (
    rem That copy takes its settings from deploy\php.ini; a newer one
    rem replaces the copy it has.
    xcopy /d /y /q "%ROOT%\deploy\php.ini" "%ROOT%\php\" >nul 2>&1
    rem It is told where they are, by a path that does not go through the
    rem folder of the system: left to look for them itself, PHP does not
    rem find them when that folder has letters that are not English.
    set "CLI_INI=-c php\php.ini"
    set "WEB_INI=-c ..\php\php.ini"
) else (
    set "PHP=php"
)

call "%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo.
    echo  PHP was not found. Put PHP 8.4 in the "php" folder of the system.
    echo  See README.
    echo.
    pause
    exit /b 1
)

call "%PHP%" %CLI_INI% deploy\check.php
if errorlevel 1 (
    echo.
    echo  This PHP cannot run the system. Read the lines above, then see README.
    echo.
    pause
    exit /b 1
)

rem What PHP may use. A backup file or a slide template is uploaded
rem through the browser, and restoring a backup takes a while: the web
rem server gets room for both, whatever the php.ini of the computer says.
set "CLI_LIMITS=-d memory_limit=512M"
set "WEB_LIMITS=-d memory_limit=512M -d max_execution_time=300 -d upload_max_filesize=512M -d post_max_size=520M"

rem Already running? Then only open the browser.
netstat -ano | findstr /r /c:"127\.0\.0\.1:%PORT% .*LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo  The %WHAT% is already running.
    start "" "%URL%"
    exit /b 0
)

echo.
echo  Preparing the %WHAT%...
call "%PHP%" %CLI_INI% %CLI_LIMITS% artisan config:clear >nul 2>&1
call "%PHP%" %CLI_INI% %CLI_LIMITS% artisan rawda:prepare
if errorlevel 1 goto failed
if "%WHAT%"=="demo" (
    call "%PHP%" %CLI_INI% %CLI_LIMITS% artisan rawda:demo
    if errorlevel 1 goto failed
)

echo.
echo  The %WHAT% is running at %URL%
echo  Keep this window open. Close it to stop the %WHAT%.
echo.

rem Open the browser a moment after the web server has started.
start "" /min cmd /c "timeout /t 3 /nobreak >nul & start "" "%URL%""

rem The web server that comes with PHP, started in the public folder with
rem the router of the framework: what "artisan serve" itself runs, without
rem the wrapper that drops the settings of the caller. What it reports
rem goes to storage\logs, so that the lines above stay on the screen.
cd /d "%ROOT%\public"
call "%PHP%" %WEB_INI% %WEB_LIMITS% -S 127.0.0.1:%PORT% ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php > "%LOG%" 2>&1

rem The web server only returns when it could not start or was stopped.
echo.
echo  The %WHAT% has stopped. What the web server reported is in
echo  storage\logs\server-%PORT%.log
if exist "%LOG%" for %%F in ("%LOG%") do if %%~zF LSS 3000 type "%LOG%"
echo.
pause
exit /b 0

:failed
echo.
echo  The %WHAT% could not start. Read the lines above, then see README.
echo.
pause
exit /b 1
