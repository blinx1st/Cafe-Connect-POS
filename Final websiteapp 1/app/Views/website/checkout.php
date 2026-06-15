<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero compact-hero">
    <p class="eyebrow">Checkout</p>
    <h1>Đặt món và tích điểm</h1>
    <p>Đăng nhập thành viên để dùng voucher, lưu đơn hàng, theo dõi trạng thái và cộng điểm tự động.</p>
  </section>

  <section class="order-band standalone">
    <div class="order-layout">
      <div class="section-title compact">
        <p class="eyebrow">Website order</p>
        <h2>Giỏ hàng website</h2>
        <p>COD sẽ tạo đơn chờ thanh toán. DemoPay nội bộ sẽ ghi nhận đã thanh toán ngay để phù hợp bản vận hành nội bộ v1.</p>
        <div class="section-actions">
          <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Tiếp tục chọn món</a>
          <a class="secondary-link" href="<?= e(base_url('login')) ?>">Đăng nhập</a>
        </div>
      </div>

      <aside class="cart-panel">
        <form class="lookup-form" data-member-lookup="site">
          <label>Số điện thoại / email <input type="text" name="identity" placeholder="0900000001"></label>
          <button type="submit">Tra cứu</button>
        </form>
        <div data-member-result="site" class="member-result muted-box">Đăng nhập hoặc tra cứu thành viên để xem điểm và voucher.</div>
        <div data-site-cart class="cart-list"></div>
        <label class="field">Voucher <select data-site-voucher><option value="">Không dùng voucher</option></select></label>
        <label class="field">Thanh toán
          <select data-site-payment>
            <option value="e_wallet">DemoPay e-wallet - paid</option>
            <option value="card">DemoPay card - paid</option>
            <option value="cash">COD - pending</option>
          </select>
        </label>
        <label class="field">Hình thức nhận
          <select data-site-fulfillment>
            <option value="pickup">Nhận tại quầy</option>
            <option value="delivery">Giao hàng</option>
          </select>
        </label>
        <label class="field">Số điện thoại nhận hàng
          <input type="tel" data-site-receiver-phone placeholder="Số điện thoại liên hệ khi giao/nhận">
        </label>
        <label class="field">Địa chỉ giao hàng
          <input type="text" data-site-delivery-address placeholder="Bắt buộc nếu chọn giao hàng">
        </label>
        <label class="field">Thời gian nhận dự kiến
          <input type="datetime-local" data-site-requested-at>
        </label>
        <label class="field">Ghi chú
          <textarea data-site-customer-note rows="3" placeholder="Ít đá, thêm muỗng, ghi chú giao hàng..."></textarea>
        </label>
        <div data-site-totals class="totals"></div>
        <button class="primary-btn full" type="button" data-site-checkout <?= $installed ? '' : 'disabled' ?>>Đặt hàng</button>
      </aside>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
