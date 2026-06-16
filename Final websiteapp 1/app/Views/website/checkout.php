<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main" data-site-checkout-page>
  <section class="page-hero compact-hero checkout-hero">
    <p class="eyebrow">Checkout</p>
    <h1>Đặt món và tích điểm</h1>
    <p>Kiểm tra giỏ hàng, chọn hình thức nhận món, áp voucher và xác nhận thanh toán trên cùng một trang.</p>
  </section>

  <section class="section-shell page-section checkout-workspace">
    <div class="checkout-stepper" aria-label="Quy trình đặt hàng">
      <span><strong>1</strong> Giỏ hàng</span>
      <span><strong>2</strong> Nhận hàng</span>
      <span><strong>3</strong> Thanh toán</span>
    </div>

    <div class="checkout-grid">
      <section class="checkout-card checkout-cart-card">
        <div class="panel-head">
          <div>
            <p class="eyebrow">Bước 1</p>
            <h2>Giỏ hàng</h2>
            <p><span data-site-cart-count>0</span> món đang chờ xác nhận.</p>
          </div>
          <div class="section-actions">
            <a class="secondary-link" href="<?= e(base_url('menu')) ?>">Chọn thêm món</a>
            <button class="ghost-btn compact danger" type="button" data-site-cart-clear>Xóa giỏ</button>
          </div>
        </div>
        <div data-site-cart class="cart-list site-cart-list"></div>
      </section>

      <aside class="checkout-side">
        <section class="checkout-card">
          <div class="panel-head">
            <div>
              <p class="eyebrow">Bước 2</p>
              <h2>Nhận hàng</h2>
            </div>
          </div>

          <div class="fulfillment-grid">
            <label class="fulfillment-card">
              <input type="radio" name="site_fulfillment" value="pickup" data-site-fulfillment checked>
              <span>
                <strong>Nhận tại quầy</strong>
                <small>Đơn được chuẩn bị tại chi nhánh đã chọn.</small>
              </span>
            </label>
            <label class="fulfillment-card">
              <input type="radio" name="site_fulfillment" value="delivery" data-site-fulfillment>
              <span>
                <strong>Giao hàng</strong>
                <small>Cần SĐT và địa chỉ nhận hàng.</small>
              </span>
            </label>
          </div>

          <div class="checkout-fields">
            <label class="field">Chi nhánh xử lý
              <select data-site-branch>
                <?php foreach (($appData['branches'] ?? []) as $branch): ?>
                  <option value="<?= e((string) $branch['id']) ?>" <?= ($branch['branch_name'] ?? '') === 'Coffee Connect số 1' ? 'selected' : '' ?>><?= e($branch['branch_name']) ?><?= !empty($branch['district']) ? ' - ' . e($branch['district']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <p class="checkout-note branch-info" data-site-branch-info></p>
            <p class="checkout-note" data-pickup-only>Vui lòng đến quầy theo thời gian nhận dự kiến. Nhân viên sẽ đối chiếu mã hóa đơn.</p>
            <label class="field" data-delivery-only hidden>Số điện thoại nhận hàng
              <input type="tel" data-site-receiver-phone placeholder="Ví dụ: 0900000001">
            </label>
            <label class="field" data-delivery-only hidden>Địa chỉ giao hàng
              <input type="text" data-site-delivery-address placeholder="Số nhà, đường, phường/xã, quận/huyện">
            </label>
            <label class="field">Thời gian nhận dự kiến
              <input type="datetime-local" data-site-requested-at>
            </label>
            <label class="field">Ghi chú đơn hàng
              <textarea data-site-customer-note rows="3" placeholder="Ghi chú giao hàng hoặc yêu cầu chung cho đơn..."></textarea>
            </label>
          </div>
        </section>

        <section class="checkout-card checkout-summary-card">
          <div class="panel-head">
            <div>
              <p class="eyebrow">Bước 3</p>
              <h2>Thanh toán</h2>
            </div>
          </div>

          <div class="checkout-fields">
            <div data-member-result="site" class="member-result muted-box">Đăng nhập thành viên để dùng voucher, tích điểm và theo dõi đơn hàng.</div>
            <label class="field">Voucher
              <select data-site-voucher><option value="">Không dùng voucher</option></select>
            </label>
            <label class="field">Phương thức thanh toán
              <select data-site-payment>
                <option value="e_wallet">DemoPay e-wallet - đã thanh toán</option>
                <option value="card">DemoPay card - đã thanh toán</option>
                <option value="cash">COD - thanh toán khi nhận hàng</option>
              </select>
            </label>
            <p class="checkout-note" data-site-payment-hint></p>
          </div>

          <div data-site-totals class="totals checkout-totals"></div>
          <button class="primary-btn full" type="button" data-site-checkout <?= $installed ? '' : 'disabled' ?>>Đặt hàng</button>
          <p class="checkout-note">Sau khi đặt hàng thành công, hệ thống sẽ chuyển sang trang chi tiết đơn để xem trạng thái và receipt.</p>
        </section>
      </aside>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
