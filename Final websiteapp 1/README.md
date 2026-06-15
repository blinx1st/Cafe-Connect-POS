# Cafe Connect POS + Website MVC

Ứng dụng PHP thuần chạy bằng XAMPP/Apache/MySQL, không dùng framework. Code nghiệp vụ được tách theo MVC trong `app/`, giao diện website và POS đã tách thành nhiều trang theo module.

## Cấu trúc chính

- `public/index.php`: front controller và router.
- `app/Core`: Router, Controller, Model, Database, Response, Session.
- `app/Models`: Customer, Product, Order, Invoice, Voucher, Staff, PosSession, Inventory, Campaign, Dashboard, Report.
- `app/Controllers`: Website, Auth, POS và API controllers.
- `app/Views/website`: trang chủ, menu, login, register, forgot/reset password, hồ sơ, checkout, member portal.
- `app/Views/pos`: đăng nhập POS và trang module POS.
- `database/cafe_connect_schema.sql`: schema + sample data cho Website + POS roles.
- `database/migrations`: migration bổ sung cho audit, CSRF/lockout support, website order, refund, receipt, recipe/BOM.
- `database/seeders`: sample seed riêng cho recipe, unit cost và website order status.
- `storage/logs/app.log`: nơi ghi lỗi backend an toàn cho API.

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
- `/login`: đăng nhập thành viên bằng số điện thoại/email và mật khẩu.
- `/register`: đăng ký thành viên mới; đăng ký thành công sẽ tự đăng nhập và quay về trang chủ.
- `/forgot-password`: gửi link đặt lại mật khẩu qua SMTP thật.
- `/reset-password?token=...`: đặt mật khẩu mới từ link email, token dùng một lần và hết hạn sau 30 phút.
- `/account`: hồ sơ cá nhân, sửa thông tin cơ bản, đổi mật khẩu, xem điểm/voucher/favorite/lịch sử hóa đơn.
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

## Tài khoản demo

Website member đăng nhập bằng số điện thoại hoặc email kèm mật khẩu:

- Khách mẫu `0900000001` đến `0900000006`: mật khẩu `123456`.
- Ví dụ nhanh: `0900000001 / 123456`.
- Member đăng ký mới cần họ tên, SĐT, email và mật khẩu tối thiểu 6 ký tự; đăng ký trùng SĐT/email sẽ yêu cầu đăng nhập.
- `/account` cho phép sửa họ tên/email/ngày sinh/giới tính/địa chỉ và đổi mật khẩu bằng mật khẩu hiện tại.
- Quên mật khẩu dùng SMTP trong `config/mail.php`. Cần điền `host`, `port`, `encryption`, `username`, `password`, `from_email`, `from_name`; nếu chưa cấu hình, API sẽ báo `SMTP chưa được cấu hình trong config/mail.php.`.

POS đăng nhập bằng tài khoản nhân viên riêng, sau đó nhập PIN riêng để mở ca làm:

- Cap nhat: POS co 2 lop xac thuc. Dang nhap bang tai khoan nhan vien, sau do nhap PIN rieng de mo ca.
- `WAIT001 / waiter123`, PIN `1111`.
- `CASH001 / cashier123`, PIN `2222`.
- `BAR001 / barista123`, PIN `3333`.
- `OWNER001 / owner123`, PIN `4444`.
- `MKT001 / marketing123`, PIN `5555`.
- `ADMIN001 / admin123`, PIN `6666`.
- `MGR001 / manager123`, PIN `7777`.

## Role POS demo

- `waiter`: bàn và order phục vụ.
- `barista`: kitchen queue.
- `cashier`: POS bán hàng, checkout order, khách hàng, thu chi.
- `marketing`: khách hàng, campaign.
- `manager`, `owner`, `admin`: dashboard, report, inventory, product/staff admin và các module vận hành.

Backend kiểm tra cả `staff_id`, `pos_session_id` và `session_token` cho các API ghi dữ liệu nhạy cảm như `customer-create`, `create-order`, `update-order-item`, `checkout-order`, `create-campaign`, `stock-movement`, `cash-transaction`, `product-save`, `staff-save`, `reports`.

## Bảo mật và audit

- Mọi API ghi dữ liệu cần header `X-CSRF-Token`. Layout PHP inject token vào `window.CAFE_CSRF_TOKEN`, và `assets/js/app.js` tự động gửi token này khi gọi API.
- Endpoint đọc dữ liệu như `member-session`, `member-lookup`, `dashboard`, `inventory`, `reports`, `receipt` được phép bỏ qua CSRF.
- Login member, login staff POS và PIN mở ca có lockout bằng DB trong bảng `auth_lockouts`; sai quá ngưỡng sẽ bị khóa tạm 15 phút.
- Bảng `audit_logs` ghi các hành động quan trọng: login/logout, register, update profile, đổi/reset password, checkout, refund, receipt print, void/cancel order, product/staff/cash/inventory.
- Lỗi backend bất ngờ được ghi vào `storage/logs/app.log`; API chỉ trả thông báo an toàn thay vì stack trace nội bộ.

