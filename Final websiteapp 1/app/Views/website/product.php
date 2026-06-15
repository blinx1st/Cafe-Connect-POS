<?php
$productId = (int) ($_GET['id'] ?? 0);
$product = $productId > 0 && $installed ? (new \App\Models\Product())->detail($productId) : null;
require VIEW_PATH . '/website/partials/header.php';
?>

<main class="page-main" data-product-detail-page data-product-id="<?= e((string) $productId) ?>">
  <section class="page-hero compact-hero">
    <p class="eyebrow">Product detail</p>
    <h1><?= e($product['product_name'] ?? 'Chi tiết sản phẩm') ?></h1>
    <p><?= e($product['take_note'] ?? 'Xem thông tin sản phẩm, giá bán và tình trạng tồn kho trước khi đặt hàng.') ?></p>
  </section>

  <section class="section-shell page-section">
    <?php if (!$product): ?>
      <div class="auth-card">
        <h2>Không tìm thấy sản phẩm</h2>
        <p>Sản phẩm không tồn tại hoặc database chưa sẵn sàng.</p>
        <a class="primary-btn" href="<?= e(base_url('menu')) ?>">Quay lại menu</a>
      </div>
    <?php else: ?>
      <article class="product-detail-layout" data-product-detail>
        <div class="product-detail-media">
          <img src="<?= e(asset_url(str_replace('assets/', '', (string) ($product['image'] ?? 'assets/images/coffee-1.png')))) ?>" alt="<?= e($product['product_name']) ?>">
        </div>
        <div class="product-detail-copy">
          <p class="eyebrow"><?= e($product['category_name'] ?? $product['category']) ?></p>
          <h2><?= e($product['product_name']) ?></h2>
          <p><?= e($product['take_note'] ?: 'Sản phẩm đang bán tại Cafe Connect.') ?></p>
          <div class="metric-grid two">
            <div class="metric"><strong><?= e(money((float) $product['price'])) ?></strong><small>Giá bán</small></div>
            <div class="metric"><strong><?= ((float) $product['stock_quantity'] > 0) ? e((string) (float) $product['stock_quantity']) : 'Tạm hết' ?></strong><small>Tình trạng kho</small></div>
          </div>
          <div class="section-actions">
            <button type="button" class="primary-btn" data-site-add="<?= e((string) $product['id']) ?>" <?= !empty($product['is_out_of_stock']) ? 'disabled' : '' ?>>Thêm vào giỏ</button>
            <a class="secondary-link" href="<?= e(base_url('checkout')) ?>">Mở giỏ hàng</a>
          </div>
        </div>
      </article>
    <?php endif; ?>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
