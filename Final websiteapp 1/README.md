# Cafe Connect POS + Website MVC

Ứng dụng PHP thuần chạy bằng XAMPP/Apache/MySQL, không dùng framework. Code nghiệp vụ đã được tách theo MVC trong `app/`.

## Cấu trúc chính

- `public/index.php`: front controller và router.
- `app/Core`: Router, Controller, Model, Database, Response, Session.
- `app/Models`: Customer, Product, Order, Invoice, Voucher, Staff, Inventory, Campaign, Dashboard, Report.
- `app/Controllers`: Website, POS và API controllers.
- `app/Views`: website, POS và layout.
- `database/cafe_connect_schema.sql`: schema + sample data cho toàn bộ Website + POS roles.

## Cách chạy bằng XAMPP

1. Start `Apache` và `MySQL` trong XAMPP Control Panel.
2. Mở `http://localhost/Cafe-Connect-POS/Final%20websiteapp%201/install.php`.
3. Bấm `Import / Reset sample data`.
4. Mở website: `http://localhost/Cafe-Connect-POS/Final%20websiteapp%201/index.php`.
5. Mở POS: `http://localhost/Cafe-Connect-POS/Final%20websiteapp%201/pos.php`.

## Role POS demo

POS đăng nhập bằng cách chọn nhân viên từ database, không dùng mật khẩu trong bản demo.

- `waiter`: bàn và order phục vụ.
- `barista`: kitchen queue.
- `cashier`: POS bán hàng, checkout order, khách hàng, thu chi.
- `marketing`: khách hàng, campaign, dashboard.
- `manager`, `owner`, `admin`: dashboard, report, inventory, product/staff admin và các module vận hành.

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

Ví dụ endpoint: `member-lookup`, `checkout`, `create-order`, `update-order-item`, `dashboard`, `create-campaign`, `stock-movement`.
