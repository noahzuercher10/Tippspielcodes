@echo off
REM Einmaliges Erstbefuellen: holt den GANZEN Spielplan bis 10.08.2026.
REM Doppelklick zum Starten - dauert je nach Internet 1-3 Minuten.
set PHP_BIN=C:\xampp\php\php.exe
set SCRIPT=C:\xampp\htdocs\Tippspiel\cron\sync_all.php

echo Starte vollen Sync (Force-Mode)...
"%PHP_BIN%" "%SCRIPT%" --force
echo.
echo Fertig. Beliebige Taste zum Schliessen.
pause >nul
