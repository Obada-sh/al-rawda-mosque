@echo off
setlocal
title Rawda - mosque system

rem ---------------------------------------------------------------------
rem  Starts the mosque system on this computer and opens it in the browser.
rem  Keep this window open while the system is in use; close it to stop.
rem ---------------------------------------------------------------------

rem This computer is where the system really runs: no debug pages.
set "APP_ENV=production"
set "APP_DEBUG=false"

call "%~dp0deploy\serve.bat" 8000
exit /b %errorlevel%
