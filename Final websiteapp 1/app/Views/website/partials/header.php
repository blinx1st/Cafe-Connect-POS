<?php $member = $appData['member'] ?? null; ?>

<header class="site-header" data-header>
  <a class="brand" href="<?= e(base_url()) ?>">Cafe Connect</a>
  <button class="icon-menu" type="button" data-nav-toggle aria-label="Mở menu">
    <span></span><span></span><span></span>
  </button>
  <nav class="site-nav" data-nav>
    <a href="<?= e(base_url()) ?>">Trang chủ</a>
    <a href="<?= e(base_url('menu')) ?>">Menu</a>
    <a href="<?= e(base_url('checkout')) ?>">Đặt hàng</a>
    <a href="<?= e(base_url('member')) ?>">Thành viên</a>
    <span class="member-nav" data-member-nav>
      <?php if ($member): ?>
        <a class="member-greeting" href="<?= e(base_url('account')) ?>">Chào, <?= e($member['customer_name'] ?? 'thành viên') ?></a>
        <a href="<?= e(base_url('account')) ?>">Hồ sơ</a>
        <button class="nav-link-button" type="button" data-member-logout>Đăng xuất</button>
      <?php else: ?>
        <a href="<?= e(base_url('login')) ?>">Đăng nhập</a>
        <a href="<?= e(base_url('register')) ?>">Đăng ký</a>
        <a href="<?= e(base_url('account')) ?>">Hồ sơ</a>
      <?php endif; ?>
    </span>
    <a class="nav-pill" href="<?= e(base_url('pos/login')) ?>">POS</a>
  </nav>
</header>

<?php if (!$installed): ?>
  <div class="setup-banner">
    Database chưa sẵn sàng. Mở <a href="<?= e(base_url('install.php')) ?>">install.php</a> để import schema mẫu.
  </div>
<?php endif; ?>
