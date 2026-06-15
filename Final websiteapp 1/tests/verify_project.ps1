param(
  [string]$BaseUrl = "http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201",
  [string]$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path,
  [string]$PhpExe = "C:\xampp\php\php.exe",
  [string]$MysqlStart = "C:\xampp\mysql_start.bat"
)

$ErrorActionPreference = "Stop"

function Write-Step {
  param([string]$Message)
  Write-Host ""
  Write-Host "== $Message =="
}

function Assert-True {
  param(
    [bool]$Condition,
    [string]$Message
  )

  if (-not $Condition) {
    throw $Message
  }
}

function Test-Port {
  param([int]$Port)
  $client = New-Object Net.Sockets.TcpClient
  try {
    $async = $client.BeginConnect("127.0.0.1", $Port, $null, $null)
    $connected = $async.AsyncWaitHandle.WaitOne(1500, $false)
    if ($connected) {
      $client.EndConnect($async)
    }
    return $connected
  } catch {
    return $false
  } finally {
    $client.Close()
  }
}

function Ensure-MySql {
  Write-Step "Checking MySQL on 127.0.0.1:3306"
  if (Test-Port 3306) {
    Write-Host "OK MySQL is already listening."
    return
  }

  Assert-True (Test-Path $MysqlStart) "MySQL is not running and $MysqlStart was not found."
  Start-Process -FilePath $MysqlStart -WorkingDirectory (Split-Path $MysqlStart) -WindowStyle Hidden
  Start-Sleep -Seconds 5
  Assert-True (Test-Port 3306) "MySQL did not start on 127.0.0.1:3306."
  Write-Host "OK MySQL started."
}

function Invoke-Install {
  Write-Step "Importing/resetting database"
  $response = Invoke-WebRequest -UseBasicParsing -Method Post -Uri "$BaseUrl/install.php" -Body @{} -TimeoutSec 120
  Assert-True ($response.StatusCode -eq 200) "install.php did not return HTTP 200."
  Assert-True ($response.Content -like "*Database cafe_connect_crm has been reset*") "Database import did not report success."
  Write-Host "OK database reset."
}

function Invoke-CafeApi {
  param(
    [string]$Endpoint,
    [hashtable]$Body = @{}
  )

  $json = $Body | ConvertTo-Json -Depth 10
  $response = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api.php?endpoint=$Endpoint" -ContentType "application/json; charset=utf-8" -Body $json -TimeoutSec 20
  Assert-True ([bool]$response.ok) "$Endpoint failed: $($response.message)"
  Write-Host "OK API $Endpoint"
  return $response.data
}

Write-Step "PHP lint"
Assert-True (Test-Path $PhpExe) "PHP executable not found: $PhpExe"
$phpFiles = Get-ChildItem -Path $ProjectRoot -Recurse -Filter *.php
foreach ($file in $phpFiles) {
  & $PhpExe -l $file.FullName | Out-Null
  Assert-True ($LASTEXITCODE -eq 0) "PHP lint failed: $($file.FullName)"
}
Write-Host "OK PHP lint files=$($phpFiles.Count)"

Write-Step "JavaScript syntax"
node --check (Join-Path $ProjectRoot "assets\js\app.js")
Assert-True ($LASTEXITCODE -eq 0) "node --check failed."
Write-Host "OK node --check assets/js/app.js"

Ensure-MySql
Invoke-Install

Write-Step "API readiness"
Invoke-CafeApi "member-session" @{} | Out-Null

Write-Step "Route checks"
$routes = @(
  "/",
  "/menu",
  "/product?id=1",
  "/login",
  "/register",
  "/forgot-password",
  "/account",
  "/checkout",
  "/order?invoice_id=1",
  "/member",
  "/pos/login",
  "/pos/checkout",
  "/pos/orders",
  "/pos/kitchen",
  "/pos/reports"
)

foreach ($route in $routes) {
  $response = Invoke-WebRequest -UseBasicParsing -Uri "$BaseUrl$route" -TimeoutSec 20
  Assert-True ($response.StatusCode -eq 200) "Route $route returned HTTP $($response.StatusCode)."
  Write-Host "OK route $route"
}

Write-Step "Smoke API workflow"
& powershell -ExecutionPolicy Bypass -File (Join-Path $ProjectRoot "tests\smoke_api.ps1") -BaseUrl $BaseUrl
Assert-True ($LASTEXITCODE -eq 0) "Smoke API workflow failed."

Invoke-Install

Write-Step "Verification complete"
Write-Host "Cafe Connect project verification passed. Database was reset to clean sample data after smoke test."
