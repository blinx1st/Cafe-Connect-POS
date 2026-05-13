let cafeApp = window.CAFE_APP || {};
const cafeInstalled = Boolean(window.CAFE_INSTALLED);
const apiBase = window.CAFE_API_BASE || "api.php";
const baseUrl = window.CAFE_BASE_URL || "";
let pageName = cafeApp.page || document.body?.dataset?.page || "website-home";
let section = cafeApp.section || (pageName.startsWith("pos-") ? "pos" : "website");

let products = Array.isArray(cafeApp.products) ? cafeApp.products : [];
let productMap = new Map(products.map((product) => [Number(product.id), product]));

const roleLabels = {
  waiter: "Phục vụ",
  cashier: "Thu ngân",
  barista: "Pha chế",
  owner: "Chủ quán",
  manager: "Quản lý",
  marketing: "Marketing",
  admin: "Admin",
};

const posModules = [
  { id: "checkout", label: "POS bán hàng", roles: ["cashier", "manager", "owner", "admin"] },
  { id: "orders", label: "Bàn & order", roles: ["waiter", "cashier", "manager", "owner", "admin"] },
  { id: "kitchen", label: "Bếp pha chế", roles: ["barista", "manager", "owner", "admin"] },
  { id: "dashboard", label: "Dashboard", roles: ["manager", "owner", "admin"] },
  { id: "customers", label: "Khách hàng", roles: ["cashier", "marketing", "manager", "owner", "admin"] },
  { id: "campaigns", label: "Campaign", roles: ["marketing", "manager", "owner", "admin"] },
  { id: "inventory", label: "Kho", roles: ["manager", "owner", "admin"] },
  { id: "reports", label: "Báo cáo", roles: ["manager", "owner", "admin"] },
  { id: "products", label: "Sản phẩm", roles: ["manager", "owner", "admin"] },
  { id: "staff", label: "Nhân viên", roles: ["owner", "admin"] },
  { id: "cash", label: "Thu chi", roles: ["cashier", "manager", "owner", "admin"] },
];

const state = {
  site: { cart: loadSiteCart(), customer: cafeApp.member || null, voucherId: "" },
  pos: {
    cart: [],
    customer: null,
    voucherId: "",
    productFilter: "",
    roleFilter: "",
    tableId: "",
    activeModule: cafeApp.posModule || "checkout",
    user: loadPosUser(),
  },
};

function url(path = "") {
  return baseUrl + String(path).replace(/^\/+/, "");
}

function loadSiteCart() {
  try {
    const raw = localStorage.getItem("cafe_site_cart");
    const cart = raw ? JSON.parse(raw) : [];
    return Array.isArray(cart) ? cart : [];
  } catch {
    return [];
  }
}

function saveSiteCart() {
  localStorage.setItem("cafe_site_cart", JSON.stringify(state.site.cart));
}

