<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main>
  <section class="hero-section" id="home">
    <div class="hero-copy">
      <p class="eyebrow">CRM + POS Omnichannel</p>
      <h1>Cafe Connect</h1>
      <p>Website khách hàng kết nối trực tiếp với POS: đơn hàng, điểm thành viên, voucher và lịch sử mua đều dùng chung một database.</p>
      <div class="hero-actions">
        <a class="primary-btn" href="<?= e(base_url('menu')) ?>">Xem menu</a>
        <a class="ghost-btn" href="<?= e(base_url('account')) ?>">Đăng nhập thành viên</a>
      </div>
    </div>
  </section>

  <section class="category-strip" aria-label="Danh mục">
    <article><img src="<?= e(asset_url('images/icon-hot.svg')) ?>" alt=""><span>Cà phê nóng</span></article>
    <article><img src="<?= e(asset_url('images/icon-cold.svg')) ?>" alt=""><span>Cà phê lạnh</span></article>
    <article><img src="<?= e(asset_url('images/icon-cup.svg')) ?>" alt=""><span>Trà & matcha</span></article>
    <article><img src="<?= e(asset_url('images/icon-dessert.svg')) ?>" alt=""><span>Bánh ngọt</span></article>
  </section>

  <section class="section-shell">
    <div class="section-title">
      <p class="eyebrow">Menu nổi bật</p>
      <h2>Sản phẩm đang bán</h2>
      <p>Danh sách lấy từ MySQL qua Model Product. Bấm thêm món rồi sang trang đặt hàng để thanh toán.</p>
    </div>
    <div class="product-grid" data-site-products data-product-limit="4"></div>
    <div class="section-actions">
      <a class="primary-btn" href="<?= e(base_url('menu')) ?>">Xem toàn bộ menu</a>
      <a class="secondary-link" href="<?= e(base_url('checkout')) ?>">Mở giỏ hàng</a>
    </div>
  </section>

  <section class="beans-band">
    <img class="beans-left" src="<?= e(asset_url('images/beans-left.png')) ?>" alt="">
    <div class="beans-copy">
      <h2>Check Out Our Best Coffee Beans</h2>
      <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Explore Our Products</a>
    </div>
    <img class="beans-right" src="<?= e(asset_url('images/beans-right.png')) ?>" alt="">
  </section>

  <section class="section-shell">
    <div class="section-title">
      <p class="eyebrow">Customer reviews</p>
      <h2>Khách hàng nói gì</h2>
    </div>
    <div class="testimonial-grid" data-reviews></div>
  </section>

  <section class="newsletter">
    <img class="newsletter-beans newsletter-left" src="<?= e(asset_url('images/newsletter-beans.png')) ?>" alt="">
    <div class="newsletter-content">
      <h2>Nhận ưu đãi 15%</h2>
      <p>Đăng ký newsletter để nhận voucher và thông tin campaign mới.</p>
      <form class="subscribe-form" data-newsletter-form>
        <label><img src="<?= e(asset_url('images/mail.svg')) ?>" alt=""><input name="email" type="email" placeholder="Email address" required></label>
        <button type="submit">Subscribe</button>
      </form>
    </div>
    <img class="newsletter-beans newsletter-right" src="<?= e(asset_url('images/newsletter-beans.png')) ?>" alt="">
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
