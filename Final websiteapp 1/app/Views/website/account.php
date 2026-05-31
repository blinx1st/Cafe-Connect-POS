<?php
$member = $appData['member'] ?? null;
$webStaff = $appData['web_staff'] ?? null;
$hasAccount = (bool) $member || (bool) $webStaff;
require VIEW_PATH . '/website/partials/header.php';
?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Account profile</p>
    <h1>Hồ sơ tài khoản</h1>
    <p>Quản lý thông tin cá nhân, mật khẩu, điểm tích lũy, voucher, lịch sử hóa đơn và quyền truy cập POS theo loại tài khoản.</p>
  </section>

  <section class="section-shell page-section <?= $hasAccount ? 'account-authenticated' : 'account-guest' ?>" data-account-root>
    <?php if (!$hasAccount): ?>
      <div class="auth-card profile-empty" data-account-guest>
        <p class="eyebrow">Cần đăng nhập</p>
        <h2>Đăng nhập để xem hồ sơ</h2>
        <p>Tài khoản giúp lưu lịch sử mua hàng, dùng voucher, tích điểm hoặc mở POS nếu bạn là nhân viên.</p>
        <div class="section-actions">
          <a class="primary-btn" href="<?= e(base_url('login')) ?>">Đăng nhập</a>
          <a class="secondary-link" href="<?= e(base_url('register')) ?>">Đăng ký</a>
        </div>
      </div>
    <?php endif; ?>

    <div class="account-profile-layout staff-profile-layout" data-account-staff <?= (!$webStaff || $member) ? 'hidden' : '' ?>>
      <aside class="account-sidebar">
        <section class="auth-card staff-account-summary">
          <p class="eyebrow">Tài khoản nhân viên</p>
          <div class="profile-head">
            <span class="avatar"><?= e(substr((string) ($webStaff['staff_name'] ?? '?'), 0, 1)) ?></span>
            <div>
              <h3><?= e($webStaff['staff_name'] ?? 'Nhân viên') ?></h3>
              <p><?= e($webStaff['staff_code'] ?? '') ?> · <?= e(role_label((string) ($webStaff['staff_role'] ?? ''))) ?></p>
            </div>
          </div>
          <div class="metric-grid two">
            <div class="metric"><strong><?= e($webStaff['branch_name'] ?? 'Cafe Connect') ?></strong><small>Chi nhánh</small></div>
            <div class="metric"><strong><?= e(role_label((string) ($webStaff['staff_role'] ?? ''))) ?></strong><small>Vai trò</small></div>
          </div>
          <div class="account-actions">
            <a class="primary-btn" href="<?= e(base_url('pos/login')) ?>">Mở POS</a>
            <button class="secondary-btn" type="button" data-member-logout>Đăng xuất</button>
          </div>
        </section>

        <section class="auth-card" id="change-password">
          <h3>Đổi mật khẩu</h3>
          <form class="create-form compact" data-member-change-password>
            <label>Mật khẩu hiện tại <input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>Mật khẩu mới <input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
            <label>Xác nhận mật khẩu <input type="password" name="password_confirm" minlength="6" required autocomplete="new-password"></label>
            <button type="submit" class="primary-btn">Đổi mật khẩu</button>
          </form>
        </section>
      </aside>

      <section class="account-main">
        <section class="auth-card">
          <h3>Thông tin nhân viên</h3>
          <form class="create-form compact profile-form" data-member-profile-update>
            <label>Họ tên <input name="staff_name" value="<?= e($webStaff['staff_name'] ?? '') ?>" required autocomplete="name"></label>
            <label>Mã nhân viên <input name="staff_code" value="<?= e($webStaff['staff_code'] ?? '') ?>" disabled></label>
            <label>Email <input type="email" name="email" value="<?= e($webStaff['email'] ?? '') ?>" autocomplete="email"></label>
            <label>Số điện thoại <input name="phone_number" value="<?= e($webStaff['phone_number'] ?? '') ?>" autocomplete="tel"></label>
            <label>Vai trò <input value="<?= e(role_label((string) ($webStaff['staff_role'] ?? ''))) ?>" disabled></label>
            <label>Chi nhánh <input value="<?= e($webStaff['branch_name'] ?? '') ?>" disabled></label>
            <button type="submit" class="primary-btn">Lưu hồ sơ</button>
          </form>
        </section>

        <section class="auth-card">
          <h3>Quyền truy cập website</h3>
          <p>Tài khoản admin/staff đăng nhập được website như member, có thể cập nhật hồ sơ, đổi mật khẩu và mở POS. Điểm, voucher và lịch sử mua hàng chỉ áp dụng cho khách hàng thành viên.</p>
          <div class="metric-grid">
            <div class="metric"><strong>Website</strong><small>Đã đăng nhập</small></div>
            <div class="metric"><strong><?= e(role_label((string) ($webStaff['staff_role'] ?? ''))) ?></strong><small>Quyền POS</small></div>
            <div class="metric"><strong>PIN riêng</strong><small>Mở ca POS</small></div>
          </div>
        </section>
      </section>
    </div>

    <div class="account-profile-layout" data-account-member <?= !$member ? 'hidden' : '' ?>>
      <aside class="account-sidebar">
        <section class="auth-card member-auth-status" data-member-auth-status></section>
        <section class="auth-card" id="change-password">
          <h3>Đổi mật khẩu</h3>
          <form class="create-form compact" data-member-change-password>
            <label>Mật khẩu hiện tại <input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>Mật khẩu mới <input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
            <label>Xác nhận mật khẩu <input type="password" name="password_confirm" minlength="6" required autocomplete="new-password"></label>
            <button type="submit" class="primary-btn">Đổi mật khẩu</button>
          </form>
        </section>
      </aside>

      <section class="account-main">
        <section class="auth-card">
          <h3>Thông tin cá nhân</h3>
          <form class="create-form compact profile-form" data-member-profile-update>
            <label>Họ tên <input name="customer_name" value="<?= e($member['customer_name'] ?? '') ?>" required autocomplete="name"></label>
            <label>Số điện thoại <input name="phone_number" value="<?= e($member['phone_number'] ?? '') ?>" disabled></label>
            <label>Email <input type="email" name="email" value="<?= e($member['email'] ?? '') ?>" autocomplete="email"></label>
            <label>Ngày sinh <input type="date" name="birth_date" value="<?= e($member['birth_date'] ?? '') ?>"></label>
            <label>Giới tính
              <select name="gender">
                <option value="">Chưa cập nhật</option>
                <option value="male" <?= ($member['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Nam</option>
                <option value="female" <?= ($member['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Nữ</option>
                <option value="other" <?= ($member['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Khác</option>
              </select>
            </label>
            <label class="full-span">Địa chỉ <input name="address" value="<?= e($member['address'] ?? '') ?>" autocomplete="street-address"></label>
            <button type="submit" class="primary-btn">Lưu hồ sơ</button>
          </form>
        </section>

        <section class="profile-dashboard" data-member-result="account">
          <div class="empty-state">Đang tải hồ sơ thành viên...</div>
        </section>
      </section>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
