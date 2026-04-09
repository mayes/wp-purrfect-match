#Requires -Version 5.1
<#
.SYNOPSIS
    Windows Firewall rules for kiosk domain whitelisting.
.DESCRIPTION
    Creates per-user firewall rules that restrict the kiosk user to only
    communicate with whitelisted domains. This is a defense-in-depth layer;
    Chrome URL policies are the primary access control.

    NOTE: Windows Firewall operates on IP addresses, not domain names.
    IPs are resolved at setup time and updated daily via scheduled task.
#>

function Set-KioskFirewallRules {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$KioskUserSID
    )

    if (-not $Config.firewall.enableDomainWhitelist) {
        Write-KioskLog -Message "Firewall domain whitelisting is disabled in config. Skipping." -Level "INFO"
        return
    }

    Write-KioskLog -Message "Configuring firewall rules for kiosk user (SID: $KioskUserSID)..." -Level "INFO"

    $groupName = "Kiosk-Rules"

    # Build the SDDL string for the kiosk user
    $localUserSDDL = "D:(A;;CC;;;$KioskUserSID)"

    # Remove existing kiosk firewall rules (idempotent)
    $existingRules = Get-NetFirewallRule -Group $groupName -ErrorAction SilentlyContinue
    if ($existingRules) {
        Write-KioskLog -Message "Removing $($existingRules.Count) existing kiosk firewall rules..." -Level "INFO"
        $existingRules | Remove-NetFirewallRule
    }

    # 1. Block all outbound traffic for kiosk user by default
    if ($Config.firewall.blockAllOutboundByDefault) {
        if ($PSCmdlet.ShouldProcess("Kiosk-Block-All-Outbound", "Create firewall rule")) {
            New-NetFirewallRule `
                -DisplayName "Kiosk-Block-All-Outbound" `
                -Direction Outbound `
                -Action Block `
                -Profile Any `
                -Group $groupName `
                -LocalUser $localUserSDDL `
                -Description "Block all outbound traffic for kiosk user" | Out-Null
            Write-KioskLog -Message "Created default outbound block rule for kiosk user" -Level "SUCCESS"
        }
    }

    # 2. Allow DNS (UDP/TCP port 53)
    if ($Config.firewall.allowDns) {
        if ($PSCmdlet.ShouldProcess("Kiosk-Allow-DNS", "Create firewall rules")) {
            New-NetFirewallRule `
                -DisplayName "Kiosk-Allow-DNS-UDP" `
                -Direction Outbound `
                -Action Allow `
                -Protocol UDP `
                -RemotePort 53 `
                -Group $groupName `
                -LocalUser $localUserSDDL `
                -Description "Allow DNS resolution for kiosk user" | Out-Null

            New-NetFirewallRule `
                -DisplayName "Kiosk-Allow-DNS-TCP" `
                -Direction Outbound `
                -Action Allow `
                -Protocol TCP `
                -RemotePort 53 `
                -Group $groupName `
                -LocalUser $localUserSDDL `
                -Description "Allow DNS resolution (TCP) for kiosk user" | Out-Null

            Write-KioskLog -Message "Created DNS allow rules (UDP/TCP 53)" -Level "SUCCESS"
        }
    }

    # 3. Allow DHCP (UDP ports 67-68)
    if ($Config.firewall.allowDhcp) {
        if ($PSCmdlet.ShouldProcess("Kiosk-Allow-DHCP", "Create firewall rule")) {
            New-NetFirewallRule `
                -DisplayName "Kiosk-Allow-DHCP" `
                -Direction Outbound `
                -Action Allow `
                -Protocol UDP `
                -RemotePort @(67, 68) `
                -Group $groupName `
                -LocalUser $localUserSDDL `
                -Description "Allow DHCP for kiosk user" | Out-Null
            Write-KioskLog -Message "Created DHCP allow rule (UDP 67-68)" -Level "SUCCESS"
        }
    }

    # 4. Allow HTTP (port 80) for certificate OCSP/CRL validation
    if ($PSCmdlet.ShouldProcess("Kiosk-Allow-OCSP", "Create firewall rule")) {
        New-NetFirewallRule `
            -DisplayName "Kiosk-Allow-OCSP-CRL" `
            -Direction Outbound `
            -Action Allow `
            -Protocol TCP `
            -RemotePort 80 `
            -Group $groupName `
            -LocalUser $localUserSDDL `
            -Description "Allow HTTP for certificate validation (OCSP/CRL)" | Out-Null
        Write-KioskLog -Message "Created OCSP/CRL allow rule (TCP 80)" -Level "SUCCESS"
    }

    # 5. Allow HTTPS (port 443) to resolved IPs of whitelisted domains
    $allIPs = @()
    foreach ($domain in $Config.firewall.allowedDomains) {
        try {
            $ips = [System.Net.Dns]::GetHostAddresses($domain) |
                Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork } |
                ForEach-Object { $_.IPAddressToString }

            if ($ips) {
                foreach ($ip in $ips) {
                    $allIPs += $ip
                    if ($PSCmdlet.ShouldProcess("Kiosk-Allow-$domain-$ip", "Create firewall rule")) {
                        New-NetFirewallRule `
                            -DisplayName "Kiosk-Allow-HTTPS-$domain-$ip" `
                            -Direction Outbound `
                            -Action Allow `
                            -Protocol TCP `
                            -RemotePort 443 `
                            -RemoteAddress $ip `
                            -Group $groupName `
                            -LocalUser $localUserSDDL `
                            -Description "Allow HTTPS to $domain ($ip)" | Out-Null
                    }
                }
                Write-KioskLog -Message "Resolved $domain -> $($ips -join ', ')" -Level "DEBUG"
            }
            else {
                Write-KioskLog -Message "No IPv4 addresses resolved for $domain" -Level "WARN"
            }
        }
        catch {
            Write-KioskLog -Message "Could not resolve $domain : $_" -Level "WARN"
        }
    }

    $ruleCount = (Get-NetFirewallRule -Group $groupName -ErrorAction SilentlyContinue).Count
    Write-KioskLog -Message "Firewall configuration complete: $ruleCount rules created, $($allIPs.Count) IPs whitelisted" -Level "SUCCESS"
    Write-KioskLog -Message "NOTE: IP-based firewall rules are a defense-in-depth layer. Chrome URL policies are the primary access control." -Level "INFO"
    Write-KioskLog -Message "NOTE: Domain IPs may change (CDNs). The daily scheduled task will re-resolve and update rules." -Level "INFO"
}

function Remove-KioskFirewallRules {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Removing kiosk firewall rules..." -Level "INFO"

    $groupName = "Kiosk-Rules"
    $rules = Get-NetFirewallRule -Group $groupName -ErrorAction SilentlyContinue

    if ($rules) {
        if ($PSCmdlet.ShouldProcess("$($rules.Count) Kiosk-Rules", "Remove firewall rules")) {
            $rules | Remove-NetFirewallRule
            Write-KioskLog -Message "Removed $($rules.Count) kiosk firewall rules" -Level "SUCCESS"
        }
    }
    else {
        Write-KioskLog -Message "No kiosk firewall rules found to remove" -Level "INFO"
    }
}

Export-ModuleMember -Function Set-KioskFirewallRules, Remove-KioskFirewallRules
