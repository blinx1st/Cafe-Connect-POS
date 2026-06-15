let cafeApp = window.CAFE_APP || {};
const cafeInstalled = Boolean(window.CAFE_INSTALLED);
const apiBase = window.CAFE_API_BASE || "api.php";
const baseUrl = window.CAFE_BASE_URL || "";
let pageName = cafeApp.page || document.body?.dataset?.page || "website-home";
let section = cafeApp.section || (pageName.startsWith("pos-") ? "pos" : "website");
let posHeartbeatTimer = null;

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

const fallbackPosModules = [
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

const posModules = Array.isArray(cafeApp.permissions?.modules) && cafeApp.permissions.modules.length
  ? cafeApp.permissions.modules
  : fallbackPosModules;

const state = {
  site: {
    cart: loadSiteCart(),
    customer: cafeApp.member || null,
    webStaff: cafeApp.web_staff || null,
    voucherId: "",
  },
  pos: {
    cart: [],
    customer: null,
    voucherId: "",
    productFilter: "",
    roleFilter: "",
    loginStaffId: "",
    loginPin: "",
    auth: loadPosAuth(),
    tableId: "",
    billStartedAt: "",
    activeModule: cafeApp.posModule || "checkout",
    user: loadPosUser(),
  },
};

function url(path = "") {
  return baseUrl + String(path).replace(/^\/+/, "");
}

function queryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
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
    const user = raw ? JSON.parse(raw) : null;
    if (!user || !user.pos_session_id || !user.session_token) return null;
    return user;
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
  renderHeaderPosLink();
}

function loadPosAuth() {
  try {
    const raw = localStorage.getItem("cafe_pos_auth");
    const auth = raw ? JSON.parse(raw) : null;
    if (!auth || !auth.auth_session_id || !auth.auth_token || !auth.id) return null;
    return auth;
  } catch {
    return null;
  }
}

function savePosAuth(auth) {
  let normalized = auth;
  if (auth?.staff && auth?.auth_session) {
    normalized = {
      ...auth.staff,
      auth_session_id: auth.auth_session.id,
      auth_token: auth.auth_session.auth_token,
      auth_logged_in_at: auth.auth_session.logged_in_at,
      auth_last_seen_at: auth.auth_session.last_seen_at,
    };
  }
  state.pos.auth = normalized;
  if (normalized) {
    localStorage.setItem("cafe_pos_auth", JSON.stringify(normalized));
  } else {
    localStorage.removeItem("cafe_pos_auth");
  }
  renderHeaderPosLink();
}

const formatMoney = (value) =>
  new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));

