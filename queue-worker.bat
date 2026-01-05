@echo off
title Laravel Queue Worker - Imports
cd /d c:\xampp\htdocs\company_projects\laravel_projects\web_scraping_project

echo ========================================
echo   Laravel Queue Worker - Imports Queue
echo ========================================
echo.
echo Press Ctrl+C to stop the worker
echo.

:loop
php artisan queue:work --queue=imports --tries=3 --timeout=3600
echo Worker stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak > nul
goto loop
