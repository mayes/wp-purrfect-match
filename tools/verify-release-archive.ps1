param(
	[Parameter(Mandatory = $true)]
	[ValidatePattern('^[0-9]+\.[0-9]+\.[0-9]+$')]
	[string]$ExpectedVersion
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$zipPath = Join-Path $root 'dist\purrfect-match.zip'
$git = [IO.Path]::GetFullPath((Join-Path $root '..\.tools\mingit\cmd\git.exe'))

if (-not (Test-Path -LiteralPath $zipPath -PathType Leaf)) {
	throw "Release archive is missing: $zipPath"
}

& $git -C $root diff --quiet
if ($LASTEXITCODE -ne 0) {
	throw 'Tracked working-tree changes remain; the archive cannot be matched to committed source.'
}
& $git -C $root diff --cached --quiet
if ($LASTEXITCODE -ne 0) {
	throw 'Staged changes remain; the archive cannot be matched to committed source.'
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::OpenRead($zipPath)

try {
	$expectedEntries = @(
		'purrfect-match/',
		'purrfect-match/LICENSE',
		'purrfect-match/assets/',
		'purrfect-match/assets/css/',
		'purrfect-match/assets/css/admin.css',
		'purrfect-match/assets/css/explorer.css',
		'purrfect-match/assets/css/purrfect-match.css',
		'purrfect-match/assets/js/',
		'purrfect-match/assets/js/admin.js',
		'purrfect-match/assets/js/explorer.js',
		'purrfect-match/assets/js/purrfect-match.js',
		'purrfect-match/includes/',
		'purrfect-match/includes/class-purrfect-match.php',
		'purrfect-match/includes/class-rest.php',
		'purrfect-match/includes/class-settings.php',
		'purrfect-match/purrfect-match.php',
		'purrfect-match/readme.txt',
		'purrfect-match/templates/',
		'purrfect-match/templates/widget.php',
		'purrfect-match/uninstall.php'
	) | Sort-Object
	$actualEntries = @($zip.Entries | ForEach-Object FullName | Sort-Object)

	if (($actualEntries -join "`n") -ne ($expectedEntries -join "`n")) {
		throw "Archive contents differ from the approved shipped-file set.`nActual:`n$($actualEntries -join "`n")"
	}

	$sourceFiles = $expectedEntries | Where-Object { -not $_.EndsWith('/') }
	foreach ($archivePath in $sourceFiles) {
		$relativePath = $archivePath.Substring('purrfect-match/'.Length)
		$sourcePath = Join-Path $root ($relativePath.Replace('/', '\'))
		if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
			throw "Archive entry has no committed source file: $relativePath"
		}

		$entry = $zip.GetEntry($archivePath)
		$reader = [IO.StreamReader]::new($entry.Open(), [Text.Encoding]::UTF8, $true)
		try {
			$archiveText = $reader.ReadToEnd()
		} finally {
			$reader.Dispose()
		}
		$sourceText = [IO.File]::ReadAllText($sourcePath, [Text.Encoding]::UTF8)
		$archiveText = $archiveText.Replace("`r`n", "`n").Replace("`r", "`n")
		$sourceText = $sourceText.Replace("`r`n", "`n").Replace("`r", "`n")
		if ($archiveText -cne $sourceText) {
			throw "Archive entry does not match committed working-tree source: $relativePath"
		}
	}

	$readmeEntry = $zip.GetEntry('purrfect-match/readme.txt')
	$reader = [IO.StreamReader]::new($readmeEntry.Open(), [Text.Encoding]::UTF8, $true)
	try {
		$readmeText = $reader.ReadToEnd()
	} finally {
		$reader.Dispose()
	}
	$stableTag = [regex]::Match($readmeText, '(?m)^Stable tag:\s*([0-9.]+)\s*$').Groups[1].Value
	if ($stableTag -ne $ExpectedVersion) {
		throw "Archive stable tag is $stableTag, expected $ExpectedVersion."
	}

	$bootstrapEntry = $zip.GetEntry('purrfect-match/purrfect-match.php')
	$reader = [IO.StreamReader]::new($bootstrapEntry.Open(), [Text.Encoding]::UTF8, $true)
	try {
		$bootstrapText = $reader.ReadToEnd()
	} finally {
		$reader.Dispose()
	}
	$headerVersion = [regex]::Match($bootstrapText, '(?m)^\s*\*\s+Version:\s*([0-9.]+)\s*$').Groups[1].Value
	$constantVersion = [regex]::Match($bootstrapText, "define\(\s*'PURRFECT_MATCH_VERSION',\s*'([^']+)'\s*\)").Groups[1].Value
	if ($headerVersion -ne $ExpectedVersion -or $constantVersion -ne $ExpectedVersion) {
		throw "Archive plugin versions are not synchronized at $ExpectedVersion."
	}
} finally {
	$zip.Dispose()
}

'RELEASE ARCHIVE VERIFICATION PASSED'
