<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member register</p>
    <h1>Đăng ký thành viên</h1>
    <p>Tạo tài khoản để lưu đơn hàng, nhận voucher và tích điểm trên cả website lẫn POS tại quán.</p>
  </section>

  <section class="section-shell page-section auth-page-shell">
    <section class="auth-card auth-page-card">
      <h3>Tạo tài khoản</h3>
      <form class="create-form compact" data-member-register>
        <label>Họ tên <input name="customer_name" required autocomplete="name"></label>
        <label>Số điện thoại <input name="phone_number" required autocomplete="tel"></label>
        <label>Email <input type="email" name="email" required autocomplete="email"></label>
        <label>Mật khẩu <input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
        <label>Xác nhận mật khẩu <input type="password" name="password_confirm" minlength="6" required autocomplete="new-password"></label>
        <button type="submit" class="primary-btn">Đăng ký</button>
      </form>
      <div class="account-actions">
        <a class="secondary-link" href="<?= e(base_url('login')) ?>">Đã có tài khoản? Đăng nhập</a>
      </div>
      <section class="member-auth-status" data-member-auth-status></section>
    </section>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
