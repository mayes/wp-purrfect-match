param(
	[Parameter(Mandatory = $true)]
	[ValidateSet('php', 'js', 'docs', 'all')]
	[string]$Mode
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
# Resolve a runtime: an explicit environment override, then the bundled
# Windows toolchain path, then whatever is on PATH.
function Resolve-Tool([string]$EnvName, [string]$Bundled, [string]$Command) {
	$override = [Environment]::GetEnvironmentVariable($EnvName)
	if ($override) { return $override }
	if ($Bundled -and (Test-Path -LiteralPath $Bundled -PathType Leaf)) { return $Bundled }
	$found = Get-Command $Command -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
	if ($found) { return $found.Source }
	return $Bundled
}
$php = Resolve-Tool 'PM_PHP' ([IO.Path]::GetFullPath((Join-Path $root '..\.tools\php-8.4\php.exe'))) 'php'
$node = Resolve-Tool 'PM_NODE' $(if ($env:USERPROFILE) { Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' } else { '' }) 'node'

foreach ($tool in @($php, $node)) {
	if (-not (Test-Path -LiteralPath $tool -PathType Leaf)) {
		throw "Required verification runtime is missing: $tool"
	}
}

function Invoke-Checked([string]$Program, [string[]]$Arguments, [string]$Expected) {
	$output = & $Program @Arguments 2>&1
	$exitCode = $LASTEXITCODE
	$text = $output -join "`n"
	if ($exitCode -ne 0 -or $text -notmatch [regex]::Escape($Expected)) {
		throw "Verification command failed: $Program $($Arguments -join ' ')`n$text"
	}
}

if ($Mode -in @('php', 'all')) {
	Invoke-Checked $php @((Join-Path $root 'tools/verify-shortcode-sort.php')) 'PHP SORT CONTRACT VERIFICATION PASSED'
}

if ($Mode -in @('docs', 'all')) {
	Invoke-Checked $php @((Join-Path $root 'tools/verify-shortcode-sort.php'), '--docs') 'NEWEST SORT DOCUMENTATION VERIFICATION PASSED'
}

if ($Mode -in @('js', 'all')) {
	Invoke-Checked $node @('--check', (Join-Path $root 'assets/js/purrfect-match.js')) ''
	Invoke-Checked $node @((Join-Path $root 'tools/verify-newest-sort.mjs')) 'JAVASCRIPT SORT CONTRACT VERIFICATION PASSED'
}

switch ($Mode) {
	'php' { 'PHP SORT CONTRACT VERIFICATION PASSED' }
	'js' { 'JAVASCRIPT SORT CONTRACT VERIFICATION PASSED' }
	'docs' { 'NEWEST SORT DOCUMENTATION VERIFICATION PASSED' }
	'all' { 'NEWEST SORT CONTRACT VERIFICATION PASSED' }
}
