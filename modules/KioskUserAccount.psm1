#Requires -Version 5.1
<#
.SYNOPSIS
    Kiosk user account creation and auto-login configuration.
.DESCRIPTION
    Creates a restricted local user account for the kiosk and configures
    automatic login so no human interaction is needed at boot.
#>

function New-KioskUserPassword {
    [CmdletBinding()]
    param(
        [int]$Length = 32
    )

    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $bytes = New-Object byte[] $Length
    $rng.GetBytes($bytes)

    # Convert to a base64 string and trim to desired length
    $password = [Convert]::ToBase64String($bytes).Substring(0, $Length)

    # Ensure complexity: inject at least one uppercase, lowercase, digit, special char
    $chars = $password.ToCharArray()
    $chars[0] = 'A'
    $chars[1] = 'a'
    $chars[2] = '9'
    $chars[3] = '!'

    return [string]::new($chars)
}

function New-KioskUser {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [switch]$Force
    )

    $username = $Config.kioskAccount.username
    $description = $Config.kioskAccount.description
    $passwordLength = $Config.kioskAccount.passwordLength

    Write-KioskLog -Message "Configuring kiosk user account: $username" -Level "INFO"

    # Check if user already exists
    $existingUser = Get-LocalUser -Name $username -ErrorAction SilentlyContinue
    $password = New-KioskUserPassword -Length $passwordLength
    $securePassword = ConvertTo-SecureString -String $password -AsPlainText -Force

    if ($existingUser) {
        if ($Force) {
            Write-KioskLog -Message "User '$username' already exists. Resetting password (Force mode)." -Level "WARN"
            if ($PSCmdlet.ShouldProcess($username, "Reset password")) {
                Set-LocalUser -Name $username -Password $securePassword
            }
        }
        else {
            Write-KioskLog -Message "User '$username' already exists. Skipping creation (use -Force to reset)." -Level "INFO"
        }
    }
    else {
        if ($PSCmdlet.ShouldProcess($username, "Create local user")) {
            $params = @{
                Name                 = $username
                Password             = $securePassword
                Description          = $description
                FullName             = "CJ Paws Kiosk"
                PasswordNeverExpires = $true
                UserMayNotChangePassword = $true
                AccountNeverExpires  = $true
            }
            New-LocalUser @params | Out-Null
            Write-KioskLog -Message "Created local user '$username'" -Level "SUCCESS"

            # Add to Users group only (NOT Administrators)
            Add-LocalGroupMember -Group "Users" -Member $username -ErrorAction SilentlyContinue
            Write-KioskLog -Message "Added '$username' to Users group" -Level "SUCCESS"
        }
    }

    # Verify user is NOT in Administrators group
    $adminMembers = Get-LocalGroupMember -Group "Administrators" -ErrorAction SilentlyContinue
    if ($adminMembers.Name -match [regex]::Escape($username)) {
        Write-KioskLog -Message "SECURITY: Removing '$username' from Administrators group!" -Level "WARN"
        Remove-LocalGroupMember -Group "Administrators" -Member $username -ErrorAction SilentlyContinue
    }

    return $password
}

