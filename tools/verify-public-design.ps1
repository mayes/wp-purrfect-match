param(
	[Parameter(Mandatory = $true)]
	[ValidateSet('design', 'contracts')]
	[string]$Mode
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))

function Read-Utf8([string]$RelativePath) {
	return [IO.File]::ReadAllText((Join-Path $root $RelativePath), [Text.Encoding]::UTF8)
}

function Assert-Pattern([string]$Text, [string]$Pattern, [string]$Label) {
	if (-not [regex]::IsMatch($Text, $Pattern, [Text.RegularExpressions.RegexOptions]::Singleline)) {
		throw "Missing required outcome: $Label"
	}
}

function Assert-NoPattern([string]$Text, [string]$Pattern, [string]$Label) {
	if ([regex]::IsMatch($Text, $Pattern, [Text.RegularExpressions.RegexOptions]::Singleline)) {
		throw "Forbidden design pattern found: $Label"
	}
}

function Get-RelativeLuminance([string]$Hex) {
	$hexValue = $Hex.TrimStart('#')
	$channels = @(0, 2, 4) | ForEach-Object {
		$value = [Convert]::ToInt32($hexValue.Substring($_, 2), 16) / 255
		if ($value -le 0.03928) { $value / 12.92 } else { [Math]::Pow(($value + 0.055) / 1.055, 2.4) }
	}
	return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2])
}

function Get-ContrastRatio([string]$First, [string]$Second) {
	$firstLum = Get-RelativeLuminance $First
	$secondLum = Get-RelativeLuminance $Second
	return ([Math]::Max($firstLum, $secondLum) + 0.05) / ([Math]::Min($firstLum, $secondLum) + 0.05)
}

$css = Read-Utf8 'assets/css/purrfect-match.css'
$js = Read-Utf8 'assets/js/purrfect-match.js'
$template = Read-Utf8 'templates/widget.php'
$runtime = Read-Utf8 'includes/class-purrfect-match.php'
$preview = Read-Utf8 'preview/public.php'
$adminPreview = Read-Utf8 'preview/admin.html'

