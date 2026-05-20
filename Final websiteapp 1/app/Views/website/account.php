<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member account</p>
    <h1>Tài khoản thành viên</h1>
    <p>Đăng nhập bằng số điện thoại/email và mật khẩu. Thành viên mới được lưu vào cùng bảng khách hàng của POS.</p>
  </section>

  <section class="section-shell page-section">
    <div class="member-account-grid">
      <section class="auth-card">
        <h3>Đăng nhập</h3>
        <form class="lookup-form wide" data-member-login>
          <label>Số điện thoại / email <input type="text" name="identity" value="0900000001" required></label>
          <label>Mật khẩu <input type="password" name="password" value="123456" required autocomplete="current-password"></label>
          <button type="submit">Đăng nhập</button>
        </form>
      </section>
      <section class="auth-card">
        <h3>Đăng ký nhanh</h3>
        <form class="create-form compact" data-member-register>
          <label>Họ tên <input name="customer_name" required></label>
          <label>Số điện thoại <input name="phone_number" required></label>
          <label>Email <input type="email" name="email"></label>
          <label>Mật khẩu <input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
          <label>Xác nhận mật khẩu <input type="password" name="password_confirm" minlength="6" required autocomplete="new-password"></label>
          <button type="submit" class="primary-btn">Tạo tài khoản</button>
        </form>
      </section>
      <section class="auth-card member-auth-status" data-member-auth-status></section>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
