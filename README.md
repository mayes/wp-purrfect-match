# CJ Paws and Whiskers - Kiosk Setup

PowerShell scripts to configure a Windows 11 Pro/Enterprise machine as a secure, locked-down kiosk for CJ Paws and Whiskers. Customers can browse business websites, book appointments, and make payments without access to the underlying system.

## Whitelisted Websites

- https://cjpaws.org/
- https://thewhiskersoasis.com/
- https://whiskersworkspace.com/

## Requirements

- **Windows 11 Pro or Enterprise** (build 22000+)
- **Google Chrome** installed
- **Administrator** privileges to run the setup script
- PowerShell 5.1 or later (included with Windows 11)

## Quick Start

1. **Edit the configuration** (optional):
   ```powershell
   notepad .\kiosk-config.json
   ```

2. **Validate the configuration**:
   ```powershell
   .\tests\Test-KioskConfig.ps1
   ```

3. **Run the setup** (as Administrator):
   ```powershell
   .\setup-kiosk.ps1
   ```

4. **Verify the setup**:
   ```powershell
   .\tests\Test-KioskSetup.ps1
   ```

5. **Reboot** to activate kiosk mode.

## What It Does

### Kiosk User Account
- Creates a restricted local user `CJPawsKiosk` (not an admin)
- Configures auto-login so the kiosk starts without human interaction
- Disables lock screen and Ctrl+Alt+Del requirement

### Chrome Kiosk Mode
- Launches Chrome in `--kiosk` mode (fullscreen, no browser UI)
- **URL whitelist**: only the 3 business websites are accessible
- **Blocks everything else** by default (`URLBlocklist: *`)
- Disables dev tools, downloads, extensions, incognito mode
- **Disables password manager and credit card autofill** (critical for payment security)
- Clears all browsing data (cookies, cache, passwords, autofill) on exit

### Security Hardening
- **USB mass storage disabled** (keyboards and mice still work)
- **System shortcuts blocked**: Win key, Ctrl+Alt+Del, Alt+Tab, Alt+F4, etc.
- **System tools blocked**: CMD, PowerShell, Registry Editor, Task Manager, Control Panel, Settings
- **Explorer restricted**: no desktop icons, no right-click, taskbar locked
- **Keyboard Filter** enabled on Enterprise edition for strongest shortcut blocking

### Firewall Rules
- Blocks all outbound traffic for the kiosk user by default
- Allows only DNS, DHCP, and HTTPS to whitelisted domain IPs
- Allows HTTP for certificate validation (OCSP/CRL)
- Daily scheduled task re-resolves domain IPs to handle CDN changes

### Automatic Recovery
- **Chrome watchdog**: restarts Chrome within 10 seconds if closed or crashed
- **Inactivity monitor**: resets session after 5 minutes of inactivity (clears all data, returns to homepage)

### Windows Configuration
- Windows Update: security patches still install, but reboots are blocked during business hours (8 AM - 8 PM)
- All notifications and toast popups disabled
- Sleep and screensaver disabled

## File Structure

```
├── kiosk-config.json          # All settings (URLs, timeouts, security toggles)
├── setup-kiosk.ps1            # Main setup script (run as Administrator)
├── revert-kiosk.ps1           # Undo everything
├── modules/
│   ├── KioskLogger.psm1       # Logging
│   ├── KioskPrerequisites.psm1# Validation
│   ├── KioskUserAccount.psm1  # User account + auto-login
│   ├── KioskChrome.psm1       # Chrome enterprise policies
│   ├── KioskSecurity.psm1     # USB, shortcuts, app blocking
│   ├── KioskFirewall.psm1     # Firewall rules
│   ├── KioskScheduledTasks.psm1# Watchdog, inactivity, log rotation
│   └── KioskWindowsConfig.psm1# Windows Update, notifications, display
├── assets/
│   ├── chrome-watchdog.ps1    # Runtime: Chrome auto-restart
│   ├── inactivity-reset.ps1   # Runtime: Session reset on idle
│   ├── clear-browsing-data.ps1# Runtime: Wipe Chrome data
│   └── update-firewall-ips.ps1# Runtime: Refresh domain IPs
├── tests/
│   ├── Test-KioskConfig.ps1   # Validate config before setup
│   └── Test-KioskSetup.ps1    # Verify setup after running
├── backup/                    # Registry & firewall backups (created at runtime)
└── logs/                      # Setup logs (created at runtime)
```

## Configuration

Edit `kiosk-config.json` before running setup. Key settings:

| Setting | Default | Description |
|---------|---------|-------------|
| `kioskAccount.username` | `CJPawsKiosk` | Local user account name |
| `chrome.kioskHomepage` | `https://cjpaws.org/` | Default homepage |
| `chrome.whitelistedUrls` | 3 sites + wildcards | Allowed websites |
| `inactivity.timeoutMinutes` | `5` | Minutes before session reset |
| `windowsUpdate.activeHoursStart` | `8` | Business hours start |
| `windowsUpdate.activeHoursEnd` | `20` | Business hours end |
| `logging.logRetentionDays` | `30` | Days to keep logs |

## Setup Script Options

```powershell
# Standard setup
.\setup-kiosk.ps1

# Dry run (preview changes without applying)
.\setup-kiosk.ps1 -DryRun

# Force mode (overwrite existing kiosk user)
.\setup-kiosk.ps1 -Force

# Custom config file
.\setup-kiosk.ps1 -ConfigPath "C:\my-config.json"

# Skip restore point
.\setup-kiosk.ps1 -SkipRestorePoint
```

## Reverting

To undo all kiosk changes and restore the machine:

```powershell
# Full revert
.\revert-kiosk.ps1

# Keep the user account
.\revert-kiosk.ps1 -KeepUser

# Keep log files
.\revert-kiosk.ps1 -KeepLogs
```

A reboot is recommended after reverting.

## Logs

Setup logs are stored at `C:\KioskLogs\`. Runtime logs (watchdog, inactivity monitor, firewall updates) are also stored there. Logs are automatically rotated weekly (kept for 30 days by default).

## Security Notes

- **Payment safety**: Credit card autofill is explicitly disabled. All browsing data (cookies, passwords, form data) is cleared on Chrome exit AND on inactivity timeout. No payment information persists between sessions.
- **Defense in depth**: URL access is controlled at two layers: Chrome enterprise policies (primary) and Windows Firewall (secondary). Even if one layer is bypassed, the other provides protection.
- **The kiosk user has no admin privileges** and cannot access system tools, install software, or modify settings.
- **Security updates are not disabled**. Windows Update still runs but reboots are deferred to outside business hours.
- A **system restore point** is created before any changes, and all registry modifications are backed up to the `backup/` directory.