if ($Mode -eq 'design') {
	Assert-Pattern $css '--pm-ink:\s*#211d19' 'warm near-black ink token'
	Assert-Pattern $css '--pm-focus:\s*#211d19' 'single-palette focus token'
	Assert-Pattern $css '\.pm-title\s*\{[^}]*word-break:\s*keep-all' 'headline hyphen protection'
	Assert-Pattern $css '\.pm-label\s*\{[^}]*display:\s*block' 'labels above fields'
	Assert-Pattern $css '\.pm-wrap\s+\.pm-select\s*\{[^}]*width:\s*100%' 'full-width styled selects'
	Assert-Pattern $css '\.pm-wrap\s*\{[^}]*letter-spacing:\s*normal' 'host-theme letter spacing isolation'
	Assert-Pattern $css '\.pm-wrap\s+button,\s*\.pm-wrap\s+select\s*\{[^}]*font-family:\s*inherit' 'component font inheritance without shorthand override'
	Assert-Pattern $css '@container\s+pm-widget\s*\(min-width:\s*42rem\)[\s\S]*?\.pm-filters\s*\{[^}]*grid-template-columns:\s*repeat\(var\(--pm-filter-cols,\s*3\),\s*minmax\(0,\s*1fr\)\)' 'balanced dynamic medium filters'
	Assert-Pattern $css '\.pm-filters-2\s*\{[^}]*--pm-filter-cols:\s*2' 'balanced two-filter mode'
	Assert-Pattern $css '@container\s+pm-widget\s*\(max-width:\s*35rem\)[\s\S]*?\.pm-filters\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)' 'single-column phone filters'
	Assert-Pattern $css '@container\s+pm-widget\s*\(max-width:\s*35rem\)[\s\S]*?\.pm-controls-hint\s*\{[^}]*display:\s*none' 'compact phone control header'
	Assert-Pattern $css '@container\s+pm-widget\s*\(max-width:\s*35rem\)[\s\S]*?\.pm-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)' 'single-column phone results'
	Assert-Pattern $css 'object-position:\s*var\(--pm-photo-position,\s*50%\s+36%\)' 'safer pet-photo focal default'
	Assert-Pattern $css '@media\s*\(prefers-reduced-motion:\s*reduce\)' 'reduced-motion fallback'
	Assert-Pattern $js 'function\s+showSkeletons\s*\(' 'loading state'
	Assert-Pattern $js 'function\s+showError\s*\(' 'error state'
	Assert-Pattern $js 'function\s+showEmpty\s*\(' 'empty state'
	Assert-Pattern $preview 'class="pm-label"' 'preview label parity'
	Assert-Pattern $preview 'class="pm-select"' 'preview select parity'
	Assert-Pattern $preview '\?state=ready, loading, empty, error, or story' 'reviewable state fixtures'
	Assert-Pattern $preview '\$_GET\[''same_site''\]' 'same-site integration fixture'
	Assert-Pattern $preview '\$_GET\[''hide_breed''\]' 'hidden-breed integration fixture'
	Assert-Pattern $template '\$pm_show_org_website[\s\S]*?home_url\(' 'same-site organization link suppression'
	Assert-Pattern $template 'pm-filters-<\?php\s+echo\s+\$pm_hide_breed\s*\?' 'dynamic filter-count class'
	Assert-Pattern $css '\.pm-wrap\s+\.pm-cta:hover[^}]*color:\s*var\(--pm-on-brand\)\s*!important' 'primary CTA host-theme state isolation'
	Assert-Pattern $css '\.pm-wrap\s+\.pm-btn-brand:hover[^}]*color:\s*var\(--pm-on-brand\)\s*!important' 'brand-button host-theme state isolation'
	Assert-NoPattern $css '\.pm-cta:hover\s*\{[^}]*opacity:' 'primary CTA hover contrast dilution'
	Assert-Pattern $css '\.pm-state-title,[\s\S]*?font-family:\s*inherit' 'state-heading font isolation'

	$publicBundle = $css + "`n" + $js + "`n" + $template + "`n" + $preview
	Assert-NoPattern $publicBundle '#000000|#000\b' 'pure black'
	Assert-NoPattern $publicBundle '\bInter\b' 'generic Inter font'
	Assert-NoPattern $publicBundle 'ui-rounded|Arial Rounded' 'juvenile rounded display type'
	Assert-NoPattern $publicBundle 'h-screen' 'fragile viewport height utility'
	Assert-NoPattern $publicBundle 'unsplash\.com' 'Unsplash fixture dependency'
	Assert-NoPattern $css '(?:linear|radial)-gradient\s*\(' 'decorative gradients'
	Assert-NoPattern $css 'transition:[^;]*(?:border|background|box-shadow)' 'non-composited transition'

	if ((($css.ToCharArray() | Where-Object { $_ -eq '{' }).Count) -ne (($css.ToCharArray() | Where-Object { $_ -eq '}' }).Count)) {
		throw 'CSS braces are unbalanced'
	}

	$ratio = Get-ContrastRatio '#e93396' '#1b1714'
	if ($ratio -lt 4.5) {
		throw ('Default brand contrast is below AA: {0:N2}' -f $ratio)
	}

	'PUBLIC DESIGN VERIFICATION PASSED'
	exit 0
}

$php = [IO.Path]::GetFullPath((Join-Path $root '..\.tools\php-8.4\php.exe'))
$node = Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
$git = [IO.Path]::GetFullPath((Join-Path $root '..\.tools\mingit\cmd\git.exe'))

foreach ($tool in @($php, $node, $git)) {
	if (-not (Test-Path -LiteralPath $tool -PathType Leaf)) {
		throw "Required verification runtime is missing: $tool"
	}
}

$phpFiles = Get-ChildItem -LiteralPath $root -Recurse -File -Filter '*.php' | Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' }
foreach ($file in $phpFiles) {
	$output = & $php -l $file.FullName 2>&1
	if ($LASTEXITCODE -ne 0) {
		throw "PHP syntax failed for $($file.FullName): $($output -join ' ')"
	}
}

$jsFiles = Get-ChildItem -LiteralPath (Join-Path $root 'assets/js') -File -Filter '*.js'
foreach ($file in $jsFiles) {
	$output = & $node --check $file.FullName 2>&1
	if ($LASTEXITCODE -ne 0) {
		throw "JavaScript syntax failed for $($file.FullName): $($output -join ' ')"
	}
}

$output = & $php (Join-Path $root 'preview/public.php') 2>&1
if ($LASTEXITCODE -ne 0 -or ($output -join "`n") -notmatch 'class="pm-select"') {
	throw "Standalone preview runtime failed: $($output -join ' ')"
}

