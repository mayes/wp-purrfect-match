#Requires -RunAsAdministrator
#Requires -Version 5.1
<#
.SYNOPSIS
    CJ Paws and Whiskers - Windows 11 Kiosk Setup Script
.DESCRIPTION
    Configures a Windows 11 Pro/Enterprise machine as a locked-down kiosk
    for browsing CJ Paws and Whiskers business websites. Customers can
    book appointments and make payments securely.

    This script:
    - Creates a restricted kiosk user account with auto-login
    - Configures Chrome in kiosk mode with URL whitelisting
    - Applies full security hardening (USB, shortcuts, system tools)
    - Sets up firewall rules to restrict network access
    - Registers watchdog and inactivity monitor scheduled tasks
    - Configures Windows Update and notification policies

    Must be run as Administrator on Windows 11 Pro/Enterprise.
.PARAMETER ConfigPath
    Path to the kiosk-config.json configuration file. Defaults to .\kiosk-config.json
.PARAMETER Force
    Skip confirmation prompts and overwrite existing kiosk user.
.PARAMETER SkipRestorePoint
    Skip creating a system restore point (not recommended).
.PARAMETER DryRun
    Show what would be done without making changes (uses -WhatIf).
.EXAMPLE
    .\setup-kiosk.ps1
    Runs the full kiosk setup with default configuration.
.EXAMPLE
    .\setup-kiosk.ps1 -ConfigPath "C:\custom-config.json" -Force
    Runs setup with a custom config file, overwriting existing settings.
.EXAMPLE
    .\setup-kiosk.ps1 -DryRun
    Shows what changes would be made without executing them.
#>

[CmdletBinding(SupportsShouldProcess)]
param(
    [string]$ConfigPath = ".\kiosk-config.json",
    [switch]$Force,
    [switch]$SkipRestorePoint,
    [switch]$DryRun
)

# If DryRun is specified, enable WhatIf for all operations
if ($DryRun) {
    $WhatIfPreference = $true
}

$ErrorActionPreference = "Stop"
$script:SetupSuccess = $true

# ============================================================
# INITIALIZATION
# ============================================================

# Resolve the config file path
$ConfigPath = Resolve-Path -Path $ConfigPath -ErrorAction Stop

# Get the script's directory (where modules and assets live)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition

# Import modules
$modulesDir = Join-Path -Path $ScriptDir -ChildPath "modules"
$modules = @(
    "KioskLogger",
    "KioskPrerequisites",
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
    else {
        Write-Error "Required module not found: $modulePath"
        exit 1
    }
}

# Load configuration
try {
    $configRaw = Get-Content -Path $ConfigPath -Raw -ErrorAction Stop
    $config = $configRaw | ConvertFrom-Json -ErrorAction Stop

    # Convert PSCustomObject to hashtable for easier manipulation
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
}
catch {
    Write-Error "Failed to load configuration from '$ConfigPath': $_"
    exit 1
}

# Initialize logging
Initialize-KioskLog -LogDirectory $config.logging.logDirectory -Verbose $config.logging.verboseLogging

Write-KioskLogSection "CJ Paws and Whiskers - Kiosk Setup"
Write-KioskLog -Message "Configuration loaded from: $ConfigPath" -Level "INFO"
Write-KioskLog -Message "Script directory: $ScriptDir" -Level "DEBUG"

if ($DryRun) {
    Write-KioskLog -Message "*** DRY RUN MODE - No changes will be made ***" -Level "WARN"
}

# ============================================================
# STEP 1: PREREQUISITES
# ============================================================

Write-KioskLogSection "Step 1: Prerequisite Validation"

$prereqsPassed = Test-KioskPrerequisites -Config $config
if (-not $prereqsPassed -and -not $Force) {
    Write-KioskLog -Message "Prerequisites failed. Use -Force to override (not recommended)." -Level "ERROR"
    Stop-KioskLog
    exit 1
}

# ============================================================
# STEP 2: SYSTEM RESTORE POINT AND BACKUPS
# ============================================================