function sqlNow() {
  const value = new Date();
  const pad = (number) => String(number).padStart(2, "0");
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())} ${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`;
}

function formatDateTime(value) {
  if (!value) return "Chưa có";
  const normalized = String(value).replace(" ", "T");
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(date);
}

function durationSince(value, closedAt = "") {
  if (!value) return "0 phut";
  const start = new Date(String(value).replace(" ", "T"));
  const end = closedAt ? new Date(String(closedAt).replace(" ", "T")) : new Date();
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return "0 phut";
  const minutes = Math.max(0, Math.round((end.getTime() - start.getTime()) / 60000));
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  return hours > 0 ? `${hours}h ${rest}p` : `${rest} phut`;
}

const escapeHtml = (value) =>
  String(value ?? "")
    .replaceAll("&", "&")
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

function renderMemberNav() {
  const target = document.querySelector("[data-member-nav]");
  if (!target) return;

  const member = state.site.customer;
  const webStaff = activeWebStaff();
  if (member) {
    target.innerHTML = `
    <button class="member-menu-toggle" type="button" data-member-menu-toggle aria-expanded="false">
      Chào, ${escapeHtml(member.customer_name || "thành viên")}
      <span>▾</span>
    </button>
    <div class="member-dropdown" data-member-menu hidden>
      <a href="${url("account")}">Thông tin cá nhân</a>
      <a href="${url("account#change-password")}">Thay đổi password</a>
      <button type="button" data-member-logout>Đăng xuất</button>
    </div>
  `;
    return;
  }

  if (webStaff) {
    target.innerHTML = `
    <button class="member-menu-toggle" type="button" data-member-menu-toggle aria-expanded="false">
      Chào, ${escapeHtml(webStaff.staff_name || "nhân viên")}
      <span>▾</span>
    </button>
    <div class="member-dropdown" data-member-menu hidden>
      <a href="${url("account")}">Thông tin nhân viên</a>
      <a href="${url("pos/login")}">Mở POS</a>
      <button type="button" data-member-logout>Đăng xuất</button>
    </div>
  `;
    return;
  }

  if (target.classList.contains("market-account")) {
    target.innerHTML = `
      <span class="market-account-icon">◎</span>
      <a href="${url("login")}"><strong>Tài khoản</strong><small>Đăng nhập</small></a>
    `;
    return;
  }

  target.innerHTML = `
    <a href="${url("login")}">Đăng nhập</a>
    <a class="nav-pill" href="${url("register")}">Đăng ký</a>
  `;
}

function renderHeaderPosLink() {
  const target = document.querySelector("[data-pos-header-link]");
  if (!target) return;

  const webStaff = activeWebStaff();
  if (!webStaff) {
    target.hidden = true;
    return;
  }

  const role = webStaff?.staff_role || "";
  target.hidden = !Object.prototype.hasOwnProperty.call(roleLabels, role);
}

function closeMemberMenu() {
  const menu = document.querySelector("[data-member-menu]");
  const toggle = document.querySelector("[data-member-menu-toggle]");
  if (menu) menu.hidden = true;
  if (toggle) toggle.setAttribute("aria-expanded", "false");
}

function setMessage(selector, message, danger = false) {
  const target = document.querySelector(selector);
  if (!target) return;
  target.textContent = message;
  target.hidden = false;
  target.classList.toggle("danger", danger);
}

function activeWebStaff() {
  if (state.site.webStaff) return state.site.webStaff;
  if (section === "website" && state.pos.auth?.auth_token) return state.pos.auth;
  return null;
}

function renderAccountState() {
  const roots = document.querySelectorAll("[data-account-root]");
  const guests = document.querySelectorAll("[data-account-guest]");
  const memberPanels = document.querySelectorAll("[data-account-member]");
  const staffPanels = document.querySelectorAll("[data-account-staff]");
  if (!guests.length && !memberPanels.length && !staffPanels.length) return;

  const hasMember = Boolean(state.site.customer);
  const hasStaff = Boolean(activeWebStaff());
  document.body.classList.toggle("has-site-auth", hasMember || hasStaff);
  roots.forEach((root) => {
    root.classList.toggle("account-authenticated", hasMember || hasStaff);
    root.classList.toggle("account-guest", !hasMember && !hasStaff);
  });
  guests.forEach((guest) => {
    const shouldHide = hasMember || hasStaff;
    guest.hidden = shouldHide;
    guest.style.display = shouldHide ? "none" : "";
  });
  memberPanels.forEach((panel) => {
    panel.hidden = !hasMember;
    panel.style.display = hasMember ? "" : "none";
  });
  staffPanels.forEach((panel) => {
    const shouldShow = hasStaff && !hasMember;
    panel.hidden = !shouldShow;
    panel.style.display = shouldShow ? "" : "none";
  });
}

function renderAccountForm() {
  const staffForm = document.querySelector("[data-account-staff] [data-member-profile-update]");
  const staff = activeWebStaff();
  if (staffForm && staff) {
    if (staffForm.elements.staff_name) staffForm.elements.staff_name.value = staff.staff_name || "";
    if (staffForm.elements.staff_code) staffForm.elements.staff_code.value = staff.staff_code || "";
    if (staffForm.elements.email) staffForm.elements.email.value = staff.email || "";
    if (staffForm.elements.phone_number) staffForm.elements.phone_number.value = staff.phone_number || "";
    return;
  }

  const form = document.querySelector("[data-account-member] [data-member-profile-update]");
  const member = state.site.customer;
  if (!form || !member) return;

  form.elements.customer_name.value = member.customer_name || "";
  if (form.elements.phone_number) form.elements.phone_number.value = member.phone_number || "";
  form.elements.email.value = member.email || "";
  form.elements.birth_date.value = member.birth_date || "";
  form.elements.gender.value = member.gender || "";
  form.elements.address.value = member.address || "";
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
  if (pageName === "website-login") return false;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return false;
  if (anchor.target && anchor.target !== "_self") return false;
  if (anchor.hasAttribute("download") || anchor.dataset.noPjax !== undefined) return false;

  const target = new URL(anchor.href, window.location.href);
  if (target.hash && target.pathname === window.location.pathname && target.search === window.location.search) return false;
  const route = websiteRouteFromUrl(target.href);
  if (route === "login") return false;
  return route !== null;
}

function applyPageAppData(nextApp) {
  cafeApp = nextApp || {};
  window.CAFE_APP = cafeApp;
  pageName = cafeApp.page || document.body?.dataset?.page || "website-home";
  section = cafeApp.section || (pageName.startsWith("pos-") ? "pos" : "website");
  document.body.dataset.page = pageName;
  state.site.customer = cafeApp.member || null;
  state.site.webStaff = cafeApp.web_staff || null;
  syncProducts(Array.isArray(cafeApp.products) ? cafeApp.products : []);
}

async function navigateWebsite(href, pushState = true) {
  const targetUrl = new URL(href, window.location.href);
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
    if (!nextMain) throw new Error("Trang không có nội dung main.");

    document.title = doc.title || document.title;
    currentMain.replaceWith(nextMain);
    applyPageAppData(parseCafeApp(doc));
    document.querySelector("[data-nav]")?.classList.remove("is-open");
    updateHeaderState();
    initialRender();
    if (pushState) {
      window.history.pushState({ cafePjax: true }, "", href);
    }
    if (targetUrl.hash) {
      document.querySelector(targetUrl.hash)?.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
      window.scrollTo(0, 0);
    }
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
  if (section === "pos" && state.pos.auth) {
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "staff_id") && !state.pos.user) {
      requestPayload.staff_id = state.pos.auth.id;
    }
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "auth_session_id")) {
      requestPayload.auth_session_id = state.pos.auth.auth_session_id;
    }
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "auth_token")) {
      requestPayload.auth_token = state.pos.auth.auth_token;
    }
  }
  if (section === "pos" && state.pos.user && !Object.prototype.hasOwnProperty.call(requestPayload, "staff_id")) {
    requestPayload.staff_id = state.pos.user.id;
  }
  if (section === "pos" && state.pos.user) {
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "pos_session_id")) {
      requestPayload.pos_session_id = state.pos.user.pos_session_id;
    }
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "session_token")) {
      requestPayload.session_token = state.pos.user.session_token;
    }
    if (!Object.prototype.hasOwnProperty.call(requestPayload, "staff_role")) {
      requestPayload.staff_role = state.pos.user.staff_role;
    }
  }

  const headers = { "Content-Type": "application/json" };
  if (window.CAFE_CSRF_TOKEN) {
    headers["X-CSRF-Token"] = window.CAFE_CSRF_TOKEN;
  }

  const response = await fetch(`${apiBase}?endpoint=${encodeURIComponent(clean)}`, {
    method: "POST",
    headers,
    body: JSON.stringify(requestPayload),
  });
  const json = await response.json();
  if (json?.data?.csrf_token) {
    window.CAFE_CSRF_TOKEN = json.data.csrf_token;
  }
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

function downloadTextFile(filename, text) {
  const blob = new Blob([text], { type: "text/csv;charset=utf-8" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  URL.revokeObjectURL(link.href);
  link.remove();
}

function showReceiptDialog(receipt) {
  const invoice = receipt.invoice || {};
  const items = receipt.items || [];
  document.querySelector("[data-receipt-dialog]")?.remove();
  const dialog = document.createElement("div");
  dialog.className = "receipt-dialog";
  dialog.dataset.receiptDialog = "true";
  dialog.innerHTML = `
    <div class="receipt-box">
      <button type="button" class="receipt-close" data-receipt-close>x</button>
      <p class="eyebrow">Cafe Connect Receipt</p>
      <h2>Hóa đơn #${escapeHtml(invoice.id || "")}</h2>
      <p>${escapeHtml(invoice.branch_name || "")} - ${escapeHtml(formatDateTime(invoice.paid_at || invoice.invoice_date || ""))}</p>
      ${tableHtml(items, ["Món", "SL", "Giá", "Tổng"], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${Number(row.quantity || 0)}</td><td>${formatMoney(row.unit_price)}</td><td>${formatMoney(row.line_total)}</td></tr>`)}
      <div class="totals"><p><span>Tổng</span><strong>${formatMoney(invoice.total_amount)}</strong></p></div>
      <button type="button" class="primary-btn full" data-receipt-print="${escapeHtml(invoice.id || "")}">In receipt</button>
    </div>
  `;
  document.body.appendChild(dialog);
}

function persistCart(scope) {
  if (scope === "site") saveSiteCart();
}

function addToCart(scope, productId) {
  const product = productMap.get(Number(productId));
  if (!product) return;

  const cart = cartFor(scope);
  if (scope === "pos" && !cart.length && !state.pos.billStartedAt) {
    state.pos.billStartedAt = sqlNow();
  }
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
  if (scope === "pos" && !state.pos.cart.length) {
    state.pos.billStartedAt = "";
  }
  persistCart(scope);
  renderCart(scope);
}

function removeItem(scope, productId) {
  state[scope].cart = cartFor(scope).filter((entry) => entry.product_id !== Number(productId));
  if (scope === "pos" && !state.pos.cart.length) {
    state.pos.billStartedAt = "";
  }
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
    ${scope === "pos" && state.pos.billStartedAt ? `<div class="total-row"><span>Tạo bill</span><strong>${escapeHtml(formatDateTime(state.pos.billStartedAt))}</strong></div>` : ""}
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
  if (member) {
    state.site.webStaff = null;
    savePosAuth(null);
  }
  state.site.voucherId = "";
  renderHeaderPosLink();
  renderMemberNav();
  renderAccountState();
  renderAccountForm();
  renderMemberAccount();
  renderMiniMember("site");
  renderVoucherOptions("site");
  renderSiteProducts();
  renderProfile("portal", state.site.customer);
  renderProfile("account", state.site.customer);
}

function setWebStaff(staff, authSession = null) {
  state.site.webStaff = staff || null;
  if (staff) {
    state.site.customer = null;
  }
  if (staff && authSession) {
    savePosAuth({ staff, auth_session: authSession });
  } else if (staff && state.pos.auth?.id && Number(state.pos.auth.id) === Number(staff.id)) {
    savePosAuth({ ...state.pos.auth, ...staff });
  } else if (!staff) {
    savePosAuth(null);
  }
  renderHeaderPosLink();
  renderMemberNav();
  renderAccountState();
  renderAccountForm();
  renderMemberAccount();
  renderMiniMember("site");
  renderVoucherOptions("site");
  renderSiteProducts();
  renderProfile("portal", state.site.customer);
  renderProfile("account", state.site.customer);
}

async function adoptWebStaffFromPosAuth() {
  if (section !== "website" || state.site.customer || state.site.webStaff || !state.pos.auth?.auth_token) return;

  try {
    const result = await api("member-staff-adopt", {
      staff_id: state.pos.auth.id,
      auth_session_id: state.pos.auth.auth_session_id,
      auth_token: state.pos.auth.auth_token,
    });
    if (result.web_staff) {
      setWebStaff(result.web_staff, result.auth_session);
    }
  } catch {
    savePosAuth(null);
    renderAccountState();
  }
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
      <a class="secondary-link" href="${url("account")}">Xem hồ sơ</a>
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
  const canClaimHere = Boolean(state.site.customer && Number(state.site.customer.id) === Number(customer.id) && ["account", "portal", "site"].includes(targetName));
  const claimRows = (customer.claimable_vouchers || []).map((promotion) => {
    const discount = promotion.discount_type === "percentage" ? `${Number(promotion.discount_value)}%` : formatMoney(promotion.discount_value);
    const remaining = promotion.remaining_quantity === null ? "Không giới hạn" : Number(promotion.remaining_quantity);
    return `
      <tr>
        <td>${escapeHtml(promotion.promotion_name)}</td>
        <td>${discount}</td>
        <td>${escapeHtml(promotion.end_date)}</td>
        <td>${escapeHtml(remaining)}</td>
        <td>
          ${canClaimHere && promotion.can_claim
            ? `<button type="button" class="secondary-btn compact" data-claim-voucher="${promotion.id}">Nhận voucher</button>`
            : `<span class="status ${promotion.can_claim ? "good" : ""}">${promotion.customer_claim_count > 0 ? "Đã nhận" : (promotion.eligible ? "Hết lượt" : "Không phù hợp")}</span>`}
        </td>
      </tr>
    `;
  }).join("");
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
  const orderRows = (customer.website_orders || []).map((order) => `
    <tr>
      <td><a href="${url(`order?invoice_id=${order.invoice_id}`)}">#${escapeHtml(order.invoice_id)}</a></td>
      <td>${escapeHtml(order.created_at || `${order.invoice_date || ""} ${order.invoice_time || ""}`)}</td>
      <td>${escapeHtml(order.fulfillment_type || "")}</td>
      <td><span class="status ${order.order_status === "cancelled" ? "bad" : (order.order_status === "pending" ? "" : "good")}">${escapeHtml(order.order_status || "")}</span></td>
      <td>${formatMoney(order.total_amount)}</td>
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
      <h3>Voucher có thể nhận</h3>
      <table class="data-table">
        <thead><tr><th>Chiến dịch</th><th>Giảm</th><th>Hạn</th><th>Còn lại</th><th></th></tr></thead>
        <tbody>${claimRows || '<tr><td colspan="5">Chưa có campaign có thể nhận.</td></tr>'}</tbody>
      </table>
    </div>
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
  if (orderRows) {
    target.insertAdjacentHTML("beforeend", `
      <div class="table-wrap">
      <h3>Đơn hàng website</h3>
      <table class="data-table">
        <thead><tr><th>Đơn</th><th>Thời gian</th><th>Nhận hàng</th><th>Trạng thái</th><th>Tổng</th></tr></thead>
          <tbody>${orderRows}</tbody>
        </table>
      </div>
    `);
  }
}

function legacyRenderSiteProducts() {
  const target = document.querySelector("[data-site-products]");
  if (!target) return;

  const limit = Number(target.dataset.productLimit || 0);
  const search = (document.querySelector("[data-site-product-search]")?.value || "").trim().toLowerCase();
  const category = document.querySelector("[data-site-category-filter]")?.value || "";
  const sort = document.querySelector("[data-site-sort]")?.value || "";
  let rows = products.filter((product) => {
    const matchesSearch = !search
      || String(product.product_name || "").toLowerCase().includes(search)
      || String(product.take_note || "").toLowerCase().includes(search);
    const matchesCategory = !category || String(product.category || "") === category;
    return matchesSearch && matchesCategory;
  });
  rows = rows.sort((a, b) => {
    if (sort === "price_asc") return Number(a.price || 0) - Number(b.price || 0);
    if (sort === "price_desc") return Number(b.price || 0) - Number(a.price || 0);
    if (sort === "name_desc") return String(b.product_name || "").localeCompare(String(a.product_name || ""));
    return 0;
  });
  rows = limit > 0 ? rows.slice(0, limit) : rows;
  target.innerHTML = rows.map((product) => {
    const isFavorite = state.site.customer?.favorites?.includes(Number(product.id));
    const isOut = Boolean(product.is_out_of_stock) || Number(product.stock_quantity || 0) <= 0;
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
    await navigateWebsite(url("login"));
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
  if (scope === "site") {
    payload.fulfillment_type = document.querySelector("[data-site-fulfillment]")?.value || "pickup";
    payload.delivery_address = document.querySelector("[data-site-delivery-address]")?.value?.trim() || "";
    const receiverPhone = document.querySelector("[data-site-receiver-phone]")?.value?.trim() || "";
    if (payload.fulfillment_type === "delivery" && !payload.delivery_address) {
      showToast("Vui lòng nhập địa chỉ giao hàng.");
      return;
    }
    payload.customer_note = [
      receiverPhone ? `Receiver phone: ${receiverPhone}` : "",
      document.querySelector("[data-site-customer-note]")?.value?.trim() || "",
    ].filter(Boolean).join(" | ");
    const requestedAt = document.querySelector("[data-site-requested-at]")?.value || "";
    if (requestedAt) {
      payload.requested_at = requestedAt.replace("T", " ") + ":00";
    }
  }
  if (scope === "pos" && !extraPayload.order_id) {
    payload.bill_started_at = state.pos.billStartedAt || sqlNow();
  }

  const result = await api(extraPayload.order_id ? "checkout-order" : "checkout", payload);
  if (!extraPayload.order_id) {
    state[scope].cart = [];
    if (scope === "pos") state.pos.billStartedAt = "";
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
  if (scope === "pos" && result.invoice_id) {
    try {
      showReceiptDialog(await api("receipt", { invoice_id: result.invoice_id }));
    } catch {}
  }
  if (scope === "site" && result.invoice_id) {
    const statusText = result.status === "pending" ? "Đơn COD đang chờ thanh toán." : "Đơn đã thanh toán DemoPay.";
    showToast(`${statusText} Đang chuyển sang chi tiết đơn #${result.invoice_id}.`);
    await navigateWebsite(url(`order?invoice_id=${result.invoice_id}`));
    return;
  }
  if (section === "pos") await refreshPosData(false);
}

function renderWebsiteOrderDetail(receipt) {
  const target = document.querySelector("[data-order-detail]");
  if (!target) return;

  const invoice = receipt?.invoice || {};
  const items = receipt?.items || [];
  const payments = receipt?.payments || [];
  const payment = payments[0] || {};
  const canCancel = invoice.order_status === "pending";
  target.innerHTML = `
    <article class="auth-card">
      <div class="panel-head">
        <div>
          <p class="eyebrow">Order #${escapeHtml(invoice.id || "")}</p>
          <h2>${escapeHtml(invoice.order_status || invoice.status || "order")}</h2>
        </div>
        <span class="status ${invoice.order_status === "cancelled" ? "bad" : (invoice.status === "paid" ? "good" : "")}">${escapeHtml(invoice.status || "")}</span>
      </div>
      <div class="receipt-summary-grid">
        <div class="metric"><strong>${formatMoney(invoice.total_amount)}</strong><small>Tổng tiền</small></div>
        <div class="metric"><strong>${escapeHtml(invoice.payment_method || "")}</strong><small>Thanh toán</small></div>
        <div class="metric"><strong>${escapeHtml(payment.status || "")}</strong><small>Trạng thái payment</small></div>
        <div class="metric"><strong>${escapeHtml(invoice.fulfillment_type || "pickup")}</strong><small>Nhận hàng</small></div>
      </div>
      <div class="order-status-line">
        <span>Dat luc: ${escapeHtml(formatDateTime(invoice.created_at || invoice.bill_started_at || ""))}</span>
        <span>Thanh toán: ${escapeHtml(formatDateTime(invoice.paid_at || ""))}</span>
        <span>Chi nhanh: ${escapeHtml(invoice.branch_name || "")}</span>
      </div>
      ${invoice.delivery_address ? `<p><strong>Dia chi:</strong> ${escapeHtml(invoice.delivery_address)}</p>` : ""}
      ${invoice.customer_note ? `<p><strong>Ghi chu:</strong> ${escapeHtml(invoice.customer_note)}</p>` : ""}
      ${tableHtml(items, ["Món", "SL", "Size", "Giá", "Tổng"], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${Number(row.quantity || 0)}</td><td>${escapeHtml(row.size || "")}</td><td>${formatMoney(row.unit_price)}</td><td>${formatMoney(row.line_total)}</td></tr>`)}
      <div class="order-action-row">
        <button type="button" class="secondary-btn" data-order-receipt>In / xem receipt</button>
        ${canCancel ? `<button type="button" class="secondary-btn danger" data-website-order-cancel="${escapeHtml(invoice.id || "")}">Hủy đơn đang chờ</button>` : ""}
      </div>
    </article>
  `;
}

async function loadWebsiteOrderDetail() {
  if (!document.querySelector("[data-order-detail]")) return;
  const invoiceId = Number(queryParam("invoice_id") || 0);
  if (!invoiceId) {
    document.querySelector("[data-order-detail]").innerHTML = '<div class="empty-state">Thieu invoice_id.</div>';
    return;
  }
  try {
    const receipt = await api("website-order-detail", { invoice_id: invoiceId });
    renderWebsiteOrderDetail(receipt);
  } catch (error) {
    document.querySelector("[data-order-detail]").innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
  }
}

function allowedModules(user = state.pos.user) {
  if (!user) return [];
  return posModules.filter((module) => module.roles.includes(user.staff_role));
}

function overrideRoles() {
  return Array.isArray(cafeApp.permissions?.override_roles) ? cafeApp.permissions.override_roles : ["manager", "owner", "admin"];
}

function isOverrideRole(role = state.pos.user?.staff_role) {
  return overrideRoles().includes(role);
}

function canAccessModule(moduleId, role = state.pos.user?.staff_role) {
  const module = posModules.find((item) => item.id === moduleId);
  return Boolean(module && role && module.roles.includes(role));
}

function canCreateServiceOrder(role = state.pos.user?.staff_role) {
  return role === "waiter" || isOverrideRole(role);
}

function canCheckoutServiceOrder(role = state.pos.user?.staff_role) {
  return ["cashier", "manager", "owner", "admin"].includes(role);
}

function kitchenActionsForRole(status, role = state.pos.user?.staff_role) {
  if (isOverrideRole(role)) return ["preparing", "ready", "served"];
  if (role === "barista") {
    return status === "waiting" || status === "preparing" ? ["preparing", "ready"] : [];
  }
  if (role === "waiter") {
    return status === "ready" ? ["served"] : [];
  }
  return [];
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
    const firstAllowed = allowedModules()[0];
    if (firstAllowed && firstAllowed.id !== state.pos.activeModule) {
      window.location.href = url(`pos/${firstAllowed.id}`);
      return;
    }
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
  applyPosActionPolicy();
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

function applyPosActionPolicy() {
  const role = state.pos.user?.staff_role || "";
  document.querySelectorAll(".kitchen-card [data-update-item][data-status]").forEach((button) => {
    const card = button.closest(".kitchen-card");
    const from = ["waiting", "preparing", "ready", "served"].find((status) => card?.classList.contains(status)) || "";
    if (!kitchenActionsForRole(from, role).includes(button.dataset.status || "")) {
      button.remove();
    }
  });
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
  const role = state.pos.user?.staff_role || "";
  const canCreate = canCreateServiceOrder(role);
  const canCheckout = canCheckoutServiceOrder(role);
  const canVoid = role === "waiter" || isOverrideRole(role);
  const canCancel = isOverrideRole(role);
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
      <p class="order-meta">Tạo bill ${escapeHtml(formatDateTime(order.created_at))} · ${escapeHtml(durationSince(order.created_at))}</p>
      <div class="order-items">
        ${(order.items || []).map((item) => `
          <div>
            <span>${Number(item.quantity)}× ${escapeHtml(item.product_name)}</span>
            <small>${escapeHtml(item.kitchen_status)}</small>
            <div class="mini-actions">
              ${kitchenActionsForRole(item.kitchen_status, role).map((status) => `<button type="button" data-update-item="${item.id}" data-status="${status}">${escapeHtml(status)}</button>`).join("")}
              ${canVoid && item.kitchen_status !== "cancelled" ? `<button type="button" data-void-item="${item.id}">Void</button>` : ""}
            </div>
          </div>
        `).join("")}
      </div>
      <footer>
        <strong>${formatMoney(order.subtotal_amount)}</strong>
        ${canCheckout ? `<button type="button" class="primary-btn" data-order-checkout="${order.id}">Thanh toán</button>` : ""}
        ${canCancel ? `<button type="button" class="ghost-btn" data-cancel-order="${order.id}">Hủy order</button>` : ""}
      </footer>
    </article>
  `).join("");

  if (!canCreate) {
    return `
      <section class="panel">
        <div class="panel-head"><h2>Order đang mở</h2><p>${canCheckout ? "Thu ngân xem order để thanh toán." : "Role này chỉ được xem order theo quyền được cấp."}</p></div>
        ${canCheckout ? `<label class="field order-payment">Thanh toán
          <select data-pos-payment>
            <option value="cash">Tien mat</option>
            <option value="card">The</option>
            <option value="e_wallet">Vi dien tu</option>
          </select>
        </label>` : ""}
        <div class="order-list">${orderCards || '<div class="empty-state">Không có order đang mở.</div>'}</div>
      </section>
    `;
  }

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
        <label class="field order-payment" ${canCheckout ? "" : "hidden"}>Thanh toán
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
  const role = state.pos.user?.staff_role || "";
  const cards = queue.map((item) => `
    <article class="kitchen-card ${escapeHtml(item.kitchen_status)}">
      <header>
        <div>
          <strong>${Number(item.quantity)}× ${escapeHtml(item.product_name)}</strong>
          <small>${escapeHtml(item.order_code)} · ${escapeHtml(item.table_name)} · ${escapeHtml(item.branch_name)} · ${escapeHtml(durationSince(item.created_at))}</small>
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

function sessionReportsTable() {
  const rows = cafeApp.reports?.session_reports || cafeApp.session_reports || [];
  return tableHtml(rows, ["Nhân viên", "Role", "Ca", "Thời lượng", "Doanh thu", "Bill", "Order", "Món pha", "Thu/chi", "Log"], (row) => `
    <tr>
      <td>${escapeHtml(row.staff_name)}</td>
      <td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td>
      <td>${escapeHtml(formatDateTime(row.opened_at))}<br><small>${escapeHtml(row.status)}${row.closed_reason ? ` - ${escapeHtml(row.closed_reason)}` : ""}</small></td>
      <td>${Number(row.duration_minutes || 0)} phut</td>
      <td>${formatMoney(row.revenue_total)}</td>
      <td>${Number(row.invoice_count || 0)}</td>
      <td>${Number(row.order_count || 0)} / ${Number(row.order_items || 0)} mon</td>
      <td>${Number(row.prepared_quantity || 0)}<br><small>TB ${Number(row.avg_prepare_minutes || 0).toFixed(1)}p</small></td>
      <td>${formatMoney(row.cash_in)} / ${formatMoney(row.cash_out)}</td>
      <td><small>${escapeHtml(row.main_actions || `${Number(row.activity_count || 0)} thao tac`)}</small></td>
    </tr>
  `, "Chưa có phiên làm việc.");
}

function renderReportsModule() {
  const reports = cafeApp.reports || {};
  const recentInvoices = cafeApp.dashboard?.recent_invoices || [];
  const invoiceActions = tableHtml(recentInvoices, ["HĐ", "Khách", "Kênh", "Tổng", ""], (row) => `
    <tr>
      <td>#${row.id}</td>
      <td>${escapeHtml(row.customer_name || "Khach le")}</td>
      <td>${escapeHtml(row.sales_channel || "")}</td>
      <td>${formatMoney(row.total_amount)}</td>
      <td><button type="button" data-receipt-invoice="${row.id}">Receipt</button>${isOverrideRole() ? `<button type="button" data-refund-invoice="${row.id}">Refund</button>` : ""}</td>
    </tr>
  `, "Chưa có hóa đơn.");
  return `
    <div class="dashboard-columns">
      <section class="panel span-2"><div class="panel-head"><h2>Report export</h2><button type="button" class="primary-btn" data-report-export>Export CSV</button></div></section>
      <section class="panel"><h2>Doanh thu theo kênh</h2>${tableHtml(reports.revenue_by_channel || [], ["Kênh", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.sales_channel)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td></tr>`)}</section>
      <section class="panel"><h2>Hiệu suất nhân viên</h2>${tableHtml(reports.staff_performance || [], ["Nhân viên", "Role", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${Number(row.orders_processed || 0)}</td><td>${formatMoney(row.revenue_handled)}</td></tr>`)}</section>
      <section class="panel span-2"><h2>Hóa đơn gần nhất</h2>${invoiceActions}</section>
      <section class="panel span-2"><h2>Phien lam viec POS</h2>${sessionReportsTable()}</section>
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

function legacyRenderStaffModule() {
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
        <label>PIN POS <input name="pin" type="password" inputmode="numeric" minlength="4" placeholder="Để trống nếu không đổi"></label>
        <button class="primary-btn" type="submit">Lưu nhân viên</button>
      </form>
      <section class="panel"><h2>Danh sách nhân viên</h2>${tableHtml(staff, ["Tên", "Role", "Chi nhánh", "Email", ""], (row) => `<tr><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.email || "")}</td><td><button type="button" data-edit-staff="${row.id}">Sửa</button></td></tr>`)}</section>
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
        <label>Mã nhân viên <input name="staff_code" placeholder="CASH003"></label>
        <label>Tên nhân viên <input name="staff_name" required></label>
        <label>Chi nhanh <select name="branch_id">${branchOptions(state.pos.user?.branch_id || 1)}</select></label>
        <label>Role <select name="staff_role">${(cafeApp.roles || Object.keys(roleLabels)).map((role) => `<option value="${escapeHtml(role)}">${escapeHtml(roleLabels[role] || role)}</option>`).join("")}</select></label>
        <label>So dien thoai <input name="phone_number"></label>
        <label>Email <input type="email" name="email"></label>
        <label>Mật khẩu POS <input name="password" type="password" minlength="6" placeholder="Để trống nếu không đổi"></label>
        <label>PIN mở ca <input name="pin" type="password" inputmode="numeric" minlength="4" placeholder="Để trống nếu không đổi"></label>
        <button class="primary-btn" type="submit">Lưu nhân viên</button>
      </form>
      <section class="panel"><h2>Danh sách nhân viên</h2>${tableHtml(staff, ["Mã", "Tên", "Role", "Chi nhánh", "Email", ""], (row) => `<tr><td>${escapeHtml(row.staff_code || "")}</td><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.email || "")}</td><td><button type="button" data-edit-staff="${row.id}">Sửa</button></td></tr>`)}</section>
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
  cafeApp.staff = data.staff || [];
  cafeApp.tables = data.tables || [];
  cafeApp.orders = data.orders || [];
  cafeApp.kitchen = data.kitchen || [];
  cafeApp.dashboard = data.dashboard || null;
  cafeApp.campaigns = data.campaigns || [];
  cafeApp.inventory = data.inventory || [];
  cafeApp.reports = data.reports || {};
  cafeApp.session_reports = data.session_reports || [];
  if (data.current_session && state.pos.user) {
    savePosUser({
      ...state.pos.user,
      session_opened_at: data.current_session.opened_at,
      session_last_seen_at: data.current_session.last_seen_at,
    });
  }
  if (Array.isArray(data.session_reports)) {
    cafeApp.reports = { ...(cafeApp.reports || {}), session_reports: data.session_reports };
  }
  if (data.current_auth_session?.auth_session) {
    savePosAuth(data.current_auth_session);
  }
  syncProducts(data.products || []);
  if (showMessage) showToast("Đã làm mới dữ liệu POS.");
  renderPosApp();
}

function stopPosHeartbeat() {
  if (posHeartbeatTimer) {
    window.clearInterval(posHeartbeatTimer);
    posHeartbeatTimer = null;
  }
}

async function heartbeatPosSession() {
  if (section !== "pos" || pageName === "pos-login" || !state.pos.user?.session_token) return;
  try {
    if (state.pos.auth?.auth_token) {
      const authResult = await api("pos-auth-heartbeat");
      if (authResult.auth_session) {
        savePosAuth(authResult);
      }
    }
    const result = await api("pos-session-heartbeat");
    if (result.current_session) {
      cafeApp.current_session = result.current_session;
      savePosUser({
        ...state.pos.user,
        session_opened_at: result.current_session.opened_at,
        session_last_seen_at: result.current_session.last_seen_at,
      });
    }
  } catch (error) {
    stopPosHeartbeat();
    savePosUser(null);
    savePosAuth(null);
    showToast(error.message || "POS session đã hết hạn.");
    window.setTimeout(() => {
      window.location.href = url("pos/login");
    }, 900);
  }
}

function startPosHeartbeat() {
  stopPosHeartbeat();
  if (section !== "pos" || pageName === "pos-login" || !state.pos.user?.session_token) return;
  heartbeatPosSession();
  posHeartbeatTimer = window.setInterval(heartbeatPosSession, 60000);
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
  const search = (document.querySelector("[data-site-product-search]")?.value || "").trim().toLowerCase();
  const category = document.querySelector("[data-site-category-filter]")?.value || "";
  const sort = document.querySelector("[data-site-sort]")?.value || "";
  let rows = products.filter((product) => {
    const matchesSearch = !search
      || String(product.product_name || "").toLowerCase().includes(search)
      || String(product.take_note || "").toLowerCase().includes(search);
    const matchesCategory = !category || String(product.category || "") === category;
    return matchesSearch && matchesCategory;
  });
  rows = rows.sort((a, b) => {
    if (sort === "price_asc") return Number(a.price || 0) - Number(b.price || 0);
    if (sort === "price_desc") return Number(b.price || 0) - Number(a.price || 0);
    if (sort === "name_desc") return String(b.product_name || "").localeCompare(String(a.product_name || ""));
    return 0;
  });
  rows = limit > 0 ? rows.slice(0, limit) : rows;
  target.innerHTML = rows.map((product) => {
    const isFavorite = state.site.customer?.favorites?.includes(Number(product.id));
    const isOut = Boolean(product.is_out_of_stock) || Number(product.stock_quantity || 0) <= 0;
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
          <span class="status ${isOut ? "bad" : "good"}">${isOut ? "Tam het" : "Con hang"}</span>
          <div class="product-actions">
            <strong>${formatMoney(product.price)}</strong>
            <a class="secondary-link" href="${url(`product?id=${product.id}`)}">Chi tiet</a>
            <button type="button" data-site-add="${product.id}" ${isOut ? "disabled" : ""}>Order Now</button>
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

function legacyRenderPosLoginWithPin() {
  const root = document.querySelector("#pos-app");
  if (!root) return;

  const roles = cafeApp.roles || Object.keys(roleLabels);
  const staff = cafeApp.staff || [];
  const filteredStaff = state.pos.roleFilter ? staff.filter((member) => member.staff_role === state.pos.roleFilter) : staff;
  const selectedStaff = staff.find((member) => String(member.id) === String(state.pos.loginStaffId)) || null;
  const pinDots = state.pos.loginPin.padEnd(4, " ").slice(0, 4).split("").map((char) => `<span class="${char.trim() ? "filled" : ""}"></span>`).join("");
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
        <h1>Chọn nhân viên và nhập PIN</h1>
        <p>Mỗi nhân viên phải nhập PIN trước khi hệ thống mở phiên làm việc POS và tạo session token.</p>
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
            <button type="button" class="staff-card ${String(state.pos.loginStaffId) === String(member.id) ? "active" : ""}" data-login-staff="${member.id}">
              <span class="avatar">${escapeHtml((member.staff_name || "?").slice(0, 1))}</span>
              <strong>${escapeHtml(member.staff_name)}</strong>
              <small>${escapeHtml(roleLabels[member.staff_role] || member.staff_role)} · ${escapeHtml(member.branch_name)}</small>
            </button>
          `).join("") || '<div class="empty-state">Không có nhân viên phù hợp.</div>'}
        </div>
      </section>
      <aside class="login-aside">
        <div class="pin-panel">
          <p class="eyebrow">Mở ca POS</p>
          <h2>${selectedStaff ? escapeHtml(selectedStaff.staff_name) : "Chưa chọn nhân viên"}</h2>
          <p>${selectedStaff ? `${escapeHtml(roleLabels[selectedStaff.staff_role] || selectedStaff.staff_role)} · ${escapeHtml(selectedStaff.branch_name)}` : "Chọn role và nhân viên bên trái trước khi nhập PIN."}</p>
          <div class="pin-dots" aria-label="PIN">${pinDots}</div>
          <div class="pin-keypad">
            ${["1","2","3","4","5","6","7","8","9"].map((digit) => `<button type="button" data-pin-digit="${digit}">${digit}</button>`).join("")}
            <button type="button" data-pin-clear>Clear</button>
            <button type="button" data-pin-digit="0">0</button>
            <button type="button" data-pin-backspace>⌫</button>
          </div>
          <button type="button" class="primary-btn full" data-pin-submit ${selectedStaff && state.pos.loginPin.length >= 4 ? "" : "disabled"}>Đăng nhập POS</button>
          <small class="login-hint">PIN demo: waiter 1111, cashier 2222, barista 3333, owner 4444, marketing 5555, admin 6666, manager 7777.</small>
        </div>
      </aside>
    </main>
  `;
}

function renderPosLogin() {
  const root = document.querySelector("#pos-app");
  if (!root) return;

  const roles = cafeApp.roles || Object.keys(roleLabels);
  const staff = cafeApp.staff || [];
  const filteredStaff = state.pos.roleFilter ? staff.filter((member) => member.staff_role === state.pos.roleFilter) : staff;
  const auth = state.pos.auth;
  const pinDots = state.pos.loginPin.padEnd(4, " ").slice(0, 4).split("").map((char) => `<span class="${char.trim() ? "filled" : ""}"></span>`).join("");
  const authIdentity = auth?.staff_code || auth?.email || auth?.phone_number || "";
  const demoByRole = {
    waiter: "WAIT001 / waiter123 / PIN 1111",
    cashier: "CASH001 / cashier123 / PIN 2222",
    barista: "BAR001 / barista123 / PIN 3333",
    owner: "OWNER001 / owner123 / PIN 4444",
    marketing: "MKT001 / marketing123 / PIN 5555",
    admin: "ADMIN001 / admin123 / PIN 6666",
    manager: "MGR001 / manager123 / PIN 7777",
  };

  if (state.pos.user?.session_token) {
    root.innerHTML = `
      <main class="pos-login login-page">
        <section class="login-card session-open-card">
          <div class="logo-lockup">
            <span class="logo-mark">C</span>
            <div>
              <p>Cafe Connect</p>
              <strong>POS Manager</strong>
            </div>
          </div>
          <div class="session-open-panel">
            <span class="step-badge">Ca đang mở</span>
            <h1>Sẵn sàng làm việc</h1>
            <p>${escapeHtml(state.pos.user.staff_name)} đang có phiên làm việc từ ${escapeHtml(formatDateTime(state.pos.user.session_opened_at))}.</p>
            <div class="account-actions">
              <a class="primary-btn" href="${url("pos/checkout")}">Vao POS</a>
              <button class="secondary-btn" type="button" data-pos-logout>Đóng ca và đăng xuất</button>
            </div>
          </div>
        </section>
      </main>
    `;
    return;
  }

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
        <div class="login-workspace">
          <div class="login-step auth-step ${auth ? "is-complete" : ""}">
            <div class="step-head">
              <span class="step-badge">Buoc 1</span>
              <div>
                <h1>Đăng nhập POS</h1>
              </div>
            </div>
            ${auth ? `
              <div class="auth-summary">
                <span class="avatar">${escapeHtml((auth.staff_name || "?").slice(0, 1))}</span>
                <div>
                  <strong>${escapeHtml(auth.staff_name)}</strong>
                  <small>${escapeHtml(auth.staff_code || "")} - ${escapeHtml(roleLabels[auth.staff_role] || auth.staff_role)} - ${escapeHtml(auth.branch_name || "")}</small>
                  <em>Đăng nhập lúc ${escapeHtml(formatDateTime(auth.auth_logged_in_at))}</em>
                </div>
                <button type="button" class="secondary-btn" data-pos-auth-logout>Đổi tài khoản</button>
              </div>  
            ` : `
              <form class="pos-auth-form" data-pos-auth-login>
                <label><span>Mã NV / Email / Số điện thoại</span><input name="identity" value="${escapeHtml(authIdentity || "CASH001")}" autocomplete="username" required></label>
                <label><span>Mật khẩu</span><input name="password" type="password" value="cashier123" autocomplete="current-password" required></label>
                <button class="primary-btn full" type="submit">Đăng nhập</button>
              </form>
            `}
            <div class="role-grid demo-role-grid">
              <button type="button" class="role-card ${state.pos.roleFilter === "" ? "active" : ""}" data-login-role="">
                <strong>Tat ca</strong>
                <span>${staff.length} nhân viên</span>
              </button>
              ${roles.map((role) => `
                <button type="button" class="role-card ${state.pos.roleFilter === role ? "active" : ""}" data-login-role="${escapeHtml(role)}">
                  <strong>${escapeHtml(roleLabels[role] || role)}</strong>
                  <span>${escapeHtml(demoByRole[role] || `${staff.filter((member) => member.staff_role === role).length} nhân viên`)}</span>
                </button>
              `).join("")}
            </div>
            <div class="staff-grid staff-preview-list">
              ${filteredStaff.map((member) => `
                <article class="staff-card">
                  <span class="avatar">${escapeHtml((member.staff_name || "?").slice(0, 1))}</span>
                  <div>
                    <strong>${escapeHtml(member.staff_name)}</strong>
                    <small>${escapeHtml(member.staff_code || "")} - ${escapeHtml(roleLabels[member.staff_role] || member.staff_role)} - ${escapeHtml(member.branch_name)}</small>
                  </div>
                </article>
              `).join("") || '<div class="empty-state">Không có nhân viên phù hợp.</div>'}
            </div>
          </div>

          <aside class="login-step pin-step ${auth ? "is-ready" : "is-locked"}">
            <div class="step-head">
              <span class="step-badge">Buoc 2</span>
              <div>
                <h2>Nhập PIN mở ca</h2>
                <p>${auth ? `${escapeHtml(auth.staff_code || "")} - ${escapeHtml(roleLabels[auth.staff_role] || auth.staff_role)} - ${escapeHtml(auth.branch_name)}` : ""}</p>
              </div>
            </div>
            <div class="pin-panel">
              <div class="pin-lock ${auth ? "is-unlocked" : ""}">${auth ? "OK" : "LOCK"}</div>
              <h3>${auth ? escapeHtml(auth.staff_name) : "Đang khoá"}</h3>
              <div class="pin-dots" aria-label="PIN">${pinDots}</div>
              <div class="pin-keypad">
                ${["1","2","3","4","5","6","7","8","9"].map((digit) => `<button type="button" data-pin-digit="${digit}" ${auth ? "" : "disabled"}>${digit}</button>`).join("")}
                <button type="button" data-pin-clear ${auth ? "" : "disabled"}>Clear</button>
                <button type="button" data-pin-digit="0" ${auth ? "" : "disabled"}>0</button>
                <button type="button" data-pin-backspace ${auth ? "" : "disabled"}>Back</button>
              </div>
              <button type="button" class="primary-btn full" data-pin-submit ${auth && state.pos.loginPin.length >= 4 ? "" : "disabled"}>Mở ca POS</button>
              <small class="login-hint">PIN chỉ xác nhận mở ca làm, không thay thế mật khẩu đăng nhập.</small>
            </div>
          </aside>
        </div>
      </section>
      <aside class="login-aside">
        <div class="login-preview">
          <div class="preview-bar"><span class="preview-dot"></span><span class="preview-dot"></span><span class="preview-dot"></span></div>
          <div class="preview-body">
            <div>
              <span class="preview-kpi"></span>
              <span class="preview-kpi short"></span>
            </div>
            <div class="preview-grid">
              <span class="preview-tile"></span>
              <span class="preview-tile"></span>
              <span class="preview-tile"></span>
              <span class="preview-tile"></span>
            </div>
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
  const sessionStarted = state.pos.user.session_opened_at || cafeApp.current_session?.opened_at || "";
  const sessionDuration = durationSince(sessionStarted, cafeApp.current_session?.closed_at || "");
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
        <span class="session-chip">Ca ${escapeHtml(formatDateTime(sessionStarted))} - ${escapeHtml(sessionDuration)}</span>
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
    const pinDigit = event.target.closest("[data-pin-digit]");
    const pinClear = event.target.closest("[data-pin-clear]");
    const pinBackspace = event.target.closest("[data-pin-backspace]");
    const pinSubmit = event.target.closest("[data-pin-submit]");
    const siteAdd = event.target.closest("[data-site-add]");
    const posAdd = event.target.closest("[data-pos-add]");
    const quantity = event.target.closest("[data-cart-scope][data-delta]");
    const remove = event.target.closest("[data-cart-scope][data-remove]");
    const tableCard = event.target.closest("[data-select-table]");
    const updateItem = event.target.closest("[data-update-item]");
    const voidItem = event.target.closest("[data-void-item]");
    const cancelOrder = event.target.closest("[data-cancel-order]");
    const orderCheckout = event.target.closest("[data-order-checkout]");
    const receiptInvoice = event.target.closest("[data-receipt-invoice]");
    const refundInvoice = event.target.closest("[data-refund-invoice]");
    const reportExport = event.target.closest("[data-report-export]");
    const receiptClose = event.target.closest("[data-receipt-close]");
    const receiptPrint = event.target.closest("[data-receipt-print]");
    const claimVoucher = event.target.closest("[data-claim-voucher]");
    const favorite = event.target.closest("[data-favorite-product]");
    const websiteOrderCancel = event.target.closest("[data-website-order-cancel]");
    const websiteOrderReceipt = event.target.closest("[data-order-receipt]");
    const editProduct = event.target.closest("[data-edit-product]");
    const editStaff = event.target.closest("[data-edit-staff]");
    const fillLogin = event.target.closest("[data-fill-login]");
    const passwordToggle = event.target.closest("[data-password-toggle]");
    const memberMenuToggle = event.target.closest("[data-member-menu-toggle]");
    const memberNav = event.target.closest("[data-member-nav]");

    if (memberMenuToggle) {
      const menu = document.querySelector("[data-member-menu]");
      const isOpen = menu && !menu.hidden;
      if (menu) menu.hidden = isOpen;
      memberMenuToggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
      return;
    }
    if (!memberNav) {
      closeMemberMenu();
    }

    if (passwordToggle) {
      const field = passwordToggle.closest(".password-field");
      const input = field?.querySelector("input");
      if (input) {
        const shouldShow = input.type === "password";
        input.type = shouldShow ? "text" : "password";
        passwordToggle.textContent = shouldShow ? "Ẩn" : "Hiện";
      }
      return;
    }

    if (fillLogin) {
      const form = document.querySelector("[data-member-login]");
      if (form) {
        form.elements.identity.value = fillLogin.dataset.identity || "";
        form.elements.password.value = fillLogin.dataset.password || "";
        form.elements.identity.focus();
      }
      return;
    }

    if (roleButton) {
      state.pos.roleFilter = roleButton.dataset.loginRole || "";
      state.pos.loginStaffId = "";
      state.pos.loginPin = "";
      renderPosLogin();
      return;
    }
    if (loginStaff) {
      state.pos.loginStaffId = loginStaff.dataset.loginStaff || "";
      state.pos.loginPin = "";
      renderPosLogin();
      return;
    }
    if (pinDigit) {
      if (state.pos.loginPin.length < 6) {
        state.pos.loginPin += pinDigit.dataset.pinDigit || "";
      }
      renderPosLogin();
      return;
    }
    if (pinClear) {
      state.pos.loginPin = "";
      renderPosLogin();
      return;
    }
    if (pinBackspace) {
      state.pos.loginPin = state.pos.loginPin.slice(0, -1);
      renderPosLogin();
      return;
    }
    if (pinSubmit) {
      const auth = state.pos.auth;
      if (auth) {
        let openedStaff = auth;
        try {
          const result = await api("pos-session-login", {
            staff_id: auth.id,
            branch_id: auth.branch_id,
            pin: state.pos.loginPin,
            opening_cash_amount: auth.staff_role === "cashier" ? 1000000 : 0,
          });
          cafeApp.current_session = result.session || null;
          openedStaff = result.staff || auth;
          savePosUser(result.staff || auth);
          state.pos.loginPin = "";
          state.pos.loginStaffId = "";
          startPosHeartbeat();
          showToast("Đã mở phiên làm việc POS.");
        } catch (error) {
          state.pos.loginPin = "";
          renderPosLogin();
          showToast(error.message);
          return;
        }
        window.location.href = url(`pos/${allowedModules(openedStaff)[0]?.id || "checkout"}`);
      } else {
        showToast("Đăng nhập tài khoản POS trước khi mở ca.");
      }
      return;
    }
    if (event.target.closest("[data-pos-logout]")) {
      let logoutMessage = "";
      try {
        if (state.pos.user?.session_token) {
          const logoutPayload = {};
          if (state.pos.user.staff_role === "cashier") {
            const closingCash = window.prompt("Nhập tiền mặt thực tế khi chốt ca:");
            if (closingCash === null || closingCash.trim() === "") return;
            logoutPayload.closing_cash_amount = closingCash.trim();
            logoutPayload.notes = "Cashier closing shift";
          }
          await api("shift-closing", logoutPayload);
        }
      } catch (error) {
        logoutMessage = error.message;
      }
      try {
        if (state.pos.auth?.auth_token) {
          await api("pos-auth-logout");
        }
      } catch (error) {
        logoutMessage = logoutMessage || error.message;
      }
      if (logoutMessage) showToast(logoutMessage);
      stopPosHeartbeat();
      savePosUser(null);
      savePosAuth(null);
      window.location.href = url("pos/login");
      return;
    }
    if (event.target.closest("[data-pos-auth-logout]")) {
      try {
        if (state.pos.auth?.auth_token) {
          await api("pos-auth-logout");
        }
      } catch (error) {
        showToast(error.message);
      }
      state.pos.loginPin = "";
      savePosUser(null);
      savePosAuth(null);
      renderPosLogin();
      return;
    }
    if (event.target.closest("[data-member-logout]")) {
      try {
        await api("member-logout");
        closeMemberMenu();
        setSiteMember(null);
        setWebStaff(null);
        showToast("Đã đăng xuất tài khoản.");
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
    if (voidItem) {
      const reason = window.prompt("Lý do void món?");
      if (!reason || !reason.trim()) return;
      try {
        const result = await api("void-order-item", { item_id: voidItem.dataset.voidItem, reason: reason.trim() });
        updatePosCollections(result);
        renderPosApp();
        showToast("Đã void món.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (cancelOrder) {
      const reason = window.prompt("Lý do hủy order?");
      if (!reason || !reason.trim()) return;
      try {
        const result = await api("cancel-order", { order_id: cancelOrder.dataset.cancelOrder, reason: reason.trim() });
        updatePosCollections(result);
        renderPosApp();
        showToast("Đã hủy order.");
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
    if (receiptInvoice) {
      try {
        const receipt = await api("receipt", { invoice_id: receiptInvoice.dataset.receiptInvoice });
        showReceiptDialog(receipt);
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (refundInvoice) {
      const reason = window.prompt("Lý do hoàn tiền?");
      if (!reason || !reason.trim()) return;
      try {
        await api("refund-invoice", { invoice_id: refundInvoice.dataset.refundInvoice, reason: reason.trim() });
        await refreshPosData(false);
        showToast("Đã hoàn tiền hóa đơn.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (reportExport) {
      try {
        const result = await api("reports-export");
        downloadTextFile(result.filename || "cafe-connect-report.csv", result.csv || "");
        showToast("Đã xuất CSV.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (receiptClose) {
      document.querySelector("[data-receipt-dialog]")?.remove();
      return;
    }
    if (receiptPrint) {
      try {
        await api("receipt-print-log", { invoice_id: receiptPrint.dataset.receiptPrint, receipt_type: "html" });
      } catch (error) {
        showToast(error.message);
      }
      window.print();
      return;
    }
    if (websiteOrderReceipt) {
      try {
        const receipt = await api("website-order-detail", { invoice_id: Number(queryParam("invoice_id") || 0) });
        showReceiptDialog(receipt);
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (websiteOrderCancel) {
      const reason = window.prompt("Lý do hủy đơn?");
      if (!reason || !reason.trim()) return;
      try {
        const receipt = await api("website-order-cancel", {
          invoice_id: websiteOrderCancel.dataset.websiteOrderCancel,
          reason: reason.trim(),
        });
        renderWebsiteOrderDetail(receipt);
        if (state.site.customer) {
          const refreshed = await api("member-lookup", { identity: state.site.customer.id });
          setSiteMember(refreshed);
          renderProfile("account", refreshed);
        }
        showToast("Đã hủy đơn đang chờ.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (claimVoucher) {
      try {
        const result = await api("voucher-claim", { promotion_id: claimVoucher.dataset.claimVoucher });
        setSiteMember(result.member);
        renderProfile("portal", result.member);
        renderProfile("account", result.member);
        showToast(`Đã nhận voucher ${result.voucher_code}.`);
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
        if (form.elements.staff_code) form.elements.staff_code.value = staff.staff_code || "";
        form.elements.staff_name.value = staff.staff_name;
        form.elements.branch_id.value = staff.branch_id;
        form.elements.staff_role.value = staff.staff_role;
        form.elements.phone_number.value = staff.phone_number || "";
        form.elements.email.value = staff.email || "";
        if (form.elements.password) form.elements.password.value = "";
        if (form.elements.pin) form.elements.pin.value = "";
      }
    }
  });

  document.addEventListener("submit", async (event) => {
    const lookupForm = event.target.closest("[data-member-lookup]");
    const posAuthForm = event.target.closest("[data-pos-auth-login]");
    const memberLoginForm = event.target.closest("[data-member-login]");
    const memberRegisterForm = event.target.closest("[data-member-register]");
    const memberProfileForm = event.target.closest("[data-member-profile-update]");
    const memberPasswordForm = event.target.closest("[data-member-change-password]");
    const memberForgotForm = event.target.closest("[data-member-forgot-password]");
    const memberResetForm = event.target.closest("[data-member-reset-password]");
    const createForm = event.target.closest("[data-customer-create]");
    const newsletterForm = event.target.closest("[data-newsletter-form]");
    const serviceOrderForm = event.target.closest("[data-service-order-create]");
    const campaignForm = event.target.closest("[data-campaign-create]");
    const stockForm = event.target.closest("[data-stock-movement]");
    const cashForm = event.target.closest("[data-cash-transaction]");
    const productForm = event.target.closest("[data-product-save]");
    const staffForm = event.target.closest("[data-staff-save]");

    if (posAuthForm) {
      event.preventDefault();
      try {
        const result = await api("pos-auth-login", Object.fromEntries(new FormData(posAuthForm)));
        savePosAuth(result);
        savePosUser(null);
        state.pos.loginPin = "";
        renderPosLogin();
        showToast("Đã đăng nhập tài khoản POS. Nhập PIN để mở ca.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberLoginForm) {
      event.preventDefault();
      try {
        const result = await api("member-login", Object.fromEntries(new FormData(memberLoginForm)));
        if (result.account_type === "staff") {
          setWebStaff(result.web_staff, result.auth_session);
          showToast("Đăng nhập nhân viên thành công. Có thể mở POS bằng PIN.");
          if (pageName === "website-login") {
            window.location.href = url("account");
          }
          return;
        }
        setSiteMember(result.member);
        showToast("Đăng nhập thành viên thành công.");
        if (pageName === "website-login") {
          window.location.href = url("account");
        }
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
        showToast("Đã tạo tài khoản thành viên.");
        if (pageName === "website-register") {
          await navigateWebsite(url(""));
        }
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberProfileForm) {
      event.preventDefault();
      try {
        const result = await api("member-profile-update", Object.fromEntries(new FormData(memberProfileForm)));
        if (result.web_staff) {
          setWebStaff(result.web_staff);
        } else {
          setSiteMember(result.member);
        }
        showToast("Đã cập nhật hồ sơ.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberPasswordForm) {
      event.preventDefault();
      try {
        await api("member-change-password", Object.fromEntries(new FormData(memberPasswordForm)));
        memberPasswordForm.reset();
        showToast("Đã đổi mật khẩu.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberForgotForm) {
      event.preventDefault();
      try {
        const result = await api("member-forgot-password", Object.fromEntries(new FormData(memberForgotForm)));
        memberForgotForm.reset();
        setMessage("[data-auth-message]", `Đã gửi link đặt lại mật khẩu tới ${result.email}.`);
        showToast("Đã gửi email đặt lại mật khẩu.");
      } catch (error) {
        setMessage("[data-auth-message]", error.message, true);
        showToast(error.message);
      }
      return;
    }
    if (memberResetForm) {
      event.preventDefault();
      try {
        await api("member-reset-password", Object.fromEntries(new FormData(memberResetForm)));
        memberResetForm.reset();
        setSiteMember(null);
        setMessage("[data-auth-message]", "Đã đặt lại mật khẩu. Vui lòng đăng nhập bằng mật khẩu mới.");
        showToast("Đã đặt lại mật khẩu.");
        window.setTimeout(() => navigateWebsite(url("login")), 900);
      } catch (error) {
        setMessage("[data-auth-message]", error.message, true);
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
        state.pos.billStartedAt = "";
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
    const siteFilter = event.target.closest("[data-site-category-filter], [data-site-sort]");
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
      return;
    }
    if (siteFilter) {
      renderSiteProducts();
    }
  });

  document.addEventListener("input", (event) => {
    const productSearch = event.target.closest("[data-product-search]");
    const siteProductSearch = event.target.closest("[data-site-product-search]");
    if (productSearch) {
      state.pos.productFilter = productSearch.value;
      renderPosProducts();
    }
    if (siteProductSearch) {
      renderSiteProducts();
    }
  });
}

function initialRender() {
  renderHeaderPosLink();
  renderMemberNav();
  renderAccountState();
  renderAccountForm();
  renderMemberAccount();
  renderSiteProducts();
  renderReviews();
  renderCart("site");
  loadWebsiteOrderDetail();
  if (state.site.customer) {
    renderMiniMember("site");
    renderVoucherOptions("site");
    renderProfile("portal", state.site.customer);
    renderProfile("account", state.site.customer);
  }
  adoptWebStaffFromPosAuth();
  if (section === "pos") {
    renderPosApp();
    if (pageName !== "pos-login" && state.pos.user?.session_token) {
      refreshPosData(false).catch((error) => showToast(error.message));
    }
    startPosHeartbeat();
  }
}

wireEvents();
initialRender();
