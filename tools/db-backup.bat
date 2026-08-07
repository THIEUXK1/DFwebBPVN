@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File C:\DFwebBPVN\tools\db-backup.ps1 >> C:\DFwebBPVN\tools\logs\db-backup.log 2>&1
