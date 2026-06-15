param(
  [string]$BaseUrl = "http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201"
)

$ErrorActionPreference = "Stop"
$script:CafeSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$script:CsrfToken = $null

function Set-CsrfToken {
  $response = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api.php?endpoint=csrf-refresh" -ContentType "application/json; charset=utf-8" -Body "{}" -WebSession $script:CafeSession -TimeoutSec 10
  if (-not $response.ok) {
    throw "csrf-refresh failed: $($response.message)"
  }
  $script:CsrfToken = $response.data.csrf_token
}

function Invoke-CafeRaw {
  param(
    [string]$Endpoint,
    [hashtable]$Body = @{},
    [switch]$WithoutCsrf
  )

  $json = $Body | ConvertTo-Json -Depth 10
  $headers = @{}
  if ($script:CsrfToken -and -not $WithoutCsrf) {
    $headers["X-CSRF-Token"] = $script:CsrfToken
  }

  return Invoke-RestMethod -Method Post -Uri "$BaseUrl/api.php?endpoint=$Endpoint" -ContentType "application/json; charset=utf-8" -Body $json -WebSession $script:CafeSession -Headers $headers -TimeoutSec 10
}

function Invoke-CafeApi {
  param(
    [string]$Endpoint,
    [hashtable]$Body = @{}
  )

  $response = Invoke-CafeRaw $Endpoint $Body
  if (-not $response.ok) {
    throw "$Endpoint failed: $($response.message)"
  }

  Write-Host "OK $Endpoint"
  return $response.data
}

function Expect-CafeApiFailure {
  param(
    [string]$Endpoint,
    [hashtable]$Body = @{},
    [string]$MessageLike = ""
  )

  $response = Invoke-CafeRaw $Endpoint $Body
  if ($response.ok) {
    throw "$Endpoint should have failed but returned ok."
  }
  if ($MessageLike -and $response.message -notlike "*$MessageLike*") {
    throw "$Endpoint failed with unexpected message: $($response.message)"
  }

  Write-Host "OK blocked $Endpoint"
}

