#Requires -Version 5.1
<#
.SYNOPSIS
    Clears Chrome browsing data for the kiosk user.
.DESCRIPTION
    Standalone script to wipe all Chrome profile data for the kiosk user.
    Can be called manually or by other kiosk scripts.
    Ensures no payment data, cookies, or session data persists.
#>

param(
    [string]$ConfigPath = "C:\KioskScripts\kiosk-config.json"
)

$ErrorActionPreference = "Continue"

# Load configuration
if (-not (Test-Path -Path $ConfigPath)) {
    Write-Error "Configuration file not found: $ConfigPath"
    exit 1
}

$config = Get-Content -Path $ConfigPath -Raw | ConvertFrom-Json
$kioskUser = $config.kioskAccount.username

# Chrome user data paths
$chromeUserDataBase = "C:\Users\$kioskUser\AppData\Local\Google\Chrome\User Data"
$chromeDefaultProfile = Join-Path -Path $chromeUserDataBase -ChildPath "Default"

Write-Host "Clearing Chrome browsing data for user: $kioskUser" -ForegroundColor Yellow

# Kill Chrome first if running
$chromeProcs = Get-Process -Name "chrome" -ErrorAction SilentlyContinue
if ($chromeProcs) {
    Write-Host "Stopping Chrome processes..." -ForegroundColor Yellow
    $chromeProcs | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
}

if (Test-Path -Path $chromeDefaultProfile) {
    # Items to clear (covers all sensitive data)
    $itemsToClear = @(
        # Browsing data
        "Cache",
        "Code Cache",
        "GPUCache",
        "ShaderCache",

        # Cookies and site data
        "Cookies",
        "Cookies-journal",

        # History
        "History",
        "History-journal",
        "Visited Links",
        "Top Sites",
        "Top Sites-journal",

        # Passwords and autofill (CRITICAL for payment security)
        "Login Data",
        "Login Data-journal",
        "Web Data",
        "Web Data-journal",

        # Session data
        "Session Storage",
        "Sessions",
        "Local Storage",
        "IndexedDB",
        "Service Worker",
        "File System",

        # Shortcuts and preferences that might contain sensitive data
        "Shortcuts",
        "Shortcuts-journal",
        "Favicons",
        "Favicons-journal",

        # Media
        "Media History",
        "Media History-journal"
    )

    $cleared = 0
    foreach ($item in $itemsToClear) {
        $fullPath = Join-Path -Path $chromeDefaultProfile -ChildPath $item
        if (Test-Path -Path $fullPath) {
            try {
                Remove-Item -Path $fullPath -Recurse -Force -ErrorAction Stop
                $cleared++
            }
            catch {
                Write-Warning "Could not clear $item : $_"
            }
        }
    }

    Write-Host "Cleared $cleared Chrome data items" -ForegroundColor Green
}
else {
    Write-Host "Chrome profile directory not found: $chromeDefaultProfile" -ForegroundColor Yellow
}

# Also clear any crash reports
$crashReportsPath = "C:\Users\$kioskUser\AppData\Local\Google\CrashReports"
if (Test-Path -Path $crashReportsPath) {
    Remove-Item -Path $crashReportsPath -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host "Browsing data cleared successfully" -ForegroundColor Green
