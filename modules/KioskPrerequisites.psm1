#Requires -Version 5.1
<#
.SYNOPSIS
    Prerequisite validation for CJ Paws and Whiskers Kiosk Setup.
.DESCRIPTION
    Validates that the system meets all requirements before kiosk setup proceeds.
#>

function Test-KioskPrerequisites {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Validating prerequisites..." -Level "INFO"
    $allPassed = $true

    # 1. Check running as Administrator
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]$identity
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        Write-KioskLog -Message "FAIL: Script must be run as Administrator" -Level "ERROR"
        $allPassed = $false
    }
    else {
        Write-KioskLog -Message "PASS: Running as Administrator" -Level "SUCCESS"
    }

    # 2. Check Windows edition (Pro, Enterprise, or Education)
    try {
        $edition = (Get-WindowsEdition -Online -ErrorAction Stop).Edition
        $supportedEditions = @("Professional", "Enterprise", "Education", "ServerStandard", "ServerDatacenter")
        if ($supportedEditions -contains $edition) {
            Write-KioskLog -Message "PASS: Windows edition is '$edition'" -Level "SUCCESS"
        }
        else {
            Write-KioskLog -Message "FAIL: Windows edition '$edition' is not supported. Requires Pro, Enterprise, or Education." -Level "ERROR"
            $allPassed = $false
        }
    }
    catch {
        Write-KioskLog -Message "WARN: Could not determine Windows edition: $_" -Level "WARN"
    }

    # 3. Check Windows 11 (build >= 22000)
    $osBuild = [System.Environment]::OSVersion.Version.Build
    if ($osBuild -ge 22000) {
        Write-KioskLog -Message "PASS: Windows 11 detected (build $osBuild)" -Level "SUCCESS"
    }
    else {
        Write-KioskLog -Message "WARN: Windows build is $osBuild. This script targets Windows 11 (build 22000+). Some features may not work correctly." -Level "WARN"
    }

    # 4. Check Google Chrome installation
    $chromePath = $Config.chrome.installPath
    if (Test-Path -Path $chromePath) {
        Write-KioskLog -Message "PASS: Google Chrome found at '$chromePath'" -Level "SUCCESS"
    }
    else {
        # Try registry fallback
        $regPath = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\chrome.exe"
        if (Test-Path -Path $regPath) {
            $chromePath = (Get-ItemProperty -Path $regPath -ErrorAction SilentlyContinue).'(Default)'
            if ($chromePath -and (Test-Path -Path $chromePath)) {
                Write-KioskLog -Message "PASS: Google Chrome found via registry at '$chromePath'" -Level "SUCCESS"
            }
            else {
                Write-KioskLog -Message "FAIL: Google Chrome not found. Install Chrome before running this script." -Level "ERROR"
                $allPassed = $false
            }
        }
        else {
            Write-KioskLog -Message "FAIL: Google Chrome not found at '$($Config.chrome.installPath)' or in registry." -Level "ERROR"
            $allPassed = $false
        }
    }

    # 5. Check disk space (minimum 500 MB free on system drive)
    $systemDrive = $env:SystemDrive
    $freeSpace = (Get-PSDrive -Name $systemDrive.TrimEnd(':') -ErrorAction SilentlyContinue).Free
    if ($freeSpace) {
        $freeSpaceMB = [math]::Round($freeSpace / 1MB, 2)
        if ($freeSpaceMB -ge 500) {
            Write-KioskLog -Message "PASS: Disk space is sufficient ($freeSpaceMB MB free)" -Level "SUCCESS"
        }
        else {
            Write-KioskLog -Message "WARN: Low disk space ($freeSpaceMB MB free). Recommend at least 500 MB." -Level "WARN"
        }
    }

    # 6. Check network connectivity to whitelisted sites
    foreach ($url in $Config.chrome.whitelistedUrls) {
        # Extract domain from URL pattern (skip wildcard entries)
        if ($url -match '^\*') { continue }
        $uri = $null
        try { $uri = [System.Uri]$url } catch { continue }
        if (-not $uri) { continue }

        try {
            $result = Test-NetConnection -ComputerName $uri.Host -Port 443 -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
            if ($result.TcpTestSucceeded) {
                Write-KioskLog -Message "PASS: Network connectivity to $($uri.Host):443" -Level "SUCCESS"
            }
            else {
                Write-KioskLog -Message "WARN: Cannot reach $($uri.Host):443. Verify network connectivity." -Level "WARN"
            }
        }
        catch {
            Write-KioskLog -Message "WARN: Network test failed for $($uri.Host): $_" -Level "WARN"
        }
    }

    if ($allPassed) {
        Write-KioskLog -Message "All critical prerequisites passed" -Level "SUCCESS"
    }
    else {
        Write-KioskLog -Message "One or more critical prerequisites failed. Review errors above." -Level "ERROR"
    }

    return $allPassed
}