function New-PosSession {
  param(
    [string]$Identity,
    [string]$Password,
    [string]$Pin,
    [int]$OpeningCash = 0
  )

  $auth = Invoke-CafeApi "pos-auth-login" @{
    identity = $Identity
    password = $Password
  }

  $session = Invoke-CafeApi "pos-session-login" @{
    staff_id = $auth.staff.id
    auth_session_id = $auth.auth_session.id
    auth_token = $auth.auth_session.auth_token
    pin = $Pin
    opening_cash_amount = $OpeningCash
  }

  $staff = $session.staff
  $staff | Add-Member -NotePropertyName auth_session_id -NotePropertyValue $auth.auth_session.id -Force
  $staff | Add-Member -NotePropertyName auth_token -NotePropertyValue $auth.auth_session.auth_token -Force
  return $staff
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

function Add-Auth {
  param(
    [hashtable]$Body,
    $Staff
  )

  $Body.staff_id = $Staff.id
  $Body.auth_session_id = $Staff.auth_session_id
  $Body.auth_token = $Staff.auth_token
  return $Body
}

function Invoke-CafeImageUpload {
  param(
    [hashtable]$Body,
    $Staff
  )

  $uploadBody = Add-Session $Body $Staff
  $boundary = "----CafeConnectSmoke$([guid]::NewGuid().ToString("N"))"
  $stream = New-Object System.IO.MemoryStream
  $encoding = [System.Text.Encoding]::ASCII

  function Write-PartText {
    param([string]$Text)
    $bytes = $encoding.GetBytes($Text)
    $stream.Write($bytes, 0, $bytes.Length)
  }

  foreach ($key in $uploadBody.Keys) {
    Write-PartText "--$boundary`r`n"
    Write-PartText "Content-Disposition: form-data; name=`"$key`"`r`n`r`n"
    Write-PartText "$($uploadBody[$key])`r`n"
  }

  $pngBytes = [Convert]::FromBase64String("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=")
  Write-PartText "--$boundary`r`n"
  Write-PartText "Content-Disposition: form-data; name=`"image`"; filename=`"smoke-product.png`"`r`n"
  Write-PartText "Content-Type: image/png`r`n`r`n"
  $stream.Write($pngBytes, 0, $pngBytes.Length)
  Write-PartText "`r`n--$boundary--`r`n"
  $bodyBytes = $stream.ToArray()
  $stream.Dispose()

  $headers = @{}
  if ($script:CsrfToken) {
    $headers["X-CSRF-Token"] = $script:CsrfToken
  }

  $json = Invoke-RestMethod -Method Post -Uri "$BaseUrl/api.php?endpoint=product-image-upload" -ContentType "multipart/form-data; boundary=$boundary" -Body $bodyBytes -WebSession $script:CafeSession -Headers $headers -TimeoutSec 10
  if (-not $json.ok) {
    throw "product-image-upload failed: $($json.message)"
  }

  Write-Host "OK product-image-upload"
  return $json.data
}

Set-CsrfToken

$blocked = Invoke-CafeRaw "member-register" @{} -WithoutCsrf
if ($blocked.ok -or $blocked.message -notlike "*CSRF*") {
  throw "CSRF negative test failed."
}
Write-Host "OK csrf write protection"

Expect-CafeApiFailure "dashboard" @{} "staff_id"

$suffix = Get-Random -Minimum 1000000 -Maximum 9999999
$phone = "098$suffix"

$member = Invoke-CafeApi "member-register" @{
  customer_name = "Smoke Test Member"
  phone_number = $phone
  email = "smoke$suffix@example.test"
  password = "123456"
  password_confirm = "123456"
}

Invoke-CafeApi "member-lookup" @{ identity = $phone } | Out-Null

$claimed = Invoke-CafeApi "voucher-claim" @{
  promotion_id = 3
}

$websitePaidOrder = Invoke-CafeApi "checkout" @{
  customer_id = $member.member.id
  voucher_id = $claimed.voucher_id
  payment_method = "e_wallet"
  sales_channel = "website"
  fulfillment_type = "pickup"
  customer_note = "Smoke website checkout"
  items = @(
    @{ product_id = 4; quantity = 1; size = "M" }
  )
}

Invoke-CafeApi "website-orders" @{} | Out-Null
Invoke-CafeApi "website-order-detail" @{ invoice_id = $websitePaidOrder.invoice_id } | Out-Null
Invoke-CafeApi "payment-demo-confirm" @{ invoice_id = $websitePaidOrder.invoice_id } | Out-Null

$websiteCodOrder = Invoke-CafeApi "checkout" @{
  customer_id = $member.member.id
  payment_method = "cash"
  sales_channel = "website"
  fulfillment_type = "delivery"
  delivery_address = "Smoke delivery address"
  customer_note = "Smoke COD pending"
  items = @(
    @{ product_id = 5; quantity = 1; size = "M" }
  )
}
if ($websiteCodOrder.status -ne "pending" -or $websiteCodOrder.order_status -ne "pending") {
  throw "Website COD checkout should create pending invoice/order."
}
Invoke-CafeApi "website-order-cancel" @{
  invoice_id = $websiteCodOrder.invoice_id
  reason = "Smoke customer cancel pending COD"
} | Out-Null

$cashier = New-PosSession -Identity "CASH001" -Password "cashier123" -Pin "2222" -OpeningCash 1000000
$waiter = New-PosSession -Identity "WAIT001" -Password "waiter123" -Pin "1111"
$barista = New-PosSession -Identity "BAR001" -Password "barista123" -Pin "3333"
$marketing = New-PosSession -Identity "MKT001" -Password "marketing123" -Pin "5555"
$manager = New-PosSession -Identity "MGR001" -Password "manager123" -Pin "7777"

$checkoutBody = Add-Session @{
  branch_id = 1
  customer_id = $member.member.id
  payment_method = "cash"
  sales_channel = "pos"
  bill_started_at = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
  items = @(
    @{ product_id = 1; quantity = 1; size = "M" }
  )
} $cashier
$posCheckout = Invoke-CafeApi "checkout" $checkoutBody

Expect-CafeApiFailure "checkout" (Add-Session @{
  branch_id = 1
  payment_method = "cash"
  sales_channel = "pos"
  items = @(
    @{ product_id = 1; quantity = 1; size = "M" }
  )
} $barista) "kh"

Invoke-CafeApi "receipt" (Add-Session @{ invoice_id = $posCheckout.invoice_id } $cashier) | Out-Null
Invoke-CafeApi "receipt-print-log" (Add-Session @{
  invoice_id = $posCheckout.invoice_id
  receipt_type = "html"
  note = "Smoke receipt print"
} $cashier) | Out-Null

Expect-CafeApiFailure "create-order" (Add-Session @{
  waiter_id = $cashier.id
  branch_id = 1
  table_id = 3
  note = "Cashier should be blocked"
  items = @(
    @{ product_id = 2; quantity = 1; size = "M" }
  )
} $cashier) "kh"

$orderResult = Invoke-CafeApi "create-order" (Add-Session @{
  waiter_id = $waiter.id
  branch_id = 1
  table_id = 3
  note = "Smoke role order"
  items = @(
    @{ product_id = 2; quantity = 1; size = "M" }
  )
} $waiter)

$createdOrder = $orderResult.orders | Where-Object { $_.id -eq $orderResult.order_id } | Select-Object -First 1
$firstItem = $createdOrder.items | Select-Object -First 1

Expect-CafeApiFailure "update-order-item" (Add-Session @{
  item_id = $firstItem.id
  status = "ready"
} $waiter) "Role"

Invoke-CafeApi "update-order-item" (Add-Session @{
  item_id = $firstItem.id
  status = "ready"
} $barista) | Out-Null

Invoke-CafeApi "update-order-item" (Add-Session @{
  item_id = $firstItem.id
  status = "served"
} $waiter) | Out-Null

$voidOrder = Invoke-CafeApi "create-order" (Add-Session @{
  waiter_id = $waiter.id
  branch_id = 1
  table_id = 4
  note = "Smoke void order"
  items = @(
    @{ product_id = 3; quantity = 1; size = "M" }
  )
} $waiter)
$voidItem = ($voidOrder.orders | Where-Object { $_.id -eq $voidOrder.order_id } | Select-Object -First 1).items | Select-Object -First 1
Invoke-CafeApi "void-order-item" (Add-Session @{
  item_id = $voidItem.id
  reason = "Smoke waiter void before ready"
} $waiter) | Out-Null

$cancelOrder = Invoke-CafeApi "create-order" (Add-Session @{
  waiter_id = $waiter.id
  branch_id = 1
  table_id = 5
  note = "Smoke cancel order"
  items = @(
    @{ product_id = 4; quantity = 1; size = "M" }
  )
} $waiter)
Invoke-CafeApi "cancel-order" (Add-Session @{
  order_id = $cancelOrder.order_id
  reason = "Smoke manager cancel"
} $manager) | Out-Null

Invoke-CafeApi "create-campaign" (Add-Session @{
  promotion_name = "Smoke Campaign $suffix"
  target_segment = "all"
  discount_type = "percentage"
  discount_value = 5
  voucher_quantity = 1
  start_date = (Get-Date).ToString("yyyy-MM-dd")
  end_date = (Get-Date).AddDays(7).ToString("yyyy-MM-dd")
} $marketing) | Out-Null

Expect-CafeApiFailure "inventory" (Add-Session @{} $marketing) "kh"

$categoryCode = "smoke_$suffix"
Invoke-CafeApi "category-save" (Add-Session @{
  category_code = $categoryCode
  category_name = "Smoke Category $suffix"
  display_order = 99
  status = "active"
} $manager) | Out-Null

$productResult = Invoke-CafeApi "product-save" (Add-Session @{
  product_name = "Smoke Product $suffix"
  category = $categoryCode
  price = 39000
  take_note = "Smoke CRUD product"
  status = "active"
  branch_id = 1
  stock_quantity = 25
  min_stock_level = 5
} $manager)
$productId = $productResult.id
if (-not ($productResult.admin_products | Where-Object { $_.id -eq $productId })) {
  throw "Created product was not returned in admin_products."
}

Invoke-CafeImageUpload @{
  product_id = $productId
  alt_text = "Smoke Product $suffix"
  is_primary = 1
  branch_id = 1
} $manager | Out-Null

Invoke-CafeApi "product-save" (Add-Session @{
  id = $productId
  product_name = "Smoke Product $suffix Updated"
  category = $categoryCode
  price = 42000
  take_note = "Smoke CRUD product updated"
  status = "active"
  branch_id = 1
  stock_quantity = 20
  min_stock_level = 4
} $manager) | Out-Null

Expect-CafeApiFailure "product-save" (Add-Session @{
  product_name = "Cashier Blocked Product $suffix"
  category = $categoryCode
  price = 1000
  status = "active"
  branch_id = 1
} $cashier) "kh"

Invoke-CafeApi "product-delete" (Add-Session @{
  id = $productId
  branch_id = 1
} $manager) | Out-Null

Invoke-CafeApi "product-restore" (Add-Session @{
  id = $productId
  branch_id = 1
} $manager) | Out-Null

Invoke-CafeApi "product-list" (Add-Session @{
  branch_id = 1
} $manager) | Out-Null

Invoke-CafeApi "refund-invoice" (Add-Session @{
  invoice_id = $posCheckout.invoice_id
  reason = "Smoke test refund"
} $manager) | Out-Null

Invoke-CafeApi "dashboard" (Add-Session @{} $manager) | Out-Null
Invoke-CafeApi "pos-session-report" (Add-Session @{} $manager) | Out-Null
Invoke-CafeApi "reports-export" (Add-Session @{} $manager) | Out-Null

foreach ($staff in @($cashier, $waiter, $barista, $marketing, $manager)) {
  if ($staff.staff_role -eq "cashier") {
    Invoke-CafeApi "shift-closing" (Add-Session @{ closing_cash_amount = 1000000 } $staff) | Out-Null
  } else {
    Invoke-CafeApi "pos-session-logout" (Add-Session @{} $staff) | Out-Null
  }
  Invoke-CafeApi "pos-auth-logout" (Add-Auth @{} $staff) | Out-Null
}

Write-Host "Smoke API test completed. Reset install.php afterward if you want clean sample data."