$previewPath = (Join-Path $root 'preview/public.php').Replace('\', '/')
$fixtureCode = '$_GET[''same_site''] = 1; $_GET[''hide_breed''] = 1; require ''' + $previewPath.Replace("'", "\'") + ''';'
$output = & $php -r $fixtureCode 2>&1
$fixtureHtml = $output -join "`n"
if (
	$LASTEXITCODE -ne 0 -or
	$fixtureHtml -notmatch 'class="pm-filters pm-filters-2"' -or
	$fixtureHtml -match 'class="pm-link"' -or
	$fixtureHtml -match '>Breed<'
) {
	throw "Same-site hidden-breed preview fixture failed: $fixtureHtml"
}

$output = & $php (Join-Path $root 'tools/verify-brand-contrast.php') 2>&1
if ($LASTEXITCODE -ne 0 -or ($output -join "`n") -notmatch 'BRAND CONTRAST OK') {
	throw "Arbitrary-brand contrast verification failed: $($output -join ' ')"
}

$bootstrap = Read-Utf8 'purrfect-match.php'
$readme = Read-Utf8 'readme.txt'
$readmeMd = Read-Utf8 'README.md'
$settings = Read-Utf8 'includes/class-settings.php'
$rest = Read-Utf8 'includes/class-rest.php'
$attributes = Read-Utf8 '.gitattributes'
$distIgnore = Read-Utf8 '.distignore'
$allPublic = $template + "`n" + $js + "`n" + $css

Assert-Pattern $bootstrap 'function\s+purrfect_match\s*\(' 'global plugin bootstrap'

$constantVersion = [regex]::Match($bootstrap, "define\(\s*'PURRFECT_MATCH_VERSION',\s*'([^']+)'\s*\)").Groups[1].Value
$headerVersion = [regex]::Match($bootstrap, '(?m)^\s*\*\s+Version:\s+([0-9.]+)\s*$').Groups[1].Value
$stableVersion = [regex]::Match($readme, '(?m)^Stable tag:\s*([0-9.]+)\s*$').Groups[1].Value
$badgeVersion = [regex]::Match($readmeMd, 'version-([0-9.]+)-').Groups[1].Value
$adminPreviewVersion = [regex]::Match($adminPreview, 'pm-ver">v([0-9.]+)').Groups[1].Value
$versionMismatch = @($headerVersion, $stableVersion, $badgeVersion, $adminPreviewVersion) | Where-Object { -not $_ -or $_ -ne $constantVersion }
if (-not $constantVersion -or $versionMismatch) {
	throw "Plugin versions are not synchronized: constant=$constantVersion header=$headerVersion stable=$stableVersion badge=$badgeVersion admin-preview=$adminPreviewVersion"
}

Assert-Pattern $settings "const\s+OPTION\s*=\s*'purrfect_match_options'" 'option key'
Assert-Pattern $runtime "add_shortcode\(\s*'purrfect_match'" 'shortcode registration'
Assert-Pattern $runtime "wp_register_style\(\s*'purrfect-match'" 'public style handle'
Assert-Pattern $runtime "wp_register_script\(\s*'purrfect-match'" 'public script handle'
Assert-Pattern $rest "const\s+NS\s*=\s*'purrfect-match/v1'" 'REST namespace'
Assert-Pattern $rest "register_rest_route\(\s*self::NS,\s*'/pets'" 'REST pets route'
Assert-Pattern $rest "'cats'\s*=>" 'legacy REST response key'

foreach ($hook in @('data-pm-config', 'data-pm-grid', 'data-pm-filter', 'data-pm-count', 'data-pm-status', 'data-pm-chips', 'data-pm-more', 'data-pm-action')) {
	Assert-Pattern $allPublic ([regex]::Escape($hook)) "public hook $hook"
}

foreach ($legacyClass in @('pm-media-link', 'pm-name-link', 'pm-links', 'pm-powered', 'pm-paw', 'pm-count-pill', 'pm-loading', 'pm-paws')) {
	Assert-Pattern $allPublic ([regex]::Escape($legacyClass)) "legacy CSS hook $legacyClass"
}

Assert-Pattern $css '--pm-ring:' 'legacy ring token'
Assert-Pattern $css '--pm-radius:' 'legacy radius token'
Assert-Pattern $js 'class="pm-skel"\s+role="listitem"' 'valid loading list semantics'
Assert-Pattern $attributes '/GATES\.md\s+export-ignore' 'acceptance ledger excluded from release ZIP'
Assert-Pattern $attributes '/preview\s+export-ignore' 'preview artifacts excluded from release ZIP'
Assert-Pattern $distIgnore '(?m)^/GATES\.md\s*$' 'acceptance ledger excluded from WordPress dist archive'
Assert-Pattern $distIgnore '(?m)^/preview\s*$' 'preview artifacts excluded from WordPress dist archive'

$previousErrorAction = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
$diffOutput = & $git -C $root diff --check 2>&1
$diffExit = $LASTEXITCODE
$ErrorActionPreference = $previousErrorAction
if ($diffExit -ne 0) {
	throw "Git whitespace check failed: $($diffOutput -join ' ')"
}

'PUBLIC CONTRACT VERIFICATION PASSED'
