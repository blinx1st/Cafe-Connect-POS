<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">Member portal</p>
    <h1>Hồ sơ khách hàng</h1>
    <p>Tra cứu cùng dữ liệu POS để xem hạng, điểm, voucher, yêu thích và lịch sử hóa đơn.</p>
  </section>

  <section class="section-shell page-section">
    <form class="lookup-form wide" data-member-lookup="portal">
      <label>Số điện thoại / email <input type="text" name="identity" value="0900000001"></label>
      <button type="submit">Mở hồ sơ</button>
    </form>
    <div data-member-result="portal" class="profile-dashboard"></div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
