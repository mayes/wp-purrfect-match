#Requires -RunAsAdministrator
#Requires -Version 5.1
<#
.SYNOPSIS
    Post-setup verification for CJ Paws and Whiskers Kiosk.
.DESCRIPTION
    Audits the kiosk configuration by checking that all expected
    registry keys, policies, firewall rules, scheduled tasks, and
    user account settings are correctly applied.
    Run this after setup-kiosk.ps1 to verify the kiosk is properly configured.
.EXAMPLE
    .\tests\Test-KioskSetup.ps1
#>

param(
    [string]$ConfigPath = ".\kiosk-config.json"
)

$passed = 0
$failed = 0
$skipped = 0

function Test-Check {
    param(
        [string]$Name,
        [bool]$Condition,
        [string]$FailMessage = ""
    )

    if ($Condition) {
        Write-Host "[PASS] $Name" -ForegroundColor Green
        $script:passed++
    }
    else {
        Write-Host "[FAIL] $Name - $FailMessage" -ForegroundColor Red
        $script:failed++
    }
}

function Test-Skip {
    param([string]$Name, [string]$Reason)
    Write-Host "[SKIP] $Name - $Reason" -ForegroundColor Yellow
    $script:skipped++
}

# Load config
if (-not (Test-Path -Path $ConfigPath)) {
    if (Test-Path -Path "C:\KioskScripts\kiosk-config.json") {
        $ConfigPath = "C:\KioskScripts\kiosk-config.json"
    }
    else {
        Write-Error "Config file not found"
        exit 1
    }
}

$config = Get-Content -Path $ConfigPath -Raw | ConvertFrom-Json
$username = $config.kioskAccount.username

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  CJ Paws and Whiskers - Kiosk Setup Verification" -ForegroundColor Cyan
Write-Host "  $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

# ============================================================
# 1. USER ACCOUNT TESTS
# ============================================================

Write-Host "`n--- User Account ---" -ForegroundColor White

$user = Get-LocalUser -Name $username -ErrorAction SilentlyContinue
Test-Check -Name "Kiosk user '$username' exists" `
    -Condition ($null -ne $user) `
    -FailMessage "User account not found"

if ($user) {
    # Check NOT in Administrators group
    $adminMembers = (Get-LocalGroupMember -Group "Administrators" -ErrorAction SilentlyContinue).Name
    $isAdmin = $adminMembers | Where-Object { $_ -match [regex]::Escape($username) }
    Test-Check -Name "Kiosk user is NOT in Administrators group" `
        -Condition ($null -eq $isAdmin) `
        -FailMessage "SECURITY: Kiosk user should not be an administrator"

    # Check password policy
    Test-Check -Name "Password never expires" `
        -Condition ($user.PasswordExpires -eq $null) `
        -FailMessage "Password should never expire"

    Test-Check -Name "User cannot change password" `
        -Condition ($user.UserMayChangePassword -eq $false) `
        -FailMessage "Kiosk user should not be able to change password"
}

# Auto-login
$winlogon = Get-ItemProperty -Path "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon" -ErrorAction SilentlyContinue
Test-Check -Name "Auto-login is enabled" `
    -Condition ($winlogon.AutoAdminLogon -eq "1") `
    -FailMessage "AutoAdminLogon should be '1'"

Test-Check -Name "Auto-login username matches" `
    -Condition ($winlogon.DefaultUserName -eq $username) `
    -FailMessage "Expected '$username', got '$($winlogon.DefaultUserName)'"

# ============================================================
# 2. CHROME POLICY TESTS
# ============================================================

Write-Host "`n--- Chrome Enterprise Policies ---" -ForegroundColor White

$chromePolicyPath = "HKLM:\SOFTWARE\Policies\Google\Chrome"
$chromeExists = Test-Path -Path $chromePolicyPath
Test-Check -Name "Chrome policy key exists" `
    -Condition $chromeExists `
    -FailMessage "Registry key not found: $chromePolicyPath"

