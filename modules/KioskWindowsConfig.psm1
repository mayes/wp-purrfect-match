#Requires -Version 5.1
<#
.SYNOPSIS
    Windows system configuration for kiosk mode.
.DESCRIPTION
    Configures Windows Update active hours, disables notifications,
    sets power/display timeouts, and other OS-level kiosk settings.
#>

function Set-WindowsUpdatePolicy {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    if (-not $Config.windowsUpdate.restrictToActiveHours) {
        Write-KioskLog -Message "Windows Update active hours restriction disabled in config. Skipping." -Level "INFO"
        return
    }

    Write-KioskLog -Message "Configuring Windows Update policy..." -Level "INFO"

    # Set Active Hours
    $uxSettingsPath = "HKLM:\SOFTWARE\Microsoft\WindowsUpdate\UX\Settings"
    if (-not (Test-Path -Path $uxSettingsPath)) {
        New-Item -Path $uxSettingsPath -Force | Out-Null
    }

    if ($PSCmdlet.ShouldProcess("Windows Update", "Set active hours $($Config.windowsUpdate.activeHoursStart)-$($Config.windowsUpdate.activeHoursEnd)")) {
        Set-ItemProperty -Path $uxSettingsPath -Name "ActiveHoursStart" -Value $Config.windowsUpdate.activeHoursStart -Type DWord
        Set-ItemProperty -Path $uxSettingsPath -Name "ActiveHoursEnd" -Value $Config.windowsUpdate.activeHoursEnd -Type DWord
        Set-ItemProperty -Path $uxSettingsPath -Name "IsActiveHoursEnabled" -Value 1 -Type DWord
        Write-KioskLog -Message "Active hours set: $($Config.windowsUpdate.activeHoursStart):00 - $($Config.windowsUpdate.activeHoursEnd):00" -Level "SUCCESS"
    }

    # Prevent forced reboots during active hours
    if ($Config.windowsUpdate.preventRebootDuringActiveHours) {
        $auPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU"
        if (-not (Test-Path -Path $auPath)) {
            New-Item -Path $auPath -Force | Out-Null
        }

        # Ensure parent key exists
        $wuPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate"
        if (-not (Test-Path -Path $wuPath)) {
            New-Item -Path $wuPath -Force | Out-Null
        }

        if ($PSCmdlet.ShouldProcess("Windows Update AU", "Prevent reboots with logged-on users")) {
            Set-ItemProperty -Path $auPath -Name "NoAutoRebootWithLoggedOnUsers" -Value 1 -Type DWord
            Set-ItemProperty -Path $auPath -Name "AUOptions" -Value 3 -Type DWord  # Download, notify to install
            Write-KioskLog -Message "Windows Update: prevented auto-reboot during active hours" -Level "SUCCESS"
        }
    }

    Write-KioskLog -Message "NOTE: Security updates are NOT disabled. Only reboot timing is controlled." -Level "INFO"
}

function Disable-Notifications {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$UserHiveKey
    )

    Write-KioskLog -Message "Disabling notifications for kiosk user..." -Level "INFO"

    # Disable Notification Center
    $explorerPolicyPath = "Registry::$UserHiveKey\SOFTWARE\Policies\Microsoft\Windows\Explorer"
    if (-not (Test-Path -Path $explorerPolicyPath)) {
        New-Item -Path $explorerPolicyPath -Force | Out-Null
    }

    if ($PSCmdlet.ShouldProcess("NotificationCenter", "Disable")) {
        Set-ItemProperty -Path $explorerPolicyPath -Name "DisableNotificationCenter" -Value 1 -Type DWord
        Write-KioskLog -Message "Notification Center disabled" -Level "SUCCESS"
    }

    # Disable Toast notifications
    $pushNotifPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\PushNotifications"
    if (-not (Test-Path -Path $pushNotifPath)) {
        New-Item -Path $pushNotifPath -Force | Out-Null
    }

    if ($PSCmdlet.ShouldProcess("ToastNotifications", "Disable")) {
        Set-ItemProperty -Path $pushNotifPath -Name "ToastEnabled" -Value 0 -Type DWord
        Write-KioskLog -Message "Toast notifications disabled" -Level "SUCCESS"
    }

    # Disable notification sounds
    $currentUserNotifPath = "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\Notifications\Settings"
    if (-not (Test-Path -Path $currentUserNotifPath)) {
        New-Item -Path $currentUserNotifPath -Force | Out-Null
    }
    Set-ItemProperty -Path $currentUserNotifPath -Name "NOC_GLOBAL_SETTING_ALLOW_NOTIFICATION_SOUND" -Value 0 -Type DWord

    Write-KioskLog -Message "All notifications disabled for kiosk user" -Level "SUCCESS"
}