function New-KioskRestorePoint {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [string]$BackupDirectory = ".\backup"
    )

    Write-KioskLog -Message "Creating system restore point and backups..." -Level "INFO"

    if (-not (Test-Path -Path $BackupDirectory)) {
        New-Item -Path $BackupDirectory -ItemType Directory -Force | Out-Null
    }

    # Enable System Restore on C: drive
    if ($PSCmdlet.ShouldProcess("C:\", "Enable System Restore")) {
        try {
            Enable-ComputerRestore -Drive "C:\" -ErrorAction SilentlyContinue
            Write-KioskLog -Message "System Restore enabled on C:\" -Level "SUCCESS"
        }
        catch {
            Write-KioskLog -Message "Could not enable System Restore: $_" -Level "WARN"
        }
    }

    # Create restore point
    if ($PSCmdlet.ShouldProcess("System", "Create Restore Point")) {
        try {
            Checkpoint-Computer -Description "Pre-Kiosk-Setup-$(Get-Date -Format yyyyMMdd-HHmmss)" -RestorePointType MODIFY_SETTINGS -ErrorAction Stop
            Write-KioskLog -Message "System restore point created" -Level "SUCCESS"
        }
        catch {
            Write-KioskLog -Message "Could not create restore point: $_. Continuing with registry backups." -Level "WARN"
        }
    }

    # Export registry keys that will be modified
    $registryPaths = @(
        @{ Key = "HKLM\SOFTWARE\Policies\Google\Chrome"; File = "chrome-policies.reg" },
        @{ Key = "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies"; File = "windows-policies.reg" },
        @{ Key = "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon"; File = "winlogon.reg" },
        @{ Key = "HKLM\SYSTEM\CurrentControlSet\Services\USBSTOR"; File = "usbstor.reg" },
        @{ Key = "HKLM\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate"; File = "windows-update.reg" }
    )

    foreach ($regEntry in $registryPaths) {
        $exportPath = Join-Path -Path $BackupDirectory -ChildPath $regEntry.File
        if ($PSCmdlet.ShouldProcess($regEntry.Key, "Export registry key")) {
            try {
                $regKey = $regEntry.Key -replace "^HKLM\\", "HKLM:\"
                if (Test-Path -Path $regKey -ErrorAction SilentlyContinue) {
                    & reg export $regEntry.Key $exportPath /y 2>$null | Out-Null
                    Write-KioskLog -Message "Exported registry: $($regEntry.Key) -> $($regEntry.File)" -Level "DEBUG"
                }
                else {
                    Write-KioskLog -Message "Registry key does not exist yet (will be created): $($regEntry.Key)" -Level "DEBUG"
                }
            }
            catch {
                Write-KioskLog -Message "Could not export $($regEntry.Key): $_" -Level "WARN"
            }
        }
    }

    # Export firewall rules
    $firewallBackup = Join-Path -Path $BackupDirectory -ChildPath "firewall-backup.wfw"
    if ($PSCmdlet.ShouldProcess("Firewall", "Export rules")) {
        try {
            & netsh advfirewall export $firewallBackup | Out-Null
            Write-KioskLog -Message "Firewall rules exported to firewall-backup.wfw" -Level "SUCCESS"
        }
        catch {
            Write-KioskLog -Message "Could not export firewall rules: $_" -Level "WARN"
        }
    }

    Write-KioskLog -Message "Backup phase complete" -Level "INFO"
}

Export-ModuleMember -Function Test-KioskPrerequisites, New-KioskRestorePoint