if ($chromeExists) {
    # URL Blocklist
    $blocklistPath = "$chromePolicyPath\URLBlocklist"
    if (Test-Path -Path $blocklistPath) {
        $blocklist1 = (Get-ItemProperty -Path $blocklistPath -Name "1" -ErrorAction SilentlyContinue)."1"
        Test-Check -Name "URL blocklist has wildcard '*'" `
            -Condition ($blocklist1 -eq "*") `
            -FailMessage "Expected '*', got '$blocklist1'"
    }
    else {
        Test-Check -Name "URL blocklist key exists" -Condition $false -FailMessage "URLBlocklist key not found"
    }

    # URL Allowlist
    $allowlistPath = "$chromePolicyPath\URLAllowlist"
    if (Test-Path -Path $allowlistPath) {
        $allowlistProps = (Get-Item -Path $allowlistPath).Property
        Test-Check -Name "URL allowlist has entries" `
            -Condition ($allowlistProps.Count -gt 0) `
            -FailMessage "No URLs in allowlist"

        # Check specific URLs
        $allowlistValues = $allowlistProps | ForEach-Object {
            (Get-ItemProperty -Path $allowlistPath -Name $_).$_
        }
        foreach ($expectedUrl in $config.chrome.whitelistedUrls) {
            $found = $allowlistValues -contains $expectedUrl
            Test-Check -Name "Allowlist contains '$expectedUrl'" `
                -Condition $found `
                -FailMessage "URL not found in allowlist"
        }
    }
    else {
        Test-Check -Name "URL allowlist key exists" -Condition $false -FailMessage "URLAllowlist key not found"
    }

    # Security policies
    $chromeProps = Get-ItemProperty -Path $chromePolicyPath -ErrorAction SilentlyContinue

    Test-Check -Name "Dev tools disabled (DeveloperToolsAvailability=2)" `
        -Condition ($chromeProps.DeveloperToolsAvailability -eq 2) `
        -FailMessage "Got: $($chromeProps.DeveloperToolsAvailability)"

    Test-Check -Name "Downloads blocked (DownloadRestrictions=3)" `
        -Condition ($chromeProps.DownloadRestrictions -eq 3) `
        -FailMessage "Got: $($chromeProps.DownloadRestrictions)"

    Test-Check -Name "Password manager disabled" `
        -Condition ($chromeProps.PasswordManagerEnabled -eq 0) `
        -FailMessage "Password manager should be disabled"

    Test-Check -Name "Credit card autofill disabled" `
        -Condition ($chromeProps.AutofillCreditCardEnabled -eq 0) `
        -FailMessage "SECURITY: Credit card autofill must be disabled for payment kiosks"

    Test-Check -Name "Address autofill disabled" `
        -Condition ($chromeProps.AutofillAddressEnabled -eq 0) `
        -FailMessage "Address autofill should be disabled"

    # Data clearing on exit
    $clearPath = "$chromePolicyPath\ClearBrowsingDataOnExitList"
    if (Test-Path -Path $clearPath) {
        $clearProps = (Get-Item -Path $clearPath).Property
        Test-Check -Name "Data clearing on exit configured" `
            -Condition ($clearProps.Count -ge 5) `
            -FailMessage "Expected at least 5 data types, got $($clearProps.Count)"
    }
    else {
        Test-Check -Name "Data clearing on exit key exists" -Condition $false -FailMessage "Key not found"
    }

    # Extension blocklist
    $extPath = "$chromePolicyPath\ExtensionInstallBlocklist"
    if (Test-Path -Path $extPath) {
        $ext1 = (Get-ItemProperty -Path $extPath -Name "1" -ErrorAction SilentlyContinue)."1"
        Test-Check -Name "All extensions blocked" `
            -Condition ($ext1 -eq "*") `
            -FailMessage "Expected '*', got '$ext1'"
    }
}

# ============================================================
# 3. SECURITY TESTS
# ============================================================

Write-Host "`n--- Security Hardening ---" -ForegroundColor White

# USB Storage
$usbStor = Get-ItemProperty -Path "HKLM:\SYSTEM\CurrentControlSet\Services\USBSTOR" -Name "Start" -ErrorAction SilentlyContinue
Test-Check -Name "USB mass storage disabled (USBSTOR Start=4)" `
    -Condition ($usbStor.Start -eq 4) `
    -FailMessage "Got Start=$($usbStor.Start)"

$removable = Get-ItemProperty -Path "HKLM:\SOFTWARE\Policies\Microsoft\Windows\RemovableStorageDevices" -Name "Deny_All" -ErrorAction SilentlyContinue
Test-Check -Name "Removable storage policy (Deny_All=1)" `
    -Condition ($removable.Deny_All -eq 1) `
    -FailMessage "Deny_All not set"

