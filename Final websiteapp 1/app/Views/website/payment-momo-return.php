<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main" data-payment-return>
  <section class="page-hero compact-hero checkout-hero">
    <p class="eyebrow">MoMo</p>
    <h1>Kết quả thanh toán</h1>
    <p>Hệ thống đang kiểm tra trạng thái thanh toán MoMo và đồng bộ đơn hàng của bạn.</p>
  </section>

  <section class="section-shell page-section">
    <div class="auth-card" data-payment-return-status>
      <p class="eyebrow">Đang kiểm tra</p>
      <h2>Vui lòng chờ trong giây lát</h2>
      <p>MoMo sẽ gửi kết quả qua IPN. Nếu trang này mở trước IPN, trạng thái có thể cần vài giây để cập nhật.</p>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
