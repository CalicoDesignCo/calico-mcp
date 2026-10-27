$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$nodePath=Join-Path $env:USERPROFILE '.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
& $nodePath (Join-Path $PSScriptRoot 'build.mjs')
if($LASTEXITCODE -ne 0){throw 'Classmap build failed'}
$sourceRoot=Join-Path $projectRoot 'source\calico-mcp'
foreach($requiredAsset in @('views\public\css\consent.css')) {
    $assetPath=Join-Path $sourceRoot $requiredAsset
    if(-not (Test-Path -LiteralPath $assetPath) -or (Get-Item -LiteralPath $assetPath).Length -eq 0){throw "Required authorization asset is missing: $requiredAsset"}
}
$main=Get-Content -LiteralPath (Join-Path $sourceRoot 'calico-mcp.php') -Raw
$version=[regex]::Match($main,'Version:\s*([^\r\n]+)').Groups[1].Value.Trim()
$distRoot=Join-Path $projectRoot ('dist\'+$version)
New-Item -ItemType Directory -Path $distRoot -Force | Out-Null
$zipPath=Join-Path $distRoot ('calico-mcp-'+$version+'.zip')
if(Test-Path -LiteralPath $zipPath){throw "Package already exists; preserve it and increment the beta version before rebuilding: $zipPath"}
Compress-Archive -LiteralPath $sourceRoot -DestinationPath $zipPath
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive=[IO.Compression.ZipFile]::OpenRead($zipPath)
$checked=0
try {
    foreach($entry in $archive.Entries){
        if($entry.FullName.EndsWith('/')){continue}
        $relative=$entry.FullName.Replace('\','/');if(-not $relative.StartsWith('calico-mcp/')){throw 'Unexpected ZIP root'}
        $local=Join-Path $sourceRoot $relative.Substring(11)
        $reader=$entry.Open();$sha=[Security.Cryptography.SHA256]::Create()
        try{$entryHash=[Convert]::ToHexString($sha.ComputeHash($reader))}finally{$reader.Dispose();$sha.Dispose()}
        if($entryHash -ne (Get-FileHash -LiteralPath $local -Algorithm SHA256).Hash){throw "ZIP/source mismatch: $relative"}
        $checked++
    }
}finally{$archive.Dispose()}
$expected=(Get-ChildItem -LiteralPath $sourceRoot -Recurse -File).Count
if($checked -ne $expected){throw "ZIP file count mismatch: $checked of $expected source files"}
$hash=Get-FileHash -LiteralPath $zipPath -Algorithm SHA256
[pscustomobject]@{Version=$version;Package=$zipPath;SHA256=$hash.Hash;Files=$checked} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $distRoot 'package.json') -Encoding utf8
Get-Content -LiteralPath (Join-Path $distRoot 'package.json')
