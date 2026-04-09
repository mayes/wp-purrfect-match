#Requires -Version 5.1
<#
.SYNOPSIS
    Security hardening for the kiosk environment.
.DESCRIPTION
    Disables USB mass storage, blocks system shortcuts, restricts
    access to system tools, and locks down the Explorer shell.
#>

function Disable-UsbMassStorage {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Disabling USB mass storage..." -Level "INFO"

    # Disable USBSTOR driver (Start=4 means disabled)
    $usbStorPath = "HKLM:\SYSTEM\CurrentControlSet\Services\USBSTOR"
    if (Test-Path -Path $usbStorPath) {
        $currentValue = (Get-ItemProperty -Path $usbStorPath -Name "Start" -ErrorAction SilentlyContinue).Start
        if ($currentValue -eq 4) {
            Write-KioskLog -Message "USB mass storage already disabled (USBSTOR Start=4)" -Level "INFO"
        }
        else {
            if ($PSCmdlet.ShouldProcess("USBSTOR", "Set Start=4 (disabled)")) {
                Set-ItemProperty -Path $usbStorPath -Name "Start" -Value 4 -Type DWord
                Write-KioskLog -Message "USBSTOR service disabled (was: $currentValue, now: 4)" -Level "SUCCESS"
            }
        }
    }

    # Also set RemovableStorageDevices policy to deny all
    $removablePath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\RemovableStorageDevices"
    if (-not (Test-Path -Path $removablePath)) {
        New-Item -Path $removablePath -Force | Out-Null
    }
    if ($PSCmdlet.ShouldProcess("RemovableStorageDevices", "Set Deny_All=1")) {
        Set-ItemProperty -Path $removablePath -Name "Deny_All" -Value 1 -Type DWord
        Write-KioskLog -Message "Removable storage devices policy: Deny_All=1" -Level "SUCCESS"
    }
}

function Enable-UsbMassStorage {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Re-enabling USB mass storage..." -Level "INFO"

    $usbStorPath = "HKLM:\SYSTEM\CurrentControlSet\Services\USBSTOR"
    if (Test-Path -Path $usbStorPath) {
        if ($PSCmdlet.ShouldProcess("USBSTOR", "Set Start=3 (enabled)")) {
            Set-ItemProperty -Path $usbStorPath -Name "Start" -Value 3 -Type DWord
            Write-KioskLog -Message "USBSTOR service re-enabled (Start=3)" -Level "SUCCESS"
        }
    }

    $removablePath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\RemovableStorageDevices"
    if (Test-Path -Path $removablePath) {
        if ($PSCmdlet.ShouldProcess($removablePath, "Remove registry key")) {
            Remove-Item -Path $removablePath -Recurse -Force
            Write-KioskLog -Message "Removable storage devices policy removed" -Level "SUCCESS"
        }
    }
}

function Block-SystemShortcuts {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$UserHiveKey
    )

    Write-KioskLog -Message "Blocking system shortcuts..." -Level "INFO"

    # --- Layer 1: Registry policies (works on Pro and Enterprise) ---

    # Disable Win key hotkeys
    $explorerPolicyPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
    if (-not (Test-Path -Path $explorerPolicyPath)) {
        New-Item -Path $explorerPolicyPath -Force | Out-Null
    }
    Set-ItemProperty -Path $explorerPolicyPath -Name "NoWinKeys" -Value 1 -Type DWord
    Write-KioskLog -Message "Win key hotkeys disabled (NoWinKeys=1)" -Level "SUCCESS"

    # --- Layer 2: Keyboard Filter (Enterprise only) ---
    try {
        $edition = (Get-WindowsEdition -Online -ErrorAction Stop).Edition
        if ($edition -eq "Enterprise" -or $edition -eq "Education") {
            Write-KioskLog -Message "Enterprise/Education edition detected. Enabling Keyboard Filter..." -Level "INFO"
            Enable-KeyboardFilter
        }
        else {
            Write-KioskLog -Message "Pro edition detected. Keyboard Filter not available; using registry policies only." -Level "INFO"
            Write-KioskLog -Message "NOTE: Some shortcuts (Alt+Tab, Alt+F4) cannot be fully blocked on Pro edition." -Level "WARN"
        }
    }
    catch {
        Write-KioskLog -Message "Could not determine edition for Keyboard Filter: $_" -Level "WARN"
    }
}

