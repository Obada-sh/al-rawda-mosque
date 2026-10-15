@echo off
setlocal
title Rawda - demo

rem ---------------------------------------------------------------------
rem  Starts the mosque system on INVENTED data, to try it or to train on
rem  it. The demo has a database (rawda_demo), backups and files of its
rem  own: nothing of the real system is read or changed.
rem  Its sign-in page shows the account to get in with.
rem  Keep this window open while the demo is in use; close it to stop.
rem ---------------------------------------------------------------------

rem Everything the demo touches is its own.
set "APP_ENV=production"
set "APP_DEBUG=false"
set "DB_DATABASE=rawda_demo"
set "RAWDA_BACKUPS_PATH=%~dp0storage\demo\backups"
set "RAWDA_FILES_PATH=%~dp0storage\demo\files"
set "SESSION_COOKIE=rawda_demo_session"
set "CACHE_PREFIX=rawda_demo_"

rem 8010 is the address of the demo (config\rawda.php names it too): a
rem system that holds another database refuses to answer there.
call "%~dp0deploy\serve.bat" 8010 demo
exit /b %errorlevel%
