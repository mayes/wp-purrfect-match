#Requires -Version 5.1
#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Updates firewall rules with current IP addresses for whitelisted domains.
.DESCRIPTION
    Re-resolves all whitelisted domain names to their current IPs and updates
    the kiosk firewall rules. Designed to run as a daily scheduled task
    to handle IP address changes from CDNs and load balancers.
#>

$ErrorActionPreference = "Continue"

# Load configuration
$configPath = "C:\KioskScripts\kiosk-config.json"
if (-not (Test-Path -Path $configPath)) {
    exit 1
}

$config = Get-Content -Path $configPath -Raw | ConvertFrom-Json
$logDir = $config.logging.logDirectory

# Ensure log directory exists
if (-not (Test-Path -Path $logDir)) {
    New-Item -Path $logDir -ItemType Directory -Force | Out-Null
}

$logFile = Join-Path -Path $logDir -ChildPath "firewall-update.log"

function Write-FWLog {
    param([string]$Message)
    $entry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message"
    Add-Content -Path $logFile -Value $entry -ErrorAction SilentlyContinue
}

Write-FWLog "Starting firewall IP update..."

# Get kiosk user SID
$kioskUser = $config.kioskAccount.username
try {
    $userObj = Get-LocalUser -Name $kioskUser -ErrorAction Stop
    $kioskSID = $userObj.SID.Value
}
catch {
    Write-FWLog "ERROR: Could not find kiosk user '$kioskUser': $_"
    exit 1
}

$localUserSDDL = "D:(A;;CC;;;$kioskSID)"
$groupName = "Kiosk-Rules"

# Get current HTTPS firewall rules
$existingHTTPS = Get-NetFirewallRule -Group $groupName -ErrorAction SilentlyContinue |
    Where-Object { $_.DisplayName -like "Kiosk-Allow-HTTPS-*" }

$existingIPs = @{}
foreach ($rule in $existingHTTPS) {
    $addressFilter = $rule | Get-NetFirewallAddressFilter
    $existingIPs[$rule.DisplayName] = $addressFilter.RemoteAddress
}

Write-FWLog "Found $($existingHTTPS.Count) existing HTTPS rules"

# Resolve current IPs for all domains
$newRules = @{}
$removedCount = 0
$addedCount = 0

foreach ($domain in $config.firewall.allowedDomains) {
    try {
        $ips = [System.Net.Dns]::GetHostAddresses($domain) |
            Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork } |
            ForEach-Object { $_.IPAddressToString }

        foreach ($ip in $ips) {
            $ruleName = "Kiosk-Allow-HTTPS-$domain-$ip"
            $newRules[$ruleName] = @{ Domain = $domain; IP = $ip }
        }

        Write-FWLog "Resolved $domain -> $($ips -join ', ')"
    }
    catch {
        Write-FWLog "WARN: Could not resolve $domain : $_"
    }
}

# Remove rules for IPs that are no longer valid
foreach ($rule in $existingHTTPS) {
    if (-not $newRules.ContainsKey($rule.DisplayName)) {
        $rule | Remove-NetFirewallRule -ErrorAction SilentlyContinue
        $removedCount++
        Write-FWLog "Removed stale rule: $($rule.DisplayName)"
    }
}

# Add rules for new IPs
foreach ($entry in $newRules.GetEnumerator()) {
    $existing = $existingHTTPS | Where-Object { $_.DisplayName -eq $entry.Key }
    if (-not $existing) {
        New-NetFirewallRule `
            -DisplayName $entry.Key `
            -Direction Outbound `
            -Action Allow `
            -Protocol TCP `
            -RemotePort 443 `
            -RemoteAddress $entry.Value.IP `
            -Group $groupName `
            -LocalUser $localUserSDDL `
            -Description "Allow HTTPS to $($entry.Value.Domain) ($($entry.Value.IP))" | Out-Null

        $addedCount++
        Write-FWLog "Added new rule: $($entry.Key)"
    }
}

Write-FWLog "Firewall update complete: $addedCount rules added, $removedCount rules removed"