function loadPosUser() {
  try {
    const raw = localStorage.getItem("cafe_pos_user");
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function savePosUser(user) {
  state.pos.user = user;
  if (user) {
    localStorage.setItem("cafe_pos_user", JSON.stringify(user));
  } else {
    localStorage.removeItem("cafe_pos_user");
  }
}

const formatMoney = (value) =>
  new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

function asset(path) {
  const value = String(path || "assets/images/coffee-1.png");
  if (/^(https?:)?\/\//.test(value) || value.startsWith("/")) return value;
  return url(value);
}

function showToast(message) {
  const toast = document.querySelector("[data-toast]");
  if (!toast) return;
  toast.textContent = message;
  toast.hidden = false;
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => {
    toast.hidden = true;
  }, 3200);
}

function updateHeaderState() {
  const header = document.querySelector("[data-header]");
  if (!header) return;
  header.classList.toggle("is-scrolled", window.scrollY > 12 || !pageName.endsWith("home"));
}

function parseCafeApp(doc) {
  const jsonScript = doc.querySelector("script[data-cafe-app]");
  if (jsonScript?.textContent?.trim()) {
    return JSON.parse(jsonScript.textContent);
  }

  const appScript = Array.from(doc.scripts).find((script) => script.textContent.includes("window.CAFE_APP"));
  const match = appScript?.textContent.match(/window\.CAFE_APP\s*=\s*(\{[\s\S]*?\});/);
  return match ? JSON.parse(match[1]) : {};
}

function websiteRouteFromUrl(rawUrl) {
  const target = new URL(rawUrl, window.location.href);
  if (target.origin !== window.location.origin) return null;

  const safeDecode = (value) => {
    try {
      return decodeURIComponent(value);
    } catch {
      return value;
    }
  };
  const targetPath = safeDecode(target.pathname);
  let basePath = baseUrl || "/";
  if (/^https?:\/\//.test(basePath)) {
    basePath = safeDecode(new URL(basePath).pathname);
  }
  basePath = safeDecode(basePath);
  if (!basePath.startsWith("/")) basePath = `/${basePath}`;
  if (!basePath.endsWith("/")) basePath += "/";
  if (!targetPath.startsWith(basePath)) return null;

  const route = targetPath.slice(basePath.length).replace(/^\/+/, "");
  if (route.startsWith("pos") || route.startsWith("assets") || route.startsWith("api.php") || route.startsWith("install.php")) {
    return null;
  }
  return route;
}

function shouldUseWebsitePjax(anchor, event) {
  if (!anchor || section !== "website") return false;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return false;
  if (anchor.target && anchor.target !== "_self") return false;
  if (anchor.hasAttribute("download") || anchor.dataset.noPjax !== undefined) return false;

  const target = new URL(anchor.href, window.location.href);
  if (target.hash && target.pathname === window.location.pathname && target.search === window.location.search) return false;
  return websiteRouteFromUrl(target.href) !== null;
}

function applyPageAppData(nextApp) {
  cafeApp = nextApp || {};
  window.CAFE_APP = cafeApp;
  pageName = cafeApp.page || document.body?.dataset?.page || "website-home";
  section = cafeApp.section || (pageName.startsWith("pos-") ? "pos" : "website");
  document.body.dataset.page = pageName;
  state.site.customer = cafeApp.member || null;
  syncProducts(Array.isArray(cafeApp.products) ? cafeApp.products : []);
}

async function navigateWebsite(href, pushState = true) {
  const route = websiteRouteFromUrl(href);
  if (route === null) {
    window.location.href = href;
    return;
  }

  const currentMain = document.querySelector("main");
  if (!currentMain) {
    window.location.href = href;
    return;
  }

  currentMain.setAttribute("aria-busy", "true");
  try {
    const response = await fetch(href, {
      headers: { "X-Requested-With": "CafeConnect-PJAX" },
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);

    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, "text/html");
    const nextMain = doc.querySelector("main");
    if (!nextMain) throw new Error("Trang khong co noi dung main.");

    document.title = doc.title || document.title;
    currentMain.replaceWith(nextMain);
    applyPageAppData(parseCafeApp(doc));
    document.querySelector("[data-nav]")?.classList.remove("is-open");
    updateHeaderState();
    initialRender();
    if (pushState) {
      window.history.pushState({ cafePjax: true }, "", href);
    }
    window.scrollTo(0, 0);
  } catch (error) {
    window.location.href = href;
  }
}

async function api(endpoint, payload = {}) {
  if (!cafeInstalled) {
    throw new Error("Database chưa sẵn sàng. Hãy chạy install.php trước.");
  }

  const clean = String(endpoint).replace(/^\/?api\/?/, "");
  const requestPayload = { ...payload };
  if (section === "pos" && state.pos.user && !Object.prototype.hasOwnProperty.call(requestPayload, "staff_id")) {
    requestPayload.staff_id = state.pos.user.id;
  }

  const response = await fetch(`${apiBase}?endpoint=${encodeURIComponent(clean)}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(requestPayload),
  });
  const json = await response.json();
  if (!json.ok) {
    throw new Error(json.message || "API request failed.");
  }
  return json.data;
}

function syncProducts(rows) {
  products = Array.isArray(rows) ? rows : [];
  cafeApp.products = products;
  productMap = new Map(products.map((product) => [Number(product.id), product]));
}

function tableHtml(rows, headers, mapper, emptyText = "Chưa có dữ liệu.") {
  return `
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join("")}</tr></thead>
        <tbody>${rows && rows.length ? rows.map(mapper).join("") : `<tr><td colspan="${headers.length}">${escapeHtml(emptyText)}</td></tr>`}</tbody>
      </table>
    </div>
  `;
}

function cartFor(scope) {
  return state[scope].cart;
}

function persistCart(scope) {
  if (scope === "site") saveSiteCart();
}

function addToCart(scope, productId) {
  const product = productMap.get(Number(productId));
  if (!product) return;

  const cart = cartFor(scope);
  const existing = cart.find((item) => item.product_id === Number(productId));
  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({ product_id: Number(productId), quantity: 1, size: "M", topping: "" });
  }
  persistCart(scope);
  renderCart(scope);
}

function updateQuantity(scope, productId, delta) {
  const cart = cartFor(scope);
  const item = cart.find((entry) => entry.product_id === Number(productId));
  if (!item) return;

  item.quantity += Number(delta);
  if (item.quantity <= 0) {
    state[scope].cart = cart.filter((entry) => entry.product_id !== Number(productId));
  }
  persistCart(scope);
  renderCart(scope);
}

function removeItem(scope, productId) {
  state[scope].cart = cartFor(scope).filter((entry) => entry.product_id !== Number(productId));
  persistCart(scope);
  renderCart(scope);
}

function selectedVoucher(scope) {
  const voucherId = String(state[scope].voucherId || "");
  const customer = state[scope].customer;
  if (!voucherId || !customer || !Array.isArray(customer.vouchers)) return null;
  return customer.vouchers.find((voucher) => String(voucher.id) === voucherId && voucher.usable) || null;
}

function totalsFor(scope) {
  const subtotal = cartFor(scope).reduce((sum, item) => {
    const product = productMap.get(Number(item.product_id));
    return sum + Number(product?.price || item.unit_price || 0) * Number(item.quantity || 0);
  }, 0);
  const rate = Number(state[scope].customer?.discount_rate || 0);
  const membershipDiscount = Math.round((subtotal * rate) / 100);
  const voucher = selectedVoucher(scope);
  const voucherBase = Math.max(0, subtotal - membershipDiscount);
  const voucherDiscount = voucher
    ? voucher.discount_type === "percentage"
      ? Math.round((voucherBase * Number(voucher.discount_value || 0)) / 100)
      : Math.min(voucherBase, Number(voucher.discount_value || 0))
    : 0;
  const total = Math.max(0, subtotal - membershipDiscount - voucherDiscount);
  const points = state[scope].customer ? Math.floor(total / 10000) : 0;
  return { subtotal, membershipDiscount, voucherDiscount, total, points };
}

function renderCart(scope) {
  const target = document.querySelector(scope === "site" ? "[data-site-cart]" : "[data-pos-cart]");
  if (!target) return;

  const cart = cartFor(scope);
  if (!cart.length) {
    target.innerHTML = '<div class="empty-state">Chưa có món trong giỏ.</div>';
    renderTotals(scope);
    return;
  }

  target.innerHTML = cart.map((item) => {
    const product = productMap.get(Number(item.product_id));
    const lineTotal = Number(product?.price || item.unit_price || 0) * Number(item.quantity || 0);
    return `
      <div class="cart-row">
        <div>
          <h4>${escapeHtml(product?.product_name || "Sản phẩm")}</h4>
          <small>${formatMoney(product?.price || item.unit_price || 0)} · Size ${escapeHtml(item.size || "M")}</small>
        </div>
        <div class="qty-control">
          <button type="button" data-cart-scope="${scope}" data-product-id="${item.product_id}" data-delta="-1">-</button>
          <strong>${Number(item.quantity || 0)}</strong>
          <button type="button" data-cart-scope="${scope}" data-product-id="${item.product_id}" data-delta="1">+</button>
        </div>
        <div class="line-total">
          <strong>${formatMoney(lineTotal)}</strong>
          <button type="button" data-cart-scope="${scope}" data-product-id="${item.product_id}" data-remove>×</button>
        </div>
      </div>
    `;
  }).join("");

  renderTotals(scope);
}

function renderTotals(scope) {
  const target = document.querySelector(scope === "site" ? "[data-site-totals]" : "[data-pos-totals]");
  if (!target) return;

  const totals = totalsFor(scope);
  target.innerHTML = `
    <div class="total-row"><span>Tạm tính</span><strong>${formatMoney(totals.subtotal)}</strong></div>
    <div class="total-row"><span>Giảm hạng thành viên</span><strong>-${formatMoney(totals.membershipDiscount)}</strong></div>
    <div class="total-row"><span>Giảm voucher</span><strong>-${formatMoney(totals.voucherDiscount)}</strong></div>
    <div class="total-row final"><span>Thanh toán</span><strong>${formatMoney(totals.total)}</strong></div>
    <div class="total-row"><span>Điểm nhận được</span><strong>+${totals.points}</strong></div>
  `;
}

function renderVoucherOptions(scope) {
  const select = document.querySelector(scope === "site" ? "[data-site-voucher]" : "[data-pos-voucher]");
  if (!select) return;

  const usable = state[scope].customer?.vouchers?.filter((voucher) => voucher.usable) || [];
  select.innerHTML = '<option value="">Không dùng voucher</option>' + usable.map((voucher) => {
    const value = voucher.discount_type === "percentage" ? `${Number(voucher.discount_value)}%` : formatMoney(voucher.discount_value);
    return `<option value="${voucher.id}">${escapeHtml(voucher.voucher_code)} · ${value}</option>`;
  }).join("");
  if (!usable.some((voucher) => String(voucher.id) === String(state[scope].voucherId))) {
    state[scope].voucherId = "";
  }
  select.value = state[scope].voucherId;
  renderTotals(scope);
}

function renderMiniMember(scope) {
  const target = document.querySelector(`[data-member-result="${scope}"]`);
  if (!target) return;

  const customer = state[scope]?.customer;
  if (!customer) {
    target.innerHTML = '<div class="empty-state">Chưa chọn khách hàng.</div>';
    return;
  }

  const usableCount = customer.vouchers?.filter((voucher) => voucher.usable).length || 0;
  target.innerHTML = `
    <div class="mini-profile">
      <div class="profile-head">
        <span class="avatar">${escapeHtml((customer.customer_name || "?").slice(0, 1))}</span>
        <div>
          <h3>${escapeHtml(customer.customer_name)}</h3>
          <small>${escapeHtml(customer.phone_number)} · ${escapeHtml(customer.email || "Chưa có email")}</small>
        </div>
      </div>
      <div class="mini-stats">
        <span><strong>${escapeHtml(customer.tier_name)}</strong><small>Hạng</small></span>
        <span><strong>${Number(customer.current_points || 0).toLocaleString("vi-VN")}</strong><small>Điểm</small></span>
        <span><strong>${usableCount}</strong><small>Voucher</small></span>
      </div>
    </div>
  `;
}

function setSiteMember(member) {
  state.site.customer = member || null;
  state.site.voucherId = "";
  renderMemberAccount();
  renderMiniMember("site");
  renderVoucherOptions("site");
  renderSiteProducts();
  renderProfile("portal", state.site.customer);
}

function renderMemberAccount() {
  const target = document.querySelector("[data-member-auth-status]");
  if (!target) return;

  const member = state.site.customer;
  if (!member) {
    target.innerHTML = `
      <h3>Chưa đăng nhập</h3>
      <p>Đăng nhập bằng số điện thoại để dùng voucher, tích điểm và đồng bộ đơn hàng với POS.</p>
      <div class="metric-grid two">
        <div class="metric"><strong>0</strong><small>Điểm hiện có</small></div>
        <div class="metric"><strong>0</strong><small>Voucher khả dụng</small></div>
      </div>
    `;
    return;
  }

  const usableCount = member.vouchers?.filter((voucher) => voucher.usable).length || 0;
  target.innerHTML = `
    <div class="profile-head">
      <span class="avatar">${escapeHtml((member.customer_name || "?").slice(0, 1))}</span>
      <div>
        <h3>${escapeHtml(member.customer_name)}</h3>
        <p>${escapeHtml(member.phone_number)} · ${escapeHtml(member.email || "Chưa có email")}</p>
      </div>
    </div>
    <div class="metric-grid two">
      <div class="metric"><strong>${Number(member.current_points || 0).toLocaleString("vi-VN")}</strong><small>Điểm</small></div>
      <div class="metric"><strong>${usableCount}</strong><small>Voucher khả dụng</small></div>
    </div>
    <div class="account-actions">
      <a class="secondary-link" href="${url("member")}">Xem hồ sơ</a>
      <button class="secondary-btn" type="button" data-member-logout>Đăng xuất</button>
    </div>
  `;
}

function voucherStatusClass(voucher) {
  if (voucher.usable) return "good";
  if (["redeemed", "expired", "cancelled"].includes(voucher.status)) return "bad";
  return "";
}

function renderProfile(targetName, customer) {
  const target = document.querySelector(`[data-member-result="${targetName}"]`);
  if (!target) return;

  if (!customer) {
    target.innerHTML = '<div class="empty-state">Không tìm thấy khách hàng.</div>';
    return;
  }

  const favoriteNames = (customer.favorites || []).map((id) => productMap.get(Number(id))?.product_name).filter(Boolean);
  const voucherRows = (customer.vouchers || []).map((voucher) => `
    <tr>
      <td>${escapeHtml(voucher.voucher_code)}</td>
      <td>${escapeHtml(voucher.promotion_name)}</td>
      <td>${voucher.discount_type === "percentage" ? Number(voucher.discount_value) + "%" : formatMoney(voucher.discount_value)}</td>
      <td>${escapeHtml(voucher.expiration_date)}</td>
      <td><span class="status ${voucherStatusClass(voucher)}">${voucher.usable ? "Khả dụng" : escapeHtml(voucher.status)}</span></td>
    </tr>
  `).join("");
  const historyRows = (customer.history || []).map((invoice) => `
    <tr>
      <td>#${invoice.id}</td>
      <td>${escapeHtml(invoice.invoice_date)} ${escapeHtml(invoice.invoice_time)}</td>
      <td>${escapeHtml(invoice.sales_channel)} · ${escapeHtml(invoice.branch_name)}</td>
      <td>${formatMoney(invoice.total_amount)}</td>
      <td>+${Number(invoice.points_earned || 0)}</td>
    </tr>
  `).join("");

  target.innerHTML = `
    <div class="profile-head">
      <span class="avatar">${escapeHtml((customer.customer_name || "?").slice(0, 1))}</span>
      <div>
        <h3>${escapeHtml(customer.customer_name)}</h3>
        <p>${escapeHtml(customer.phone_number)} · ${escapeHtml(customer.email || "Chưa có email")}</p>
      </div>
    </div>
    <div class="metric-grid">
      <div class="metric"><strong>${escapeHtml(customer.tier_name)}</strong><small>Hạng thành viên</small></div>
      <div class="metric"><strong>${Number(customer.current_points || 0).toLocaleString("vi-VN")}</strong><small>Điểm hiện có</small></div>
      <div class="metric"><strong>${formatMoney(customer.total_spending)}</strong><small>Tổng chi tiêu</small></div>
    </div>
    <div class="favorite-line"><strong>Yêu thích:</strong> ${favoriteNames.length ? favoriteNames.map(escapeHtml).join(", ") : "Chưa có sản phẩm yêu thích"}</div>
    <div class="table-wrap">
      <h3>Voucher</h3>
      <table class="data-table">
        <thead><tr><th>Mã</th><th>Chiến dịch</th><th>Giảm</th><th>Hạn</th><th>Trạng thái</th></tr></thead>
        <tbody>${voucherRows || '<tr><td colspan="5">Chưa có voucher.</td></tr>'}</tbody>
      </table>
    </div>
    <div class="table-wrap">
      <h3>Lịch sử mua hàng</h3>
      <table class="data-table">
        <thead><tr><th>Hóa đơn</th><th>Thời gian</th><th>Kênh</th><th>Tổng</th><th>Điểm</th></tr></thead>
        <tbody>${historyRows || '<tr><td colspan="5">Chưa có lịch sử.</td></tr>'}</tbody>
      </table>
    </div>
  `;
}

function legacyRenderSiteProducts() {
  const target = document.querySelector("[data-site-products]");
  if (!target) return;

  const limit = Number(target.dataset.productLimit || 0);
  const rows = limit > 0 ? products.slice(0, limit) : products;
  target.innerHTML = rows.map((product) => {
    const isFavorite = state.site.customer?.favorites?.includes(Number(product.id));
    return `
      <article class="product-card">
        <img src="${escapeHtml(asset(product.image))}" alt="${escapeHtml(product.product_name)}">
        <div>
          <span class="tag">${escapeHtml(product.category_name || product.category)}</span>
          <h3>${escapeHtml(product.product_name)}</h3>
          <p>${escapeHtml(product.take_note || "Sản phẩm đang bán")}</p>
        </div>
        <footer>
          <strong>${formatMoney(product.price)}</strong>
          <div class="card-actions">
            <button type="button" data-site-add="${product.id}">Thêm</button>
            <button type="button" class="icon-action ${isFavorite ? "is-active" : ""}" data-favorite-product="${product.id}" title="Yêu thích">♡</button>
          </div>
        </footer>
      </article>
    `;
  }).join("") || '<div class="empty-state">Chưa có sản phẩm. Hãy chạy install.php.</div>';
}

function legacyRenderReviews() {
  const target = document.querySelector("[data-reviews]");
  if (!target) return;

  const reviews = Array.isArray(cafeApp.reviews) ? cafeApp.reviews : [];
  target.innerHTML = reviews.map((review) => `
    <article class="testimonial-card">
      <div class="profile-head">
        <img class="review-avatar" src="${escapeHtml(asset(review.avatar_path || "assets/images/avatar-1.png"))}" alt="${escapeHtml(review.customer_name)}">
        <div>
          <h3>${escapeHtml(review.customer_name)}</h3>
          <small>${escapeHtml(review.customer_title || "Cafe Connect member")}</small>
        </div>
      </div>
      <p>${escapeHtml(review.review_text)}</p>
      <span class="rating">${"★".repeat(Math.max(1, Math.min(5, Number(review.rating || 5))))}</span>
    </article>
  `).join("") || '<div class="empty-state">Chưa có đánh giá.</div>';
}

async function lookupMember(scope, identity) {
  const customer = await api("member-lookup", { identity });
  if (!customer) {
    if (scope === "site" || scope === "pos") {
      state[scope].customer = null;
      renderMiniMember(scope);
      renderVoucherOptions(scope);
    }
    renderProfile(scope, null);
    return null;
  }

  if (scope === "site") {
    setSiteMember(customer);
  } else if (scope === "pos") {
    state.pos.customer = customer;
    state.pos.voucherId = "";
    renderMiniMember("pos");
    renderVoucherOptions("pos");
  } else if (scope === "crm") {
    state.pos.customer = customer;
    renderProfile("crm", customer);
  } else {
    renderProfile(scope, customer);
  }
  return customer;
}

async function checkoutScope(scope, extraPayload = {}) {
  const cart = cartFor(scope);
  if (!cart.length && !extraPayload.order_id) {
    showToast("Giỏ hàng đang rỗng.");
    return;
  }
  if (scope === "site" && !state.site.customer) {
    showToast("Vui lòng đăng nhập hoặc đăng ký thành viên trước khi đặt hàng.");
    await navigateWebsite(url("account"));
    return;
  }

  const user = state.pos.user || {};
  const paymentSelect = document.querySelector(scope === "site" ? "[data-site-payment]" : "[data-pos-payment]");
  const payload = {
    sales_channel: scope === "site" ? "website" : "pos",
    staff_id: scope === "site" ? cafeApp.staff?.find((item) => item.staff_role === "cashier")?.id || 2 : user.id || 2,
    branch_id: scope === "site" ? cafeApp.branches?.[0]?.id || 1 : user.branch_id || 1,
    customer_id: state[scope].customer?.id || null,
    voucher_id: state[scope].voucherId || null,
    payment_method: paymentSelect?.value || "cash",
    items: cart,
    ...extraPayload,
  };

  const result = await api(extraPayload.order_id ? "checkout-order" : "checkout", payload);
  if (!extraPayload.order_id) {
    state[scope].cart = [];
    persistCart(scope);
  }
  state[scope].voucherId = "";
  if (result.customer && scope === "site") {
    setSiteMember(result.customer);
  } else if (result.customer && scope === "pos") {
    state.pos.customer = result.customer;
  }
  renderCart(scope);
  renderVoucherOptions(scope);
  renderMiniMember(scope);
  showToast(`Thanh toán thành công hóa đơn #${result.invoice_id}, tổng ${formatMoney(result.total_amount)}.`);
  if (section === "pos") await refreshPosData(false);
}

function allowedModules(user = state.pos.user) {
  if (!user) return [];
  return posModules.filter((module) => module.roles.includes(user.staff_role));
}

function currentModule() {
  return posModules.find((module) => module.id === state.pos.activeModule) || posModules[0];
}

function branchOptions(selected = "") {
  return (cafeApp.branches || []).map((branch) =>
    `<option value="${branch.id}" ${String(selected) === String(branch.id) ? "selected" : ""}>${escapeHtml(branch.branch_name)}</option>`
  ).join("");
}

function categoryOptions(selected = "") {
  return (cafeApp.categories || []).map((category) =>
    `<option value="${escapeHtml(category.category_code)}" ${selected === category.category_code ? "selected" : ""}>${escapeHtml(category.category_name)}</option>`
  ).join("");
}

function legacyRenderPosLogin() {
  const root = document.querySelector("#pos-app");
  if (!root) return;

  const roles = cafeApp.roles || Object.keys(roleLabels);
  const staff = cafeApp.staff || [];
  const filteredStaff = state.pos.roleFilter ? staff.filter((member) => member.staff_role === state.pos.roleFilter) : staff;
  root.innerHTML = `
    <main class="pos-login">
      <section class="login-card">
        <p class="eyebrow">Cafe Connect POS</p>
        <h1>Chọn vai trò đăng nhập</h1>
        <p class="login-note">Đăng nhập demo bằng nhân viên trong database. Mỗi role chỉ thấy module phù hợp.</p>
        <div class="role-grid">
          <button type="button" class="${state.pos.roleFilter === "" ? "is-active" : ""}" data-login-role="">Tất cả</button>
          ${roles.map((role) => `
            <button type="button" class="${state.pos.roleFilter === role ? "is-active" : ""}" data-login-role="${escapeHtml(role)}">
              ${escapeHtml(roleLabels[role] || role)}
              <small>${staff.filter((member) => member.staff_role === role).length} nhân viên</small>
            </button>
          `).join("")}
        </div>
        <div class="staff-grid">
          ${filteredStaff.map((member) => `
            <button type="button" class="staff-card" data-login-staff="${member.id}">
              <span class="avatar">${escapeHtml((member.staff_name || "?").slice(0, 1))}</span>
              <strong>${escapeHtml(member.staff_name)}</strong>
              <small>${escapeHtml(roleLabels[member.staff_role] || member.staff_role)} · ${escapeHtml(member.branch_name)}</small>
            </button>
          `).join("") || '<div class="empty-state">Không có nhân viên phù hợp.</div>'}
        </div>
      </section>
    </main>
  `;
}

function legacyRenderPosShell(contentHtml) {
  const module = currentModule();
  const allowed = allowedModules();
  return `
    <aside class="pos-sidebar">
      <a class="brand small" href="${url("pos/checkout")}">Cafe Connect</a>
      <div class="operator-card">
        <span class="avatar">${escapeHtml((state.pos.user.staff_name || "?").slice(0, 1))}</span>
        <div>
          <strong>${escapeHtml(state.pos.user.staff_name)}</strong>
          <small>${escapeHtml(roleLabels[state.pos.user.staff_role] || state.pos.user.staff_role)} · ${escapeHtml(state.pos.user.branch_name)}</small>
        </div>
      </div>
      <nav>
        ${allowed.map((item) => `<a class="${item.id === module.id ? "is-active" : ""}" href="${url(`pos/${item.id}`)}">${escapeHtml(item.label)}</a>`).join("")}
      </nav>
      <button type="button" class="secondary-btn sidebar-btn" data-pos-refresh>Làm mới dữ liệu</button>
      <button type="button" class="ghost-btn sidebar-btn" data-pos-logout>Đăng xuất</button>
    </aside>
    <main class="pos-main">
      <div class="pos-topbar">
        <div>
          <p class="eyebrow">MVC POS</p>
          <h1>${escapeHtml(module.label)}</h1>
        </div>
        <div class="operator-panel compact">
          <label>Chi nhánh <select disabled>${branchOptions(state.pos.user.branch_id)}</select></label>
          <label>Nhân viên <input value="${escapeHtml(state.pos.user.staff_name)}" disabled></label>
        </div>
      </div>
      ${contentHtml}
    </main>
  `;
}

function renderPosApp() {
  const root = document.querySelector("#pos-app");
  if (!root) return;

  if (pageName === "pos-login") {
    renderPosLogin();
    return;
  }

  if (!state.pos.user) {
    root.innerHTML = `
      <main class="pos-login">
        <section class="login-card">
          <p class="eyebrow">Cafe Connect POS</p>
          <h1>Cần đăng nhập POS</h1>
          <p class="login-note">Chọn nhân viên trước khi mở module nội bộ.</p>
          <a class="primary-btn" href="${url("pos/login")}">Đăng nhập POS</a>
        </section>
      </main>
    `;
    return;
  }

  const module = currentModule();
  if (!module.roles.includes(state.pos.user.staff_role)) {
    root.innerHTML = renderPosShell(`
      <section class="panel">
        <h2>Không có quyền truy cập</h2>
        <p>Role ${escapeHtml(roleLabels[state.pos.user.staff_role] || state.pos.user.staff_role)} không được mở module ${escapeHtml(module.label)}.</p>
      </section>
    `);
    return;
  }

  root.innerHTML = renderPosShell(renderModule(module.id));
  afterModuleRender(module.id);
}

function renderModule(moduleId) {
  return {
    checkout: renderCheckoutModule,
    orders: renderOrdersModule,
    kitchen: renderKitchenModule,
    dashboard: renderDashboardModule,
    customers: renderCustomersModule,
    campaigns: renderCampaignsModule,
    inventory: renderInventoryModule,
    reports: renderReportsModule,
    products: renderProductsModule,
    staff: renderStaffModule,
    cash: renderCashModule,
  }[moduleId]?.() || '<div class="empty-state">Module chưa khả dụng.</div>';
}

function afterModuleRender(moduleId) {
  if (["checkout", "orders"].includes(moduleId)) {
    renderPosProducts();
    renderCart("pos");
    renderVoucherOptions("pos");
    renderMiniMember("pos");
  }
  if (moduleId === "customers" && state.pos.customer) {
    renderProfile("crm", state.pos.customer);
  }
}

function legacyProductPickerHtml(title = "Menu POS") {
  return `
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2>${escapeHtml(title)}</h2>
          <p>Danh sách lấy trực tiếp từ products.</p>
        </div>
        <input type="search" data-product-search placeholder="Tìm món" value="${escapeHtml(state.pos.productFilter)}">
      </div>
      <div class="pos-product-grid" data-pos-products></div>
    </section>
  `;
}

function renderCheckoutModule() {
  return `
    <div class="pos-grid">
      ${productPickerHtml("Chọn món")}
      <aside class="cart-panel checkout-panel">
        <form class="lookup-form" data-member-lookup="pos">
          <label>Số điện thoại / email <input type="text" name="identity" placeholder="0900000001"></label>
          <button type="submit">Tra cứu</button>
        </form>
        <div data-member-result="pos" class="member-result muted-box">Chưa chọn khách hàng.</div>
        <div data-pos-cart class="cart-list"></div>
        <label class="field">Voucher <select data-pos-voucher><option value="">Không dùng voucher</option></select></label>
        <label class="field">Thanh toán
          <select data-pos-payment>
            <option value="cash">Tiền mặt</option>
            <option value="card">Thẻ</option>
            <option value="e_wallet">Ví điện tử</option>
          </select>
        </label>
        <div data-pos-totals class="totals"></div>
        <button type="button" class="primary-btn full" data-pos-checkout>Thanh toán</button>
      </aside>
    </div>
  `;
}

function renderOrdersModule() {
  const tables = cafeApp.tables || [];
  const orders = cafeApp.orders || [];
  if (!state.pos.tableId && tables.length) state.pos.tableId = String(tables[0].id);

  const tableCards = tables.map((table) => `
    <button type="button" class="table-card ${String(state.pos.tableId) === String(table.id) ? "is-active" : ""} ${table.status}" data-select-table="${table.id}">
      <strong>${escapeHtml(table.table_name)}</strong>
      <small>${escapeHtml(table.area_name)} · ${Number(table.seat_count)} ghế</small>
      <span class="status ${table.status === "available" ? "good" : ""}">${escapeHtml(table.order_status || table.status)}</span>
    </button>
  `).join("");

  const orderCards = orders.map((order) => `
    <article class="order-card">
      <header>
        <div><strong>${escapeHtml(order.order_code)}</strong><small>${escapeHtml(order.table_name)} · ${escapeHtml(order.customer_name)}</small></div>
        <span class="status ${order.status === "ready" || order.status === "served" ? "good" : ""}">${escapeHtml(order.status)}</span>
      </header>
      <div class="order-items">
        ${(order.items || []).map((item) => `
          <div>
            <span>${Number(item.quantity)}× ${escapeHtml(item.product_name)}</span>
            <small>${escapeHtml(item.kitchen_status)}</small>
            <div class="mini-actions">
              ${["preparing", "ready", "served"].map((status) => `<button type="button" data-update-item="${item.id}" data-status="${status}">${escapeHtml(status)}</button>`).join("")}
            </div>
          </div>
        `).join("")}
      </div>
      <footer>
        <strong>${formatMoney(order.subtotal_amount)}</strong>
        <button type="button" class="primary-btn" data-order-checkout="${order.id}">Thanh toán</button>
      </footer>
    </article>
  `).join("");

  return `
    <div class="admin-grid">
      <section class="panel">
        <div class="panel-head"><h2>Sơ đồ bàn</h2><p>Phục vụ chọn bàn rồi tạo order.</p></div>
        <div class="table-board">${tableCards}</div>
      </section>
      <form class="cart-panel" data-service-order-create>
        <h2>Tạo order phục vụ</h2>
        <label class="field">Bàn
          <select name="table_id" data-table-select>
            ${tables.map((table) => `<option value="${table.id}" ${String(state.pos.tableId) === String(table.id) ? "selected" : ""}>${escapeHtml(table.table_name)} · ${escapeHtml(table.area_name)}</option>`).join("")}
          </select>
        </label>
        <div data-member-result="pos" class="member-result muted-box">Có thể tra khách ở module POS bán hàng trước khi tạo order.</div>
        <div data-pos-cart class="cart-list"></div>
        <label class="field">Ghi chú <textarea name="note" placeholder="Ít đá, giao trước bánh..."></textarea></label>
        <button class="primary-btn full" type="submit">Gửi order xuống bếp</button>
      </form>
    </div>
    <div class="pos-grid order-picker">
      ${productPickerHtml("Thêm món vào order")}
      <section class="panel">
        <div class="panel-head"><h2>Order đang mở</h2><p>Thu ngân có thể checkout order đã phục vụ.</p></div>
        <label class="field order-payment">Thanh toán
          <select data-pos-payment>
            <option value="cash">Tiền mặt</option>
            <option value="card">Thẻ</option>
            <option value="e_wallet">Ví điện tử</option>
          </select>
        </label>
        <div class="order-list">${orderCards || '<div class="empty-state">Không có order đang mở.</div>'}</div>
      </section>
    </div>
  `;
}

function renderKitchenModule() {
  const queue = cafeApp.kitchen || [];
  const cards = queue.map((item) => `
    <article class="kitchen-card ${escapeHtml(item.kitchen_status)}">
      <header>
        <div>
          <strong>${Number(item.quantity)}× ${escapeHtml(item.product_name)}</strong>
          <small>${escapeHtml(item.order_code)} · ${escapeHtml(item.table_name)} · ${escapeHtml(item.branch_name)}</small>
        </div>
        <span class="status">${escapeHtml(item.kitchen_status)}</span>
      </header>
      <p>${escapeHtml([item.size ? `Size ${item.size}` : "", item.topping, item.note].filter(Boolean).join(" · ") || "Không có ghi chú")}</p>
      <div class="action-row">
        <button type="button" data-update-item="${item.id}" data-status="preparing">Đang làm</button>
        <button type="button" data-update-item="${item.id}" data-status="ready">Sẵn sàng</button>
        <button type="button" data-update-item="${item.id}" data-status="served">Đã phục vụ</button>
      </div>
    </article>
  `).join("");
  return `<section class="panel"><div class="panel-head"><h2>Kitchen queue</h2><p>Barista cập nhật trạng thái từng món.</p></div><div class="kitchen-board">${cards || '<div class="empty-state">Không có món chờ pha chế.</div>'}</div></section>`;
}

function renderDashboardModule() {
  return `<section class="panel">${dashboardHtml(cafeApp.dashboard)}</section>`;
}

function dashboardHtml(data) {
  if (!data) return '<div class="empty-state">Chưa có dữ liệu dashboard.</div>';
  const summary = data.summary || {};
  const month = data.month || {};
  const voucherRate = Number(summary.orders || 0) > 0 ? Math.round((Number(summary.voucher_orders || 0) / Number(summary.orders)) * 100) : 0;
  return `
    <div class="dashboard-grid">
      <div class="metric"><strong>${formatMoney(summary.revenue)}</strong><small>Doanh thu ngày ${escapeHtml(data.business_date)}</small></div>
      <div class="metric"><strong>${Number(summary.orders || 0)}</strong><small>Đơn trong ngày</small></div>
      <div class="metric"><strong>${formatMoney(month.revenue)}</strong><small>Doanh thu tháng</small></div>
      <div class="metric"><strong>${voucherRate}%</strong><small>Tỷ lệ dùng voucher</small></div>
    </div>
    <div class="dashboard-columns">
      <section><h3>Sản phẩm bán chạy</h3>${tableHtml(data.top_products || [], ["Sản phẩm", "SL", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${Number(row.quantity_sold || 0)}</td><td>${formatMoney(row.product_revenue)}</td></tr>`)}</section>
      <section><h3>Tồn kho thấp</h3>${tableHtml(data.low_inventory || [], ["Chi nhánh", "Sản phẩm", "Tồn", "Tối thiểu"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.product_name)}</td><td>${Number(row.stock_quantity || 0)}</td><td>${Number(row.min_stock_level || 0)}</td></tr>`)}</section>
      <section><h3>Doanh thu chi nhánh</h3>${tableHtml(data.branch_revenue || [], ["Chi nhánh", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td></tr>`)}</section>
      <section><h3>Hóa đơn mới</h3>${tableHtml(data.recent_invoices || [], ["HĐ", "Khách", "Kênh", "Tổng"], (row) => `<tr><td>#${row.id}</td><td>${escapeHtml(row.customer_name)}</td><td>${escapeHtml(row.sales_channel)}</td><td>${formatMoney(row.total_amount)}</td></tr>`)}</section>
    </div>
  `;
}

function renderCustomersModule() {
  return `
    <div class="crm-grid">
      <section class="panel">
        <div class="panel-head"><h2>CRM khách hàng</h2><p>Tra cứu hoặc tạo mới khách hàng.</p></div>
        <form class="lookup-form wide" data-member-lookup="crm">
          <label>Số điện thoại / email <input type="text" name="identity" value="0900000001"></label>
          <button type="submit">Tra cứu</button>
        </form>
        <form class="create-form" data-customer-create>
          <label>Tên khách <input name="customer_name" required></label>
          <label>Số điện thoại <input name="phone_number" required></label>
          <label>Email <input type="email" name="email"></label>
          <label>Kênh ưa thích
            <select name="preferred_channel"><option value="pos">POS</option><option value="website">Website</option><option value="email">Email</option><option value="zalo">Zalo</option></select>
          </label>
          <button type="submit" class="secondary-btn">Tạo khách hàng</button>
        </form>
      </section>
      <section class="panel profile-dashboard compact" data-member-result="crm"><div class="empty-state">Chưa mở hồ sơ khách hàng.</div></section>
    </div>
  `;
}

function campaignsTable(rows = cafeApp.campaigns || cafeApp.dashboard?.campaigns || []) {
  return tableHtml(rows, ["Chiến dịch", "Nhóm", "Giảm", "Dùng/Phát", "Doanh thu", "Trạng thái"], (campaign) => {
    const issued = Number(campaign.issued_vouchers || 0);
    const redeemed = Number(campaign.redeemed_vouchers || 0);
    const rate = issued > 0 ? Math.round((redeemed / issued) * 100) : 0;
    const discount = campaign.discount_type === "percentage" ? `${Number(campaign.discount_value)}%` : formatMoney(campaign.discount_value);
    return `<tr><td>${escapeHtml(campaign.promotion_name)}</td><td>${escapeHtml(campaign.target_segment)}</td><td>${discount}</td><td>${redeemed}/${issued} (${rate}%)</td><td>${formatMoney(campaign.attributed_revenue)}</td><td><span class="status good">${escapeHtml(campaign.status)}</span></td></tr>`;
  });
}

function renderCampaignsModule() {
  return `
    <div class="campaign-layout">
      <form class="create-form" data-campaign-create>
        <h2>Tạo campaign</h2>
        <label>Tên chiến dịch <input name="promotion_name" required></label>
        <label>Mô tả <textarea name="description"></textarea></label>
        <label>Ngày bắt đầu <input type="date" name="start_date" value="2026-05-13" required></label>
        <label>Ngày kết thúc <input type="date" name="end_date" value="2026-06-15" required></label>
        <label>Nhóm khách <select name="target_segment"><option value="all">Tất cả</option><option value="bronze">Bronze</option><option value="silver">Silver</option><option value="gold">Gold</option><option value="birthday">Sinh nhật</option><option value="inactive">Khách ngủ đông</option></select></label>
        <label>Loại giảm <select name="discount_type"><option value="fixed">Số tiền</option><option value="percentage">Phần trăm</option></select></label>
        <label>Giá trị <input type="number" name="discount_value" value="20000" min="0"></label>
        <label>Số voucher <input type="number" name="voucher_quantity" value="5" min="0"></label>
        <button class="primary-btn" type="submit">Tạo và phát voucher</button>
      </form>
      <section class="panel"><div class="panel-head"><h2>Hiệu quả campaign</h2><p>Voucher redeemed và doanh thu quy đổi.</p></div>${campaignsTable()}</section>
    </div>
  `;
}

function renderInventoryModule() {
  const inventory = cafeApp.inventory || {};
  return `
    <div class="admin-grid">
      <form class="create-form" data-stock-movement>
        <h2>Nhập/xuất kho</h2>
        <label>Nguyên vật liệu <select name="material_id">${(inventory.materials || []).map((item) => `<option value="${item.id}">${escapeHtml(item.material_name)} (${escapeHtml(item.unit)})</option>`).join("")}</select></label>
        <label>Loại <select name="movement_type"><option value="import">Nhập kho</option><option value="sales_export">Xuất bán</option><option value="waste_export">Hủy hao hụt</option></select></label>
        <label>Số lượng <input type="number" name="quantity" value="1" min="1"></label>
        <label>Giá trị <input type="number" name="total_amount" value="0" min="0"></label>
        <label>Ghi chú <textarea name="note"></textarea></label>
        <button class="primary-btn" type="submit">Ghi nhận</button>
      </form>
      <section class="panel"><h2>Tồn kho sản phẩm</h2>${tableHtml(inventory.product_inventory || [], ["Chi nhánh", "Sản phẩm", "Tồn", "Tối thiểu", "Trạng thái"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.product_name)}</td><td>${Number(row.stock_quantity)}</td><td>${Number(row.min_stock_level)}</td><td><span class="status ${row.stock_status === "low" ? "bad" : "good"}">${escapeHtml(row.stock_status)}</span></td></tr>`)}</section>
    </div>
    <div class="dashboard-columns">
      <section class="panel"><h2>Nguyên vật liệu</h2>${tableHtml(inventory.materials || [], ["Tên", "ĐVT", "Tồn", "Tối thiểu", "Nhà cung cấp"], (row) => `<tr><td>${escapeHtml(row.material_name)}</td><td>${escapeHtml(row.unit)}</td><td>${Number(row.stock_quantity)}</td><td>${Number(row.min_stock_level)}</td><td>${escapeHtml(row.supplier_name)}</td></tr>`)}</section>
      <section class="panel"><h2>Lịch sử kho</h2>${tableHtml(inventory.movements || [], ["Mã", "Loại", "NVL", "SL", "Nhân viên"], (row) => `<tr><td>${escapeHtml(row.movement_code)}</td><td>${escapeHtml(row.movement_type)}</td><td>${escapeHtml(row.material_name)}</td><td>${Number(row.quantity)}</td><td>${escapeHtml(row.staff_name)}</td></tr>`)}</section>
    </div>
  `;
}

