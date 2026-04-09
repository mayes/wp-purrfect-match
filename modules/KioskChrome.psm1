#Requires -Version 5.1
<#
.SYNOPSIS
    Chrome kiosk mode and enterprise policy configuration.
.DESCRIPTION
    Configures Google Chrome enterprise policies via the Windows registry
    for URL whitelisting, kiosk mode behavior, and security hardening.
    Policies apply to all users on the machine (HKLM-based).
#>

function Set-ChromeKioskPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    Write-KioskLog -Message "Configuring Chrome enterprise policies..." -Level "INFO"

    $chromePolicyPath = "HKLM:\SOFTWARE\Policies\Google\Chrome"

    # Ensure the policy key tree exists
    $policyPaths = @(
        "HKLM:\SOFTWARE\Policies\Google",
        $chromePolicyPath,
        "$chromePolicyPath\URLBlocklist",
        "$chromePolicyPath\URLAllowlist",
        "$chromePolicyPath\ExtensionInstallBlocklist",
        "$chromePolicyPath\ClearBrowsingDataOnExitList",
        "$chromePolicyPath\RestoreOnStartupURLs"
    )

    foreach ($path in $policyPaths) {
        if (-not (Test-Path -Path $path)) {
            if ($PSCmdlet.ShouldProcess($path, "Create registry key")) {
                New-Item -Path $path -Force | Out-Null
            }
        }
    }

    # --- URL Filtering (Primary Access Control) ---
    Set-ChromeURLPolicies -Config $Config -PolicyPath $chromePolicyPath

    # --- Kiosk Behavior ---
    Set-ChromeKioskBehavior -Config $Config -PolicyPath $chromePolicyPath

    # --- Security Policies ---
    Set-ChromeSecurityPolicies -Config $Config -PolicyPath $chromePolicyPath

    # --- Data Clearing ---
    Set-ChromeDataClearingPolicies -PolicyPath $chromePolicyPath

    Write-KioskLog -Message "Chrome enterprise policies configured" -Level "SUCCESS"
}

function Set-ChromeURLPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$PolicyPath
    )

    Write-KioskLog -Message "Setting Chrome URL whitelist/blocklist policies..." -Level "INFO"

    # Block everything by default
    $blocklistPath = "$PolicyPath\URLBlocklist"
    # Clear existing entries
    Get-Item -Path $blocklistPath -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty Property -ErrorAction SilentlyContinue |
        ForEach-Object { Remove-ItemProperty -Path $blocklistPath -Name $_ -ErrorAction SilentlyContinue }

    $blockedUrls = $Config.chrome.blockedUrls
    for ($i = 0; $i -lt $blockedUrls.Count; $i++) {
        $name = ($i + 1).ToString()
        if ($PSCmdlet.ShouldProcess("URLBlocklist\$name", "Set to '$($blockedUrls[$i])'")) {
            Set-ItemProperty -Path $blocklistPath -Name $name -Value $blockedUrls[$i] -Type String
        }
    }
    Write-KioskLog -Message "URL blocklist: $($blockedUrls -join ', ')" -Level "INFO"

    # Allow whitelisted URLs
    $allowlistPath = "$PolicyPath\URLAllowlist"
    Get-Item -Path $allowlistPath -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty Property -ErrorAction SilentlyContinue |
        ForEach-Object { Remove-ItemProperty -Path $allowlistPath -Name $_ -ErrorAction SilentlyContinue }

    $allowedUrls = $Config.chrome.whitelistedUrls
    for ($i = 0; $i -lt $allowedUrls.Count; $i++) {
        $name = ($i + 1).ToString()
        if ($PSCmdlet.ShouldProcess("URLAllowlist\$name", "Set to '$($allowedUrls[$i])'")) {
            Set-ItemProperty -Path $allowlistPath -Name $name -Value $allowedUrls[$i] -Type String
        }
    }
    Write-KioskLog -Message "URL allowlist: $($allowedUrls -join ', ')" -Level "INFO"
}

