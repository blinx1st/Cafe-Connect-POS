<?php
$member = $appData['member'] ?? null;
$webStaff = $appData['web_staff'] ?? null;
?>

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
    <a class="nav-pill pos-header-link" href="<?= e(base_url('pos/login')) ?>" data-pos-header-link <?= $webStaff ? '' : 'hidden' ?>>POS</a>
  </nav>
  <div class="site-actions member-nav" data-member-nav>
    <?php if ($member): ?>
      <button class="member-menu-toggle" type="button" data-member-menu-toggle aria-expanded="false">
        Chào, <?= e($member['customer_name'] ?? 'thành viên') ?>
        <span>▾</span>
      </button>
      <div class="member-dropdown" data-member-menu hidden>
        <a href="<?= e(base_url('account')) ?>">Thông tin cá nhân</a>
        <a href="<?= e(base_url('account#change-password')) ?>">Thay đổi password</a>
        <button type="button" data-member-logout>Đăng xuất</button>
      </div>
    <?php elseif ($webStaff): ?>
      <button class="member-menu-toggle" type="button" data-member-menu-toggle aria-expanded="false">
        Chào, <?= e($webStaff['staff_name'] ?? 'nhân viên') ?>
        <span>▾</span>
      </button>
      <div class="member-dropdown" data-member-menu hidden>
        <a href="<?= e(base_url('account')) ?>">Thông tin nhân viên</a>
        <a href="<?= e(base_url('pos/login')) ?>">Mở POS</a>
        <button type="button" data-member-logout>Đăng xuất</button>
      </div>
    <?php else: ?>
      <a href="<?= e(base_url('login')) ?>">Đăng nhập</a>
      <a class="nav-pill" href="<?= e(base_url('register')) ?>">Đăng ký</a>
    <?php endif; ?>
  </div>
</header>

<?php if (!$installed): ?>
  <div class="setup-banner">
    Database chưa sẵn sàng. Mở <a href="<?= e(base_url('install.php')) ?>">install.php</a> để import schema mẫu.
  </div>
<?php endif; ?>