## Phiên làm việc POS

- Khi đăng nhập đúng tài khoản ở `/pos/login`, API `pos-auth-login` tạo một dòng `staff_login_sessions` trạng thái `active`.
- Khi nhập đúng PIN mở ca, API `pos-session-login` tạo một dòng `pos_sessions` trạng thái `open`.
- POS frontend lưu `auth_session_id/auth_token` và `pos_session_id/session_token` riêng trong `localStorage`, tự gửi kèm request POS và heartbeat mỗi 60 giây.
- Logout gọi `pos-session-logout` để đóng ca, lưu `closed_at`, `closed_reason`, tiền dự kiến, tiền chốt ca và chênh lệch.
- Session không heartbeat quá 30 phút sẽ bị đóng với `closed_reason = timeout`.
- Invoice POS lưu `bill_started_at`, `paid_at`, `pos_session_id`; service order dùng `created_at` làm thời điểm bắt đầu bill khi cashier checkout.
- Bảng `pos_activity_logs` lưu thao tác quan trọng theo role: waiter tạo order, barista cập nhật bếp, cashier checkout/thu chi, marketing tạo campaign, manager/admin sửa sản phẩm/nhân viên/kho.
- `/pos/reports` có phần `Phiên làm việc POS` để xem thời lượng ca, doanh thu, số bill, số order, số món pha, thu/chi và log chính theo session.

## Nghiệp vụ vận hành mới

- Website checkout có hình thức nhận hàng, địa chỉ giao hàng, thời gian nhận dự kiến và ghi chú; dữ liệu lưu vào `website_orders`.
- POS/manager có API nền cho `refund-invoice`, `void-order-item`, `cancel-order`, `order-status-update`, `receipt`, `receipt-print-log`, `checkout-closing`.
- Inventory có recipe/BOM qua `recipes` và `recipe_items`; khi invoice paid, hệ thống tự tạo stock movement `sales_export` và trừ nguyên liệu từ `inventory_materials`.
- Report có gross margin/COGS cơ bản và `reports-export` trả CSV để nghiệm thu.

## Dữ liệu demo

- Tra thành viên `0900000001`: Nguyen An, Gold member, có voucher khả dụng và lịch sử website order.
- Website checkout lưu `sales_channel = website`.
- POS tạo khách bằng SĐT để tra cứu/checkout tại quầy; khách cần đăng ký mật khẩu trên `/register` để đăng nhập website.
- Waiter tạo service order, barista cập nhật trạng thái món, cashier checkout thành invoice.
- Dashboard, campaign, inventory và report đều lấy dữ liệu thật qua Models.

## API

Gọi `POST api.php?endpoint=...` với JSON body. API ghi dữ liệu cần thêm header `X-CSRF-Token`. Response thống nhất:

```json
{ "ok": true, "data": {}, "message": "" }
```

Endpoint chính: `csrf-refresh`, `member-login`, `member-register`, `member-logout`, `member-profile-update`, `member-change-password`, `member-forgot-password`, `member-reset-password`, `member-lookup`, `customer-create`, `checkout`, `create-order`, `update-order-item`, `void-order-item`, `cancel-order`, `refund-invoice`, `receipt`, `receipt-print-log`, `dashboard`, `create-campaign`, `stock-movement`, `reports-export`.

- `member-login`: `{ "identity": "0900000001", "password": "123456" }`.
- `member-register`: `{ "customer_name": "...", "phone_number": "...", "email": "...", "password": "123456", "password_confirm": "123456" }`.
- `member-profile-update`: `{ "customer_name": "...", "email": "...", "birth_date": "2002-01-01", "gender": "other", "address": "..." }`.
- `member-change-password`: `{ "current_password": "123456", "password": "654321", "password_confirm": "654321" }`.
- `member-forgot-password`: `{ "identity": "0900000001" }`.
- `member-reset-password`: `{ "token": "...", "password": "123456", "password_confirm": "123456" }`.
- `pos-auth-login`: `{ "identity": "CASH001", "password": "cashier123" }`.
- `pos-session-login`: `{ "staff_id": 2, "auth_session_id": 1, "auth_token": "...", "pin": "2222", "opening_cash_amount": 1000000 }`.

Endpoint POS auth/session: `pos-auth-login`, `pos-auth-current`, `pos-auth-heartbeat`, `pos-auth-logout`, `pos-session-login`, `pos-session-current`, `pos-session-heartbeat`, `pos-session-logout`, `pos-session-report`.

