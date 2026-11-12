@echo off
setlocal
cd /d "%~dp0"

echo Starting the ShopKeeprr demo server on http://127.0.0.1:8080 ...
start "ShopKeeprr Demo Server" "..\.tools\php\php.exe" artisan serve --host=127.0.0.1 --port=8080

timeout /t 3 /nobreak >nul

echo.
echo Permanent public demo: https://demo.shopkeeprr.com
echo Local storefront:      http://127.0.0.1:8080/
echo Demo guide:            http://127.0.0.1:8080/demo
echo Admin sign-in:         http://127.0.0.1:8080/admin/login
echo.
echo The hosted demo is already available at the permanent URL above. This script starts only the local copy and no longer opens a temporary tunnel.
pause
