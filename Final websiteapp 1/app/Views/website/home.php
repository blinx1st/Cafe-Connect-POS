<header class="site-header" data-header>
  <a class="brand" href="<?= e(base_url('index.php')) ?>#home">Cafe Connect</a>
  <button class="icon-menu" type="button" data-nav-toggle aria-label="Mở menu">
    <span></span><span></span><span></span>
  </button>
  <nav class="site-nav" data-nav>
    <a href="#menu">Menu</a>
    <a href="#account">Tài khoản</a>
    <a href="#order">Đặt hàng</a>
    <a href="#member">Thành viên</a>
    <a href="#reviews">Đánh giá</a>
    <a class="nav-pill" href="<?= e(base_url('pos.php')) ?>">POS</a>
  </nav>
</header>

<?php if (!$installed): ?>
  <div class="setup-banner">
    Database chưa sẵn sàng. Mở <a href="<?= e(base_url('install.php')) ?>">install.php</a> để import schema mẫu.
  </div>
<?php endif; ?>

<main>
  <section class="hero-section" id="home">
    <div class="hero-copy">
      <p class="eyebrow">CRM + POS Omnichannel</p>
      <h1>Cafe Connect</h1>
      <p>Website khách hàng kết nối trực tiếp với POS: đơn hàng, điểm thành viên, voucher và lịch sử mua đều dùng chung một database.</p>
      <div class="hero-actions">
        <a class="primary-btn" href="#account">Đăng nhập thành viên</a>
        <a class="ghost-btn" href="#order">Đặt món</a>
      </div>
    </div>
  </section>

  <section class="category-strip" aria-label="Danh mục" data-category-strip>
    <article><img src="<?= e(asset_url('images/icon-hot.svg')) ?>" alt=""><span>Cà phê nóng</span></article>
    <article><img src="<?= e(asset_url('images/icon-cold.svg')) ?>" alt=""><span>Cà phê lạnh</span></article>
    <article><img src="<?= e(asset_url('images/icon-cup.svg')) ?>" alt=""><span>Trà & matcha</span></article>
    <article><img src="<?= e(asset_url('images/icon-dessert.svg')) ?>" alt=""><span>Bánh ngọt</span></article>
  </section>

  <section class="section-shell" id="account">
    <div class="section-title">
      <p class="eyebrow">Member account</p>
      <h2>Đăng nhập hoặc đăng ký thành viên</h2>
      <p>Dùng số điện thoại để đăng nhập. Thành viên đăng ký trên website sẽ xuất hiện ngay trong POS CRM.</p>
    </div>
    <div class="member-account-grid">
      <section class="auth-card">
        <h3>Đăng nhập</h3>
        <form class="lookup-form wide" data-member-login>
          <label>Số điện thoại / email <input type="text" name="identity" value="0900000001" required></label>
          <button type="submit">Đăng nhập</button>
        </form>
      </section>
      <section class="auth-card">
        <h3>Đăng ký nhanh</h3>
        <form class="create-form compact" data-member-register>
          <label>Họ tên <input name="customer_name" required></label>
          <label>Số điện thoại <input name="phone_number" required></label>
          <label>Email <input type="email" name="email"></label>
          <button type="submit" class="primary-btn">Tạo tài khoản</button>
        </form>
      </section>
      <section class="auth-card member-auth-status" data-member-auth-status></section>
    </div>
  </section>

  <section class="section-shell" id="menu">
    <div class="section-title">
      <p class="eyebrow">Menu từ MySQL</p>
      <h2>Sản phẩm đang bán</h2>
      <p>Danh sách lấy từ Model Product, gồm bảng products, product_images và product_categories.</p>
    </div>
    <div class="product-grid" data-site-products></div>
  </section>

  <section class="order-band" id="order">
    <div class="order-layout">
      <div class="section-title compact">
        <p class="eyebrow">Website order</p>
        <h2>Đặt món và tích điểm</h2>
        <p>Đăng nhập thành viên để dùng voucher, lưu hóa đơn vào hồ sơ và cộng điểm tự động.</p>
      </div>

      <aside class="cart-panel">
        <form class="lookup-form" data-member-lookup="site">
          <label>Số điện thoại / email <input type="text" name="identity" value="0900000001" placeholder="0900000001"></label>
          <button type="submit">Tra cứu</button>
        </form>
        <div data-member-result="site" class="member-result muted-box">Đăng nhập hoặc tra cứu số điện thoại để xem điểm và voucher.</div>
        <div data-site-cart class="cart-list"></div>
        <label class="field">Voucher <select data-site-voucher><option value="">Không dùng voucher</option></select></label>
        <label class="field">Thanh toán
          <select data-site-payment>
            <option value="e_wallet">Ví điện tử</option>
            <option value="card">Thẻ</option>
            <option value="cash">Tiền mặt khi nhận</option>
          </select>
        </label>
        <div data-site-totals class="totals"></div>
        <button class="primary-btn full" type="button" data-site-checkout <?= $installed ? '' : 'disabled' ?>>Đặt hàng</button>
      </aside>
    </div>
  </section>

  <section class="section-shell" id="member">
    <div class="member-portal">
      <div class="section-title compact">
        <p class="eyebrow">Member portal</p>
        <h2>Hồ sơ khách hàng</h2>
        <p>Tra cứu cùng dữ liệu POS để xem hạng, điểm, voucher, yêu thích và lịch sử hóa đơn.</p>
      </div>
      <form class="lookup-form wide" data-member-lookup="portal">
        <label>Số điện thoại / email <input type="text" name="identity" value="0900000001"></label>
        <button type="submit">Mở hồ sơ</button>
      </form>
      <div data-member-result="portal" class="profile-dashboard"></div>
    </div>
  </section>

  <section class="section-shell" id="reviews">
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

<footer class="footer">
  <strong>Cafe Connect</strong>
  <span>MVC PHP + XAMPP + MySQL.</span>
</footer>
