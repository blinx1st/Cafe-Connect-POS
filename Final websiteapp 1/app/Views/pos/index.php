<?php if (!$installed): ?>
  <main class="pos-main">
    <div class="setup-banner in-flow">
      Database chưa sẵn sàng. Mở <a href="<?= e(base_url('install.php')) ?>">install.php</a> để import schema mẫu.
    </div>
  </main>
<?php else: ?>
  <div id="pos-app" class="pos-app-shell"></div>
<?php endif; ?>
