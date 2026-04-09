#Requires -Version 5.1
<#
.SYNOPSIS
    Logging functions for the CJ Paws and Whiskers Kiosk Setup.
.DESCRIPTION
    Provides centralized logging with file output and console display.
    All kiosk modules use these functions for audit trail.
#>

$script:LogFile = $null
$script:VerboseLogging = $false

function Initialize-KioskLog {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [string]$LogDirectory,

        [bool]$Verbose = $false
    )

    $script:VerboseLogging = $Verbose

    if (-not (Test-Path -Path $LogDirectory)) {
        New-Item -Path $LogDirectory -ItemType Directory -Force | Out-Null
    }

    $timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $script:LogFile = Join-Path -Path $LogDirectory -ChildPath "kiosk-setup-$timestamp.log"

    # Write log header
    $header = @"
==============================================================
  CJ Paws and Whiskers - Kiosk Setup Log
  Timestamp  : $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
  Computer   : $env:COMPUTERNAME
  OS Version : $([System.Environment]::OSVersion.VersionString)
  User       : $env:USERNAME
  Log File   : $($script:LogFile)
==============================================================
"@
    Set-Content -Path $script:LogFile -Value $header -Encoding UTF8
    Write-Host $header -ForegroundColor Cyan

    # Start transcript alongside the structured log
    $transcriptPath = Join-Path -Path $LogDirectory -ChildPath "kiosk-transcript-$timestamp.log"
    try {
        Start-Transcript -Path $transcriptPath -Append | Out-Null
    }
    catch {
        # Transcript may already be running in some environments
        Write-Warning "Could not start transcript: $_"
    }

    Write-KioskLog -Message "Logging initialized" -Level "INFO"
}

function Write-KioskLog {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [string]$Message,

        [ValidateSet("INFO", "WARN", "ERROR", "SUCCESS", "DEBUG")]
        [string]$Level = "INFO"
    )

    if ($Level -eq "DEBUG" -and -not $script:VerboseLogging) {
        return
    }

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $entry = "[$timestamp] [$Level] $Message"

    if ($script:LogFile -and (Test-Path -Path (Split-Path $script:LogFile -Parent))) {
        Add-Content -Path $script:LogFile -Value $entry -Encoding UTF8
    }

    switch ($Level) {
        "ERROR"   { Write-Host $entry -ForegroundColor Red }
        "WARN"    { Write-Host $entry -ForegroundColor Yellow }
        "SUCCESS" { Write-Host $entry -ForegroundColor Green }
        "DEBUG"   { Write-Host $entry -ForegroundColor Gray }
        default   { Write-Host $entry -ForegroundColor White }
    }
}

function Write-KioskLogSection {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [string]$Title
    )

    $separator = "-" * 60
    Write-KioskLog -Message $separator -Level "INFO"
    Write-KioskLog -Message "  $Title" -Level "INFO"
    Write-KioskLog -Message $separator -Level "INFO"
}

function Stop-KioskLog {
    [CmdletBinding()]
    param()

    Write-KioskLog -Message "Logging session ended" -Level "INFO"
    try {
        Stop-Transcript | Out-Null
    }
    catch {
        # Transcript may not have been started
    }
}

Export-ModuleMember -Function Initialize-KioskLog, Write-KioskLog, Write-KioskLogSection, Stop-KioskLog
