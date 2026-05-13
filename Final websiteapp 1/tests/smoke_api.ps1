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

$suffix = [DateTimeOffset]::Now.ToUnixTimeSeconds()
$phone = "098$suffix".Substring(0, 10)

$member = Invoke-CafeApi "member-register" @{
  customer_name = "Smoke Test Member"
  phone_number = $phone
  email = "smoke$suffix@example.test"
}

Invoke-CafeApi "member-lookup" @{ identity = $phone } | Out-Null

Invoke-CafeApi "checkout" @{
  staff_id = 2
  branch_id = 1
  customer_id = $member.member.id
  payment_method = "cash"
  sales_channel = "pos"
  items = @(
    @{ product_id = 1; quantity = 1; size = "M" }
  )
} | Out-Null

$orderResult = Invoke-CafeApi "create-order" @{
  staff_id = 1
  waiter_id = 1
  branch_id = 1
  table_id = 3
  note = "Smoke test order"
  items = @(
    @{ product_id = 2; quantity = 1; size = "M" }
  )
}

$createdOrder = $orderResult.orders | Where-Object { $_.id -eq $orderResult.order_id } | Select-Object -First 1
$firstItem = $createdOrder.items | Select-Object -First 1

Invoke-CafeApi "update-order-item" @{
  staff_id = 3
  item_id = $firstItem.id
  status = "ready"
} | Out-Null

Invoke-CafeApi "dashboard" @{} | Out-Null

Write-Host "Smoke API test completed. Reset install.php afterward if you want clean sample data."
