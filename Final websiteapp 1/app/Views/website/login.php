<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member login</p>
    <h1>Đăng nhập tài khoản</h1>
    <p>Đăng nhập bằng email, số điện thoại hoặc mã nhân viên để xem hồ sơ, đặt hàng, tích điểm và mở POS theo đúng vai trò.</p>
  </section>

  <section class="section-shell page-section auth-page-shell">
    <section class="auth-card auth-page-card login-auth-card">
      <div class="auth-title-row">
        <div>
          <p class="eyebrow">Cafe Connect Account</p>
          <h3>Đăng nhập</h3>
        </div>
        <span class="auth-badge">Member</span>
      </div>

      <form class="create-form compact" data-member-login>
        <label>Email / SĐT / Mã nhân viên
          <input type="text" name="identity" placeholder="VD: 0900000001 hoặc ADMIN001" required autocomplete="username">
        </label>
        <label>Mật khẩu
          <span class="password-field">
            <input type="password" name="password" placeholder="Nhập mật khẩu" required autocomplete="current-password">
            <button type="button" data-password-toggle>Hiện</button>
          </span>
        </label>
        <button type="submit" class="primary-btn">Đăng nhập</button>
      </form>

      <div class="account-actions login-links-row">
        <a class="secondary-link" href="<?= e(base_url('register')) ?>">Chưa có tài khoản? Đăng ký</a>
        <a class="secondary-link" href="<?= e(base_url('forgot-password')) ?>">Quên mật khẩu?</a>
      </div>

      <div class="login-helper-box">
        <div>
          <h4>Đăng nhập dùng chung</h4>
          <p>Khách hàng dùng email/SĐT. Nhân viên dùng mã NV, email hoặc SĐT POS; sau đó nhập PIN riêng để mở ca.</p>
        </div>
        <div class="login-demo-grid">
          <button type="button" data-fill-login data-identity="0900000001" data-password="123456">
            <span>Member</span>
            <strong>0900000001 / 123456</strong>
          </button>
          <button type="button" data-fill-login data-identity="ADMIN001" data-password="admin123">
            <span>Admin</span>
            <strong>ADMIN001 / admin123</strong>
          </button>
          <button type="button" data-fill-login data-identity="admin@cafeconnect.test" data-password="admin123">
            <span>Admin email</span>
            <strong>admin@cafeconnect.test</strong>
          </button>
          <button type="button" data-fill-login data-identity="0911000006" data-password="admin123">
            <span>Admin SĐT</span>
            <strong>0911000006</strong>
          </button>
        </div>
      </div>
    </section>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>