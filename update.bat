@echo off
rem ---------------------------------------------------------------------
rem  Brings the system on this computer up to date with the version on
rem  GitHub. On the mosque laptop: close the system, make sure the
rem  computer is online, then double-click this file.
rem
rem  It downloads the new version (git pull) and hands over to
rem  deploy\after-update.bat, which installs the libraries the new version
rem  needs and updates the database, after taking a backup of it.
rem
rem  It is careful in three ways:
rem    - it does nothing while the system is running;
rem    - it never overwrites a file of the system that was changed on this
rem      computer: it stops and names the file;
rem    - when the libraries of the new version cannot be installed, it goes
rem      back to the version this computer had, which still works.
rem ---------------------------------------------------------------------
setlocal
title Rawda - update
cd /d "%~dp0"

echo.

where git >nul 2>&1
if errorlevel 1 goto nogit

rem What Git answers stays on the screen: it says more than this file can.
git rev-parse --is-inside-work-tree >nul
if errorlevel 1 goto notaclone

rem The system and the demo must be closed: their files are about to be
rem replaced. These are the ports start.bat and demo.bat serve them at.
for %%P in (8000 8010) do (
    netstat -ano | findstr /r /c:"127\.0\.0\.1:%%P .*LISTENING" >nul 2>&1
    if not errorlevel 1 goto running
)

rem A file of the system that was changed on this computer would be in
rem the way of the new version. Nobody edits the system here, so this is
rem a surprise that a person must look at.
git update-index -q --refresh >nul 2>&1
set "CHANGED="
for /f "delims=" %%L in ('git status --porcelain -uno') do set "CHANGED=1"
if defined CHANGED goto changed

rem The version to go back to if the new one cannot be completed.
set "BEFORE="
for /f %%H in ('git rev-parse HEAD') do set "BEFORE=%%H"
if not defined BEFORE goto notaclone

echo  Downloading the new version...
echo.

rem From here on, this very file may be replaced by the new version. The
rem command interpreter reads a block in parentheses whole before it runs
rem it, so everything that follows the download is inside one block, and
rem nothing in it jumps to another place of the file.
(
    git pull --ff-only
    if errorlevel 1 (
        echo.
        echo  The new version could not be downloaded. Nothing was changed.
        echo  Check that this computer is online and start update.bat again.
        echo  The lines above say what Git answered.
        echo.
        pause
        exit /b 1
    )

    call "%~dp0deploy\after-update.bat" called
    if errorlevel 2 (
        for /f %%H in ('git rev-parse HEAD') do if not "%%H"=="%BEFORE%" (
            echo.
            echo  Going back to the version this computer had...
            echo.
            git reset --hard %BEFORE%
            call "%~dp0deploy\after-update.bat" called
        )
        echo.
        echo  The system was NOT updated: the libraries of the new version
        echo  could not be installed. It is on the version it had before.
        echo  Check that this computer is online and start update.bat again.
        echo.
        pause
        exit /b 1
    )
    if errorlevel 1 (
        echo.
        echo  The update is not complete. Read the lines above, then see
        echo  docs\deployment.md. Start update.bat again when it is solved.
        echo.
        pause
        exit /b 1
    )

    echo.
    echo  Start the system with start.bat.
    echo.
    pause
    exit /b 0
)

:nogit
echo  Git was not found on this computer. Install "Git for Windows" from
echo  https://git-scm.com and start update.bat again.
goto stop

:notaclone
echo.
echo  Git cannot work in this folder: the line above says why. To update
echo  itself, the folder of the system must be a package built by
echo  deploy\make-package.bat, or a clone of the project. See
echo  docs\deployment.md.
goto stop

:running
echo  The system is running. Close its black window first, and the window
echo  of the demo if it is open, then start update.bat again.
goto stop

:changed
echo  These files of the system were changed on this computer:
echo.
git status --short -uno
echo.
echo  The update does not overwrite them. Tell the developer. To drop the
echo  changes and take the files of the project again:  git restore .
goto stop

:stop
echo.
pause
exit /b 1
