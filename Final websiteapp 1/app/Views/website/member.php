<?php require VIEW_PATH . '/website/partials/header.php'; ?>

<main class="page-main">
  <section class="page-hero">
    <p class="eyebrow">CRM khách hàng</p>
    <h1>Tra cứu hồ sơ khách hàng</h1>
    <p>Khu vực nội bộ dành cho nhân viên có quyền CRM để xem hạng, điểm, voucher, yêu thích và lịch sử hóa đơn.</p>
  </section>

  <section class="section-shell page-section">
    <form class="lookup-form wide" data-member-lookup="portal">
      <label>Số điện thoại / email <input type="text" name="identity" value="0900000001"></label>
      <button type="submit">Tra cứu hồ sơ</button>
    </form>
    <div data-member-result="portal" class="profile-dashboard"></div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>
