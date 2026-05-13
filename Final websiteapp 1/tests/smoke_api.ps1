param(
  [string]$BaseUrl = "http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201"
)

$ErrorActionPreference = "Stop"

function Invoke-CafeApi {
  param(
    [string]$Endpoint,
    [hashtable]$Body = @{}
  )

  $json = $Body | ConvertTo-Json -Depth 10
  $response = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api.php?endpoint=$Endpoint" -ContentType "application/json; charset=utf-8" -Body $json -TimeoutSec 10
  if (-not $response.ok) {
    throw "$Endpoint failed: $($response.message)"
  }

  Write-Host "OK $Endpoint"
  return $response.data
}

function New-PosSession {
  param(
    [int]$StaffId,
    [int]$OpeningCash = 0
  )

  $session = Invoke-CafeApi "pos-session-login" @{
    staff_id = $StaffId
    opening_cash_amount = $OpeningCash
  }

  return $session.staff
}

function Add-Session {
  param(
    [hashtable]$Body,
    $Staff
  )

  $Body.staff_id = $Staff.id
  $Body.pos_session_id = $Staff.pos_session_id
  $Body.session_token = $Staff.session_token
  $Body.staff_role = $Staff.staff_role
  return $Body
}

$suffix = [DateTimeOffset]::Now.ToUnixTimeSeconds()
$phone = "098$suffix".Substring(0, 10)

$member = Invoke-CafeApi "member-register" @{
  customer_name = "Smoke Test Member"
  phone_number = $phone
  email = "smoke$suffix@example.test"
}

Invoke-CafeApi "member-lookup" @{ identity = $phone } | Out-Null

$cashier = New-PosSession -StaffId 2 -OpeningCash 1000000
$waiter = New-PosSession -StaffId 1
$barista = New-PosSession -StaffId 3
$manager = New-PosSession -StaffId 7

$checkoutBody = Add-Session @{
  staff_id = 2
  branch_id = 1
  customer_id = $member.member.id
  payment_method = "cash"
  sales_channel = "pos"
  bill_started_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
  items = @(
    @{ product_id = 1; quantity = 1; size = "M" }
  )
} $cashier
Invoke-CafeApi "checkout" $checkoutBody | Out-Null

$orderBody = Add-Session @{
  staff_id = 1
  waiter_id = 1
  branch_id = 1
  table_id = 3
  note = "Smoke test order"
  items = @(
    @{ product_id = 2; quantity = 1; size = "M" }
  )
} $waiter
$orderResult = Invoke-CafeApi "create-order" $orderBody

$createdOrder = $orderResult.orders | Where-Object { $_.id -eq $orderResult.order_id } | Select-Object -First 1
$firstItem = $createdOrder.items | Select-Object -First 1

$kitchenBody = Add-Session @{
  staff_id = 3
  item_id = $firstItem.id
  status = "ready"
} $barista
Invoke-CafeApi "update-order-item" $kitchenBody | Out-Null

Invoke-CafeApi "dashboard" @{} | Out-Null

$reportBody = Add-Session @{} $manager
Invoke-CafeApi "pos-session-report" $reportBody | Out-Null

Invoke-CafeApi "pos-session-logout" (Add-Session @{} $cashier) | Out-Null
Invoke-CafeApi "pos-session-logout" (Add-Session @{} $waiter) | Out-Null
Invoke-CafeApi "pos-session-logout" (Add-Session @{} $barista) | Out-Null
Invoke-CafeApi "pos-session-logout" (Add-Session @{} $manager) | Out-Null

Write-Host "Smoke API test completed. Reset install.php afterward if you want clean sample data."
