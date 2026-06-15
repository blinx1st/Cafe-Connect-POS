<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main" data-order-detail-page>
  <section class="page-hero compact-hero">
    <p class="eyebrow">Order tracking</p>
    <h1>Theo dõi đơn hàng</h1>
    <p>Xem chi tiết món, thanh toán, trạng thái và receipt của đơn hàng website.</p>
  </section>

  <section class="section-shell page-section">
    <div class="panel-head">
      <div>
        <h2>Chi tiết đơn hàng</h2>
        <p>Dữ liệu lấy từ website_orders, invoices, payments và invoice_details.</p>
      </div>
      <div class="section-actions">
        <a class="secondary-link" href="<?= e(base_url('account')) ?>">Hồ sơ</a>
        <a class="primary-btn" href="<?= e(base_url('menu')) ?>">Tiếp tục mua hàng</a>
      </div>
    </div>
    <div class="order-detail-shell" data-order-detail>
      <div class="empty-state">Đang tải đơn hàng...</div>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
