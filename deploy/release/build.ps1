[CmdletBinding()]
param(
    [string]$Version,
    [string]$DistDirectory
)

$ErrorActionPreference = 'Stop'
$root = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
if ([string]::IsNullOrWhiteSpace($Version)) {
    $Version = (Get-Content -LiteralPath (Join-Path $root 'VERSION') -Raw).Trim()
}
if ($Version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+([.-][A-Za-z0-9.-]+)?$') {
    throw "VERSION must be semantic; received: $Version"
}
foreach ($tool in @('php', 'composer', 'tar')) {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        throw "$tool is required on PATH to build a release."
    }
}
if ([string]::IsNullOrWhiteSpace($DistDirectory)) {
    $DistDirectory = Join-Path $root 'dist'
}
$dist = [System.IO.Path]::GetFullPath($DistDirectory)
$marker = Join-Path $dist '.lightdocs-dist'

if (Test-Path -LiteralPath $dist) {
    if (-not (Test-Path -LiteralPath $marker -PathType Leaf)) {
        throw "Refusing to replace unmarked distribution directory: $dist"
    }
    Remove-Item -LiteralPath $dist -Recurse -Force
}
New-Item -ItemType Directory -Path $dist -Force | Out-Null
[System.IO.File]::WriteAllText($marker, "Managed Lightdocs release output.`n", [System.Text.UTF8Encoding]::new($false))

$temporaryRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$stage = Join-Path $temporaryRoot ('lightdocs-release-' + [guid]::NewGuid().ToString('N'))
$staticStage = Join-Path $temporaryRoot ('lightdocs-static-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage | Out-Null

function Test-ReleaseExcluded([string]$RelativePath) {
    $relative = $RelativePath.Replace('\', '/').TrimStart('/')
    $first = $relative.Split('/', 2)[0]
    if ($first -in @('.git', '.github', '.claude', '.codex', 'content', 'dist', 'site', 'tests', 'var', 'storage', 'vendor')) { return $true }
    if ($first -like 'build*') { return $true }
    if ($relative -eq '.env' -or $relative -eq 'upload/vendor' -or $relative.StartsWith('upload/vendor/')) { return $true }
    return $false
}

try {
    foreach ($file in Get-ChildItem -LiteralPath $root -Recurse -Force -File) {
        $relative = $file.FullName.Substring($root.Length).TrimStart('\', '/')
        if (Test-ReleaseExcluded $relative) { continue }
        $target = Join-Path $stage $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -LiteralPath $file.FullName -Destination $target
    }

    $content = Join-Path $stage 'content'
    $uploads = Join-Path $stage 'storage\uploads'
    New-Item -ItemType Directory -Path $content,$uploads,(Join-Path $stage 'storage\cache'),(Join-Path $stage 'storage\revisions'),(Join-Path $stage 'storage\exports') -Force | Out-Null
    Copy-Item -Path (Join-Path $root 'resources\starter-site\content\*') -Destination $content -Recurse -Force
    Copy-Item -Path (Join-Path $root 'resources\starter-site\public\uploads\*') -Destination $uploads -Recurse -Force
    [System.IO.File]::WriteAllText((Join-Path $stage 'VERSION'), "$Version`n", [System.Text.UTF8Encoding]::new($false))

    & composer "--working-dir=$stage" install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative
    if ($LASTEXITCODE -ne 0) { throw 'Composer release installation failed.' }
    & php (Join-Path $stage 'bin\docs') doctor
    if ($LASTEXITCODE -ne 0) { throw 'Release doctor failed.' }
    & php (Join-Path $stage 'bin\docs') validate
    if ($LASTEXITCODE -ne 0) { throw 'Release validation failed.' }

    Get-ChildItem -LiteralPath (Join-Path $stage 'storage') -Filter 'lightdocs.sqlite*' -File -ErrorAction SilentlyContinue | Remove-Item -Force
    Get-ChildItem -LiteralPath (Join-Path $stage 'storage\cache') -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force

    $archive = Join-Path $dist 'lightdocs-release.tar.gz'
    & tar -C $stage -czf $archive .
    if ($LASTEXITCODE -ne 0) { throw 'Release archive creation failed.' }
    $versioned = Join-Path $dist "lightdocs-v$Version.tar.gz"
    Copy-Item -LiteralPath $archive -Destination $versioned
    foreach ($asset in @($archive, $versioned)) {
        $hash = (Get-FileHash -LiteralPath $asset -Algorithm SHA256).Hash.ToLowerInvariant()
        $line = "$hash  $([System.IO.Path]::GetFileName($asset))`n"
        [System.IO.File]::WriteAllText("$asset.sha256", $line, [System.Text.UTF8Encoding]::new($false))
    }

    & php (Join-Path $stage 'bin\docs') build $staticStage --profile=public
    if ($LASTEXITCODE -ne 0) { throw 'Static public release build failed.' }
    $staticArchive = Join-Path $dist 'lightdocs-static-public.zip'
    & tar -a -C $staticStage -cf $staticArchive .
    if ($LASTEXITCODE -ne 0) { throw 'Static ZIP creation failed.' }
    $staticVersioned = Join-Path $dist "lightdocs-static-public-v$Version.zip"
    Copy-Item -LiteralPath $staticArchive -Destination $staticVersioned
    foreach ($asset in @($staticArchive, $staticVersioned)) {
        $hash = (Get-FileHash -LiteralPath $asset -Algorithm SHA256).Hash.ToLowerInvariant()
        $line = "$hash  $([System.IO.Path]::GetFileName($asset))`n"
        [System.IO.File]::WriteAllText("$asset.sha256", $line, [System.Text.UTF8Encoding]::new($false))
    }
    Write-Output "Built Lightdocs $Version release assets in $dist"
}
finally {
    $resolvedStage = [System.IO.Path]::GetFullPath($stage)
    if ($resolvedStage.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedStage)) {
        Remove-Item -LiteralPath $resolvedStage -Recurse -Force
    }
    $resolvedStatic = [System.IO.Path]::GetFullPath($staticStage)
    if ($resolvedStatic.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $resolvedStatic)) {
        Remove-Item -LiteralPath $resolvedStatic -Recurse -Force
    }
}
