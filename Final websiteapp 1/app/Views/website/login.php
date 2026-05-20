<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member login</p>
    <h1>Đăng nhập thành viên</h1>
    <p>Đăng nhập bằng số điện thoại hoặc email để xem hồ sơ, dùng voucher và tích điểm khi đặt món.</p>
  </section>

  <section class="section-shell page-section auth-page-shell">
    <section class="auth-card auth-page-card">
      <h3>Đăng nhập</h3>
      <form class="lookup-form wide" data-member-login>
        <label>Số điện thoại / email <input type="text" name="identity" value="0900000001" required autocomplete="username"></label>
        <label>Mật khẩu <input type="password" name="password" value="123456" required autocomplete="current-password"></label>
        <button type="submit">Đăng nhập</button>
      </form>
      <div class="account-actions">
        <a class="secondary-link" href="<?= e(base_url('register')) ?>">Tạo tài khoản mới</a>
        <a class="secondary-link" href="<?= e(base_url('forgot-password')) ?>">Quên mật khẩu?</a>
      </div>
      <section class="member-auth-status" data-member-auth-status></section>
    </section>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
