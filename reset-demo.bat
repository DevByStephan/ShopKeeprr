@echo off
setlocal
cd /d "%~dp0"

echo Restoring the ShopKeeprr demo database from storage\app\demo-snapshot.sqlite ...
"..\.tools\php\php.exe" artisan shopkeeprr:demo:reset

echo.
echo Demo reset complete. Existing sessions were signed out.
pause
