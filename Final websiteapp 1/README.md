# Cafe Connect POS + Website MVC

Ứng dụng PHP thuần chạy bằng XAMPP/Apache/MySQL, không dùng framework. Code nghiệp vụ được tách theo MVC trong `app/`.

## Cấu trúc chính

- `public/index.php`: front controller và router.
- `app/Core`: Router, Controller, Model, Database, Response, Session.
- `app/Models`: Customer, Product, Order, Invoice, Voucher, Staff, Inventory, Campaign, Dashboard, Report.
- `app/Controllers`: Website, Auth, POS và API controllers.
- `app/Views`: website, POS và layout.
- `database/cafe_connect_schema.sql`: schema + sample data cho Website + POS roles.

## Cách chạy bằng XAMPP

1. Start `Apache` và `MySQL` trong XAMPP Control Panel.
2. Mở `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/install.php`.
3. Bấm `Import / Reset sample data`.
4. Mở website: `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/index.php`.
5. Mở POS: `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/pos.php`.

## Website member

- Đăng nhập bằng số điện thoại/email tại khu vực `Tài khoản`.
- Đăng ký nhanh bằng họ tên, số điện thoại, email; dữ liệu được lưu vào bảng `customers`.
- Thành viên đã đăng nhập sẽ tự dùng `customer_id` khi website checkout, được cộng điểm và xem lịch sử hóa đơn.
- POS tạo khách bằng SĐT thì website đăng nhập được ngay bằng SĐT đó.

## Role POS demo

POS đăng nhập bằng cách chọn nhân viên từ database, không dùng mật khẩu trong bản demo.

- `waiter`: bàn và order phục vụ.
- `barista`: kitchen queue.
- `cashier`: POS bán hàng, checkout order, khách hàng, thu chi.
- `marketing`: khách hàng, campaign.
- `manager`, `owner`, `admin`: dashboard, report, inventory, product/staff admin và các module vận hành.

Backend cũng kiểm tra `staff_id` cho các API ghi dữ liệu nhạy cảm như `create-order`, `update-order-item`, `checkout-order`, `create-campaign`, `stock-movement`, `product-save`, `staff-save`, `reports`.

## Dữ liệu demo

- Tra thành viên `0900000001`: Nguyen An, Gold member, có voucher khả dụng và lịch sử website order.
- Website checkout lưu `sales_channel = website`.
- Waiter tạo service order, barista cập nhật trạng thái món, cashier checkout thành invoice.
- Dashboard, campaign, inventory và report đều lấy dữ liệu thật qua Models.

## API

Gọi `POST api.php?endpoint=...` với JSON body. Response thống nhất:

```json
{ "ok": true, "data": {}, "message": "" }
```

Endpoint chính: `member-login`, `member-register`, `member-logout`, `member-lookup`, `customer-create`, `checkout`, `create-order`, `update-order-item`, `dashboard`, `create-campaign`, `stock-movement`.

## Kiểm thử nhanh

Sau khi chạy `install.php`, có thể chạy smoke test API bằng PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File "tests/smoke_api.ps1"
```

Script sẽ tạo member mới, checkout, tạo order bàn, cập nhật kitchen item và gọi dashboard. Chạy lại `install.php` nếu muốn reset sample data sạch.
