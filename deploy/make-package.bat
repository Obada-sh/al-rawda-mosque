@echo off
setlocal
title Rawda - build the package for the mosque laptop
cd /d "%~dp0.."

rem ---------------------------------------------------------------------
rem  Builds the folder the mosque laptop runs the system from: a clone of
rem  this project as it was last committed, and next to it what git does
rem  not carry.
rem
rem      deploy\make-package.bat            builds ..\rawda-package
rem      deploy\make-package.bat D:\rawda   builds D:\rawda
rem
rem  In the package:
rem    - the committed files of the project, with their history (.git):
rem      that is what lets update.bat bring the laptop up to date later;
rem    - vendor: the libraries, as they are installed here;
rem    - the scripts and styles of the panel that are generated from them;
rem    - php: the PHP of this computer, with Composer next to it.
rem  Not in the package: changes that are not committed, the backups, and
rem  the settings file, which holds the password of the database: copy
rem  that one by hand (see docs\deployment.md).
rem
rem  Building again into the same folder brings it up to date. Nothing of
rem  this project is changed.
rem ---------------------------------------------------------------------

set "SOURCE=%cd%"
set "TARGET=%~1"
if "%TARGET%"=="" set "TARGET=%SOURCE%\..\rawda-package"

echo.

where git >nul 2>&1
if errorlevel 1 goto nogit
if not exist "%SOURCE%\.git" goto nocommit
git rev-parse --verify HEAD >nul 2>&1
if errorlevel 1 goto nocommit

set "BRANCH="
for /f "delims=" %%B in ('git symbolic-ref --short HEAD 2^>nul') do set "BRANCH=%%B"
if not defined BRANCH goto nobranch

rem Where the laptop will ask for updates: where this project pushes to.
set "ORIGIN="
for /f "delims=" %%U in ('git remote get-url origin 2^>nul') do set "ORIGIN=%%U"

echo  Building the package in:
echo    %TARGET%
echo.

set "UNCOMMITTED="
for /f "delims=" %%L in ('git status --porcelain') do set "UNCOMMITTED=1"
if defined UNCOMMITTED (
    echo  NOTE: this project has changes that are not committed. They are not
    echo  in the package: it holds the project as it was last committed.
    echo.
)

rem 1) The project as it was last committed.
if exist "%TARGET%\.git" (
    git -C "%TARGET%" pull --ff-only "%SOURCE%" %BRANCH%
) else (
    git clone --no-hardlinks --branch %BRANCH% "%SOURCE%" "%TARGET%"
)
if errorlevel 1 goto noclone

if defined ORIGIN git -C "%TARGET%" remote set-url origin "%ORIGIN%"

rem 2) The libraries, as they are installed here. A library that has left
rem    the project since the last build stays behind in the copy, unused.
if not exist "%SOURCE%\vendor\autoload.php" goto novendor
robocopy "%SOURCE%\vendor" "%TARGET%\vendor" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP
if errorlevel 8 goto failed

rem 3) What is generated from the libraries and kept out of git: the
rem    scripts, styles and fonts of the panel.
for %%D in (css\filament js\filament fonts\filament) do (
    if exist "%SOURCE%\public\%%D" robocopy "%SOURCE%\public\%%D" "%TARGET%\public\%%D" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP >nul
)

if not exist "%TARGET%\backups" mkdir "%TARGET%\backups"

rem 4) PHP: a copy of the PHP this computer runs the system with. Its own
rem    settings file stays behind: start.bat gives it deploy\php.ini.
set "PHPDIR="
for /f "usebackq delims=" %%P in (`call php -r "echo dirname(PHP_BINARY);"`) do set "PHPDIR=%%P"
if not exist "%PHPDIR%\php.exe" (
    echo  PHP was not found on this computer: the "php" folder was not filled.
    echo  Put PHP 8.4 for Windows in it by hand. See docs\deployment.md.
    goto done
)

robocopy "%PHPDIR%" "%TARGET%\php" /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP /XF php.ini
if errorlevel 8 goto failed

rem    Composer next to it: update.bat installs with it the libraries a
rem    later version needs.
set "COMPOSER_PHAR="
for /f "usebackq delims=" %%C in (`where composer 2^>nul`) do if not defined COMPOSER_PHAR if exist "%%~dpCcomposer.phar" set "COMPOSER_PHAR=%%~dpCcomposer.phar"
if defined COMPOSER_PHAR (
    copy /y "%COMPOSER_PHAR%" "%TARGET%\php\composer.phar" >nul
) else (
    echo  Composer was not found on this computer: put composer.phar in the
    echo  "php" folder of the package by hand. See docs\deployment.md.
)

:done
echo.
echo  The package is ready. What is left to do by hand:
echo    1. Copy the settings file of this project into the package.
echo    2. Copy the package to the mosque laptop, for example to C:\rawda.
echo    3. On the laptop: install MySQL and Git, then double-click start.bat.
echo  Later, update.bat on the laptop brings it up to date from GitHub.
echo  docs\deployment.md has the details.
if not defined ORIGIN (
    echo.
    echo  NOTE: this project has no remote named "origin" yet, so the package
    echo  does not know where to ask for updates. Push the project to GitHub
    echo  and build the package again.
)
echo.
pause
exit /b 0

:nogit
echo  Git was not found on this computer. Install "Git for Windows" from
echo  https://git-scm.com and start this file again.
goto stop

:nocommit
echo  This project has no commit yet. The package is a clone of what is
echo  committed: commit the project first. See docs\deployment.md.
goto stop

:nobranch
echo  This project is not on a branch. Switch to the branch the laptop
echo  follows, for example:  git switch main
goto stop

:noclone
echo.
echo  The project could not be copied into the package: the lines above
echo  say why. A folder that is there already and was not built by this
echo  file must be removed or renamed first, or name another folder:
echo    deploy\make-package.bat D:\rawda
goto stop

:novendor
echo  The libraries of this project are not installed: run "composer install"
echo  first, then start this file again.
goto stop

:failed
echo.
echo  The package could not be built. Check that the target can be written
echo  and that it has room for about 400 MB.
goto stop

:stop
echo.
pause
exit /b 1
