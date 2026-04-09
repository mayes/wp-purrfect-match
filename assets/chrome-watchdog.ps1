#Requires -Version 5.1
<#
.SYNOPSIS
    Chrome kiosk mode watchdog.
.DESCRIPTION
    Monitors Chrome and restarts it in kiosk mode if it is closed or crashes.
    Designed to run as a scheduled task under SYSTEM at kiosk user logon.
    Reads configuration from C:\KioskScripts\kiosk-config.json.
#>

$ErrorActionPreference = "Continue"

# Load configuration
$configPath = "C:\KioskScripts\kiosk-config.json"
if (-not (Test-Path -Path $configPath)) {
    Write-EventLog -LogName Application -Source "KioskWatchdog" -EventId 1001 -EntryType Error -Message "Config not found: $configPath"
    exit 1
}

$config = Get-Content -Path $configPath -Raw | ConvertFrom-Json

$chromePath = $config.chrome.installPath
$homepage = $config.chrome.kioskHomepage
$checkInterval = $config.watchdog.checkIntervalSeconds
$restartDelay = $config.watchdog.restartDelaySeconds
$logDir = $config.logging.logDirectory

# Ensure log directory exists
if (-not (Test-Path -Path $logDir)) {
    New-Item -Path $logDir -ItemType Directory -Force | Out-Null
}

$logFile = Join-Path -Path $logDir -ChildPath "chrome-watchdog.log"

function Write-WatchdogLog {
    param([string]$Message)
    $entry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $logFile -Value $entry -ErrorAction SilentlyContinue
}

function Clear-ChromeUserData {
    # Clear Chrome user data to ensure fresh session
    $kioskUser = $config.kioskAccount.username
    $chromeDataPath = "C:\Users\$kioskUser\AppData\Local\Google\Chrome\User Data\Default"

    if (Test-Path -Path $chromeDataPath) {
        $pathsToClear = @(
            "Cache", "Code Cache", "GPUCache",
            "Cookies", "Cookies-journal",
            "History", "History-journal",
            "Login Data", "Login Data-journal",
            "Web Data", "Web Data-journal",
            "Visited Links", "Top Sites", "Top Sites-journal",
            "Shortcuts", "Shortcuts-journal"
        )

        foreach ($item in $pathsToClear) {
            $fullPath = Join-Path -Path $chromeDataPath -ChildPath $item
            if (Test-Path -Path $fullPath) {
                Remove-Item -Path $fullPath -Recurse -Force -ErrorAction SilentlyContinue
            }
        }
    }
}

function Start-ChromeKiosk {
    $chromeArgs = @(
        "--kiosk",
        "--no-first-run",
        "--disable-translate",
        "--disable-infobars",
        "--disable-suggestions-service",
        "--disable-save-password-bubble",
        "--noerrdialogs",
        "--disable-session-crashed-bubble",
        "--disable-component-update",
        "--disable-background-networking",
        "--disable-sync",
        "--autoplay-policy=no-user-gesture-required",
        $homepage
    )

    Start-Process -FilePath $chromePath -ArgumentList $chromeArgs -ErrorAction SilentlyContinue
    Write-WatchdogLog "Chrome started in kiosk mode: $homepage"
}

# Register event source if it doesn't exist
try {
    if (-not [System.Diagnostics.EventLog]::SourceExists("KioskWatchdog")) {
        [System.Diagnostics.EventLog]::CreateEventSource("KioskWatchdog", "Application")
    }
}
catch {
    # May fail if not running as admin on first call
}

Write-WatchdogLog "Watchdog started. Monitoring Chrome every $checkInterval seconds."

# Initial delay to let the desktop fully load
Start-Sleep -Seconds 5

# Main watchdog loop
while ($true) {
    try {
        $chromeRunning = Get-Process -Name "chrome" -ErrorAction SilentlyContinue

        if (-not $chromeRunning) {
            Write-WatchdogLog "Chrome not running. Clearing data and restarting..."

            # Clear browsing data before restart
            if ($config.chrome.clearDataOnRestart) {
                Clear-ChromeUserData
            }

            Start-Sleep -Seconds $restartDelay
            Start-ChromeKiosk
        }
    }
    catch {
        Write-WatchdogLog "Watchdog error: $_"
    }

    Start-Sleep -Seconds $checkInterval
}
