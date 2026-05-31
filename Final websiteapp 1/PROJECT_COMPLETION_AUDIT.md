# Cafe Connect - Audit hoan thien du an tu demo thanh san pham van hanh

Ngay danh gia: 2026-05-31

Pham vi danh gia: thu muc `Cafe-Connect-POS/Final websiteapp 1`, kien truc PHP MVC thuan, XAMPP/Apache/MySQL, website member, POS, CRM, inventory, campaign, report.

## Cap nhat sau Phase 1 nen tang

Trang thai moi sau dot nang cap hien tai:

- Da tach `database/migrations` va `database/seeders`; `install.php` reset DB bang schema goc, sau do chay migration/seed theo thu tu.
- Database hien co 41 bang sau reset, gom them `schema_migrations`, `auth_lockouts`, `audit_logs`, `website_orders`, `invoice_refunds`, `receipt_print_logs`, `recipes`, `recipe_items`.
- Da them CSRF cho API ghi du lieu: layout inject `window.CAFE_CSRF_TOKEN`, frontend gui `X-CSRF-Token`, backend chan request ghi thieu/sai token.
- Da them DB lockout cho member login, POS staff login va PIN mo ca; sai qua nguong bi khoa tam 15 phut.
- Da them `App\Core\AppLogger` ghi loi vao `storage/logs/app.log`, API khong tra stack/internal exception tho.
- Da them audit log dung chung cho login/logout, register, profile/password, checkout, refund, receipt print, void/cancel order, cash/product/staff/inventory.
- Da them website order fields o checkout va bang `website_orders`.
- Da them receipt/refund/void/cancel/order-status/payment-demo/report-export endpoint nen.
- Da them recipe/BOM va tu dong tru nguyen lieu khi invoice paid.
- `tests/verify_project.ps1` da pass sau nang cap: PHP lint 58 files, node check, install, routes, smoke API co CSRF, website checkout, POS checkout, receipt, void/refund, reports export, logout, reset DB sach.

## 1. Ket luan nhanh

Du an hien khong con la landing page demo don gian. Code hien tai da co nen tang MVC, schema MySQL tuong doi rong, website nhieu trang, POS phan quyen theo role, dang nhap member/staff, POS session, checkout ghi invoice, order ban, kitchen queue, voucher, campaign, inventory, cash transaction va report.

Tuy nhien de tro thanh website/POS hoat dong day du tu A-Z, du an van can cac lop san pham that sau:

- Migration/seed da co baseline, nhung can nang len up/down migration va che do giu du lieu.
- Bao mat web da co CSRF, rate limit, DB lockout, secure session cookie va audit log; can bo sung password policy, HTTPS bat buoc va audit bat bien neu production.
- Payment/delivery/order tracking that cho website.
- Nghiep vu POS sau ban hang: huy bill, hoan tien, in hoa don, tach/gop/chuyen ban, chot ca bat buoc.
- Inventory theo cong thuc nguyen lieu thay vi tru ton kho san pham don gian.
- Admin CMS va upload hinh anh san pham.
- Test tu dong co database fixture, CI, backup, monitoring va tai lieu van hanh.

## 2. Bang diem hien trang

| Hang muc | Muc hien tai | Danh gia |
| --- | --- | --- |
| Kien truc MVC | `public/index.php`, `app/Core`, Controllers, Models, Views | Tot cho do an, can middleware/validation/migration de len production |
| Database | Schema lon co customer, staff, POS session, invoices, orders, inventory, campaign, audit, lockout, refund, receipt, recipe | Kha day, da co migration/seed baseline; can up/down migration neu production |
| Website UI | Co home, menu, login, register, forgot/reset, account, checkout, member | Kha day, can order tracking, payment/delivery that, product detail |
| Member auth | Phone/email + password, register, profile, change password, forgot password SMTP, CSRF, DB lockout, audit, session regenerate | Tot cho nghiem thu, can password policy va email provider that neu production |
| Staff/Admin web login | Staff POS co the login tren website, header hien POS | Da co, can tach ro trang ho so nhan vien va quan ly quyen nang cao |
| POS auth | Tai khoan/mat khau + PIN mo ca + heartbeat + token + rate limit + DB lockout + audit | Tot cho nghiem thu, can phan quyen middleware tap trung hon va chot ca bat buoc hon |
| POS cashier | Checkout, voucher, customer lookup, invoice/payment/points, refund, void, receipt log | Tot cho nghiem thu, can receipt printer/PDF va split payment |
| Service order | Waiter tao order, barista cap nhat, cashier checkout | Tot, can quy trinh huy mon/chuyen ban/gop ban/SLA bep |
| Reporting | Dashboard, report, session performance | Co nen tang, can filter ngay/chi nhanh/export va doi soat tai chinh |
| Inventory | Ton kho san pham, vat tu, stock movement, recipe/BOM, tu dong tru nguyen lieu | Tot hon demo, can supplier/batch/expiry UI day du va kiem kho |
| Campaign/CRM | Campaign, voucher, favorite, newsletter, segment | Co ban, can automation, email/SMS/Zalo, consent, unsubscribe |
| Quality | PHP lint OK, JS syntax OK, route HTTP 200, smoke API pass sau khi import DB | Tot cho nghiem thu hien tai, van can test tu dong co fixture/CI |