function Enable-KeyboardFilter {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    # Check if Keyboard Filter feature is available and enable it
    try {
        $feature = Get-WindowsOptionalFeature -Online -FeatureName "Client-KeyboardFilter" -ErrorAction Stop
        if ($feature.State -ne "Enabled") {
            if ($PSCmdlet.ShouldProcess("Client-KeyboardFilter", "Enable Windows feature")) {
                Enable-WindowsOptionalFeature -Online -FeatureName "Client-KeyboardFilter" -NoRestart -ErrorAction Stop | Out-Null
                Write-KioskLog -Message "Keyboard Filter feature enabled (reboot may be required)" -Level "SUCCESS"
            }
        }
        else {
            Write-KioskLog -Message "Keyboard Filter feature already enabled" -Level "INFO"
        }

        # Configure blocked key combinations via WMI
        $namespace = "root\standardcimv2\embedded"
        $blockedKeys = @(
            @{ Modifier = "Win"; Key = "" },
            @{ Modifier = "Ctrl+Alt"; Key = "Del" },
            @{ Modifier = "Alt"; Key = "Tab" },
            @{ Modifier = "Alt"; Key = "F4" },
            @{ Modifier = "Ctrl"; Key = "Esc" },
            @{ Modifier = "Alt"; Key = "Esc" },
            @{ Modifier = "Ctrl+Shift"; Key = "Esc" },
            @{ Modifier = "Win"; Key = "L" },
            @{ Modifier = "Win"; Key = "R" },
            @{ Modifier = "Win"; Key = "E" },
            @{ Modifier = "Win"; Key = "D" },
            @{ Modifier = "Win"; Key = "X" },
            @{ Modifier = "Win"; Key = "I" }
        )

        foreach ($combo in $blockedKeys) {
            try {
                $keyId = if ($combo.Key) { "$($combo.Modifier)+$($combo.Key)" } else { $combo.Modifier }
                $filter = Get-CimInstance -Namespace $namespace -ClassName WEKF_PredefinedKey -ErrorAction SilentlyContinue |
                    Where-Object { $_.Id -eq $keyId }
                if ($filter -and -not $filter.Enabled) {
                    $filter | Set-CimInstance -Property @{ Enabled = $true } -ErrorAction SilentlyContinue
                    Write-KioskLog -Message "Keyboard Filter: blocked $keyId" -Level "DEBUG"
                }
            }
            catch {
                Write-KioskLog -Message "Could not configure Keyboard Filter for $keyId: $_" -Level "DEBUG"
            }
        }

        Write-KioskLog -Message "Keyboard Filter key combinations configured" -Level "SUCCESS"
    }
    catch {
        Write-KioskLog -Message "Keyboard Filter configuration failed: $_" -Level "WARN"
    }
}