# Software Restriction Policies
$srpPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\Safer\CodeIdentifiers"
Test-Check -Name "Software Restriction Policies configured" `
    -Condition (Test-Path -Path $srpPath) `
    -FailMessage "SRP key not found"

# User-specific policies (check if user hive is accessible)
if ($user) {
    $sid = $user.SID.Value
    $hivePath = "C:\Users\$username\NTUSER.DAT"

    if (Test-Path -Path $hivePath) {
        $hiveLoaded = $false
        $hiveKey = "HKU\$sid"

        if (-not (Test-Path -Path "Registry::$hiveKey" -ErrorAction SilentlyContinue)) {
            & reg load $hiveKey $hivePath 2>$null | Out-Null
            $hiveLoaded = ($LASTEXITCODE -eq 0)
        }
        else {
            $hiveLoaded = $true
        }

        if ($hiveLoaded) {
            $sysPolicies = Get-ItemProperty -Path "Registry::$hiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System" -ErrorAction SilentlyContinue
            $expPolicies = Get-ItemProperty -Path "Registry::$hiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer" -ErrorAction SilentlyContinue

            Test-Check -Name "Task Manager disabled for kiosk user" `
                -Condition ($sysPolicies.DisableTaskMgr -eq 1) `
                -FailMessage "DisableTaskMgr not set"

            Test-Check -Name "Registry Editor disabled for kiosk user" `
                -Condition ($sysPolicies.DisableRegistryTools -eq 1) `
                -FailMessage "DisableRegistryTools not set"

            Test-Check -Name "Control Panel disabled for kiosk user" `
                -Condition ($expPolicies.NoControlPanel -eq 1) `
                -FailMessage "NoControlPanel not set"

            Test-Check -Name "Win keys disabled for kiosk user" `
                -Condition ($expPolicies.NoWinKeys -eq 1) `
                -FailMessage "NoWinKeys not set"

            Test-Check -Name "Desktop icons hidden" `
                -Condition ($expPolicies.NoDesktop -eq 1) `
                -FailMessage "NoDesktop not set"

            Test-Check -Name "Right-click context menu disabled" `
                -Condition ($expPolicies.NoViewContextMenu -eq 1) `
                -FailMessage "NoViewContextMenu not set"

            # Unload hive if we loaded it
            [gc]::Collect()
            Start-Sleep -Seconds 1
            & reg unload $hiveKey 2>$null | Out-Null
        }
        else {
            Test-Skip -Name "User-specific policies" -Reason "Could not load user registry hive (user may be logged in)"
        }
    }
    else {
        Test-Skip -Name "User-specific policies" -Reason "User profile not yet created (will apply on first login)"
    }
}

# ============================================================
# 4. FIREWALL TESTS
# ============================================================

Write-Host "`n--- Firewall Rules ---" -ForegroundColor White

$kioskRules = Get-NetFirewallRule -Group "Kiosk-Rules" -ErrorAction SilentlyContinue
Test-Check -Name "Kiosk firewall rules exist" `
    -Condition ($null -ne $kioskRules -and $kioskRules.Count -gt 0) `
    -FailMessage "No Kiosk-Rules firewall rules found"

if ($kioskRules) {
    $blockRule = $kioskRules | Where-Object { $_.DisplayName -eq "Kiosk-Block-All-Outbound" }
    Test-Check -Name "Default outbound block rule exists" `
        -Condition ($null -ne $blockRule) `
        -FailMessage "Kiosk-Block-All-Outbound rule not found"

    $dnsRule = $kioskRules | Where-Object { $_.DisplayName -like "*DNS*" }
    Test-Check -Name "DNS allow rules exist" `
        -Condition ($null -ne $dnsRule) `
        -FailMessage "No DNS rules found"

    $httpsRules = $kioskRules | Where-Object { $_.DisplayName -like "*HTTPS*" }
    Test-Check -Name "HTTPS allow rules exist for whitelisted domains" `
        -Condition ($null -ne $httpsRules -and $httpsRules.Count -gt 0) `
        -FailMessage "No HTTPS whitelist rules found"

    Write-Host "  Total firewall rules: $($kioskRules.Count)" -ForegroundColor Gray
}

