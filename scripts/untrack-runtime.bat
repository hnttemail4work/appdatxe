@echo off
REM Go bo file da track nham truoc khi push (chay 1 lan)
cd /d "%~dp0"

echo Dang go bo cache/storage khoi git index...

git rm -r --cached server/storage/framework/sessions/ 2>nul
git rm -r --cached server/storage/framework/views/ 2>nul
git rm -r --cached server/storage/framework/cache/data/ 2>nul
git rm -r --cached server/storage/app/public/drivers/ 2>nul
git rm -r --cached server/storage/logs/ 2>nul
git rm -r --cached server/bootstrap/cache/packages.php 2>nul
git rm -r --cached server/bootstrap/cache/services.php 2>nul
git rm -r --cached server/vendor/ 2>nul
git rm -r --cached server/.env 2>nul

echo.
echo Xong. Tiep theo:
echo   git add .gitignore server/.gitignore server/storage server/bootstrap/cache
echo   git commit -m "chore: ignore runtime files for deploy"
echo   git push
pause