function Set-ChromeKioskBehavior {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$PolicyPath
    )

    Write-KioskLog -Message "Setting Chrome kiosk behavior policies..." -Level "INFO"

    $homepage = $Config.chrome.kioskHomepage

    $kioskPolicies = @{
        # Homepage and startup
        "HomepageLocation"                      = @{ Value = $homepage; Type = "String" }
        "HomepageIsNewTabPage"                   = @{ Value = 0; Type = "DWord" }
        "RestoreOnStartup"                       = @{ Value = 4; Type = "DWord" }  # Open list of URLs
        "NewTabPageLocation"                     = @{ Value = $homepage; Type = "String" }
        "ShowHomeButton"                         = @{ Value = 0; Type = "DWord" }

        # UI restrictions
        "FullscreenAllowed"                      = @{ Value = 1; Type = "DWord" }
        "BrowserSignin"                          = @{ Value = 0; Type = "DWord" }  # Disable sign-in
        "PromotionalTabsEnabled"                 = @{ Value = 0; Type = "DWord" }
        "CommandLineFlagSecurityWarningsEnabled"  = @{ Value = 0; Type = "DWord" }
    }

    foreach ($policy in $kioskPolicies.GetEnumerator()) {
        if ($PSCmdlet.ShouldProcess($policy.Key, "Set to $($policy.Value.Value)")) {
            Set-ItemProperty -Path $PolicyPath -Name $policy.Key -Value $policy.Value.Value -Type $policy.Value.Type
        }
    }

    # Set startup URL
    $startupUrlPath = "$PolicyPath\RestoreOnStartupURLs"
    Set-ItemProperty -Path $startupUrlPath -Name "1" -Value $homepage -Type String

    Write-KioskLog -Message "Chrome homepage set to: $homepage" -Level "INFO"
}

function Set-ChromeSecurityPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config,

        [Parameter(Mandatory)]
        [string]$PolicyPath
    )

    Write-KioskLog -Message "Setting Chrome security policies..." -Level "INFO"

    $securityPolicies = @{
        # Developer tools and debugging
        "DeveloperToolsAvailability"    = @{ Value = 2; Type = "DWord" }       # Disable completely
        "IncognitoModeAvailability"     = @{ Value = 1; Type = "DWord" }       # Disable incognito
        "TaskManagerEndProcessEnabled"  = @{ Value = 0; Type = "DWord" }       # Disable Chrome task manager

        # Downloads and file access
        "DownloadRestrictions"          = @{ Value = 3; Type = "DWord" }       # Block all downloads
        "AllowFileSelectionDialogs"     = @{ Value = 0; Type = "DWord" }       # Block file dialogs

        # Safe browsing (keep enabled for protection)
        "SafeBrowsingProtectionLevel"   = @{ Value = 1; Type = "DWord" }       # Standard protection

        # Password and autofill - CRITICAL for payment security
        "PasswordManagerEnabled"        = @{ Value = 0; Type = "DWord" }       # Disable password save
        "AutofillAddressEnabled"        = @{ Value = 0; Type = "DWord" }       # No address autofill
        "AutofillCreditCardEnabled"     = @{ Value = 0; Type = "DWord" }       # No credit card autofill

        # History and bookmarks
        "SavingBrowserHistoryDisabled"  = @{ Value = 1; Type = "DWord" }       # Don't save history
        "AllowDeletingBrowserHistory"   = @{ Value = 0; Type = "DWord" }
        "BookmarkBarEnabled"            = @{ Value = 0; Type = "DWord" }
        "EditBookmarksEnabled"          = @{ Value = 0; Type = "DWord" }

        # Extensions
        # (blocklist is set below as a subkey)

        # Printing
        "PrintingEnabled"               = @{ Value = 0; Type = "DWord" }

        # Media and permissions
        "EnableMediaRouter"             = @{ Value = 0; Type = "DWord" }       # Disable Chromecast
        "DefaultPopupsSetting"          = @{ Value = 2; Type = "DWord" }       # Block popups
        "DefaultNotificationsSetting"   = @{ Value = 2; Type = "DWord" }       # Block notifications
        "DefaultGeolocationSetting"     = @{ Value = 2; Type = "DWord" }       # Block geolocation
        "VideoCaptureAllowed"           = @{ Value = 0; Type = "DWord" }       # Block camera
        "AudioCaptureAllowed"           = @{ Value = 0; Type = "DWord" }       # Block microphone

        # Protocol handlers
        "ExternalProtocolDialogShowAlwaysOpenCheckbox" = @{ Value = 0; Type = "DWord" }

        # Misc
        "TranslateEnabled"              = @{ Value = 0; Type = "DWord" }       # Disable translate
        "SearchSuggestEnabled"          = @{ Value = 0; Type = "DWord" }       # Disable search suggest
        "SpellCheckServiceEnabled"      = @{ Value = 0; Type = "DWord" }       # Disable spellcheck
        "MetricsReportingEnabled"       = @{ Value = 0; Type = "DWord" }       # Disable telemetry
        "UrlKeyedAnonymizedDataCollectionEnabled" = @{ Value = 0; Type = "DWord" }
    }

    foreach ($policy in $securityPolicies.GetEnumerator()) {
        if ($PSCmdlet.ShouldProcess($policy.Key, "Set to $($policy.Value.Value)")) {
            Set-ItemProperty -Path $PolicyPath -Name $policy.Key -Value $policy.Value.Value -Type $policy.Value.Type
        }
    }

    # Block all extensions
    $extBlocklistPath = "$PolicyPath\ExtensionInstallBlocklist"
    Set-ItemProperty -Path $extBlocklistPath -Name "1" -Value "*" -Type String

    Write-KioskLog -Message "Chrome security policies applied (dev tools disabled, downloads blocked, autofill disabled, extensions blocked)" -Level "SUCCESS"
}