function Block-SystemApplications {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$UserHiveKey,

        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Blocking system applications for kiosk user..." -Level "INFO"

    # Disable Command Prompt
    if ($Config.security.blockCmd) {
        $systemPath = "Registry::$UserHiveKey\SOFTWARE\Policies\Microsoft\Windows\System"
        if (-not (Test-Path -Path $systemPath)) {
            New-Item -Path $systemPath -Force | Out-Null
        }
        Set-ItemProperty -Path $systemPath -Name "DisableCMD" -Value 1 -Type DWord
        Write-KioskLog -Message "Command Prompt disabled (DisableCMD=1)" -Level "SUCCESS"
    }

    # Disable Registry Editor
    if ($Config.security.blockRegedit) {
        $systemPoliciesPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
        if (-not (Test-Path -Path $systemPoliciesPath)) {
            New-Item -Path $systemPoliciesPath -Force | Out-Null
        }
        Set-ItemProperty -Path $systemPoliciesPath -Name "DisableRegistryTools" -Value 1 -Type DWord
        Write-KioskLog -Message "Registry Editor disabled (DisableRegistryTools=1)" -Level "SUCCESS"
    }

    # Disable Task Manager
    if ($Config.security.blockTaskManager) {
        $systemPoliciesPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
        if (-not (Test-Path -Path $systemPoliciesPath)) {
            New-Item -Path $systemPoliciesPath -Force | Out-Null
        }
        Set-ItemProperty -Path $systemPoliciesPath -Name "DisableTaskMgr" -Value 1 -Type DWord
        Write-KioskLog -Message "Task Manager disabled (DisableTaskMgr=1)" -Level "SUCCESS"
    }

    # Disable Control Panel and Settings
    if ($Config.security.blockControlPanel) {
        $explorerPolicyPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
        if (-not (Test-Path -Path $explorerPolicyPath)) {
            New-Item -Path $explorerPolicyPath -Force | Out-Null
        }
        Set-ItemProperty -Path $explorerPolicyPath -Name "NoControlPanel" -Value 1 -Type DWord
        Write-KioskLog -Message "Control Panel and Settings disabled (NoControlPanel=1)" -Level "SUCCESS"
    }

    # Disable Run dialog
    if ($Config.security.blockRunDialog) {
        $explorerPolicyPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
        if (-not (Test-Path -Path $explorerPolicyPath)) {
            New-Item -Path $explorerPolicyPath -Force | Out-Null
        }
        Set-ItemProperty -Path $explorerPolicyPath -Name "NoRun" -Value 1 -Type DWord
        Write-KioskLog -Message "Run dialog disabled (NoRun=1)" -Level "SUCCESS"
    }

    # Block PowerShell and other system tools via Software Restriction Policies
    if ($Config.security.blockPowerShell) {
        Set-SoftwareRestrictionPolicies
    }
}

function Set-SoftwareRestrictionPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Configuring Software Restriction Policies..." -Level "INFO"

    $srp = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\Safer\CodeIdentifiers"
    if (-not (Test-Path -Path $srp)) {
        New-Item -Path $srp -Force | Out-Null
    }

    # Set default security level to Unrestricted (0x40000 = Unrestricted)
    Set-ItemProperty -Path $srp -Name "DefaultLevel" -Value 0x40000 -Type DWord
    Set-ItemProperty -Path $srp -Name "TransparentEnabled" -Value 1 -Type DWord
    Set-ItemProperty -Path $srp -Name "PolicyScope" -Value 1 -Type DWord  # 1 = All users except admins

    # Create path rules to block dangerous executables
    $blockedPaths = @(
        "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe",
        "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell_ise.exe",
        "%SystemRoot%\SysWOW64\WindowsPowerShell\v1.0\powershell.exe",
        "%SystemRoot%\SysWOW64\WindowsPowerShell\v1.0\powershell_ise.exe",
        "%ProgramFiles%\PowerShell\7\pwsh.exe",
        "%SystemRoot%\System32\mmc.exe",
        "%SystemRoot%\System32\msconfig.exe",
        "%SystemRoot%\System32\mshta.exe",
        "%SystemRoot%\System32\cscript.exe",
        "%SystemRoot%\System32\wscript.exe"
    )

    $pathRulesBase = "$srp\0\Paths"
    if (-not (Test-Path -Path $pathRulesBase)) {
        New-Item -Path $pathRulesBase -Force | Out-Null
    }

    foreach ($blockedPath in $blockedPaths) {
        # Generate a deterministic GUID from the path for the rule ID
        $md5 = [System.Security.Cryptography.MD5]::Create()
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($blockedPath)
        $hash = $md5.ComputeHash($bytes)
        $guid = [guid]::new($hash)
        $rulePath = "$pathRulesBase\{$guid}"

        if (-not (Test-Path -Path $rulePath)) {
            New-Item -Path $rulePath -Force | Out-Null
        }

        if ($PSCmdlet.ShouldProcess($blockedPath, "Create SRP block rule")) {
            Set-ItemProperty -Path $rulePath -Name "ItemData" -Value $blockedPath -Type ExpandString
            Set-ItemProperty -Path $rulePath -Name "SaferFlags" -Value 0 -Type DWord
            Write-KioskLog -Message "SRP blocked: $blockedPath" -Level "DEBUG"
        }
    }

    Write-KioskLog -Message "Software Restriction Policies configured ($($blockedPaths.Count) executables blocked)" -Level "SUCCESS"
}

