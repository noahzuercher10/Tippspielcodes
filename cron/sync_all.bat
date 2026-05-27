@echo off
REM Aufruf vom Windows Task Scheduler fuer das Tippspiel.
REM Optional: %1 = --force (ignoriert Cache)
set PHP_BIN=C:\xampp\php\php.exe
set SCRIPT=C:\xampp\htdocs\Tippspiel\cron\sync_all.php
set LOG=C:\xampp\htdocs\Tippspiel\cron\sync.log

echo. >> "%LOG%"
echo ===== %date% %time% ===== >> "%LOG%"
"%PHP_BIN%" "%SCRIPT%" %1 >> "%LOG%" 2>&1
