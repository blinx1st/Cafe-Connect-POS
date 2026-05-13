<header class="site-header" data-header>
  <a class="brand" href="<?= e(base_url()) ?>">Cafe Connect</a>
  <button class="icon-menu" type="button" data-nav-toggle aria-label="Mở menu">
    <span></span><span></span><span></span>
  </button>
  <nav class="site-nav" data-nav>
    <a href="<?= e(base_url()) ?>">Trang chủ</a>
    <a href="<?= e(base_url('menu')) ?>">Menu</a>
    <a href="<?= e(base_url('account')) ?>">Tài khoản</a>
    <a href="<?= e(base_url('checkout')) ?>">Đặt hàng</a>
    <a href="<?= e(base_url('member')) ?>">Thành viên</a>
    <a class="nav-pill" href="<?= e(base_url('pos/login')) ?>">POS</a>
  </nav>
</header>

<?php if (!$installed): ?>
  <div class="setup-banner">
    Database chưa sẵn sàng. Mở <a href="<?= e(base_url('install.php')) ?>">install.php</a> để import schema mẫu.
  </div>
<?php endif; ?>
