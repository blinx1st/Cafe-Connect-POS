<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Forgot password</p>
    <h1>Quên mật khẩu</h1>
    <p>Nhập email hoặc số điện thoại đã đăng ký. Hệ thống sẽ gửi link đặt lại mật khẩu tới Gmail/email của tài khoản.</p>
  </section>

  <section class="section-shell page-section auth-page-shell">
    <section class="auth-card auth-page-card">
      <h3>Gửi email đặt lại mật khẩu</h3>
      <form class="lookup-form wide" data-member-forgot-password>
        <label>Email / số điện thoại <input type="text" name="identity" required autocomplete="username"></label>
        <button type="submit">Gửi email</button>
      </form>
      <div class="account-actions">
        <a class="secondary-link" href="<?= e(base_url('login')) ?>">Quay lại đăng nhập</a>
      </div>
      <div class="notice" data-auth-message hidden></div>
    </section>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
