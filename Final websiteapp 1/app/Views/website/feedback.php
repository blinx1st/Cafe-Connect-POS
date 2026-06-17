<?php
$member = $appData['member'] ?? null;
require VIEW_PATH . '/website/partials/header.php';
?>

<main>
  <section class="page-hero feedback-hero">
    <div>
      <p class="eyebrow">Member feedback</p>
      <h1>Gửi phản hồi</h1>
      <p>Chia sẻ trải nghiệm của bạn về món uống, đơn hàng, voucher hoặc dịch vụ. Phản hồi sẽ được gửi trực tiếp tới Gmail quản trị của Cafe Connect.</p>
    </div>
  </section>

  <section class="section-shell feedback-section" id="member-feedback">
    <div class="feedback-layout">
      <article class="feedback-intro">
        <span class="feedback-badge">Gửi đánh giá tới Cafe Connect</span>
        <h3><?= $member ? 'Cảm ơn ' . e($member['customer_name'] ?? 'thành viên') : 'Đăng nhập để gửi phản hồi' ?></h3>
        <p>
          <?= $member
            ? 'Thông tin tài khoản của bạn sẽ được đính kèm trong email để quán có thể kiểm tra đơn hàng, voucher hoặc điểm thành viên khi cần.'
            : 'Bạn cần đăng nhập tài khoản thành viên trước khi gửi feedback để Cafe Connect xác thực đúng người gửi.' ?>
        </p>
        <?php if (!$member): ?>
          <a class="primary-btn" href="<?= e(base_url('login')) ?>">Đăng nhập thành viên</a>
        <?php else: ?>
          <a class="secondary-link" href="<?= e(base_url('account')) ?>">Xem hồ sơ của tôi</a>
        <?php endif; ?>
      </article>

      <form class="feedback-card" data-member-feedback>
        <div class="feedback-grid">
          <label>
            Chủ đề
            <select name="topic" required>
              <option value="service">Dịch vụ</option>
              <option value="product">Sản phẩm</option>
              <option value="delivery">Giao hàng</option>
              <option value="website">Website / đặt hàng</option>
              <option value="loyalty">Thành viên / voucher</option>
              <option value="other">Khác</option>
            </select>
          </label>
          <label>
            Đánh giá
            <select name="rating" required>
              <option value="5">5 sao - Rất hài lòng</option>
              <option value="4">4 sao - Hài lòng</option>
              <option value="3">3 sao - Bình thường</option>
              <option value="2">2 sao - Cần cải thiện</option>
              <option value="1">1 sao - Không hài lòng</option>
            </select>
          </label>
        </div>
        <label>
          Nội dung phản hồi
          <textarea name="message" rows="6" maxlength="2000" placeholder="Nhập góp ý, trải nghiệm đặt hàng, chất lượng món hoặc vấn đề cần hỗ trợ..." required></textarea>
        </label>
        <div class="feedback-actions">
          <p data-feedback-message hidden></p>
          <button class="primary-btn" type="submit">Gửi đánh giá</button>
        </div>
      </form>
    </div>
  </section>
</main>

<?php require VIEW_PATH . '/website/partials/footer.php'; ?>