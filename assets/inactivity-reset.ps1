#Requires -Version 5.1
<#
.SYNOPSIS
    Inactivity monitor for kiosk session reset.
.DESCRIPTION
    Monitors user input activity using the GetLastInputInfo Win32 API.
    Resets the kiosk session (kills Chrome, clears data, restarts Chrome)
    after a configurable period of inactivity.
    Runs as a scheduled task under SYSTEM.
#>

$ErrorActionPreference = "Continue"

# P/Invoke for GetLastInputInfo
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public struct LASTINPUTINFO {
    public uint cbSize;
    public uint dwTime;
}

public static class IdleDetector {
    [DllImport("user32.dll")]
    static extern bool GetLastInputInfo(ref LASTINPUTINFO plii);

    public static uint GetIdleTimeMs() {
        LASTINPUTINFO lii = new LASTINPUTINFO();
        lii.cbSize = (uint)Marshal.SizeOf(typeof(LASTINPUTINFO));
        if (GetLastInputInfo(ref lii)) {
            return (uint)Environment.TickCount - lii.dwTime;
        }
        return 0;
    }
}
"@ -ErrorAction SilentlyContinue

# Load configuration
$configPath = "C:\KioskScripts\kiosk-config.json"
if (-not (Test-Path -Path $configPath)) {
    exit 1
}

$config = Get-Content -Path $configPath -Raw | ConvertFrom-Json

$timeoutMs = $config.inactivity.timeoutMinutes * 60 * 1000
$homepage = $config.chrome.kioskHomepage
$chromePath = $config.chrome.installPath
$logDir = $config.logging.logDirectory
$checkInterval = 10  # Check every 10 seconds

# Ensure log directory exists
if (-not (Test-Path -Path $logDir)) {
    New-Item -Path $logDir -ItemType Directory -Force | Out-Null
}

$logFile = Join-Path -Path $logDir -ChildPath "inactivity-monitor.log"

function Write-InactivityLog {
    param([string]$Message)
    $entry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $logFile -Value $entry -ErrorAction SilentlyContinue
}

function Reset-KioskSession {
    Write-InactivityLog "Inactivity timeout reached ($($config.inactivity.timeoutMinutes) min). Resetting session..."

    # Kill all Chrome processes
    Get-Process -Name "chrome" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2

    # Clear browsing data
    if ($config.inactivity.clearDataOnReset) {
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
                "Shortcuts", "Shortcuts-journal",
                "Session Storage", "Local Storage",
                "IndexedDB", "Service Worker"
            )

            foreach ($item in $pathsToClear) {
                $fullPath = Join-Path -Path $chromeDataPath -ChildPath $item
                if (Test-Path -Path $fullPath) {
                    Remove-Item -Path $fullPath -Recurse -Force -ErrorAction SilentlyContinue
                }
            }
            Write-InactivityLog "Chrome browsing data cleared"
        }
    }

    # Chrome watchdog will restart Chrome automatically
    Write-InactivityLog "Session reset complete. Chrome watchdog will restart the browser."
}

Write-InactivityLog "Inactivity monitor started. Timeout: $($config.inactivity.timeoutMinutes) minutes"

# Initial delay
Start-Sleep -Seconds 10

# Main monitoring loop
while ($true) {
    try {
        $idleTimeMs = [IdleDetector]::GetIdleTimeMs()

        if ($idleTimeMs -ge $timeoutMs) {
            Reset-KioskSession

            # Wait for the timeout period again before checking
            # (prevents rapid resets)
            Start-Sleep -Seconds ($config.inactivity.timeoutMinutes * 60)
        }
    }
    catch {
        Write-InactivityLog "Monitor error: $_"
    }

    Start-Sleep -Seconds $checkInterval
}
