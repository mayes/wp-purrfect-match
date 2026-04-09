#Requires -RunAsAdministrator
#Requires -Version 5.1
<#
.SYNOPSIS
    CJ Paws and Whiskers - Revert Kiosk Configuration
.DESCRIPTION
    Undoes all changes made by setup-kiosk.ps1, restoring the machine
    to its pre-kiosk state. Processes changes in reverse order.

    Must be run as Administrator.
.PARAMETER ConfigPath
    Path to kiosk-config.json. Defaults to .\kiosk-config.json
.PARAMETER KeepUser
    Keep the kiosk user account (do not delete it).
.PARAMETER KeepLogs
    Keep the log files in C:\KioskLogs.
.EXAMPLE
    .\revert-kiosk.ps1
    Fully reverts all kiosk changes.
.EXAMPLE
    .\revert-kiosk.ps1 -KeepUser -KeepLogs
    Reverts kiosk lockdown but preserves the user account and logs.
#>

[CmdletBinding(SupportsShouldProcess)]
param(
    [string]$ConfigPath = ".\kiosk-config.json",
    [switch]$KeepUser,
    [switch]$KeepLogs
)

$ErrorActionPreference = "Continue"

# Resolve paths
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition

# Try to load config from local path first, then from deployed path
$configFile = $null
if (Test-Path -Path $ConfigPath) {
    $configFile = $ConfigPath
}
elseif (Test-Path -Path "C:\KioskScripts\kiosk-config.json") {
    $configFile = "C:\KioskScripts\kiosk-config.json"
}

if (-not $configFile) {
    Write-Error "Configuration file not found. Provide the path with -ConfigPath."
    exit 1
}

# Import modules
$modulesDir = Join-Path -Path $ScriptDir -ChildPath "modules"
$modules = @(
    "KioskLogger",
    "KioskUserAccount",
    "KioskChrome",
    "KioskSecurity",
    "KioskFirewall",
    "KioskScheduledTasks",
    "KioskWindowsConfig"
)

foreach ($moduleName in $modules) {
    $modulePath = Join-Path -Path $modulesDir -ChildPath "$moduleName.psm1"
    if (Test-Path -Path $modulePath) {
        Import-Module $modulePath -Force -DisableNameChecking
    }
}

# Load configuration
$configRaw = Get-Content -Path $configFile -Raw
$config = $configRaw | ConvertFrom-Json

function ConvertTo-Hashtable {
    param([Parameter(ValueFromPipeline)]$InputObject)
    process {
        if ($InputObject -is [System.Collections.IEnumerable] -and $InputObject -isnot [string]) {
            $collection = @(
                foreach ($item in $InputObject) {
                    ConvertTo-Hashtable -InputObject $item
                }
            )
            Write-Output -NoEnumerate $collection
        }
        elseif ($InputObject -is [PSCustomObject]) {
            $hash = @{}
            foreach ($property in $InputObject.PSObject.Properties) {
                $hash[$property.Name] = ConvertTo-Hashtable -InputObject $property.Value
            }
            $hash
        }
        else {
            $InputObject
        }
    }
}

$config = ConvertTo-Hashtable -InputObject $config

# Initialize logging
Initialize-KioskLog -LogDirectory $config.logging.logDirectory -Verbose $true

Write-KioskLogSection "CJ Paws and Whiskers - Kiosk Revert"
Write-KioskLog -Message "Reverting kiosk configuration..." -Level "WARN"

# ============================================================
# STEP 1: STOP AND REMOVE SCHEDULED TASKS
# ============================================================

Write-KioskLogSection "Step 1: Remove Scheduled Tasks"

try {
    # Stop watchdog and inactivity monitor first so they don't interfere
    $taskNames = @("Kiosk-ChromeWatchdog", "Kiosk-InactivityMonitor")
    foreach ($taskName in $taskNames) {
        $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
        if ($task -and $task.State -eq "Running") {
            Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
            Write-KioskLog -Message "Stopped running task: $taskName" -Level "INFO"
        }
    }

    Remove-KioskScheduledTasks
}
catch {
    Write-KioskLog -Message "Error removing scheduled tasks: $_" -Level "WARN"
}

