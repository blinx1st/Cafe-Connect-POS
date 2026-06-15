param(
  [string]$Database = "cafe_connect_crm",
  [string]$HostName = "127.0.0.1",
  [int]$Port = 3306,
  [string]$User = "root",
  [string]$Password = "",
  [string]$MysqlDump = "C:\xampp\mysql\bin\mysqldump.exe",
  [string]$OutputDir = (Join-Path $PSScriptRoot "..\storage\backups")
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $MysqlDump)) {
  throw "mysqldump was not found: $MysqlDump"
}

if (-not (Test-Path $OutputDir)) {
  New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputFile = Join-Path $OutputDir "$Database-$timestamp.sql"
$args = @(
  "--host=$HostName",
  "--port=$Port",
  "--user=$User",
  "--single-transaction",
  "--routines",
  "--triggers",
  "--default-character-set=utf8mb4",
  $Database
)

if ($Password -ne "") {
  $args = @("--password=$Password") + $args
}

& $MysqlDump @args | Out-File -FilePath $outputFile -Encoding utf8

if ($LASTEXITCODE -ne 0) {
  throw "mysqldump failed with exit code $LASTEXITCODE"
}

Write-Host "Backup created: $outputFile"