# ============================================================
# 5. SCHEDULED TASK TESTS
# ============================================================

Write-Host "`n--- Scheduled Tasks ---" -ForegroundColor White

$expectedTasks = @(
    @{ Name = "Kiosk-ChromeWatchdog"; Description = "Chrome watchdog" },
    @{ Name = "Kiosk-InactivityMonitor"; Description = "Inactivity monitor" },
    @{ Name = "Kiosk-FirewallIPUpdate"; Description = "Firewall IP updater" },
    @{ Name = "Kiosk-LogRotation"; Description = "Log rotation" }
)

foreach ($taskDef in $expectedTasks) {
    $task = Get-ScheduledTask -TaskName $taskDef.Name -ErrorAction SilentlyContinue
    Test-Check -Name "$($taskDef.Description) task registered ($($taskDef.Name))" `
        -Condition ($null -ne $task) `
        -FailMessage "Scheduled task not found"
}

# ============================================================
# 6. WINDOWS CONFIG TESTS
# ============================================================

Write-Host "`n--- Windows Configuration ---" -ForegroundColor White

# Active hours
$uxSettings = Get-ItemProperty -Path "HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings" -ErrorAction SilentlyContinue
if ($uxSettings) {
    Test-Check -Name "Windows Update active hours configured" `
        -Condition ($uxSettings.IsActiveHoursEnabled -eq 1) `
        -FailMessage "Active hours not enabled"
}

# Auto-reboot prevention
$auSettings = Get-ItemProperty -Path "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" -ErrorAction SilentlyContinue
if ($auSettings) {
    Test-Check -Name "Auto-reboot prevented during active hours" `
        -Condition ($auSettings.NoAutoRebootWithLoggedOnUsers -eq 1) `
        -FailMessage "NoAutoRebootWithLoggedOnUsers not set"
}

# ============================================================
# 7. DEPLOYED SCRIPTS
# ============================================================

Write-Host "`n--- Deployed Scripts ---" -ForegroundColor White

$kioskScriptsDir = "C:\KioskScripts"
Test-Check -Name "KioskScripts directory exists" `
    -Condition (Test-Path -Path $kioskScriptsDir) `
    -FailMessage "Directory not found: $kioskScriptsDir"

$expectedScripts = @(
    "chrome-watchdog.ps1",
    "inactivity-reset.ps1",
    "clear-browsing-data.ps1",
    "update-firewall-ips.ps1",
    "kiosk-config.json"
)

foreach ($scriptName in $expectedScripts) {
    $scriptFullPath = Join-Path -Path $kioskScriptsDir -ChildPath $scriptName
    Test-Check -Name "Script deployed: $scriptName" `
        -Condition (Test-Path -Path $scriptFullPath) `
        -FailMessage "File not found"
}

# Check permissions on KioskScripts
if (Test-Path -Path $kioskScriptsDir) {
    $acl = Get-Acl -Path $kioskScriptsDir
    $inheritanceDisabled = $acl.AreAccessRulesProtected
    Test-Check -Name "KioskScripts inheritance disabled (restricted access)" `
        -Condition $inheritanceDisabled `
        -FailMessage "Inheritance should be disabled to prevent kiosk user access"
}

# ============================================================
# 8. CONNECTIVITY TESTS
# ============================================================

Write-Host "`n--- Connectivity (from admin context) ---" -ForegroundColor White

foreach ($domain in $config.firewall.allowedDomains) {
    try {
        $result = Test-NetConnection -ComputerName $domain -Port 443 -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
        Test-Check -Name "Can reach $domain`:443" `
            -Condition ($result.TcpTestSucceeded) `
            -FailMessage "Connection failed"
    }
    catch {
        Test-Skip -Name "Connectivity to $domain" -Reason "Test failed: $_"
    }
}

# ============================================================
# SUMMARY
# ============================================================

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Results: $passed passed, $failed failed, $skipped skipped" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

if ($failed -eq 0) {
    Write-Host "All checks passed! The kiosk is properly configured." -ForegroundColor Green
    Write-Host "Reboot the machine to activate kiosk mode." -ForegroundColor Green
}
else {
    Write-Host "$failed check(s) failed. Review the output above and re-run setup-kiosk.ps1 if needed." -ForegroundColor Red
}

exit $failed
