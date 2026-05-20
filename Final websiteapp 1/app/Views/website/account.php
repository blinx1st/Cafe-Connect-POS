<?php
$member = $appData['member'] ?? null;
require VIEW_PATH . '/website/partials/header.php';
?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member profile</p>
    <h1>Hồ sơ tài khoản</h1>
    <p>Quản lý thông tin thành viên, điểm tích lũy, voucher, món yêu thích và lịch sử hóa đơn từ cùng dữ liệu POS.</p>
  </section>

  <section class="section-shell page-section">
    <div class="auth-card profile-empty" data-account-guest <?= $member ? 'hidden' : '' ?>>
      <p class="eyebrow">Cần đăng nhập</p>
      <h2>Đăng nhập để xem hồ sơ</h2>
      <p>Tài khoản thành viên giúp lưu lịch sử mua hàng, dùng voucher và tích điểm tự động khi đặt món trên website.</p>
      <div class="section-actions">
        <a class="primary-btn" href="<?= e(base_url('login')) ?>">Đăng nhập</a>
        <a class="secondary-link" href="<?= e(base_url('register')) ?>">Đăng ký</a>
      </div>
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
