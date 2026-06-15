<?php
$productId = (int) ($_GET['id'] ?? 0);
$product = $productId > 0 && $installed ? (new \App\Models\Product())->detail($productId) : null;
$assetFor = static function (?string $path): string {
    $path = trim((string) ($path ?: 'assets/images/coffee-1.png'));
    return asset_url((string) preg_replace('#^assets/#', '', $path));
};
$categoryNotes = [
    'coffee' => ['Espresso', 'Sữa tươi', 'Hương rang ấm', 'Uống ngon nhất khi còn lạnh nhẹ'],
    'tea' => ['Trà ủ mới', 'Hương trái cây', 'Ít ngọt tùy chọn', 'Phù hợp dùng buổi chiều'],
    'smoothie' => ['Trái cây xay', 'Sữa chua', 'Kết cấu mịn', 'Dùng ngay sau khi pha'],
    'food' => ['Làm nóng trước khi dùng', 'Ăn kèm cà phê', 'Đóng gói mang đi', 'Phù hợp bữa nhẹ'],
    'seasonal' => ['Phiên bản giới hạn', 'Công thức theo mùa', 'Số lượng có hạn', 'Nên đặt trước'],
];
$notes = $product ? ($categoryNotes[$product['category']] ?? ['Pha chế theo chuẩn Cafe Connect', 'Có thể mang đi', 'Tích điểm thành viên', 'Áp voucher hợp lệ']) : [];
$images = $product['images'] ?? [];
$branches = $product['branch_inventory'] ?? [];
$relatedProducts = $product['related_products'] ?? [];
$stockTotal = $product ? (float) ($product['stock_quantity'] ?? 0) : 0;
$stockLabel = $stockTotal > 0 ? number_format($stockTotal, 0, ',', '.') . ' phần còn lại' : 'Tạm hết';
$stockClass = $stockTotal > 0 ? 'good' : 'bad';
require VIEW_PATH . '/website/partials/header.php';
?>

