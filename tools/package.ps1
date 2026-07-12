# Biscotto - packaging del plugin WordPress (Windows/PowerShell).
# Equivalente di tools/package.sh ma senza dipendenza dal comando `zip`.
# Produce: dist\biscotto-cookie-consent\  (cartella installabile via FTP)
#          dist\biscotto-cookie-consent.zip (upload da Plugin -> Aggiungi nuovo, o submission WP.org)
#
# Uso:  powershell -ExecutionPolicy Bypass -File tools\package.ps1

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root   = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$core   = Join-Path $root 'packages\core'
$wp     = Join-Path $root 'packages\wordpress'
$dist   = Join-Path $root 'dist'
$plugin = Join-Path $dist 'biscotto-cookie-consent'

Write-Host "Biscotto package - root: $root"

# 1) Sync del core (unica fonte di verita') dentro l'adattatore WordPress.
New-Item -ItemType Directory -Force -Path (Join-Path $wp 'public\js'), (Join-Path $wp 'public\css') | Out-Null
Copy-Item (Join-Path $core 'src\consent-manager.js')      (Join-Path $wp 'public\js\consent-manager.js') -Force
Copy-Item (Join-Path $core 'src\consent-mode-default.js') (Join-Path $wp 'public\js\consent-mode-default.js') -Force
Copy-Item (Join-Path $core 'css\banner.css')              (Join-Path $wp 'public\css\banner.css') -Force
Write-Host "  [ok] core sincronizzato in packages\wordpress\public"

# 2) Assembla la cartella del plugin (nome cartella = slug = biscotto-cookie-consent).
if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
New-Item -ItemType Directory -Force -Path $plugin | Out-Null
Copy-Item (Join-Path $wp '*') $plugin -Recurse -Force
Copy-Item (Join-Path $root 'LICENSE') (Join-Path $plugin 'LICENSE') -Force
Write-Host "  [ok] cartella plugin assemblata in $plugin"

# 3) Zip con separatori '/' (necessari per l'estrazione su WordPress.org/Linux).
$zipPath = Join-Path $dist 'biscotto-cookie-consent.zip'
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
  Get-ChildItem -Path $plugin -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Substring($dist.Length + 1).Replace('\','/')
    [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal)
  }
} finally { $zip.Dispose() }
Write-Host "  [ok] dist\biscotto-cookie-consent.zip creato"
Write-Host "Pacchetto pronto: $zipPath"