## 3. Bang chung kiem tra hien tai

Kiem tra khong pha du lieu da chay:

- `C:\xampp\php\php.exe -l` tren 58 file PHP: OK.
- `node --check assets/js/app.js`: OK.
- Route HTTP 200: `/`, `/menu`, `/login`, `/register`, `/forgot-password`, `/account`, `/checkout`, `/member`, `/pos/login`, `/pos/checkout`, `/pos/reports`.
- MySQL/MariaDB da duoc khoi dong tren `127.0.0.1:3306`.
- `install.php` POST import thanh cong database `cafe_connect_crm`: 41 bang.
- Sau reset sach: `staff_count = 8`, `customer_count = 6`.
- API `member-session`: tra `{ ok: true, data: { member: null, web_staff: null } }`.
- `tests/smoke_api.ps1`: pass toan bo cac buoc `member-register`, `member-lookup`, `pos-auth-login`, `pos-session-login`, `checkout`, `create-order`, `update-order-item`, `dashboard`, `pos-session-report`, logout session/auth.
- Da reset lai `install.php` sau smoke test de dua sample data ve trang thai sach.
- Them va chay thanh cong `tests/verify_project.ps1`: script tong hop PHP lint, JS syntax, MySQL readiness, DB reset, API readiness, route checks, smoke API, reset DB sach sau smoke.
- Them `App\Core\RateLimiter` va session hardening: cookie `HttpOnly`, `SameSite=Lax`, `Secure` khi HTTPS, regenerate session sau login/register/adopt/logout/reset password. Rate limit da gan cho member login/register/forgot/reset, POS staff login va POS PIN mo ca.

Ket luan: code khong loi cu phap, route render duoc va cac luong nghiep vu API chinh da chay duoc tren database mau. Phase nen tang da co CSRF, DB lockout, audit, logger, migration/seed, refund/receipt/recipe baseline; cac muc san pham that tiep theo van la payment/delivery that, POS printer/split payment, inventory UI sau hon, CI va backup.

## 4. Nhung phan da lam duoc

### 4.1 Kien truc va routing

- Co front controller `public/index.php`.
- Co router tu build trong `app/Core/Router.php`.
- Co views rieng cho website va POS.
- Co wrapper `index.php`, `pos.php`, `api.php`, `install.php` de chay truc tiep bang XAMPP.
- Co PJAX website de chuyen trang ma khong reload header/footer.

### 4.2 Website

- Trang chu, menu, checkout, account, member portal.
- Login, register, forgot password, reset password.
- Header co trang thai guest/member/staff va link POS neu staff dang nhap.
- Gio hang website dung `localStorage`.
- Checkout website ghi invoice voi `sales_channel = website`.
- Member xem ho so, diem, voucher, favorite, lich su hoa don.
- Newsletter va favorite co API.

### 4.3 POS

- Login nhan vien bang ma/email/SDT + mat khau.
- Mo ca bang PIN rieng va tao `pos_sessions`.
- Heartbeat, logout, timeout session.
- POS checkout tai quay.
- Tao khach hang tu POS, neu SDT da ton tai thi tra ho so.
- Waiter tao order ban.
- Barista cap nhat mon waiting/preparing/ready/served.
- Cashier checkout service order thanh invoice.
- Quan ly customer, campaign, inventory, product, staff, cash.
- Report session theo doanh thu, so order, so mon pha, cash in/out, action logs.

### 4.4 Database

Schema da co cac nhom bang chinh:

