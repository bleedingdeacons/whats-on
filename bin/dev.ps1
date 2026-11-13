<#
.SYNOPSIS
    Dev environment for What's On, on a Local (localwp.com) site running Divi 5 and The Events Calendar.

.DESCRIPTION
    Works against the Local site named by -Site (default: aa-bristol-future). The site must be
    started in Local. Everything Local-specific — the site's PHP binary, php.ini, MySQL port and
    bundled WP-CLI / Composer — is read from Local's own config, so nothing is hard-coded.

      setup     link + build + activate + seed  (one command from a fresh checkout)
      link      junction <site>\wp-content\plugins\whats-on -> this checkout
      unlink    remove that junction (rmdir, never a recursive delete through it)
      activate  wp plugin activate whats-on
      seed      sample events (+ images, categories) and a "What's On" demo page
      reset     remove the sample data only
      wp        run any WP-CLI command, e.g.  .\bin\dev.ps1 wp post list --post_type=tribe_events
      composer  run Local's bundled Composer in this checkout, e.g.  .\bin\dev.ps1 composer install
      info      print the resolved paths and URLs

.EXAMPLE
    .\bin\dev.ps1 setup
.EXAMPLE
    .\bin\dev.ps1 -Site test wp plugin list
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'link', 'unlink', 'activate', 'seed', 'reset', 'wp', 'composer', 'info')]
    [string] $Command = 'info',

    [string] $Site = $(if ($env:WHATS_ON_SITE) { $env:WHATS_ON_SITE } else { 'aa-bristol-future' }),

    [Parameter(Position = 1, ValueFromRemainingArguments = $true)]
    [string[]] $Rest = @()
)

$ErrorActionPreference = 'Stop'
$PluginSlug = 'whats-on'
$Root = Split-Path -Parent $PSScriptRoot

function Get-LocalSite {
    $sitesJson = Join-Path $env:APPDATA 'Local\sites.json'
    if (-not (Test-Path $sitesJson)) { throw "Local is not installed (no $sitesJson)." }

    $sites = Get-Content $sitesJson -Raw | ConvertFrom-Json
    $entry = $sites.PSObject.Properties | Where-Object { $_.Value.name -eq $Site } | Select-Object -First 1
    if (-not $entry) {
        $names = ($sites.PSObject.Properties.Value | ForEach-Object name) -join ', '
        throw "No Local site named '$Site'. Known sites: $names"
    }

    $id = $entry.Name
    $s = $entry.Value
    $sitePath = $s.path -replace '^~', $env:USERPROFILE
    $phpVersion = $s.services.php.version
    $phpDir = Get-ChildItem (Join-Path $env:APPDATA 'Local\lightning-services') -Directory |
        Where-Object Name -like "php-$phpVersion+*" | Select-Object -First 1
    if (-not $phpDir) { throw "Local's PHP $phpVersion runtime was not found." }

    $localApp = @(
        (Join-Path $env:LOCALAPPDATA 'Programs\Local'),
        'C:\Program Files\Local',
        'C:\Program Files (x86)\Local'
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $localApp) { throw 'Could not find the Local application folder.' }
    $bin = Join-Path $localApp 'resources\extraResources\bin'

    $statusFile = Join-Path $env:APPDATA 'Local\site-statuses.json'
    $status = if (Test-Path $statusFile) { (Get-Content $statusFile -Raw | ConvertFrom-Json).$id } else { 'unknown' }

    [pscustomobject]@{
        Id        = $id
        Name      = $s.name
        Domain    = $s.domain
        Status    = $status
        Public    = Join-Path $sitePath 'app\public'
        Php       = Join-Path $phpDir.FullName 'bin\win64\php.exe'
        PhpIni    = Join-Path $env:APPDATA "Local\run\$id\conf\php\php.ini"
        MysqlPort = $s.services.mysql.ports.MYSQL[0]
        WpCli     = Join-Path $bin 'wp-cli\wp-cli.phar'
        Composer  = Join-Path $bin 'composer\composer.phar'
    }
}

