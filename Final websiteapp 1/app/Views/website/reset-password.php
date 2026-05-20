<?php
$token = trim((string) ($_GET['token'] ?? ''));
require VIEW_PATH . '/website/partials/header.php';
?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Reset password</p>
    <h1>Đặt lại mật khẩu</h1>
    <p>Link đặt lại mật khẩu chỉ dùng một lần và hết hạn sau 30 phút.</p>
  </section>

  <section class="section-shell page-section auth-page-shell">
    <section class="auth-card auth-page-card">
      <h3>Mật khẩu mới</h3>
      <form class="create-form compact" data-member-reset-password>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Mật khẩu mới <input type="password" name="password" minlength="6" required autocomplete="new-password"></label>
        <label>Xác nhận mật khẩu <input type="password" name="password_confirm" minlength="6" required autocomplete="new-password"></label>
        <button type="submit" class="primary-btn" <?= $token === '' ? 'disabled' : '' ?>>Đặt lại mật khẩu</button>
      </form>
      <?php if ($token === ''): ?>
        <div class="notice danger">Link đặt lại mật khẩu không có token hợp lệ.</div>
      <?php endif; ?>
      <div class="account-actions">
        <a class="secondary-link" href="<?= e(base_url('forgot-password')) ?>">Gửi lại link</a>
        <a class="secondary-link" href="<?= e(base_url('login')) ?>">Đăng nhập</a>
      </div>
      <div class="notice" data-auth-message hidden></div>
    </section>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