- Member/CRM: `customers`, `membership_tiers`, `customer_segments`, `customer_favorites`, `customer_reviews`, `customer_password_resets`.
- Staff/POS: `staff`, `staff_login_sessions`, `pos_sessions`, `staff_shifts`, `pos_activity_logs`.
- Catalog: `product_categories`, `products`, `product_images`.
- Order/payment: `service_orders`, `service_order_items`, `invoices`, `invoice_details`, `payments`.
- Marketing: `promotions`, `vouchers`, `marketing_emails`, `campaign_recipients`, `newsletter_subscribers`.
- Inventory/cash: `branch_inventory`, `inventory_materials`, `stock_movements`, `cash_transactions`.

### 4.5 Backend/API

- API response thong nhat `{ ok, data, message }`.
- Checkout va order dung transaction.
- Password/PIN dung `password_hash()` va `password_verify()`.
- Session cookie da bat `HttpOnly`, `SameSite=Lax`, `Secure` khi HTTPS; session id regenerate sau cac diem auth quan trong.
- Rate limit co ban bang file storage cho login/register/forgot/reset va POS auth/PIN.
- POS API ghi du lieu nhay cam co check role/session.
- Forgot password customer dung token hash, het han 30 phut, dung mot lan.
- SMTP co `App\Core\Mailer`.

## 5. Khoang thieu de thanh san pham that

### 5.1 Cai dat, migration va moi truong

Hien tai `install.php` reset/import sample data. Cach nay hop do an nhung chua hop production.

Can them:

- `migrations/` co version, up/down va lich su migration.
- `seeders/` rieng cho sample data.
- Cau hinh `.env` hoac `config/*.local.php` khong commit credential.
- Che do install an toan: chi cho phep reset trong local/dev.
- Backup/restore MySQL.
- Script health check DB.

### 5.2 Bao mat

Da co hash mat khau, token session, role check, nhung van thieu cac lop quan trong:

- CSRF token cho form website va API state-changing da co baseline; can them rotation policy neu production.
- Rate limit login/register/forgot password/POS PIN da co ban bang file storage; DB lockout da co cho login/PIN, co the nang len Redis neu production.
- Lock account tam thoi khi sai mat khau/PIN nhieu lan da co baseline bang `auth_lockouts`.
- Session cookie options va regenerate session id da co ban; can them policy HTTPS bat buoc neu deploy production.
- Phan quyen middleware tap trung thay vi goi le trong controller.
- Log audit bat buoc da co cho nhieu luong chinh; can mo rong cho tat ca CRUD admin/CMS ve sau.
- Chinh sach mat khau manh, expire/reset staff password.
- An thong tin loi noi bo voi nguoi dung, ghi log chi tiet vao file/server.

### 5.3 Website commerce

Dang co gio hang/checkout nhung chua la thuong mai dien tu that:

- Trang chi tiet san pham, search/filter/sort, category page.
- Dia chi giao hang va phi giao hang.
- Lich su don hang co trang thai: pending, paid, preparing, delivering, completed, cancelled.
- Cong thanh toan that: VNPAY/Momo/ZaloPay/card sandbox.
- Email/SMS xac nhan don hang.
- Quan ly huy don/hoan tien doi voi website.
- Ma giam gia public va private, gioi han so lan dung.
- Hoa don/receipt PDF.

### 5.4 POS cashier

Can bo sung de dung nhu POS that:

- In hoa don nhiet hoac xuat PDF.
- Huy bill, void item, refund invoice, ly do huy.
- Tach/gop bill, split payment, split table.
- Chuyen ban, gop ban, dat ban.
- Chiet khau thu cong theo quyen manager.
- Kiem tien dau ca/cuoi ca bat buoc, doi soat chenh lech.
- Drawer open log va cash count denomination.
- Barcode/QR scan neu can.

### 5.5 Kitchen/service operations

Da co queue nhung can chi tiet hon:

- SLA thoi gian pha che theo loai mon.
- Ly do remake/cancel item.
- Staff nhan mon va hoan tat mon rieng.
- Man hinh kitchen filter theo chi nhanh/khu vuc/uu tien.
- Thong bao real-time khi order moi hoac mon ready.
- Lich su thay doi trang thai item.

### 5.6 Inventory

Hien dang tru `branch_inventory` theo product va co `inventory_materials`, nhung chua co cong thuc nguyen lieu.

Can them:

- Bang recipe/BOM: product -> material quantity.
- Khi checkout/order paid thi tru nguyen lieu tu dong.
- Nhap kho theo supplier, gia von, batch/expiry date.
- Ton kho theo chi nhanh va canh bao het han.
- Kiem kho, dieu chinh ton kho, ly do dieu chinh.
- Gia von/COGS va margin report.

### 5.7 CRM/marketing

Da co customer, voucher, campaign, favorite, newsletter. Can them:

