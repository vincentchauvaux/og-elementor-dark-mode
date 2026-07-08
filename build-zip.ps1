# ZIP compatible WordPress (chemins avec /, pas \).
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = $PSScriptRoot
$slug = 'hakou-dark-mode'
$staging = Join-Path $env:TEMP ($slug + '-build')
$folder = Join-Path $staging $slug
$zipOut = Join-Path $root ($slug + '.zip')

if (Test-Path $staging) {
    Remove-Item $staging -Recurse -Force
}
New-Item -ItemType Directory -Path $folder -Force | Out-Null
Copy-Item (Join-Path $root 'hakou-dark-mode.php') $folder
Copy-Item (Join-Path $root 'assets') $folder -Recurse
if (Test-Path (Join-Path $root 'includes')) {
    Copy-Item (Join-Path $root 'includes') $folder -Recurse
}
if (Test-Path (Join-Path $root 'readme.txt')) {
    Copy-Item (Join-Path $root 'readme.txt') $folder
}
if (Test-Path (Join-Path $root 'LICENSE.txt')) {
    Copy-Item (Join-Path $root 'LICENSE.txt') $folder
}

if (Test-Path $zipOut) {
    Remove-Item $zipOut -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($zipOut, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem $folder -Recurse -File | ForEach-Object {
        $relative = $_.FullName.Substring($folder.Length + 1).Replace('\', '/')
        $entryName = $slug + '/' + $relative
        [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip,
            $_.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        )
    }
}
finally {
    $zip.Dispose()
}

Write-Host "ZIP cree : $zipOut"
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::OpenRead($zipOut).Entries | ForEach-Object { $_.FullName }