<main class="page-main product-detail-page" data-product-detail-page data-product-id="<?= e((string) $productId) ?>">
  <?php if (!$product): ?>
    <section class="section-shell page-section">
      <div class="auth-card product-not-found">
        <p class="eyebrow">Không tìm thấy</p>
        <h1>Không tìm thấy sản phẩm</h1>
        <p>Sản phẩm không tồn tại, đã ngừng bán hoặc database chưa sẵn sàng.</p>
        <div class="section-actions">
          <a class="primary-btn" href="<?= e(base_url('menu')) ?>">Quay lại menu</a>
          <a class="secondary-link" href="<?= e(base_url('')) ?>">Về trang chủ</a>
        </div>
      </div>
    </section>
  <?php else: ?>
    <section class="product-hero-detail">
      <div class="section-shell product-hero-grid">
        <nav class="product-breadcrumb" aria-label="breadcrumb">
          <a href="<?= e(base_url('')) ?>">Trang chủ</a>
          <span>/</span>
          <a href="<?= e(base_url('menu')) ?>">Menu</a>
          <span>/</span>
          <strong><?= e($product['product_name']) ?></strong>
        </nav>

        <div class="product-showcase">
          <div class="product-gallery">
            <figure class="product-main-image">
              <img src="<?= e($assetFor((string) ($product['image'] ?? ''))) ?>" alt="<?= e($product['product_name']) ?>">
              <figcaption><?= e($product['category_name'] ?? $product['category']) ?></figcaption>
            </figure>
            <?php if (count($images) > 1): ?>
              <div class="product-thumbs">
                <?php foreach ($images as $image): ?>
                  <button type="button" class="product-thumb" data-product-thumb="<?= e($assetFor((string) $image['image_path'])) ?>">
                    <img src="<?= e($assetFor((string) $image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?: $product['product_name']) ?>">
                  </button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <article class="product-info-card">
            <p class="eyebrow"><?= e($product['category_name'] ?? $product['category']) ?></p>
            <h1><?= e($product['product_name']) ?></h1>
            <p class="product-summary"><?= e($product['take_note'] ?: 'Sản phẩm đang bán tại Cafe Connect, phù hợp dùng tại quán hoặc mang đi.') ?></p>

            <div class="product-price-row">
              <strong><?= e(money((float) $product['price'])) ?></strong>
              <span class="status <?= e($stockClass) ?>"><?= e($stockLabel) ?></span>
            </div>

            <div class="product-quick-specs">
              <div><span>Size mặc định</span><strong>M</strong></div>
              <div><span>Tích điểm</span><strong><?= e((string) max(1, (int) floor(((float) $product['price']) / 10000))) ?> điểm</strong></div>
              <div><span>Kênh bán</span><strong>Website / POS</strong></div>
            </div>

            <div class="product-option-preview">
              <p>Gợi ý tuỳ chọn</p>
              <div>
                <span>Size S</span>
                <span>Size M</span>
                <span>Size L</span>
                <span>Ít đá</span>
                <span>Ít ngọt</span>
              </div>
            </div>

            <div class="section-actions product-actions">
              <button type="button" class="primary-btn" data-site-add="<?= e((string) $product['id']) ?>" <?= !empty($product['is_out_of_stock']) ? 'disabled' : '' ?>>
                Thêm vào giỏ
              </button>
              <a class="secondary-link" href="<?= e(base_url('checkout')) ?>">Mở giỏ hàng</a>
              <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Xem menu</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section-shell page-section product-detail-bands">
      <article class="product-story-card">
        <p class="eyebrow">Thông tin sản phẩm</p>
        <h2>Hương vị và trải nghiệm</h2>
        <p><?= e($product['take_note'] ?: 'Mỗi phần được chuẩn bị theo quy trình Cafe Connect, ưu tiên hương vị ổn định và tốc độ phục vụ.') ?></p>
        <div class="product-note-grid">
          <?php foreach ($notes as $note): ?>
            <div><span></span><?= e($note) ?></div>
          <?php endforeach; ?>
        </div>
      </article>

      <aside class="product-stock-card">
        <p class="eyebrow">Tồn kho chi nhánh</p>
        <h2>Tình trạng phục vụ</h2>
        <div class="branch-stock-list">
          <?php foreach ($branches as $branch): ?>
            <?php
              $status = (string) ($branch['stock_status'] ?? 'available');
              $label = $status === 'out' ? 'Tạm hết' : ($status === 'low' ? 'Sắp hết' : 'Sẵn sàng');
              $class = $status === 'out' ? 'bad' : ($status === 'low' ? '' : 'good');
            ?>
            <div class="branch-stock-item">
              <div>
                <strong><?= e($branch['branch_name']) ?></strong>
                <small><?= e($branch['district']) ?></small>
              </div>
              <span class="status <?= e($class) ?>"><?= e($label) ?> · <?= e(number_format((float) $branch['stock_quantity'], 0, ',', '.')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </aside>
    </section>

    <?php if ($relatedProducts): ?>
      <section class="section-shell page-section">
        <div class="panel-head">
          <div>
            <p class="eyebrow">Có thể bạn thích</p>
            <h2>Sản phẩm cùng danh mục</h2>
          </div>
          <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Xem tất cả</a>
        </div>
        <div class="product-grid related-product-grid">
          <?php foreach ($relatedProducts as $related): ?>
            <article class="product-card related-card">
              <img src="<?= e($assetFor((string) $related['image'])) ?>" alt="<?= e($related['product_name']) ?>">
              <div>
                <span class="tag"><?= e($related['category_name'] ?? $related['category']) ?></span>
                <h3><?= e($related['product_name']) ?></h3>
                <p><?= e($related['take_note'] ?: 'Sản phẩm đang bán tại Cafe Connect.') ?></p>
              </div>
              <footer>
                <strong><?= e(money((float) $related['price'])) ?></strong>
                <div class="card-actions">
                  <a class="secondary-link" href="<?= e(base_url('product?id=' . (int) $related['id'])) ?>">Chi tiết</a>
                  <button type="button" data-site-add="<?= e((string) $related['id']) ?>" <?= !empty($related['is_out_of_stock']) ? 'disabled' : '' ?>>Thêm</button>
                </div>
              </footer>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