function Set-DisplayPowerSettings {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Configuring display and power settings..." -Level "INFO"

    if ($PSCmdlet.ShouldProcess("Power Plan", "Set screen timeout and sleep settings")) {
        # Set screen timeout
        $timeout = $Config.display.screenTimeoutMinutes
        & powercfg /change monitor-timeout-ac $timeout
        & powercfg /change monitor-timeout-dc $timeout
        Write-KioskLog -Message "Screen timeout set to $timeout minutes" -Level "SUCCESS"

        # Prevent sleep
        if ($Config.display.preventSleep) {
            & powercfg /change standby-timeout-ac 0
            & powercfg /change standby-timeout-dc 0
            & powercfg /change hibernate-timeout-ac 0
            & powercfg /change hibernate-timeout-dc 0
            Write-KioskLog -Message "Sleep and hibernate disabled" -Level "SUCCESS"
        }

        # Prevent screensaver
        if ($Config.display.preventScreensaver) {
            $screensaverPath = "HKCU:\Control Panel\Desktop"
            if (Test-Path -Path $screensaverPath) {
                Set-ItemProperty -Path $screensaverPath -Name "ScreenSaveActive" -Value "0" -Type String
                Set-ItemProperty -Path $screensaverPath -Name "ScreenSaverIsSecure" -Value "0" -Type String
            }
            Write-KioskLog -Message "Screensaver disabled" -Level "SUCCESS"
        }
    }
}

function Restore-WindowsUpdatePolicy {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Restoring Windows Update policy to defaults..." -Level "INFO"

    $auPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU"
    if (Test-Path -Path $auPath) {
        if ($PSCmdlet.ShouldProcess($auPath, "Remove policy key")) {
            Remove-Item -Path $auPath -Recurse -Force -ErrorAction SilentlyContinue
            Write-KioskLog -Message "Windows Update AU policy removed" -Level "SUCCESS"
        }
    }

    $wuPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate"
    if (Test-Path -Path $wuPath) {
        # Only remove if empty (may contain other policies)
        $children = Get-ChildItem -Path $wuPath -ErrorAction SilentlyContinue
        if (-not $children) {
            Remove-Item -Path $wuPath -Force -ErrorAction SilentlyContinue
        }
    }
}

function Restore-Notifications {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [string]$UserHiveKey
    )

    Write-KioskLog -Message "Restoring notification settings..." -Level "INFO"

    if ($UserHiveKey) {
        $paths = @(
            "Registry::$UserHiveKey\SOFTWARE\Policies\Microsoft\Windows\Explorer",
            "Registry::$UserHiveKey\SOFTWARE\Microsoft\Windows\CurrentVersion\PushNotifications"
        )

        foreach ($path in $paths) {
            if (Test-Path -Path $path) {
                Remove-Item -Path $path -Recurse -Force -ErrorAction SilentlyContinue
            }
        }
    }

    Write-KioskLog -Message "Notification settings restored" -Level "SUCCESS"
}

function Restore-DisplayPowerSettings {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Restoring display and power settings to defaults..." -Level "INFO"

    if ($PSCmdlet.ShouldProcess("Power Plan", "Restore default schemes")) {
        & powercfg /restoredefaultschemes
        Write-KioskLog -Message "Power plan restored to defaults" -Level "SUCCESS"
    }
}

Export-ModuleMember -Function Set-WindowsUpdatePolicy, Disable-Notifications, Set-DisplayPowerSettings, Restore-WindowsUpdatePolicy, Restore-Notifications, Restore-DisplayPowerSettings
