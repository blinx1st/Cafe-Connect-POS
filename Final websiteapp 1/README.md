# Cafe Connect POS + Website MVC

Ứng dụng PHP thuần chạy bằng XAMPP/Apache/MySQL, không dùng framework. Code nghiệp vụ được tách theo MVC trong `app/`, giao diện website và POS đã tách thành nhiều trang theo module.

## Cấu trúc chính

- `public/index.php`: front controller và router.
- `app/Core`: Router, Controller, Model, Database, Response, Session.
- `app/Models`: Customer, Product, Order, Invoice, Voucher, Staff, Inventory, Campaign, Dashboard, Report.
- `app/Controllers`: Website, Auth, POS và API controllers.
- `app/Views/website`: trang chủ, menu, tài khoản, checkout, member portal.
- `app/Views/pos`: đăng nhập POS và trang module POS.
- `database/cafe_connect_schema.sql`: schema + sample data cho Website + POS roles.

## Cách chạy bằng XAMPP

1. Start `Apache` và `MySQL` trong XAMPP Control Panel.
2. Mở `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/install.php`.
3. Bấm `Import / Reset sample data`.
4. Mở website: `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/`.
5. Mở POS login: `http://localhost/C%C3%A1c%20h%E1%BB%87%20th%E1%BB%91ng%20th%C3%B4ng%20tin%20doanh%20nghi%E1%BB%87p/Cafe-Connect-POS/Final%20websiteapp%201/pos/login`.

Nếu Apache chưa bật rewrite, dùng fallback dạng `index.php?route=/menu` hoặc `index.php?route=/pos/checkout`.

## Website routes

- `/` hoặc `index.php`: trang chủ, giới thiệu ngắn, sản phẩm nổi bật, CTA.
- `/menu`: menu đầy đủ từ MySQL, thêm món vào giỏ bằng `localStorage`.
- `/account`: đăng nhập/đăng ký thành viên bằng số điện thoại/email.
- `/checkout`: giỏ hàng website, voucher, thanh toán và ghi invoice.
- `/member`: hồ sơ thành viên, điểm, voucher, favorite, lịch sử hóa đơn.

## POS routes

- `/pos/login`: chọn nhân viên/role từ database.
- `/pos` hoặc `/pos/checkout`: bán hàng tại quầy.
- `/pos/orders`: bàn và order phục vụ.
- `/pos/kitchen`: kitchen queue cho barista.
- `/pos/customers`: CRM khách hàng.
- `/pos/campaigns`: campaign/voucher.
- `/pos/inventory`: kho.
- `/pos/reports`: báo cáo.
- `/pos/products`: quản lý sản phẩm.
- `/pos/staff`: quản lý nhân viên.
- `/pos/cash`: thu chi.

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
- POS tạo khách bằng SĐT thì website đăng nhập được ngay bằng SĐT đó.
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