function Set-ChromeDataClearingPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param(
        [Parameter(Mandatory)]
        [string]$PolicyPath
    )

    Write-KioskLog -Message "Setting Chrome data clearing policies..." -Level "INFO"

    $clearPath = "$PolicyPath\ClearBrowsingDataOnExitList"

    # Clear existing entries
    Get-Item -Path $clearPath -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty Property -ErrorAction SilentlyContinue |
        ForEach-Object { Remove-ItemProperty -Path $clearPath -Name $_ -ErrorAction SilentlyContinue }

    # Data types to clear on browser exit
    $dataTypes = @(
        "browsing_history",
        "download_history",
        "cookies_and_other_site_data",
        "cached_images_and_files",
        "password_signin",
        "autofill",
        "site_settings"
    )

    for ($i = 0; $i -lt $dataTypes.Count; $i++) {
        $name = ($i + 1).ToString()
        if ($PSCmdlet.ShouldProcess("ClearBrowsingDataOnExitList\$name", "Set to '$($dataTypes[$i])'")) {
            Set-ItemProperty -Path $clearPath -Name $name -Value $dataTypes[$i] -Type String
        }
    }

    Write-KioskLog -Message "Chrome will clear all browsing data on exit (history, cookies, cache, passwords, autofill, site settings)" -Level "SUCCESS"
}

function Get-ChromeKioskLaunchArgs {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)]
        [hashtable]$Config
    )

    $homepage = $Config.chrome.kioskHomepage
    $args = @(
        "--kiosk"
        "--no-first-run"
        "--disable-translate"
        "--disable-infobars"
        "--disable-suggestions-service"
        "--disable-save-password-bubble"
        "--noerrdialogs"
        "--disable-session-crashed-bubble"
        "--disable-component-update"
        "--disable-background-networking"
        "--disable-sync"
        "--autoplay-policy=no-user-gesture-required"
        "`"$homepage`""
    )

    return $args -join " "
}

function Remove-ChromeKioskPolicies {
    [CmdletBinding(SupportsShouldProcess)]
    param()

    Write-KioskLog -Message "Removing Chrome enterprise policies..." -Level "INFO"

    $googlePolicyPath = "HKLM:\SOFTWARE\Policies\Google"
    if (Test-Path -Path $googlePolicyPath) {
        if ($PSCmdlet.ShouldProcess($googlePolicyPath, "Remove registry key tree")) {
            Remove-Item -Path $googlePolicyPath -Recurse -Force
            Write-KioskLog -Message "Chrome enterprise policies removed" -Level "SUCCESS"
        }
    }
    else {
        Write-KioskLog -Message "No Chrome enterprise policies found to remove" -Level "INFO"
    }
}

Export-ModuleMember -Function Set-ChromeKioskPolicies, Get-ChromeKioskLaunchArgs, Remove-ChromeKioskPolicies
