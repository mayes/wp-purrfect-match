#Requires -Version 5.1
<#
.SYNOPSIS
    Validates the kiosk configuration file.
.DESCRIPTION
    Checks kiosk-config.json for valid JSON syntax, required fields,
    URL format, and sane value ranges before running the setup script.
.EXAMPLE
    .\tests\Test-KioskConfig.ps1
    .\tests\Test-KioskConfig.ps1 -ConfigPath "C:\custom-config.json"
#>

param(
    [string]$ConfigPath = ".\kiosk-config.json"
)

$passed = 0
$failed = 0
$warnings = 0

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

function Test-Warning {
    param(
        [string]$Name,
        [string]$Message
    )
    Write-Host "[WARN] $Name - $Message" -ForegroundColor Yellow
    $script:warnings++
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  CJ Paws and Whiskers - Configuration Validation" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# Test 1: File exists
Test-Check -Name "Config file exists" -Condition (Test-Path -Path $ConfigPath) -FailMessage "File not found: $ConfigPath"

if (-not (Test-Path -Path $ConfigPath)) {
    Write-Host "`nCannot continue without config file." -ForegroundColor Red
    exit 1
}

# Test 2: Valid JSON
$config = $null
try {
    $configRaw = Get-Content -Path $ConfigPath -Raw
    $config = $configRaw | ConvertFrom-Json -ErrorAction Stop
    Test-Check -Name "Valid JSON syntax" -Condition $true
}
catch {
    Test-Check -Name "Valid JSON syntax" -Condition $false -FailMessage $_
    Write-Host "`nCannot continue with invalid JSON." -ForegroundColor Red
    exit 1
}

# Test 3: Required sections exist
$requiredSections = @("kioskAccount", "chrome", "security", "firewall", "watchdog", "inactivity", "display", "windowsUpdate", "logging")
foreach ($section in $requiredSections) {
    $exists = $null -ne $config.$section
    Test-Check -Name "Section '$section' exists" -Condition $exists -FailMessage "Missing required section"
}

# Test 4: Kiosk account settings
if ($config.kioskAccount) {
    Test-Check -Name "kioskAccount.username is set" `
        -Condition ($config.kioskAccount.username -and $config.kioskAccount.username.Length -gt 0) `
        -FailMessage "Username is empty"

    Test-Check -Name "kioskAccount.username has no spaces" `
        -Condition ($config.kioskAccount.username -notmatch '\s') `
        -FailMessage "Username should not contain spaces"

    $pwLen = $config.kioskAccount.passwordLength
    Test-Check -Name "kioskAccount.passwordLength is reasonable (16-128)" `
        -Condition ($pwLen -ge 16 -and $pwLen -le 128) `
        -FailMessage "Password length $pwLen is outside recommended range"
}

# Test 5: Chrome settings
if ($config.chrome) {
    Test-Check -Name "chrome.kioskHomepage is HTTPS" `
        -Condition ($config.chrome.kioskHomepage -match '^https://') `
        -FailMessage "Homepage should use HTTPS: $($config.chrome.kioskHomepage)"

    Test-Check -Name "chrome.whitelistedUrls has entries" `
        -Condition ($config.chrome.whitelistedUrls.Count -gt 0) `
        -FailMessage "No whitelisted URLs configured"

    # Check each whitelisted URL
    foreach ($url in $config.chrome.whitelistedUrls) {
        if ($url -notmatch '^\*' -and $url -notmatch '^https://') {
            Test-Warning -Name "URL '$url'" -Message "Non-wildcard URLs should use HTTPS"
        }
    }

    Test-Check -Name "chrome.blockedUrls includes wildcard '*'" `
        -Condition ($config.chrome.blockedUrls -contains "*") `
        -FailMessage "blockedUrls should contain '*' to block all non-whitelisted URLs"

    # Payment security checks
    Test-Check -Name "chrome.disableAutofillCreditCards is true" `
        -Condition ($config.chrome.disableAutofillCreditCards -eq $true) `
        -FailMessage "SECURITY: Credit card autofill should be disabled for payment kiosks"

    Test-Check -Name "chrome.disablePasswordManager is true" `
        -Condition ($config.chrome.disablePasswordManager -eq $true) `
        -FailMessage "SECURITY: Password manager should be disabled for shared kiosks"

    Test-Check -Name "chrome.disableDownloads is true" `
        -Condition ($config.chrome.disableDownloads -eq $true) `
        -FailMessage "SECURITY: Downloads should be disabled on kiosks"
}

# Test 6: Firewall settings
if ($config.firewall) {
    Test-Check -Name "firewall.allowedDomains has entries" `
        -Condition ($config.firewall.allowedDomains.Count -gt 0) `
        -FailMessage "No allowed domains configured for firewall"

    Test-Check -Name "firewall.blockAllOutboundByDefault is true" `
        -Condition ($config.firewall.blockAllOutboundByDefault -eq $true) `
        -FailMessage "SECURITY: Outbound traffic should be blocked by default"
}

# Test 7: Inactivity settings
if ($config.inactivity) {
    $timeout = $config.inactivity.timeoutMinutes
    Test-Check -Name "inactivity.timeoutMinutes is reasonable (1-30)" `
        -Condition ($timeout -ge 1 -and $timeout -le 30) `
        -FailMessage "Timeout of $timeout minutes seems unusual"

    Test-Check -Name "inactivity.clearDataOnReset is true" `
        -Condition ($config.inactivity.clearDataOnReset -eq $true) `
        -FailMessage "SECURITY: Data should be cleared on inactivity reset"
}

# Test 8: Windows Update
if ($config.windowsUpdate) {
    $start = $config.windowsUpdate.activeHoursStart
    $end = $config.windowsUpdate.activeHoursEnd
    Test-Check -Name "windowsUpdate active hours are valid (0-23)" `
        -Condition ($start -ge 0 -and $start -le 23 -and $end -ge 0 -and $end -le 23) `
        -FailMessage "Active hours must be between 0 and 23"

    Test-Check -Name "windowsUpdate active hours span is <= 18 hours" `
        -Condition (($end - $start) -le 18) `
        -FailMessage "Windows limits active hours to 18-hour span"
}

# Test 9: Logging
if ($config.logging) {
    Test-Check -Name "logging.logRetentionDays is reasonable (7-365)" `
        -Condition ($config.logging.logRetentionDays -ge 7 -and $config.logging.logRetentionDays -le 365) `
        -FailMessage "Retention of $($config.logging.logRetentionDays) days seems unusual"
}

# Summary
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Results: $passed passed, $failed failed, $warnings warnings" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

if ($failed -gt 0) {
    Write-Host "Fix the failed checks before running setup-kiosk.ps1" -ForegroundColor Red
    exit 1
}
elseif ($warnings -gt 0) {
    Write-Host "Configuration is valid with warnings. Review before proceeding." -ForegroundColor Yellow
    exit 0
}
else {
    Write-Host "Configuration is valid. Ready to run setup-kiosk.ps1" -ForegroundColor Green
    exit 0
}