function cashTable() {
  const rows = cafeApp.reports?.cash_transactions || [];
  return tableHtml(rows, ["Loại", "Lý do", "Số tiền", "Nhân viên", "Thời gian"], (row) => `
    <tr><td><span class="status ${row.transaction_type === "in" ? "good" : "bad"}">${escapeHtml(row.transaction_type)}</span></td><td>${escapeHtml(row.reason)}</td><td>${formatMoney(row.amount)}</td><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(row.created_at)}</td></tr>
  `);
}

function renderReportsModule() {
  const reports = cafeApp.reports || {};
  return `
    <div class="dashboard-columns">
      <section class="panel"><h2>Doanh thu theo kênh</h2>${tableHtml(reports.revenue_by_channel || [], ["Kênh", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.sales_channel)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td></tr>`)}</section>
      <section class="panel"><h2>Hiệu suất nhân viên</h2>${tableHtml(reports.staff_performance || [], ["Nhân viên", "Role", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${Number(row.orders_processed || 0)}</td><td>${formatMoney(row.revenue_handled)}</td></tr>`)}</section>
      <section class="panel span-2"><h2>Thu chi gần nhất</h2>${cashTable()}</section>
    </div>
  `;
}

function renderProductsModule() {
  return `
    <div class="admin-grid">
      <form class="create-form" data-product-save>
        <h2>Sản phẩm</h2>
        <input type="hidden" name="id">
        <label>Tên sản phẩm <input name="product_name" required></label>
        <label>Danh mục <select name="category">${categoryOptions("coffee")}</select></label>
        <label>Giá <input type="number" name="price" min="0" value="45000"></label>
        <label>Ghi chú <textarea name="take_note"></textarea></label>
        <label>Trạng thái <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        <button class="primary-btn" type="submit">Lưu sản phẩm</button>
      </form>
      <section class="panel"><h2>Danh sách sản phẩm</h2>${tableHtml(products, ["Tên", "Danh mục", "Giá", "Trạng thái", ""], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${escapeHtml(row.category_name || row.category)}</td><td>${formatMoney(row.price)}</td><td><span class="status good">${escapeHtml(row.status)}</span></td><td><button type="button" data-edit-product="${row.id}">Sửa</button></td></tr>`)}</section>
    </div>
  `;
}

function renderStaffModule() {
  const staff = cafeApp.staff || [];
  return `
    <div class="admin-grid">
      <form class="create-form" data-staff-save>
        <h2>Nhân viên</h2>
        <input type="hidden" name="id">
        <label>Tên nhân viên <input name="staff_name" required></label>
        <label>Chi nhánh <select name="branch_id">${branchOptions(state.pos.user?.branch_id || 1)}</select></label>
        <label>Role <select name="staff_role">${(cafeApp.roles || Object.keys(roleLabels)).map((role) => `<option value="${escapeHtml(role)}">${escapeHtml(roleLabels[role] || role)}</option>`).join("")}</select></label>
        <label>Số điện thoại <input name="phone_number"></label>
        <label>Email <input type="email" name="email"></label>
        <button class="primary-btn" type="submit">Lưu nhân viên</button>
      </form>
      <section class="panel"><h2>Danh sách nhân viên</h2>${tableHtml(staff, ["Tên", "Role", "Chi nhánh", "Email", ""], (row) => `<tr><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.email || "")}</td><td><button type="button" data-edit-staff="${row.id}">Sửa</button></td></tr>`)}</section>
    </div>
  `;
}

function renderCashModule() {
  return `
    <div class="campaign-layout">
      <form class="create-form" data-cash-transaction>
        <h2>Thu chi quầy</h2>
        <label>Loại <select name="transaction_type"><option value="in">Thu</option><option value="out">Chi</option></select></label>
        <label>Lý do <input name="reason" value="Điều chỉnh quỹ POS" required></label>
        <label>Số tiền <input type="number" name="amount" value="50000" min="0"></label>
        <button class="primary-btn" type="submit">Ghi nhận</button>
      </form>
      <section class="panel"><h2>Lịch sử thu chi</h2>${cashTable()}</section>
    </div>
  `;
}

function legacyRenderPosProducts() {
  const target = document.querySelector("[data-pos-products]");
  if (!target) return;

  const keyword = state.pos.productFilter.trim().toLowerCase();
  const filtered = products.filter((product) => `${product.product_name} ${product.category} ${product.category_name || ""}`.toLowerCase().includes(keyword));
  target.innerHTML = filtered.map((product) => `
    <article class="pos-product-card">
      <img src="${escapeHtml(asset(product.image))}" alt="${escapeHtml(product.product_name)}">
      <div>
        <span class="tag">${escapeHtml(product.category_name || product.category)}</span>
        <h3>${escapeHtml(product.product_name)}</h3>
        <p>${escapeHtml(product.take_note || "Sản phẩm đang bán")}</p>
      </div>
      <footer>
        <strong>${formatMoney(product.price)}</strong>
        <button type="button" data-pos-add="${product.id}">Thêm</button>
      </footer>
    </article>
  `).join("") || '<div class="empty-state">Không có sản phẩm phù hợp.</div>';
}

async function refreshPosData(showMessage = true) {
  const data = await api("pos-bootstrap");
  Object.assign(cafeApp, data);
  syncProducts(data.products || []);
  if (showMessage) showToast("Đã làm mới dữ liệu POS.");
  renderPosApp();
}

function updatePosCollections(result = {}) {
  if (result.orders) cafeApp.orders = result.orders;
  if (result.tables) cafeApp.tables = result.tables;
  if (result.kitchen) cafeApp.kitchen = result.kitchen;
  if (result.product_inventory || result.materials || result.movements) cafeApp.inventory = result;
}

function renderSiteProducts() {
  const target = document.querySelector("[data-site-products]");
  if (!target) return;

  const limit = Number(target.dataset.productLimit || 0);
  const rows = limit > 0 ? products.slice(0, limit) : products;
  target.innerHTML = rows.map((product) => {
    const isFavorite = state.site.customer?.favorites?.includes(Number(product.id));
    return `
      <article class="product-card">
        <div class="product-media">
          <img src="${escapeHtml(asset(product.image))}" alt="${escapeHtml(product.product_name)}">
          <button type="button" class="favorite-button ${isFavorite ? "is-active" : ""}" data-favorite-product="${product.id}" title="Yêu thích" aria-label="Yêu thích">
            <img src="${escapeHtml(asset("assets/images/heart.svg"))}" alt="">
          </button>
        </div>
        <div class="product-body">
          <h3>${escapeHtml(product.product_name)}</h3>
          <p>${escapeHtml(product.take_note || "Sản phẩm đang bán")}</p>
          <div class="product-actions">
            <strong>${formatMoney(product.price)}</strong>
            <button type="button" data-site-add="${product.id}">Order Now</button>
          </div>
        </div>
      </article>
    `;
  }).join("") || '<div class="empty-state">Chưa có sản phẩm. Hãy chạy install.php.</div>';
}

function renderReviews() {
  const target = document.querySelector("[data-reviews]");
  if (!target) return;

  const reviews = Array.isArray(cafeApp.reviews) ? cafeApp.reviews : [];
  target.innerHTML = reviews.map((review, index) => `
    <article class="testimonial-card ${index === 1 ? "featured-card" : "side-card"}">
      <div class="testimonial-head">
        <img src="${escapeHtml(asset(review.avatar_path || (index === 1 ? "assets/images/avatar-1.png" : "assets/images/avatar-2.png")))}" alt="${escapeHtml(review.customer_name)}">
        <div>
          <h3>${escapeHtml(review.customer_name)}</h3>
          <p>${escapeHtml(review.customer_title || "Cafe Connect member")}</p>
        </div>
        <span class="stars">${"*".repeat(Math.max(1, Math.min(5, Number(review.rating || 5))))}</span>
      </div>
      <p>${escapeHtml(review.review_text)}</p>
    </article>
  `).join("") || '<div class="empty-state">Chưa có đánh giá.</div>';
}

function renderPosLogin() {
  const root = document.querySelector("#pos-app");
  if (!root) return;

  const roles = cafeApp.roles || Object.keys(roleLabels);
  const staff = cafeApp.staff || [];
  const filteredStaff = state.pos.roleFilter ? staff.filter((member) => member.staff_role === state.pos.roleFilter) : staff;
  root.innerHTML = `
    <main class="pos-login login-page">
      <section class="login-card">
        <div class="logo-lockup">
          <span class="logo-mark">C</span>
          <div>
            <p>Cafe Connect</p>
            <strong>POS Manager</strong>
          </div>
        </div>
        <h1>Chọn vai trò đăng nhập</h1>
        <p>Đăng nhập demo bằng nhân viên trong database. Mỗi role chỉ thấy module phù hợp.</p>
        <div class="role-grid">
          <button type="button" class="role-card ${state.pos.roleFilter === "" ? "active" : ""}" data-login-role="">
            <strong>Tất cả</strong>
            <span>${staff.length} nhân viên đang có</span>
          </button>
          ${roles.map((role) => `
            <button type="button" class="role-card ${state.pos.roleFilter === role ? "active" : ""}" data-login-role="${escapeHtml(role)}">
              <strong>${escapeHtml(roleLabels[role] || role)}</strong>
              <span>${staff.filter((member) => member.staff_role === role).length} nhân viên</span>
            </button>
          `).join("")}
        </div>
        <div class="staff-grid">
          ${filteredStaff.map((member) => `
            <button type="button" class="staff-card" data-login-staff="${member.id}">
              <span class="avatar">${escapeHtml((member.staff_name || "?").slice(0, 1))}</span>
              <strong>${escapeHtml(member.staff_name)}</strong>
              <small>${escapeHtml(roleLabels[member.staff_role] || member.staff_role)} · ${escapeHtml(member.branch_name)}</small>
            </button>
          `).join("") || '<div class="empty-state">Không có nhân viên phù hợp.</div>'}
        </div>
      </section>
      <aside class="login-aside">
        <div class="login-preview">
          <div class="preview-bar"><span class="preview-dot"></span><span class="preview-dot"></span><span class="preview-dot"></span></div>
          <div class="preview-body">
            <div class="preview-grid"><span class="preview-tile"></span><span class="preview-tile"></span><span class="preview-tile"></span><span class="preview-tile"></span></div>
            <span class="preview-side"></span>
          </div>
        </div>
      </aside>
    </main>
  `;
}

function renderPosShell(contentHtml) {
  const module = currentModule();
  const allowed = allowedModules();
  return `
    <header class="pos-topbar topbar">
      <a class="brand" href="${url("pos/checkout")}">
        <span class="logo-mark">${escapeHtml((state.pos.user.staff_role || "P").slice(0, 1).toUpperCase())}</span>
        <span><p>Cafe Connect</p><strong>${escapeHtml(roleLabels[state.pos.user.staff_role] || state.pos.user.staff_role)}</strong></span>
      </a>
      <nav class="topnav">
        ${allowed.map((item) => `<a class="nav-item ${item.id === module.id ? "active" : ""}" href="${url(`pos/${item.id}`)}">${escapeHtml(item.label)}</a>`).join("")}
      </nav>
      <div class="top-actions">
        <button type="button" class="icon-btn" data-pos-refresh title="Làm mới">↻</button>
        <span class="user-chip"><span class="avatar">${escapeHtml((state.pos.user.staff_name || "?").slice(0, 1))}</span>${escapeHtml(state.pos.user.staff_name)}</span>
        <button type="button" class="icon-btn" data-pos-logout title="Đăng xuất">↗</button>
      </div>
    </header>
    <main class="pos-main page">
      <div class="page-heading">
        <div>
          <span class="eyebrow">MVC POS</span>
          <h1>${escapeHtml(module.label)}</h1>
        </div>
        <div class="operator-panel compact">
          <label>Chi nhánh <select disabled>${branchOptions(state.pos.user.branch_id)}</select></label>
          <label>Nhân viên <input value="${escapeHtml(state.pos.user.staff_name)}" disabled></label>
        </div>
      </div>
      ${contentHtml}
    </main>
  `;
}

function productPickerHtml(title = "Menu POS") {
  return `
    <section class="panel">
      <div class="panel-head">
        <div>
          <h2>${escapeHtml(title)}</h2>
          <p>Danh sách lấy trực tiếp từ products.</p>
        </div>
        <label class="search-box">
          <span>⌕</span>
          <input type="search" data-product-search placeholder="Tìm món" value="${escapeHtml(state.pos.productFilter)}">
        </label>
      </div>
      <div class="pos-product-grid" data-pos-products></div>
    </section>
  `;
}

function renderPosProducts() {
  const target = document.querySelector("[data-pos-products]");
  if (!target) return;

  const keyword = state.pos.productFilter.trim().toLowerCase();
  const filtered = products.filter((product) => `${product.product_name} ${product.category} ${product.category_name || ""}`.toLowerCase().includes(keyword));
  target.innerHTML = filtered.map((product) => `
    <article class="pos-product-card">
      <img src="${escapeHtml(asset(product.image))}" alt="${escapeHtml(product.product_name)}">
      <div class="pos-product-body">
        <strong class="product-title">${escapeHtml(product.product_name)}</strong>
        <small>${escapeHtml(product.category_name || product.category)}</small>
        <div class="product-foot">
          <span class="price">${formatMoney(product.price)}</span>
          <button type="button" class="add-btn" data-pos-add="${product.id}">+</button>
        </div>
      </div>
    </article>
  `).join("") || '<div class="empty-state">Không có sản phẩm phù hợp.</div>';
}

function wireEvents() {
  document.querySelector("[data-nav-toggle]")?.addEventListener("click", () => {
    document.querySelector("[data-nav]")?.classList.toggle("is-open");
  });

  const header = document.querySelector("[data-header]");
  if (header) {
    updateHeaderState();
    window.addEventListener("scroll", updateHeaderState, { passive: true });
  }

  window.addEventListener("popstate", () => {
    if (section === "website") {
      navigateWebsite(window.location.href, false);
    }
  });

  document.addEventListener("click", async (event) => {
    const navLink = event.target.closest("a[href]");
    if (shouldUseWebsitePjax(navLink, event)) {
      event.preventDefault();
      await navigateWebsite(navLink.href);
      return;
    }

    const roleButton = event.target.closest("[data-login-role]");
    const loginStaff = event.target.closest("[data-login-staff]");
    const siteAdd = event.target.closest("[data-site-add]");
    const posAdd = event.target.closest("[data-pos-add]");
    const quantity = event.target.closest("[data-cart-scope][data-delta]");
    const remove = event.target.closest("[data-cart-scope][data-remove]");
    const tableCard = event.target.closest("[data-select-table]");
    const updateItem = event.target.closest("[data-update-item]");
    const orderCheckout = event.target.closest("[data-order-checkout]");
    const favorite = event.target.closest("[data-favorite-product]");
    const editProduct = event.target.closest("[data-edit-product]");
    const editStaff = event.target.closest("[data-edit-staff]");

    if (roleButton) {
      state.pos.roleFilter = roleButton.dataset.loginRole || "";
      renderPosLogin();
      return;
    }
    if (loginStaff) {
      const staff = (cafeApp.staff || []).find((member) => String(member.id) === String(loginStaff.dataset.loginStaff));
      if (staff) {
        savePosUser(staff);
        window.location.href = url("pos/checkout");
      }
      return;
    }
    if (event.target.closest("[data-pos-logout]")) {
      savePosUser(null);
      window.location.href = url("pos/login");
      return;
    }
    if (event.target.closest("[data-member-logout]")) {
      try {
        await api("member-logout");
        setSiteMember(null);
        showToast("Đã đăng xuất tài khoản thành viên.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (event.target.closest("[data-pos-refresh]")) {
      try {
        await refreshPosData();
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (siteAdd) {
      addToCart("site", siteAdd.dataset.siteAdd);
      showToast("Đã thêm món vào giỏ website.");
      return;
    }
    if (posAdd) {
      addToCart("pos", posAdd.dataset.posAdd);
      return;
    }
    if (quantity) {
      updateQuantity(quantity.dataset.cartScope, quantity.dataset.productId, quantity.dataset.delta);
      return;
    }
    if (remove) {
      removeItem(remove.dataset.cartScope, remove.dataset.productId);
      return;
    }
    if (tableCard) {
      state.pos.tableId = tableCard.dataset.selectTable;
      renderPosApp();
      return;
    }
    if (updateItem) {
      try {
        const result = await api("update-order-item", { item_id: updateItem.dataset.updateItem, status: updateItem.dataset.status });
        updatePosCollections(result);
        renderPosApp();
        showToast("Đã cập nhật trạng thái món.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (orderCheckout) {
      try {
        await checkoutScope("pos", { order_id: Number(orderCheckout.dataset.orderCheckout), items: [], payment_method: document.querySelector("[data-pos-payment]")?.value || "cash" });
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (favorite) {
      try {
        if (!state.site.customer) {
          showToast("Đăng nhập thành viên trước khi đánh dấu yêu thích.");
          return;
        }
        const result = await api("favorite-toggle", { customer_id: state.site.customer.id, product_id: favorite.dataset.favoriteProduct });
        state.site.customer.favorites = result.favorites || [];
        renderSiteProducts();
        showToast(result.favorited ? "Đã thêm vào yêu thích." : "Đã bỏ yêu thích.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (event.target.closest("[data-site-checkout]")) {
      try {
        await checkoutScope("site");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (event.target.closest("[data-pos-checkout]")) {
      try {
        await checkoutScope("pos");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (editProduct) {
      const product = products.find((item) => String(item.id) === String(editProduct.dataset.editProduct));
      const form = document.querySelector("[data-product-save]");
      if (product && form) {
        form.elements.id.value = product.id;
        form.elements.product_name.value = product.product_name;
        form.elements.category.value = product.category;
        form.elements.price.value = product.price;
        form.elements.take_note.value = product.take_note || "";
        form.elements.status.value = product.status || "active";
      }
      return;
    }
    if (editStaff) {
      const staff = (cafeApp.staff || []).find((item) => String(item.id) === String(editStaff.dataset.editStaff));
      const form = document.querySelector("[data-staff-save]");
      if (staff && form) {
        form.elements.id.value = staff.id;
        form.elements.staff_name.value = staff.staff_name;
        form.elements.branch_id.value = staff.branch_id;
        form.elements.staff_role.value = staff.staff_role;
        form.elements.phone_number.value = staff.phone_number || "";
        form.elements.email.value = staff.email || "";
      }
    }
  });

  document.addEventListener("submit", async (event) => {
    const lookupForm = event.target.closest("[data-member-lookup]");
    const memberLoginForm = event.target.closest("[data-member-login]");
    const memberRegisterForm = event.target.closest("[data-member-register]");
    const createForm = event.target.closest("[data-customer-create]");
    const newsletterForm = event.target.closest("[data-newsletter-form]");
    const serviceOrderForm = event.target.closest("[data-service-order-create]");
    const campaignForm = event.target.closest("[data-campaign-create]");
    const stockForm = event.target.closest("[data-stock-movement]");
    const cashForm = event.target.closest("[data-cash-transaction]");
    const productForm = event.target.closest("[data-product-save]");
    const staffForm = event.target.closest("[data-staff-save]");

    if (memberLoginForm) {
      event.preventDefault();
      try {
        const result = await api("member-login", Object.fromEntries(new FormData(memberLoginForm)));
        setSiteMember(result.member);
        showToast("Đăng nhập thành viên thành công.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberRegisterForm) {
      event.preventDefault();
      try {
        const result = await api("member-register", Object.fromEntries(new FormData(memberRegisterForm)));
        setSiteMember(result.member);
        memberRegisterForm.reset();
        showToast(result.member?.was_existing ? "Số điện thoại đã tồn tại, đã đăng nhập hồ sơ." : "Đã tạo tài khoản thành viên.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (lookupForm) {
      event.preventDefault();
      try {
        const customer = await lookupMember(lookupForm.dataset.memberLookup, new FormData(lookupForm).get("identity"));
        if (customer) showToast("Đã mở hồ sơ thành viên.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (createForm) {
      event.preventDefault();
      try {
        const customer = await api("customer-create", Object.fromEntries(new FormData(createForm)));
        state.pos.customer = customer;
        renderPosApp();
        showToast(customer.was_existing ? "Số điện thoại đã tồn tại, đã mở hồ sơ." : "Đã tạo khách hàng mới.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (newsletterForm) {
      event.preventDefault();
      try {
        await api("newsletter-subscribe", Object.fromEntries(new FormData(newsletterForm)));
        newsletterForm.reset();
        showToast("Đã đăng ký newsletter.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (serviceOrderForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(serviceOrderForm));
        payload.items = state.pos.cart;
        payload.branch_id = state.pos.user?.branch_id || 1;
        payload.waiter_id = state.pos.user?.id || 1;
        payload.customer_id = state.pos.customer?.id || "";
        const result = await api("create-order", payload);
        state.pos.cart = [];
        updatePosCollections(result);
        renderPosApp();
        showToast(`Đã tạo order #${result.order_id}.`);
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (campaignForm) {
      event.preventDefault();
      try {
        const result = await api("create-campaign", Object.fromEntries(new FormData(campaignForm)));
        cafeApp.campaigns = result.campaigns || [];
        if (cafeApp.dashboard) cafeApp.dashboard.campaigns = cafeApp.campaigns;
        renderPosApp();
        showToast(`Đã tạo campaign và phát hành ${result.issued_count} voucher.`);
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (stockForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(stockForm));
        payload.staff_id = state.pos.user?.id || 1;
        payload.branch_id = state.pos.user?.branch_id || 1;
        cafeApp.inventory = await api("stock-movement", payload);
        renderPosApp();
        showToast("Đã ghi nhận nhập/xuất kho.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (cashForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(cashForm));
        payload.staff_id = state.pos.user?.id || 1;
        payload.branch_id = state.pos.user?.branch_id || 1;
        cafeApp.reports = await api("cash-transaction", payload);
        renderPosApp();
        showToast("Đã ghi nhận thu chi.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (productForm) {
      event.preventDefault();
      try {
        const result = await api("product-save", Object.fromEntries(new FormData(productForm)));
        syncProducts(result.products || []);
        productForm.reset();
        renderPosApp();
        showToast("Đã lưu sản phẩm.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (staffForm) {
      event.preventDefault();
      try {
        const result = await api("staff-save", Object.fromEntries(new FormData(staffForm)));
        cafeApp.staff = result.staff || [];
        staffForm.reset();
        renderPosApp();
        showToast("Đã lưu nhân viên.");
      } catch (error) {
        showToast(error.message);
      }
    }
  });

  document.addEventListener("change", (event) => {
    const siteVoucher = event.target.closest("[data-site-voucher]");
    const posVoucher = event.target.closest("[data-pos-voucher]");
    const tableSelect = event.target.closest("[data-table-select]");
    if (siteVoucher) {
      state.site.voucherId = siteVoucher.value;
      renderTotals("site");
      return;
    }
    if (posVoucher) {
      state.pos.voucherId = posVoucher.value;
      renderTotals("pos");
      return;
    }
    if (tableSelect) {
      state.pos.tableId = tableSelect.value;
      renderPosApp();
    }
  });

  document.addEventListener("input", (event) => {
    const productSearch = event.target.closest("[data-product-search]");
    if (productSearch) {
      state.pos.productFilter = productSearch.value;
      renderPosProducts();
    }
  });
}

function initialRender() {
  renderMemberAccount();
  renderSiteProducts();
  renderReviews();
  renderCart("site");
  if (state.site.customer) {
    renderMiniMember("site");
    renderVoucherOptions("site");
    renderProfile("portal", state.site.customer);
  }
  if (section === "pos") {
    renderPosApp();
  }
}

wireEvents();
initialRender();