# ============================================================
# STEP 2: REMOVE FIREWALL RULES
# ============================================================

Write-KioskLogSection "Step 2: Remove Firewall Rules"

try {
    Remove-KioskFirewallRules

    # Optionally restore from backup
    $firewallBackup = Join-Path -Path $ScriptDir -ChildPath "backup\firewall-backup.wfw"
    if (Test-Path -Path $firewallBackup) {
        Write-KioskLog -Message "Firewall backup found at $firewallBackup. To restore: netsh advfirewall import `"$firewallBackup`"" -Level "INFO"
    }
}
catch {
    Write-KioskLog -Message "Error removing firewall rules: $_" -Level "WARN"
}

# ============================================================
# STEP 3: REMOVE CHROME ENTERPRISE POLICIES
# ============================================================

Write-KioskLogSection "Step 3: Remove Chrome Enterprise Policies"

try {
    Remove-ChromeKioskPolicies
}
catch {
    Write-KioskLog -Message "Error removing Chrome policies: $_" -Level "WARN"
}

# ============================================================
# STEP 4: REMOVE SECURITY HARDENING
# ============================================================

Write-KioskLogSection "Step 4: Remove Security Hardening"

try {
    $username = $config.kioskAccount.username
    $userHiveKey = $null

    # Try to load user's registry hive
    $userObj = Get-LocalUser -Name $username -ErrorAction SilentlyContinue
    if ($userObj) {
        $sid = $userObj.SID.Value
        $profilePath = "C:\Users\$username"
        $hivePath = Join-Path -Path $profilePath -ChildPath "NTUSER.DAT"

        if (Test-Path -Path $hivePath) {
            $tempKey = "HKU\$sid"
            $loaded = Test-Path -Path "Registry::$tempKey" -ErrorAction SilentlyContinue
            if (-not $loaded) {
                & reg load $tempKey $hivePath 2>$null | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    $userHiveKey = $tempKey
                }
            }
            else {
                $userHiveKey = $tempKey
            }
        }
    }

    Remove-SecurityHardening -UserHiveKey $userHiveKey

    # Unload hive
    if ($userHiveKey) {
        [gc]::Collect()
        Start-Sleep -Seconds 1
        & reg unload $userHiveKey 2>$null | Out-Null
    }
}
catch {
    Write-KioskLog -Message "Error removing security hardening: $_" -Level "WARN"
}

# ============================================================
# STEP 5: RESTORE WINDOWS CONFIGURATION
# ============================================================

Write-KioskLogSection "Step 5: Restore Windows Configuration"

try {
    Restore-WindowsUpdatePolicy
    Restore-DisplayPowerSettings
}
catch {
    Write-KioskLog -Message "Error restoring Windows configuration: $_" -Level "WARN"
}

# ============================================================
# STEP 6: REMOVE AUTO-LOGIN
# ============================================================

Write-KioskLogSection "Step 6: Remove Auto-Login"

try {
    $winlogonPath = "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon"

    $propsToRemove = @("AutoAdminLogon", "DefaultPassword", "ForceAutoLogon")
    foreach ($prop in $propsToRemove) {
        if (Get-ItemProperty -Path $winlogonPath -Name $prop -ErrorAction SilentlyContinue) {
            Remove-ItemProperty -Path $winlogonPath -Name $prop -ErrorAction SilentlyContinue
            Write-KioskLog -Message "Removed Winlogon property: $prop" -Level "DEBUG"
        }
    }

    # Restore lock screen
    $personalizationPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\Personalization"
    if (Test-Path -Path $personalizationPath) {
        Remove-ItemProperty -Path $personalizationPath -Name "NoLockScreen" -ErrorAction SilentlyContinue
    }

    # Restore Ctrl+Alt+Del
    $systemPath = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
    if (Test-Path -Path $systemPath) {
        Remove-ItemProperty -Path $systemPath -Name "DisableCAD" -ErrorAction SilentlyContinue
    }

    Write-KioskLog -Message "Auto-login configuration removed" -Level "SUCCESS"
}
catch {
    Write-KioskLog -Message "Error removing auto-login: $_" -Level "WARN"
}

# ============================================================
# STEP 7: REMOVE KIOSK USER ACCOUNT
# ============================================================

Write-KioskLogSection "Step 7: Remove Kiosk User Account"

if (-not $KeepUser) {
    try {
        $username = $config.kioskAccount.username
        $existingUser = Get-LocalUser -Name $username -ErrorAction SilentlyContinue

        if ($existingUser) {
            if ($PSCmdlet.ShouldProcess($username, "Remove local user and profile")) {
                # Remove user profile
                $profile = Get-CimInstance -ClassName Win32_UserProfile -ErrorAction SilentlyContinue |
                    Where-Object { $_.LocalPath -like "*$username*" }
                if ($profile) {
                    $profile | Remove-CimInstance -ErrorAction SilentlyContinue
                    Write-KioskLog -Message "Removed user profile for '$username'" -Level "SUCCESS"
                }

                # Remove user account
                Remove-LocalUser -Name $username
                Write-KioskLog -Message "Removed local user '$username'" -Level "SUCCESS"
            }
        }
        else {
            Write-KioskLog -Message "Kiosk user '$username' does not exist. Skipping." -Level "INFO"
        }
    }
    catch {
        Write-KioskLog -Message "Error removing kiosk user: $_" -Level "WARN"
    }
}
else {
    Write-KioskLog -Message "Keeping kiosk user account (-KeepUser specified)" -Level "INFO"
}

# ============================================================
# STEP 8: REMOVE DEPLOYED SCRIPTS
# ============================================================

Write-KioskLogSection "Step 8: Remove Deployed Scripts"

try {
    $kioskScriptsDir = "C:\KioskScripts"
    if (Test-Path -Path $kioskScriptsDir) {
        if ($PSCmdlet.ShouldProcess($kioskScriptsDir, "Remove directory")) {
            Remove-Item -Path $kioskScriptsDir -Recurse -Force
            Write-KioskLog -Message "Removed $kioskScriptsDir" -Level "SUCCESS"
        }
    }
    else {
        Write-KioskLog -Message "KioskScripts directory not found. Skipping." -Level "INFO"
    }
}
catch {
    Write-KioskLog -Message "Error removing deployed scripts: $_" -Level "WARN"
}

# ============================================================
# STEP 9: CLEAN UP LOGS
# ============================================================

Write-KioskLogSection "Step 9: Clean Up"

if (-not $KeepLogs) {
    Write-KioskLog -Message "Log files at $($config.logging.logDirectory) will be preserved for this session." -Level "INFO"
    Write-KioskLog -Message "To manually remove: Remove-Item -Path '$($config.logging.logDirectory)' -Recurse -Force" -Level "INFO"
}
else {
    Write-KioskLog -Message "Keeping log files (-KeepLogs specified)" -Level "INFO"
}

# ============================================================
# SUMMARY
# ============================================================

Write-KioskLogSection "Revert Complete"

Write-KioskLog -Message "Kiosk configuration has been reverted." -Level "SUCCESS"
Write-KioskLog -Message "" -Level "INFO"
Write-KioskLog -Message "What was restored:" -Level "INFO"
Write-KioskLog -Message "  - Scheduled tasks removed" -Level "INFO"
Write-KioskLog -Message "  - Firewall rules removed" -Level "INFO"
Write-KioskLog -Message "  - Chrome enterprise policies removed" -Level "INFO"
Write-KioskLog -Message "  - USB mass storage re-enabled" -Level "INFO"
Write-KioskLog -Message "  - System shortcuts unblocked" -Level "INFO"
Write-KioskLog -Message "  - Software Restriction Policies removed" -Level "INFO"
Write-KioskLog -Message "  - Windows Update policy restored" -Level "INFO"
Write-KioskLog -Message "  - Power settings restored to defaults" -Level "INFO"
Write-KioskLog -Message "  - Auto-login removed" -Level "INFO"

if (-not $KeepUser) {
    Write-KioskLog -Message "  - Kiosk user account removed" -Level "INFO"
}
if (-not $KeepLogs) {
    Write-KioskLog -Message "  - Deployed scripts removed" -Level "INFO"
}

Write-KioskLog -Message "" -Level "INFO"
Write-KioskLog -Message "A reboot is recommended to ensure all changes take effect." -Level "WARN"

Stop-KioskLog