- Consent marketing, unsubscribe token.
- Segment dong theo hanh vi mua hang.
- Automation: sinh nhat, inactive, VIP, win-back.
- Gui email/SMS/Zalo that va luu delivery status.
- Customer service notes/tickets.
- Loyalty redemption: dung diem doi voucher/san pham.
- Referral code.

### 5.8 Admin/CMS

Can phan quan tri san pham va noi dung day du hon:

- Upload/doi anh san pham.
- Quan ly category, banner, hero, footer, policy page.
- CRUD promotion/voucher voi trang thai va lich len song.
- CRUD branch, table, role, shift.
- Import/export Excel/CSV.
- Soft delete va restore.

### 5.9 Reporting/accounting

Can nang report tu demo thanh bao cao van hanh:

- Filter theo ngay, chi nhanh, role, staff, payment method.
- Export Excel/PDF.
- Daily closing report.
- Revenue, COGS, gross margin.
- Product mix, slow moving products.
- Campaign ROI.
- Staff productivity by shift.
- Cash reconciliation by cashier.
- Audit trail report.

### 5.10 Frontend UX

Can hoan thien trai nghiem:

- Loading/error state thong nhat.
- Form validation phia client + server message ro rang.
- Empty state cho tung module.
- Responsive test that tren mobile/tablet/desktop.
- Accessibility: label, keyboard navigation, focus state, contrast.
- Tach `assets/js/app.js` thanh module nho theo page de de bao tri.

### 5.11 Test va chat luong

Can them:

- Test database co fixture va reset rieng; hien da co `tests/verify_project.ps1` lam smoke gate tong hop, can tiep tuc tach thanh test suite chuan hon.
- Unit test model nghiep vu: voucher, checkout, points, tier, refund.
- Integration test API: auth, checkout, order, kitchen, inventory, campaign.
- Browser test cho website/POS bang Playwright.
- CI script chay lint + test.
- Log loi PHP va API request id.

## 6. RUI RO can xu ly som

1. Database hien tai dang bao chua install, nen cac luong API chua duoc xac minh.
2. `install.php` reset du lieu, phu hop demo nhung nguy hiem neu dung that.
3. `app.js` qua lon va gom ca website/POS, rui ro regression cao khi sua UI.
4. Con `includes/repository.php` va `includes/helpers.php` kieu legacy, de gay nham lan voi MVC moi.
5. README hoac terminal hien thi tieng Viet bi mojibake, can chuan hoa encoding UTF-8.
6. Bao mat nen da co CSRF/rate-limit/lockout/audit; rui ro con lai la chua co HTTPS/password policy/production hardening.
7. API tra message exception truc tiep, tien loi demo nhung can log noi bo va response an toan hon.
8. Payment/SMTP la cau hinh demo, chua co doi soat provider that.

## 7. Roadmap nang cap A-Z

### Phase 0 - Lam sach nen tang truoc khi them tinh nang

Muc tieu: du an cai duoc, test duoc, khong bi lech schema/code.

- Chay `install.php` va xac minh DB ready.
- Tach migration/seed ra khoi `install.php`.
- Chuan hoa UTF-8 cho README/views/messages.
- Xoa hoac danh dau deprecated thu muc `includes/` neu khong dung.
- Tao `logs/` va logger co request id.
- Tao file `.env.example` hoac `config/*.example.php`.
- Cap nhat README thanh tai lieu cai dat + tai khoan + test + troubleshooting.

Tieu chi nghiem thu:

- `member-session` tra `{ ok: true }`.
- Smoke API chay het.
- Reset DB va seed demo lap lai duoc.

### Phase 1 - Bao mat va auth san pham

Muc tieu: login/register/POS auth an toan hon.

- CSRF token cho form website va API state-changing.
- Rate limit theo IP + identity cho login/register/forgot/PIN.
- Account lockout tam thoi khi sai nhieu lan.
- Secure session config va regenerate session id sau login.
- Staff forgot/reset password rieng.
- Central middleware `requireAuth`, `requireRole`, `requirePosSession`.
- Audit log cho login/logout/password/profile/staff/product/cash/refund.

Tieu chi nghiem thu:

- Sai mat khau qua nguong bi khoa tam.
- API ghi du lieu thieu CSRF/session bi chan.
- Admin xem duoc audit trail.

### Phase 2 - Website commerce hoan chinh

Muc tieu: khach hang dat hang that, theo doi don va thanh toan that.

- Product detail, search, filter, category.
- Checkout co dia chi, pickup/delivery, ghi chu, thoi gian nhan hang.
- Order status website.
- Payment gateway sandbox VNPAY/Momo.
- Email xac nhan don hang.
- Account order detail va receipt.
- Cancel order theo trang thai.