Write-KioskLogSection "Step 2: System Restore Point and Backups"

if (-not $SkipRestorePoint) {
    $backupDir = Join-Path -Path $ScriptDir -ChildPath "backup"
    New-KioskRestorePoint -BackupDirectory $backupDir
}
else {
    Write-KioskLog -Message "Restore point creation skipped (-SkipRestorePoint)" -Level "WARN"
}

# ============================================================
# STEP 3: KIOSK USER ACCOUNT
# ============================================================

Write-KioskLogSection "Step 3: Kiosk User Account"

try {
    $kioskPassword = New-KioskUser -Config $config -Force:$Force
    Set-KioskAutoLogin -Username $config.kioskAccount.username -Password $kioskPassword

    # Initialize user profile and get hive info
    $profileInfo = Initialize-KioskUserProfile -Username $config.kioskAccount.username
    $userHiveKey = $profileInfo.HiveKey
    $kioskSID = $profileInfo.SID
}
catch {
    Write-KioskLog -Message "Failed to configure kiosk user account: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 4: CHROME ENTERPRISE POLICIES
# ============================================================

Write-KioskLogSection "Step 4: Chrome Enterprise Policies"

try {
    Set-ChromeKioskPolicies -Config $config
}
catch {
    Write-KioskLog -Message "Failed to configure Chrome policies: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 5: SECURITY HARDENING
# ============================================================

Write-KioskLogSection "Step 5: Security Hardening"

try {
    # Disable USB mass storage
    if ($config.security.disableUsbStorage) {
        Disable-UsbMassStorage
    }

    # Block system shortcuts and applications (requires user hive)
    if ($userHiveKey) {
        if ($config.security.blockSystemShortcuts) {
            Block-SystemShortcuts -UserHiveKey $userHiveKey
        }

        Block-SystemApplications -UserHiveKey $userHiveKey -Config $config
        Set-ExplorerRestrictions -UserHiveKey $userHiveKey -Config $config
    }
    else {
        Write-KioskLog -Message "User hive not loaded. User-specific security policies will apply on first login." -Level "WARN"
    }
}
catch {
    Write-KioskLog -Message "Failed to apply security hardening: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 6: FIREWALL RULES
# ============================================================

Write-KioskLogSection "Step 6: Firewall Rules"

try {
    if ($kioskSID) {
        Set-KioskFirewallRules -Config $config -KioskUserSID $kioskSID
    }
    else {
        Write-KioskLog -Message "Kiosk user SID not available. Firewall rules skipped." -Level "WARN"
    }
}
catch {
    Write-KioskLog -Message "Failed to configure firewall rules: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 7: DEPLOY RUNTIME SCRIPTS
# ============================================================

Write-KioskLogSection "Step 7: Deploy Runtime Scripts"

try {
    $kioskScriptsDir = "C:\KioskScripts"
    if (-not (Test-Path -Path $kioskScriptsDir)) {
        New-Item -Path $kioskScriptsDir -ItemType Directory -Force | Out-Null
    }

    # Copy asset scripts
    $assetsDir = Join-Path -Path $ScriptDir -ChildPath "assets"
    $assetScripts = @(
        "chrome-watchdog.ps1",
        "inactivity-reset.ps1",
        "clear-browsing-data.ps1",
        "update-firewall-ips.ps1"
    )

    foreach ($script in $assetScripts) {
        $source = Join-Path -Path $assetsDir -ChildPath $script
        $destination = Join-Path -Path $kioskScriptsDir -ChildPath $script
        if (Test-Path -Path $source) {
            Copy-Item -Path $source -Destination $destination -Force
            Write-KioskLog -Message "Deployed: $script -> $kioskScriptsDir" -Level "DEBUG"
        }
    }

    # Copy configuration file for runtime scripts
    Copy-Item -Path $ConfigPath -Destination (Join-Path -Path $kioskScriptsDir -ChildPath "kiosk-config.json") -Force
    Write-KioskLog -Message "Runtime scripts deployed to $kioskScriptsDir" -Level "SUCCESS"

    # Restrict permissions on KioskScripts directory (SYSTEM and Administrators only)
    $acl = Get-Acl -Path $kioskScriptsDir
    $acl.SetAccessRuleProtection($true, $false)  # Disable inheritance

    # SYSTEM - Full Control
    $systemRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "NT AUTHORITY\SYSTEM", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.AddAccessRule($systemRule)

    # Administrators - Full Control
    $adminRule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "BUILTIN\Administrators", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.AddAccessRule($adminRule)

    Set-Acl -Path $kioskScriptsDir -AclObject $acl
    Write-KioskLog -Message "Restricted permissions on $kioskScriptsDir (SYSTEM and Administrators only)" -Level "SUCCESS"
}
catch {
    Write-KioskLog -Message "Failed to deploy runtime scripts: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 8: SCHEDULED TASKS
# ============================================================

Write-KioskLogSection "Step 8: Scheduled Tasks"

try {
    Register-ChromeWatchdog -Config $config -KioskUsername $config.kioskAccount.username
    Register-InactivityMonitor -Config $config -KioskUsername $config.kioskAccount.username
    Register-FirewallUpdateTask -Config $config
    Register-LogRotationTask -Config $config
}
catch {
    Write-KioskLog -Message "Failed to register scheduled tasks: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# STEP 9: WINDOWS CONFIGURATION
# ============================================================

Write-KioskLogSection "Step 9: Windows Configuration"

try {
    Set-WindowsUpdatePolicy -Config $config

    if ($userHiveKey) {
        Disable-Notifications -UserHiveKey $userHiveKey
    }

    Set-DisplayPowerSettings -Config $config
}
catch {
    Write-KioskLog -Message "Failed to configure Windows settings: $_" -Level "ERROR"
    $script:SetupSuccess = $false
}

# ============================================================
# CLEANUP AND SUMMARY
# ============================================================

Write-KioskLogSection "Setup Complete"

# Unload user registry hive
if ($userHiveKey) {
    Dismount-KioskUserProfile -HiveKey $userHiveKey
}

if ($script:SetupSuccess) {
    Write-KioskLog -Message "Kiosk setup completed SUCCESSFULLY" -Level "SUCCESS"
    Write-KioskLog -Message "" -Level "INFO"
    Write-KioskLog -Message "Summary:" -Level "INFO"
    Write-KioskLog -Message "  Kiosk User    : $($config.kioskAccount.username)" -Level "INFO"
    Write-KioskLog -Message "  Homepage      : $($config.chrome.kioskHomepage)" -Level "INFO"
    Write-KioskLog -Message "  Whitelisted   : $($config.chrome.whitelistedUrls -join ', ')" -Level "INFO"
    Write-KioskLog -Message "  Inactivity    : $($config.inactivity.timeoutMinutes) min timeout" -Level "INFO"
    Write-KioskLog -Message "  Active Hours  : $($config.windowsUpdate.activeHoursStart):00 - $($config.windowsUpdate.activeHoursEnd):00" -Level "INFO"
    Write-KioskLog -Message "" -Level "INFO"
    Write-KioskLog -Message "NEXT STEPS:" -Level "INFO"
    Write-KioskLog -Message "  1. Review the setup log at: $($config.logging.logDirectory)" -Level "INFO"
    Write-KioskLog -Message "  2. Run .\tests\Test-KioskSetup.ps1 to verify the configuration" -Level "INFO"
    Write-KioskLog -Message "  3. Reboot the machine to activate the kiosk" -Level "INFO"
    Write-KioskLog -Message "  4. To undo all changes, run: .\revert-kiosk.ps1" -Level "INFO"
}
else {
    Write-KioskLog -Message "Kiosk setup completed with ERRORS. Review the log for details." -Level "ERROR"
    Write-KioskLog -Message "You may want to run .\revert-kiosk.ps1 to undo partial changes." -Level "WARN"
}

Stop-KioskLog
