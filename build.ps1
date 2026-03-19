$ErrorActionPreference = "Stop"
$root = $PSScriptRoot
Set-Location $root

# Package Info aus package.xml lesen
[xml]$pkg = Get-Content "$root\package.xml"
$packageName = $pkg.package.name
$version = $pkg.package.packageinformation.version
$outputFile = "${packageName}_${version}.tar"

Write-Host "Building $packageName v${version} ..." -ForegroundColor Cyan

# Cleanup intermediate artifacts
Remove-Item -Force -ErrorAction SilentlyContinue files.tar, acptemplates.tar, templates.tar

# files.tar
if (Test-Path "$root\files") {
    Write-Host "  Creating files.tar..."
    Set-Location "$root\files"
    tar cf ..\files.tar *
    Set-Location $root
}

# acptemplates.tar
if (Test-Path "$root\acptemplates") {
    Write-Host "  Creating acptemplates.tar..."
    Set-Location "$root\acptemplates"
    tar cf ..\acptemplates.tar *
    Set-Location $root
}

# templates.tar
if (Test-Path "$root\templates") {
    Write-Host "  Creating templates.tar..."
    Set-Location "$root\templates"
    tar cf ..\templates.tar *
    Set-Location $root
}

# Package-Inhalte sammeln
$items = @("package.xml")

# Inner Tar Archives
if (Test-Path "$root\files.tar")        { $items += "files.tar" }
if (Test-Path "$root\acptemplates.tar") { $items += "acptemplates.tar" }
if (Test-Path "$root\templates.tar")    { $items += "templates.tar" }

# XML Config Files
$xmlFiles = @(
    "option.xml", "userGroupOption.xml", "acpMenu.xml",
    "page.xml", "menuItem.xml", "eventListener.xml",
    "objectType.xml", "cronjob.xml", "box.xml",
    "templateListener.xml", "aclOption.xml"
)
foreach ($xml in $xmlFiles) {
    if (Test-Path "$root\$xml") {
        $items += $xml
        Write-Host "  Including $xml"
    }
}

# Language
if (Test-Path "$root\language") {
    $langFiles = Get-ChildItem "$root\language\*.xml" -ErrorAction SilentlyContinue
    foreach ($lang in $langFiles) {
        $items += "language/$($lang.Name)"
        Write-Host "  Including language/$($lang.Name)"
    }
}

# Final Package bauen
Write-Host "  Creating $outputFile ..." -ForegroundColor Yellow
tar cf $outputFile @items

# Cleanup
Remove-Item -Force -ErrorAction SilentlyContinue files.tar, acptemplates.tar, templates.tar

$fileSize = [math]::Round((Get-Item $outputFile).Length / 1KB, 1)
Write-Host "Done: $outputFile (${fileSize} KB)" -ForegroundColor Green
Write-Host "Upload via ACP > Packages > Install Package"
