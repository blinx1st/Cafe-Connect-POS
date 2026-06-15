<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero compact-hero">
    <p class="eyebrow">Menu</p>
    <h1>Menu sản phẩm</h1>
    <p>Chọn món tại đây. Giỏ hàng được lưu tạm trên trình duyệt để sang checkout không bị mất.</p>
  </section>

  <section class="section-shell page-section">
    <div class="panel-head">
      <div>
        <h2>Tất cả sản phẩm</h2>
        <p>Tìm kiếm, lọc theo danh mục và sắp xếp menu từ MySQL.</p>
      </div>
      <a class="primary-btn" href="<?= e(base_url('checkout')) ?>">Mở giỏ hàng</a>
    </div>

    <div class="menu-toolbar">
      <label>Tìm món
        <input type="search" data-site-product-search placeholder="Nhập tên món">
      </label>
      <label>Danh mục
        <select data-site-category-filter>
          <option value="">Tất cả</option>
          <?php foreach (($appData['categories'] ?? []) as $category): ?>
            <option value="<?= e($category['category_code']) ?>"><?= e($category['category_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Sắp xếp
        <select data-site-sort>
          <option value="">Mặc định</option>
          <option value="price_asc">Giá tăng dần</option>
          <option value="price_desc">Giá giảm dần</option>
          <option value="name_desc">Tên Z-A</option>
        </select>
      </label>
    </div>

    <div class="product-grid" data-site-products></div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
