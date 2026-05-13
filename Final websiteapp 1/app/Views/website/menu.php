<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Menu</p>
    <h1>Menu sản phẩm</h1>
    <p>Chọn món tại đây. Giỏ hàng được lưu tạm để chuyển sang trang checkout không bị mất.</p>
  </section>

  <section class="section-shell page-section">
    <div class="panel-head">
      <div>
        <h2>Tất cả sản phẩm</h2>
        <p>Dữ liệu từ products, product_categories và product_images.</p>
      </div>
      <a class="primary-btn" href="<?= e(base_url('checkout')) ?>">Mở giỏ hàng</a>
    </div>
    <div class="product-grid" data-site-products></div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