## Kiểm thử nhanh

Sau khi chạy `install.php`, có thể chạy smoke test API bằng PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File "tests/smoke_api.ps1"
```

Script sẽ lấy CSRF token, kiểm tra thiếu CSRF bị chặn, tạo member mới, website checkout, login POS session cho cashier/waiter/barista/manager, checkout, in receipt, tạo order bàn, cập nhật kitchen item, void item, refund invoice, gọi dashboard/report/export rồi logout các session. Chạy lại `install.php` nếu muốn reset sample data sạch.

## Audit hoan thien

Xem `PROJECT_COMPLETION_AUDIT.md` de biet du an hien da co nhung gi, con thieu gi de len muc san pham van hanh that, va roadmap nang cap A-Z theo tung phase.

## Verification gate

Co the chay mot lenh kiem tra tong hop sau khi bat Apache:

```powershell
powershell -ExecutionPolicy Bypass -File "tests/verify_project.ps1"
```

Script nay se lint PHP, check JS, dam bao MySQL dang chay, reset database mau, check route, chay smoke API va reset database lai sach sau khi test.

## Security baseline

- Mat khau member, mat khau staff va PIN POS duoc luu bang `password_hash()`.
- PHP session dung cookie `HttpOnly`, `SameSite=Lax` va `Secure` khi chay HTTPS.
- Session id duoc regenerate sau login/register/adopt/logout/reset password.
- Cac diem auth nhay cam co rate limit co ban va DB lockout: member login/register/forgot/reset, POS staff login va PIN mo ca.
- Cac API ghi du lieu co CSRF header `X-CSRF-Token`.
- Audit log dung chung duoc luu trong `audit_logs`; loi he thong ghi vao `storage/logs/app.log`.
- Cac API POS ghi du lieu van bat buoc `staff_id`, role hop le, `pos_session_id` va `session_token`.

## Role matrix POS chuan hoa

Quyen POS hien duoc khai bao tap trung trong `app/Core/RolePolicy.php` va duoc dung chung cho backend API, du lieu `pos-bootstrap` va nav frontend.

| Role | Module duoc xem | Thao tac chinh | API chinh |
| --- | --- | --- | --- |
| `waiter` | `orders`, `kitchen` | Tao order ban, gui mon xuong bep, danh dau mon `ready -> served`, void mon chua ready/served co ly do | `orders`, `create-order`, `update-order-item`, `void-order-item` |
| `barista` | `kitchen` | Xem kitchen queue, chuyen mon `waiting/preparing -> preparing/ready` | `kitchen`, `update-order-item` |
| `cashier` | `checkout`, `orders`, `customers`, `cash` | Ban hang tai quay, checkout service order, lookup/tao khach, ap voucher, thu chi, xem/in receipt | `checkout`, `checkout-order`, `customer-create`, `cash-transaction`, `receipt` |
| `marketing` | `customers`, `campaigns` | CRM, campaign/voucher/newsletter, xem hieu qua campaign | `customer-create`, `campaigns`, `create-campaign` |
| `manager` | `dashboard`, `reports`, `inventory`, `products`, `campaigns`, `cash`, `orders`, `kitchen` | Bao cao, kho, san pham, refund invoice, cancel order, void override, export CSV | `dashboard`, `reports`, `reports-export`, `inventory`, `product-save`, `refund-invoice`, `cancel-order` |
| `owner` | Nhu manager + `staff` | Van hanh va nhan su | Toan bo API quan ly, gom `staff-save` |
| `admin` | Toan quyen POS | Quan tri he thong, nhan su, du lieu van hanh | Toan bo API POS noi bo |

API doc/ghi nhay cam nhu `orders`, `kitchen`, `dashboard`, `campaigns`, `inventory`, `reports`, `reports-export`, `receipt`, `checkout`, `checkout-order`, `refund-invoice`, `void-order-item`, `cancel-order`, `stock-movement`, `cash-transaction`, `product-save`, `staff-save` deu bat buoc `staff_id`, `pos_session_id`, `session_token` va role hop le.

## Claim voucher member

- Member dang nhap co the vao `/account` de xem muc `Voucher co the nhan`.
- Danh sach nay lay tu campaign `active`, con han, kenh `website/omnichannel`, dung segment cua khach va con so luong.
- Bam `Nhan voucher` goi API `voucher-claim` voi payload `{ "promotion_id": 3 }`.
- He thong tao voucher rieng trong bang `vouchers` gan voi `customer_id`; voucher moi lap tuc hien o ho so va dropdown checkout.
- Khi thanh toan co chon voucher, `Invoice::checkout()` validate voucher va tu chuyen voucher sang `redeemed`.