Tieu chi nghiem thu:

- Khach register -> dat hang -> thanh toan sandbox -> xem don trong account.
- Invoice/payment/order status khop DB.

### Phase 3 - POS nghiep vu thuc te

Muc tieu: cashier/waiter/barista dung duoc trong quan.

- Receipt print/PDF.
- Void/refund/cancel item co ly do va role manager.
- Split bill/payment.
- Transfer/merge table.
- Closing cash workflow bat buoc.
- Kitchen real-time refresh hoac polling thong minh.
- Chuyen order website vao hang doi POS/kitchen.

Tieu chi nghiem thu:

- Mot ban co the order, doi mon, cancel mon, thanh toan tach tien, in receipt.
- Refund tao transaction am va audit log.

### Phase 4 - Inventory va cost

Muc tieu: ban hang anh huong dung toi kho va gia von.

- Recipe product -> materials.
- Tru nguyen lieu khi invoice paid.
- Batch/expiry/supplier.
- Stock count va adjustment.
- Low stock alert.
- COGS va margin report.

Tieu chi nghiem thu:

- Ban 1 ly ca phe tru dung gram/ml nguyen lieu.
- Report hien doanh thu, gia von, loi nhuan gop.

### Phase 5 - CRM/marketing automation

Muc tieu: khong chi tao voucher, ma tu dong cham soc khach.

- Consent/unsubscribe.
- Segment dong theo rule.
- Automation campaign.
- Email/SMS/Zalo delivery status.
- Loyalty redemption bang diem.
- Customer notes/tickets.

Tieu chi nghiem thu:

- Tao campaign sinh nhat -> sinh voucher -> gui email -> redeemed -> tinh ROI.

### Phase 6 - Bao cao, quan tri va trien khai

Muc tieu: san pham co the van hanh dai ngay.

- Dashboard filter/export.
- Daily close report.
- Backup DB auto.
- Error monitoring/log rotation.
- Role/permission matrix trong admin.
- User manual cho owner/manager/cashier/waiter/barista.
- Deployment checklist XAMPP/local hoac server.

Tieu chi nghiem thu:

- Chu quan xem duoc ngay hom nay ban bao nhieu, ai lam ca nao, tien mat lech bao nhieu, ton kho can nhap gi.

## 8. Viec nen lam ngay tiep theo

Thu tu uu tien de dua du an len muc nghiem thu tot:

1. Chuan hoa encoding UTF-8 cho README, view va message dang bi mojibake khi hien thi.
2. Tach `app.js` thanh cac module: `site-auth.js`, `site-cart.js`, `pos-auth.js`, `pos-modules.js`, `api.js`.
3. Hoan thien receipt HTML/PDF/in printer va split payment cho POS.
4. Them payment sandbox UI va theo doi trang thai don website chi tiet.
5. Hoan thien UI supplier/batch/expiry/kiem kho cho inventory recipe.
6. Them filter report theo ngay/chi nhanh/staff va daily closing report.
7. Xoa legacy `includes/` sau khi xac minh khong route nao dung.
8. Viet Playwright/API test sau hon cho cac luong nghiem thu ngoai smoke gate `tests/verify_project.ps1`.
9. Nang migration/seed baseline thanh migration up/down giu du lieu khi can.

## 9. Checklist nghiem thu de khang dinh khong con la demo

- [x] Cai dat DB bang migration/seed baseline; production up/down migration con de phase sau.
- [x] Member dang ky, dang nhap, quen mat khau, doi mat khau, sua ho so.
- [x] Staff/admin dang nhap website va mo POS bang PIN.
- [ ] Website dat hang voi payment sandbox va theo doi trang thai.
- [x] POS checkout, service order, kitchen queue, checkout order.
- [x] POS role policy da dong bo backend/frontend; cashier khong tao order, waiter chi served, barista chi preparing/ready, manager/owner/admin co override.
- [x] POS co void/cancel/refund/receipt/export UI baseline; printer/PDF va split payment con de phase sau.
- [x] Voucher/campaign/loyalty co log va report baseline.
- [x] Inventory tru nguyen lieu theo recipe baseline.
- [x] Dashboard/report export baseline; filter sau hon con de phase tiep.
- [x] Security baseline: CSRF, rate limit, DB lockout, secure session, audit log, app logger.
- [x] Test: PHP lint, JS check, smoke API; browser automation con de phase tiep.
- [ ] Backup/logging/deployment docs san sang.
