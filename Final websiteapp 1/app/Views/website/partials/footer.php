<footer class="footer">
  <a class="footer-brand" href="<?= e(base_url()) ?>">Coffee</a>
  <div class="footer-column">
    <h2>Privacy</h2>
    <a href="<?= e(base_url('account')) ?>">Member account</a>
    <a href="<?= e(base_url('member')) ?>">Member portal</a>
    <a href="<?= e(base_url('checkout')) ?>">Checkout</a>
  </div>
  <div class="footer-column">
    <h2>Services</h2>
    <a href="<?= e(base_url('menu')) ?>">Shop</a>
    <a href="<?= e(base_url('checkout')) ?>">Order ahead</a>
    <a href="<?= e(base_url('menu')) ?>">Menu</a>
  </div>
  <div class="footer-column">
    <h2>About Us</h2>
    <a href="<?= e(base_url()) ?>">Our story</a>
    <a href="<?= e(base_url('member')) ?>">CRM rewards</a>
    <a href="<?= e(base_url('pos/login')) ?>">POS system</a>
  </div>
  <div class="footer-column social-column">
    <h2>Social Media</h2>
    <div class="social-links">
      <a href="#" aria-label="Instagram"><img src="<?= e(asset_url('images/instagram.svg')) ?>" alt=""></a>
      <a href="#" aria-label="Facebook"><img src="<?= e(asset_url('images/facebook.svg')) ?>" alt=""></a>
      <a href="#" aria-label="LinkedIn"><img src="<?= e(asset_url('images/linkedin.svg')) ?>" alt=""></a>
    </div>
  </div>
</footer>
