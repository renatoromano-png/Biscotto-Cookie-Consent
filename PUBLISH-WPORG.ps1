<#
  PUBLISH-WPORG.ps1  -  Prima pubblicazione (e aggiornamenti) del plugin
  "Biscotto - Cookie Consent" sulla WordPress.org Plugin Directory via SVN.

  Prerequisiti:
    1. Password SVN impostata su:
       https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
       (username SVN = renatosaka, case-sensitive; diversa dalla password del sito)
    2. SlikSVN installato (client 'svn' nel PATH).  https://sliksvn.com/download/
    3. Attesa >= 1 ora dall'email di approvazione (attivazione commit).

  Lo script prepara la working copy e i file, poi CHIEDE CONFERMA prima di
  eseguire 'svn commit' (l'upload vero, dove inserirai la password SVN).

  Uso:   pwsh -File .\PUBLISH-WPORG.ps1
         (oppure tasto destro > Esegui con PowerShell)
#>

$ErrorActionPreference = 'Stop'

# --- Configurazione -------------------------------------------------------
$SvnUrl    = 'https://plugins.svn.wordpress.org/biscotto-cookie-consent'
$SvnUser   = 'renatosaka'
$ProjectDir = $PSScriptRoot
$DistDir    = Join-Path $ProjectDir 'dist\biscotto-cookie-consent'
$AssetsSrc  = Join-Path $ProjectDir 'wporg-assets'
# Working copy SVN tenuta FUORI dal repo Git:
$WorkDir    = Join-Path (Split-Path $ProjectDir -Parent) 'biscotto-wporg-svn'

Write-Host '============================================================' -ForegroundColor Cyan
Write-Host ' PUBLISH WPORG - Biscotto Cookie Consent' -ForegroundColor Cyan
Write-Host '============================================================' -ForegroundColor Cyan

# --- 0. Controlli ---------------------------------------------------------
# Individua svn: prima nel PATH, poi nei percorsi standard di SlikSVN
# (utile se il terminale e' stato aperto prima dell'installazione).
$Svn = (Get-Command svn -ErrorAction SilentlyContinue).Source
if (-not $Svn) {
    $Svn = @(
        'C:\Program Files\SlikSvn\bin\svn.exe',
        'C:\Program Files (x86)\SlikSvn\bin\svn.exe'
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
}
if (-not $Svn) {
    Write-Host "ERRORE: 'svn' non trovato. Installa SlikSVN, poi riapri il terminale." -ForegroundColor Red
    Write-Host '  https://sliksvn.com/download/' -ForegroundColor Yellow
    exit 1
}
Write-Host "Client SVN: $Svn" -ForegroundColor DarkGray
if (-not (Test-Path $DistDir))   { Write-Host "ERRORE: build mancante: $DistDir" -ForegroundColor Red; exit 1 }
if (-not (Test-Path $AssetsSrc)) { Write-Host "ERRORE: asset mancanti: $AssetsSrc" -ForegroundColor Red; exit 1 }

# Versione letta dallo Stable tag del readme.txt
$readme = Join-Path $DistDir 'readme.txt'
$Version = (Select-String -Path $readme -Pattern '^Stable tag:\s*(.+)$').Matches.Groups[1].Value.Trim()
if (-not $Version) { Write-Host 'ERRORE: Stable tag non trovato in readme.txt' -ForegroundColor Red; exit 1 }
Write-Host "Versione da pubblicare: $Version" -ForegroundColor Green

# --- 1. Checkout / update working copy -----------------------------------
if (Test-Path (Join-Path $WorkDir '.svn')) {
    Write-Host "[1/5] Working copy esistente, aggiorno: $WorkDir"
    & $Svn update --username $SvnUser -- "$WorkDir"
} else {
    Write-Host "[1/5] Checkout repository SVN in: $WorkDir"
    & $Svn checkout --username $SvnUser -- "$SvnUrl" "$WorkDir"
}

$Trunk  = Join-Path $WorkDir 'trunk'
$TagDir = Join-Path $WorkDir "tags\$Version"
$Assets = Join-Path $WorkDir 'assets'
New-Item -ItemType Directory -Force -Path $Trunk, $Assets | Out-Null

# --- 2. Copia build -> trunk ---------------------------------------------
Write-Host '[2/5] Copio la build in trunk/ ...'
# /MIR rende trunk identico a dist (esclude i metadati .svn)
robocopy "$DistDir" "$Trunk" /MIR /XD .svn /NFL /NDL /NJH /NJS /NP | Out-Null

# --- 3. Copia asset -> assets --------------------------------------------
Write-Host '[3/5] Copio gli asset in assets/ ...'
robocopy "$AssetsSrc" "$Assets" /E /XD .svn /NFL /NDL /NJH /NJS /NP | Out-Null

# --- 4. Crea il tag copiando trunk ---------------------------------------
if (Test-Path $TagDir) {
    Write-Host "[4/5] tags/$Version esiste gia', lo salto." -ForegroundColor Yellow
} else {
    Write-Host "[4/5] Creo tags/$Version da trunk ..."
    New-Item -ItemType Directory -Force -Path (Split-Path $TagDir -Parent) | Out-Null
    robocopy "$Trunk" "$TagDir" /E /XD .svn /NFL /NDL /NJH /NJS /NP | Out-Null
}

# --- 5. Sincronizza stato SVN (add nuovi, delete mancanti) ---------------
Write-Host '[5/5] Registro le modifiche in SVN ...'
Push-Location $WorkDir
# Aggiunge tutti i nuovi file/cartelle
& $Svn add --force . --auto-props --parents -q
# Rimuove da SVN i file spariti (status '!')
& $Svn status | Where-Object { $_ -match '^!' } | ForEach-Object {
    $p = ($_ -replace '^!\s+', '').Trim()
    & $Svn delete -- "$p"
}
Write-Host ''
Write-Host '--- Anteprima modifiche (svn status) ---' -ForegroundColor Cyan
& $Svn status
Pop-Location

# --- Conferma commit ------------------------------------------------------
Write-Host ''
Write-Host '============================================================' -ForegroundColor Cyan
Write-Host " Pronto al COMMIT (upload) della versione $Version." -ForegroundColor Green
Write-Host " Verranno caricati: trunk/ + tags/$Version/ + assets/" -ForegroundColor Green
Write-Host '============================================================' -ForegroundColor Cyan
$ans = Read-Host "Procedo con 'svn commit'? Ti verra' chiesta la password SVN. (s/N)"
if ($ans -notmatch '^[sSyY]') {
    Write-Host 'Commit annullato. La working copy resta pronta in:' -ForegroundColor Yellow
    Write-Host "  $WorkDir" -ForegroundColor Yellow
    Write-Host "Puoi committare a mano con:  svn commit -m '...' --username $SvnUser" -ForegroundColor Yellow
    exit 0
}

Push-Location $WorkDir
& $Svn commit -m "Release $Version" --username $SvnUser
$rc = $LASTEXITCODE
Pop-Location

if ($rc -eq 0) {
    Write-Host ''
    Write-Host "Pubblicato! La pagina apparira' entro qualche minuto/ora su:" -ForegroundColor Green
    Write-Host '  https://wordpress.org/plugins/biscotto-cookie-consent' -ForegroundColor Green
} else {
    Write-Host "svn commit ha restituito codice $rc. Controlla il messaggio sopra." -ForegroundColor Red
    exit $rc
}
