#Requires -Version 5.1
<#
.SYNOPSIS
    Scheduled task registration for kiosk watchdog and maintenance.
.DESCRIPTION
    Registers scheduled tasks for Chrome watchdog (auto-restart),
    inactivity monitor, firewall IP updates, and log rotation.
#>

function Register-ChromeWatchdog {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$KioskUsername
    )

    if (-not $Config.watchdog.enabled) {
        Write-KioskLog -Message "Chrome watchdog is disabled in config. Skipping." -Level "INFO"
        return
    }

    Write-KioskLog -Message "Registering Chrome watchdog scheduled task..." -Level "INFO"

    $taskName = "Kiosk-ChromeWatchdog"
    $scriptPath = "C:\KioskScripts\chrome-watchdog.ps1"

    # Remove existing task if present (idempotent)
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

    if ($PSCmdlet.ShouldProcess($taskName, "Register scheduled task")) {
        $action = New-ScheduledTaskAction `
            -Execute "powershell.exe" `
            -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File `"$scriptPath`""

        $trigger = New-ScheduledTaskTrigger -AtLogOn -User $KioskUsername

        $principal = New-ScheduledTaskPrincipal `
            -UserId "NT AUTHORITY\SYSTEM" `
            -RunLevel Highest `
            -LogonType ServiceAccount

        $settings = New-ScheduledTaskSettingsSet `
            -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries `
            -ExecutionTimeLimit ([TimeSpan]::Zero) `
            -RestartCount 3 `
            -RestartInterval ([TimeSpan]::FromMinutes(1)) `
            -StartWhenAvailable

        Register-ScheduledTask `
            -TaskName $taskName `
            -Action $action `
            -Trigger $trigger `
            -Principal $principal `
            -Settings $settings `
            -Description "Monitors and restarts Chrome in kiosk mode if it is closed" `
            -Force | Out-Null

        Write-KioskLog -Message "Chrome watchdog task registered (runs as SYSTEM at logon of $KioskUsername)" -Level "SUCCESS"
    }
}

function Register-InactivityMonitor {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$KioskUsername
    )

    if (-not $Config.inactivity.enabled) {
        Write-KioskLog -Message "Inactivity monitor is disabled in config. Skipping." -Level "INFO"
        return
    }

    Write-KioskLog -Message "Registering inactivity monitor scheduled task..." -Level "INFO"

    $taskName = "Kiosk-InactivityMonitor"
    $scriptPath = "C:\KioskScripts\inactivity-reset.ps1"

    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

    if ($PSCmdlet.ShouldProcess($taskName, "Register scheduled task")) {
        $action = New-ScheduledTaskAction `
            -Execute "powershell.exe" `
            -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File `"$scriptPath`""

        $trigger = New-ScheduledTaskTrigger -AtLogOn -User $KioskUsername

        $principal = New-ScheduledTaskPrincipal `
            -UserId "NT AUTHORITY\SYSTEM" `
            -RunLevel Highest `
            -LogonType ServiceAccount

        $settings = New-ScheduledTaskSettingsSet `
            -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries `
            -ExecutionTimeLimit ([TimeSpan]::Zero) `
            -RestartCount 3 `
            -RestartInterval ([TimeSpan]::FromMinutes(1)) `
            -StartWhenAvailable

        Register-ScheduledTask `
            -TaskName $taskName `
            -Action $action `
            -Trigger $trigger `
            -Principal $principal `
            -Settings $settings `
            -Description "Resets kiosk session after period of inactivity" `
            -Force | Out-Null

        Write-KioskLog -Message "Inactivity monitor task registered (timeout: $($Config.inactivity.timeoutMinutes) min)" -Level "SUCCESS"
    }
}

function Register-FirewallUpdateTask {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    if (-not $Config.firewall.enableDomainWhitelist) {
        Write-KioskLog -Message "Firewall whitelist disabled. Skipping firewall update task." -Level "INFO"
        return
    }

    Write-KioskLog -Message "Registering firewall IP update scheduled task..." -Level "INFO"

    $taskName = "Kiosk-FirewallIPUpdate"
    $scriptPath = "C:\KioskScripts\update-firewall-ips.ps1"

    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

    if ($PSCmdlet.ShouldProcess($taskName, "Register scheduled task")) {
        $action = New-ScheduledTaskAction `
            -Execute "powershell.exe" `
            -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -File `"$scriptPath`""

        $trigger = New-ScheduledTaskTrigger -Daily -At "3:00AM"

        $principal = New-ScheduledTaskPrincipal `
            -UserId "NT AUTHORITY\SYSTEM" `
            -RunLevel Highest `
            -LogonType ServiceAccount

        $settings = New-ScheduledTaskSettingsSet `
            -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries `
            -StartWhenAvailable

        Register-ScheduledTask `
            -TaskName $taskName `
            -Action $action `
            -Trigger $trigger `
            -Principal $principal `
            -Settings $settings `
            -Description "Re-resolves whitelisted domain IPs and updates firewall rules" `
            -Force | Out-Null

        Write-KioskLog -Message "Firewall IP update task registered (daily at 3:00 AM)" -Level "SUCCESS"
    }
}

function Register-LogRotationTask {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Registering log rotation scheduled task..." -Level "INFO"

    $taskName = "Kiosk-LogRotation"
    $logDir = $Config.logging.logDirectory
    $retentionDays = $Config.logging.logRetentionDays

    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

    if ($PSCmdlet.ShouldProcess($taskName, "Register scheduled task")) {
        $script = "Get-ChildItem -Path '$logDir' -File -Recurse | Where-Object { `$_.LastWriteTime -lt (Get-Date).AddDays(-$retentionDays) } | Remove-Item -Force"

        $action = New-ScheduledTaskAction `
            -Execute "powershell.exe" `
            -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -NonInteractive -Command `"$script`""

        $trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At "4:00AM"

        $principal = New-ScheduledTaskPrincipal `
            -UserId "NT AUTHORITY\SYSTEM" `
            -RunLevel Highest `
            -LogonType ServiceAccount

        $settings = New-ScheduledTaskSettingsSet `
            -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries `
            -StartWhenAvailable

        Register-ScheduledTask `
            -TaskName $taskName `
            -Action $action `
            -Trigger $trigger `
            -Principal $principal `
            -Settings $settings `
            -Description "Rotates kiosk logs older than $retentionDays days" `
            -Force | Out-Null

        Write-KioskLog -Message "Log rotation task registered (weekly, retain $retentionDays days)" -Level "SUCCESS"
    }
}

function Remove-KioskScheduledTasks {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Removing kiosk scheduled tasks..." -Level "INFO"

    $taskNames = @(
        "Kiosk-ChromeWatchdog",
        "Kiosk-InactivityMonitor",
        "Kiosk-FirewallIPUpdate",
        "Kiosk-LogRotation"
    )

    foreach ($taskName in $taskNames) {
        $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
        if ($task) {
            if ($PSCmdlet.ShouldProcess($taskName, "Unregister scheduled task")) {
                Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
                Write-KioskLog -Message "Removed scheduled task: $taskName" -Level "SUCCESS"
            }
        }
        else {
            Write-KioskLog -Message "Scheduled task not found (already removed): $taskName" -Level "DEBUG"
        }
    }
}

Export-ModuleMember -Function Register-ChromeWatchdog, Register-InactivityMonitor, Register-FirewallUpdateTask, Register-LogRotationTask, Remove-KioskScheduledTasks