function Set-KioskAutoLogin {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$Username,

        [Parameter(Mandatory)]
        [string]$Password
    )

    Write-KioskLog -Message "Configuring auto-login for '$Username'" -Level "INFO"

    $winlogonPath = "HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon"

    if ($PSCmdlet.ShouldProcess("Winlogon", "Configure auto-login")) {
        # Set auto-login registry values
        Set-ItemProperty -Path $winlogonPath -Name "AutoAdminLogon" -Value "1" -Type String
        Set-ItemProperty -Path $winlogonPath -Name "DefaultUserName" -Value $Username -Type String
        Set-ItemProperty -Path $winlogonPath -Name "DefaultPassword" -Value $Password -Type String
        Set-ItemProperty -Path $winlogonPath -Name "DefaultDomainName" -Value $env:COMPUTERNAME -Type String
        Set-ItemProperty -Path $winlogonPath -Name "ForceAutoLogon" -Value "1" -Type String

        Write-KioskLog -Message "Auto-login registry values set" -Level "SUCCESS"
    }

    # Disable lock screen
    $personalizationPath = "HKLM:\SOFTWARE\Policies\Microsoft\Windows\Personalization"
    if (-not (Test-Path -Path $personalizationPath)) {
        New-Item -Path $personalizationPath -Force | Out-Null
    }
    Set-ItemProperty -Path $personalizationPath -Name "NoLockScreen" -Value 1 -Type DWord

    # Disable Ctrl+Alt+Del requirement at login
    $systemPath = "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System"
    if (-not (Test-Path -Path $systemPath)) {
        New-Item -Path $systemPath -Force | Out-Null
    }
    Set-ItemProperty -Path $systemPath -Name "DisableCAD" -Value 1 -Type DWord

    Write-KioskLog -Message "Lock screen and Ctrl+Alt+Del requirement disabled" -Level "SUCCESS"
}

function Initialize-KioskUserProfile {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$Username
    )

    Write-KioskLog -Message "Initializing profile for '$Username'" -Level "INFO"

    # Get the user's SID
    $userObj = Get-LocalUser -Name $Username -ErrorAction Stop
    $sid = $userObj.SID.Value
    Write-KioskLog -Message "User SID: $sid" -Level "DEBUG"

    # Check if user profile directory exists
    $profilePath = "C:\Users\$Username"
    if (-not (Test-Path -Path $profilePath)) {
        Write-KioskLog -Message "User profile does not exist yet. It will be created on first login." -Level "INFO"
        Write-KioskLog -Message "Policies will be applied to the default user profile as a fallback." -Level "INFO"

        # Apply policies to default user profile NTUSER.DAT
        $defaultHive = "C:\Users\Default\NTUSER.DAT"
        if (Test-Path -Path $defaultHive) {
            $tempKey = "HKU\KioskDefault"
            & reg load $tempKey $defaultHive 2>$null | Out-Null
            if ($LASTEXITCODE -eq 0) {
                Write-KioskLog -Message "Loaded default user registry hive" -Level "DEBUG"
                return @{ HiveKey = $tempKey; SID = $sid; ProfilePath = $profilePath; IsDefault = $true }
            }
        }
    }
    else {
        # Load the user's NTUSER.DAT
        $hivePath = Join-Path -Path $profilePath -ChildPath "NTUSER.DAT"
        if (Test-Path -Path $hivePath) {
            $tempKey = "HKU\$sid"

            # Check if already loaded
            $loaded = Test-Path -Path "Registry::$tempKey" -ErrorAction SilentlyContinue
            if (-not $loaded) {
                & reg load $tempKey $hivePath 2>$null | Out-Null
                if ($LASTEXITCODE -eq 0) {
                    Write-KioskLog -Message "Loaded user registry hive from $hivePath" -Level "DEBUG"
                }
                else {
                    Write-KioskLog -Message "Could not load user hive. It may be in use (user logged in)." -Level "WARN"
                }
            }
            return @{ HiveKey = $tempKey; SID = $sid; ProfilePath = $profilePath; IsDefault = $false }
        }
    }

    return @{ HiveKey = $null; SID = $sid; ProfilePath = $profilePath; IsDefault = $false }
}

function Dismount-KioskUserProfile {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [string]$HiveKey
    )

    if ($HiveKey) {
        [gc]::Collect()
        Start-Sleep -Seconds 1
        & reg unload $HiveKey 2>$null | Out-Null
        Write-KioskLog -Message "Unloaded registry hive: $HiveKey" -Level "DEBUG"
    }
}

Export-ModuleMember -Function New-KioskUser, Set-KioskAutoLogin, Initialize-KioskUserProfile, Dismount-KioskUserProfile