function Set-ExplorerRestrictions {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$UserHiveKey,

        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Setting Explorer restrictions for kiosk user..." -Level "INFO"

    $explorerPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer"
    if (-not (Test-Path -Path $explorerPath)) {
        New-Item -Path $explorerPath -Force | Out-Null
    }

    $restrictions = @{}

    if ($Config.security.hideDesktopIcons) {
        $restrictions["NoDesktop"] = 1
    }
    if ($Config.security.disableRightClickDesktop) {
        $restrictions["NoViewContextMenu"] = 1
    }
    if ($Config.security.lockTaskbar) {
        $restrictions["TaskbarLockAll"] = 1
        $restrictions["NoSetTaskbar"] = 1
        $restrictions["NoTrayContextMenu"] = 1
        $restrictions["NoTrayItemsDisplay"] = 1
    }

    # Always apply these for kiosk
    $restrictions["NoLogoff"] = 0              # Allow logoff for maintenance
    $restrictions["NoClose"] = 0               # Allow shutdown for maintenance
    $restrictions["NoFind"] = 1                # Disable search
    $restrictions["NoRecentDocsMenu"] = 1      # No recent docs
    $restrictions["NoSMHelp"] = 1              # No Start Menu help
    $restrictions["NoNetConnectDisconnect"] = 1  # No network connection changes

    foreach ($entry in $restrictions.GetEnumerator()) {
        if ($PSCmdlet.ShouldProcess("Explorer\$($entry.Key)", "Set to $($entry.Value)")) {
            Set-ItemProperty -Path $explorerPath -Name $entry.Key -Value $entry.Value -Type DWord
        }
    }

    Write-KioskLog -Message "Explorer restrictions applied ($($restrictions.Count) policies set)" -Level "SUCCESS"
}

function Remove-SecurityHardening {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [string]$UserHiveKey
    )

    Write-KioskLog -Message "Removing security hardening..." -Level "INFO"

    # Re-enable USB storage
    Enable-UsbMassStorage

    # Remove Software Restriction Policies
    $srp = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\Safer"
    if (Test-Path -Path $srp) {
        if ($PSCmdlet.ShouldProcess($srp, "Remove SRP policies")) {
            Remove-Item -Path $srp -Recurse -Force
            Write-KioskLog -Message "Software Restriction Policies removed" -Level "SUCCESS"
        }
    }

    # Remove user-specific policies if hive is loaded
    if ($UserHiveKey) {
        $pathsToRemove = @(
            "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\Explorer",
            "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System",
            "Registry::$UserHiveKey\SOFTWARE\Policies\Microsoft\Windows\System"
        )

        foreach ($path in $pathsToRemove) {
            if (Test-Path -Path $path) {
                Remove-Item -Path $path -Recurse -Force -ErrorAction SilentlyContinue
                Write-KioskLog -Message "Removed: $path" -Level "DEBUG"
            }
        }
        Write-KioskLog -Message "User-specific policy keys removed" -Level "SUCCESS"
    }
}

Export-ModuleMember -Function Disable-UsbMassStorage, Enable-UsbMassStorage, Block-SystemShortcuts, Block-SystemApplications, Set-ExplorerRestrictions, Remove-SecurityHardening
