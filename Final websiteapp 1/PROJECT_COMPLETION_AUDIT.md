# Cafe Connect - Audit hoàn thiện dự án

Ngày cập nhật: 2026-06-15

Phạm vi: `Cafe-Connect-POS/Final websiteapp 1`, kiến trúc PHP MVC thuần, XAMPP/Apache/MySQL, Website member, POS, CRM, inventory, campaign và report.

## Kết luận nhanh

Dự án hiện đã vượt mức demo cơ bản. Hệ thống có MVC, route nhiều trang, đăng nhập member/staff, POS session, phân quyền theo role, checkout, invoice, payment, voucher, campaign, inventory, audit log, CSRF, lockout, smoke test và verification gate.

Những phần đã nâng trong bản vận hành nội bộ v1:

- `APP_ENV=local|production_internal` trong `config/app.php`.
- `install.php` chặn reset sample data khi chạy `production_internal`.
- `config/payment.php` cấu hình COD và DemoPay nội bộ.
- `scripts/backup_database.ps1` backup MySQL vào `storage/backups`.
- `/menu` có tìm kiếm, lọc, sắp xếp và trạng thái tồn kho.
- `/product?id=...` hiển thị chi tiết sản phẩm.
- `/checkout` hỗ trợ pickup/delivery, COD pending và DemoPay paid.
- `/order?invoice_id=...` hiển thị chi tiết đơn, receipt và hủy đơn pending.
- `/account` hiển thị website orders của member.
- POS cashier khi đóng ca phải nhập closing cash qua endpoint `shift-closing`.
- API baseline cho `category-save` và `content-save` đã có audit log.

## Trạng thái nghiệm thu

| Hạng mục | Trạng thái | Ghi chú |
| --- | --- | --- |
| Kiến trúc MVC | Đạt | `public/index.php`, `app/Core`, Controllers, Models, Views |
| Database | Đạt | Schema lớn, migration/seed baseline, audit, lockout, receipt, refund, website orders |
| Website auth | Đạt | Login/register, profile, đổi mật khẩu, quên mật khẩu SMTP |
| Staff web login | Đạt | Staff đăng nhập website, header hiện POS theo role |
| POS auth | Đạt | Mật khẩu nhân viên + PIN mở ca + heartbeat + session token |
| Role policy | Đạt | Backend/frontend dùng quyền tập trung trong `RolePolicy` |
| Website commerce | Đạt v1 | Cart, voucher, COD/DemoPay, order detail, cancel pending |
| POS cashier | Đạt | Checkout, receipt, hoàn toàn phần/một phần theo chi nhánh, thu chi liên kết refund và closing shift |
| Service order | Đạt | Waiter tạo order, barista xử lý, cashier thanh toán |
| Inventory | Đạt baseline | Stock movement, recipe/BOM, trừ nguyên liệu khi invoice paid |
| Report | Đạt baseline | Dashboard, session performance, CSV export |
| Security | Đạt baseline | CSRF, DB lockout, password hash, audit log, app logger |
| Test | Đạt | `tests/verify_project.ps1` pass đầy đủ |

## Verification đã chạy

Lệnh đã chạy thành công:

```powershell
powershell -ExecutionPolicy Bypass -File "tests/verify_project.ps1"
```

Kết quả:

- PHP lint: OK.
- `node --check assets/js/app.js`: OK.
- MySQL readiness: OK.
- `install.php` reset local DB: OK.
- Route checks: OK với `/`, `/menu`, `/product?id=1`, `/login`, `/register`, `/forgot-password`, `/account`, `/checkout`, `/order?invoice_id=1`, `/member`, `/pos/login`, `/pos/checkout`, `/pos/orders`, `/pos/kitchen`, `/pos/reports`.
- Smoke API: OK với CSRF, member register, voucher claim, website checkout, website order detail/cancel, POS auth/session, checkout, receipt, order, kitchen, campaign, inventory block, refund, dashboard, reports export, shift closing.
- Database đã được reset sạch lại sau smoke test.

## Những phần đã hoàn thiện

### Website

- Trang chủ, menu, chi tiết sản phẩm, login, register, forgot/reset password, account, checkout, member portal và order detail.
- Giỏ hàng website dùng `localStorage`.
- Member có thể claim voucher, dùng voucher khi checkout, xem hồ sơ, điểm, favorite, lịch sử invoice và website orders.
- Checkout website có hình thức nhận hàng, địa chỉ giao hàng, thời gian nhận dự kiến và ghi chú.
- COD tạo đơn pending; DemoPay tạo đơn paid.
- Member có thể hủy đơn nếu trạng thái còn pending.

### POS

- Staff đăng nhập bằng mã/email/SĐT + mật khẩu.
- PIN riêng dùng để mở ca POS.
- `pos_sessions` lưu thời gian mở ca, heartbeat, closing cash, expected cash và chênh lệch.
- Cashier checkout POS, lookup/tạo khách, áp voucher, thu chi và in/xem receipt.
- Waiter tạo order bàn và đánh dấu món đã phục vụ.
- Barista xử lý kitchen queue.
- Cashier được hoàn hóa đơn đúng chi nhánh; manager/owner/admin có quyền hoàn và tra cứu mở rộng.
- Refund tiền mặt tạo cash-out theo ca; refund card/e-wallet lưu mã tham chiếu, không tác động két tiền.
- Báo cáo và phiên làm việc tách gross sales, refund, net revenue; nguyên liệu lỗi được ghi nhận `waste` và không hoàn kho.
- Manager/owner/admin có dashboard, report, inventory, product, campaign, cancel và override.

### Backend/API

- Response thống nhất `{ ok, data, message }`.
- API ghi dữ liệu yêu cầu CSRF token.
- POS API nhạy cảm yêu cầu `staff_id`, `pos_session_id`, `session_token` và role hợp lệ.
- Checkout dùng transaction.
- Password/PIN dùng `password_hash()` và `password_verify()`.
- Audit log ghi các thao tác quan trọng.

## Những phần còn nên nâng tiếp

Ưu tiên sau bản nội bộ v1:

1. Tách `assets/js/app.js` thành các module nhỏ theo website/POS để dễ bảo trì.
2. Thêm receipt PDF hoặc kết nối máy in hóa đơn nhiệt.
3. Thêm split payment, chuyển/gộp bàn và tách bill.
4. Thêm UI quản lý supplier, batch, expiry và kiểm kho.
5. Thêm report filter nâng cao theo ngày, chi nhánh, nhân viên và payment method.
6. Thêm CMS đầy đủ cho banner, policy, footer, social links và upload ảnh sản phẩm.
7. Thêm password policy, HTTPS bắt buộc và log rotation nếu đưa ra môi trường production thật.
8. Thêm browser/E2E test cho các luồng website và POS quan trọng.

## Checklist kết luận

- [x] MVC nhiều trang.
- [x] Website member auth.
- [x] Staff/admin đăng nhập website.
- [x] POS auth bằng mật khẩu + PIN mở ca.
- [x] Role policy tập trung.
- [x] Website checkout COD/DemoPay.
- [x] Order detail và cancel pending.
- [x] Voucher claim/redeem/reserve.
- [x] POS checkout/service order/kitchen.
- [x] Receipt, full/partial refund, cash-out theo ca, void và cancel.
- [x] Inventory recipe baseline.
- [x] Dashboard/report/export baseline.
- [x] CSRF, lockout, audit log, app logger.
- [x] Backup script nội bộ.
- [x] Verification gate pass.
