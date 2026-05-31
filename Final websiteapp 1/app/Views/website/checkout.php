<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Checkout</p>
    <h1>Đặt món và tích điểm</h1>
    <p>Đăng nhập thành viên để dùng voucher, lưu hóa đơn vào hồ sơ và cộng điểm tự động.</p>
  </section>

  <section class="order-band standalone">
    <div class="order-layout">
      <div class="section-title compact">
        <p class="eyebrow">Website order</p>
        <h2>Giỏ hàng website</h2>
        <p>Giỏ hàng lấy từ trình duyệt. Sau thanh toán, invoice được ghi vào MySQL với sales_channel = website.</p>
        <div class="section-actions">
          <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Tiếp tục chọn món</a>
          <a class="secondary-link" href="<?= e(base_url('login')) ?>">Đăng nhập</a>
        </div>
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
        <label class="field">Hinh thuc nhan
          <select data-site-fulfillment>
            <option value="pickup">Nhan tai quay</option>
            <option value="delivery">Giao hang</option>
          </select>
        </label>
        <label class="field">Dia chi giao hang
          <input type="text" data-site-delivery-address placeholder="Nhap dia chi neu chon giao hang">
        </label>
        <label class="field">Thoi gian nhan du kien
          <input type="datetime-local" data-site-requested-at>
        </label>
        <label class="field">Ghi chu
          <textarea data-site-customer-note rows="3" placeholder="It da, them muong, ghi chu giao hang..."></textarea>
        </label>
        <div data-site-totals class="totals"></div>
        <button class="primary-btn full" type="button" data-site-checkout <?= $installed ? '' : 'disabled' ?>>Đặt hàng</button>
      </aside>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