function Invoke-Wp {
    param([string[]] $WpArgs)
    $lsite = Get-LocalSite
    if ($lsite.Status -ne 'running') { throw "Start '$($lsite.Name)' in Local first (status: $($lsite.Status))." }

    # wp-config.php uses DB_HOST=localhost; point mysqli at the site's MySQL port.
    $phpArgs = @('-c', $lsite.PhpIni, '-d', "mysqli.default_port=$($lsite.MysqlPort)", '-d', 'display_startup_errors=0', $lsite.WpCli, "--path=$($lsite.Public)") + $WpArgs
    & $lsite.Php @phpArgs 2>&1 | Where-Object { "$_" -notmatch 'php_imagick' }
    if ($LASTEXITCODE -ne 0) { throw "wp $($WpArgs -join ' ') failed ($LASTEXITCODE)." }
}

function Invoke-Link {
    $lsite = Get-LocalSite
    $target = Join-Path $lsite.Public "wp-content\plugins\$PluginSlug"
    if (Test-Path $target) {
        $item = Get-Item $target -Force
        if ($item.LinkType -eq 'Junction' -and $item.Target -contains $Root) {
            Write-Host "Already linked: $target -> $Root"
            return
        }
        throw "$target already exists and is not a junction to this checkout. Remove it yourself first."
    }
    New-Item -ItemType Junction -Path $target -Target $Root | Out-Null
    Write-Host "Linked $target -> $Root"
}

function Invoke-Unlink {
    $lsite = Get-LocalSite
    $target = Join-Path $lsite.Public "wp-content\plugins\$PluginSlug"
    if (-not (Test-Path $target)) { Write-Host 'Not linked.'; return }
    if ((Get-Item $target -Force).LinkType -ne 'Junction') { throw "$target is not a junction; refusing to touch it." }
    cmd /c rmdir "$target" | Out-Null
    Write-Host "Removed junction $target"
}

function Invoke-Build {
    Push-Location $Root
    try {
        if (-not (Test-Path 'node_modules')) { npm install --no-audit --no-fund }
        npm run build:dev
        if ($LASTEXITCODE -ne 0) { throw 'Asset build failed.' }
    } finally { Pop-Location }
}

switch ($Command) {
    'info' {
        $lsite = Get-LocalSite
        $lsite | Format-List
        Write-Host "Front end : http://$($lsite.Domain)/whats-on-demo/"
        Write-Host "Builder   : http://$($lsite.Domain)/whats-on-demo/?et_fb=1"
        Write-Host "Admin     : http://$($lsite.Domain)/wp-admin/"
    }
    'link'     { Invoke-Link }
    'unlink'   { Invoke-Unlink }
    'activate' { Invoke-Wp @('plugin', 'activate', $PluginSlug) }
    'seed'     { Invoke-Wp @('eval-file', (Join-Path $Root 'bin\seed.php')) }
    'reset'    { Invoke-Wp @('eval-file', (Join-Path $Root 'bin\seed.php'), 'reset') }
    'wp'       { Invoke-Wp $Rest }
    'composer' {
        $lsite = Get-LocalSite
        Push-Location $Root
        # PHPRC so the PHP processes Composer starts itself (@php scripts, build.php's
        # nested composer install) load the same php.ini, and with it ext-zip.
        $previousPhprc = $env:PHPRC
        $env:PHPRC = $lsite.PhpIni
        try { & $lsite.Php -c $lsite.PhpIni -d display_startup_errors=0 $lsite.Composer @Rest }
        finally { $env:PHPRC = $previousPhprc; Pop-Location }
    }
    'setup' {
        Invoke-Build
        Invoke-Link
        Invoke-Wp @('plugin', 'activate', $PluginSlug)
        Invoke-Wp @('eval-file', (Join-Path $Root 'bin\seed.php'))
        $lsite = Get-LocalSite
        Write-Host ''
        Write-Host "Ready: http://$($lsite.Domain)/whats-on-demo/   (builder: add ?et_fb=1)"
    }
}
