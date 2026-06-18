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

const customerCrmRoles = ["cashier", "marketing", "manager", "owner", "admin"];

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
    adminProductSearch: "",
    adminProductStatus: "all",
    adminProductCategory: "",
    editingCampaignId: "",
    reportStartDate: "",
    reportEndDate: "",
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

function newCartLineId() {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return `line-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function normalizeSize(size) {
  return ["S", "M", "L"].includes(String(size || "").toUpperCase()) ? String(size).toUpperCase() : "M";
}

function normalizeCartNote(note) {
  return String(note || "").trim().replace(/\s+/g, " ");
}

function siteCartMergeKey(item) {
  return [
    Number(item.product_id || 0),
    normalizeSize(item.size),
    normalizeCartNote(item.topping).toLowerCase(),
  ].join("|");
}

function normalizeSiteCartItem(item) {
  return {
    line_id: item.line_id || item.cart_key || newCartLineId(),
    product_id: Number(item.product_id || 0),
    quantity: Math.max(1, Number(item.quantity || 1)),
    size: normalizeSize(item.size),
    topping: normalizeCartNote(item.topping),
  };
}

function normalizePosCartItem(item) {
  return {
    line_id: item.line_id || item.cart_key || newCartLineId(),
    product_id: Number(item.product_id || 0),
    quantity: Math.max(1, Number(item.quantity || 1)),
    size: normalizeSize(item.size),
    topping: normalizeCartNote(item.topping).slice(0, 100),
    note: normalizeCartNote(item.note).slice(0, 180),
  };
}

function posCartMergeKey(item) {
  return [
    Number(item.product_id || 0),
    normalizeSize(item.size),
    normalizeCartNote(item.topping).toLowerCase(),
    normalizeCartNote(item.note).toLowerCase(),
  ].join("|");
}

function posCartItemForInvoice(item) {
  const topping = [normalizeCartNote(item.topping), normalizeCartNote(item.note)]
    .filter(Boolean)
    .join(" | ")
    .slice(0, 100);
  return {
    ...cartItemPayload(item),
    size: normalizeSize(item.size),
    topping,
  };
}

function loadSiteCart() {
  try {
    const raw = localStorage.getItem("cafe_site_cart");
    const cart = raw ? JSON.parse(raw) : [];
    return Array.isArray(cart) ? cart.map(normalizeSiteCartItem).filter((item) => item.product_id > 0) : [];
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
  renderCustomerCrmLinks();
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
  renderCustomerCrmLinks();
}

const formatMoney = (value) =>
  new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));

function productSizePrices(product) {
  const basePrice = Number(product?.price || 0);
  const isFood = String(product?.category || "").toLowerCase() === "food";
  const prices = isFood
    ? { S: basePrice, M: basePrice, L: basePrice }
    : { S: Math.max(0, basePrice - 5000), M: basePrice, L: basePrice + 7000 };
  const raw = product?.size_prices || {};
  if (Array.isArray(raw)) {
    raw.forEach((entry) => {
      if (entry && entry.size !== undefined) {
        prices[normalizeSize(entry.size)] = Number(entry.price || 0);
      }
    });
  } else if (raw && typeof raw === "object") {
    ["S", "M", "L"].forEach((size) => {
      if (raw[size] !== undefined && raw[size] !== null && raw[size] !== "") {
        prices[size] = Number(raw[size] || 0);
      }
    });
  }
  return prices;
}

function productPriceForSize(product, size = "M", fallbackPrice = 0) {
  const normalized = normalizeSize(size);
  if (!product) return Number(fallbackPrice || 0);
  return Number(productSizePrices(product)[normalized] ?? product.price ?? fallbackPrice ?? 0);
}

function productPriceRangeLabel(product) {
  const prices = productSizePrices(product);
  const values = ["S", "M", "L"].map((size) => Number(prices[size] || 0));
  const min = Math.min(...values);
  const max = Math.max(...values);
  if (min === max) return formatMoney(min);
  return `${formatMoney(min)} - ${formatMoney(max)}`;
}

function sizeOptionLabel(product, size) {
  return `${size} - ${formatMoney(productPriceForSize(product, size))}`;
}

function cartItemUnitPrice(item) {
  const product = productMap.get(Number(item.product_id));
  return productPriceForSize(product, item.size, item.unit_price);
}

function cartItemPayload(item) {
  const unitPrice = cartItemUnitPrice(item);
  const quantity = Number(item.quantity || 0);
  return {
    ...item,
    size: normalizeSize(item.size),
    unit_price: unitPrice,
    line_total: unitPrice * quantity,
  };
}

function sqlNow() {
  const value = new Date();
  const pad = (number) => String(number).padStart(2, "0");
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())} ${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`;
}

function sqlDate(daysFromToday = 0) {
  const value = new Date();
  value.setDate(value.getDate() + Number(daysFromToday || 0));
  const pad = (number) => String(number).padStart(2, "0");
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}`;
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
      <a href="${url("feedback")}">Feedback</a>
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
  if (state.site.customer) return null;
  if (state.site.webStaff) return state.site.webStaff;
  if (section === "website" && state.pos.auth?.auth_token) return state.pos.auth;
  return null;
}

function canAccessCustomerCrm(staff = activeWebStaff()) {
  return Boolean(staff?.staff_role && customerCrmRoles.includes(staff.staff_role));
}

function renderCustomerCrmLinks() {
  const allowed = canAccessCustomerCrm();
  document.querySelectorAll("[data-crm-member-link]").forEach((link) => link.remove());

  if (!allowed) {
    document.querySelectorAll("[data-crm-footer-slot]").forEach((slot) => {
      slot.innerHTML = "";
    });
    return;
  }

  const nav = document.querySelector("[data-nav]");
  if (nav) {
    const posLink = nav.querySelector("[data-pos-header-link]");
    const memberLinkHtml = `<a href="${url("member")}" data-crm-member-link>Thành viên</a>`;
    if (posLink) {
      posLink.insertAdjacentHTML("beforebegin", memberLinkHtml);
    } else {
      nav.insertAdjacentHTML("beforeend", memberLinkHtml);
    }
  }

  document.querySelectorAll("[data-crm-footer-slot]").forEach((slot) => {
    slot.innerHTML = `<a href="${url("member")}" data-crm-member-link>CRM khách hàng</a>`;
  });
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
    if (pushState) {
      window.history.pushState({ cafePjax: true }, "", targetUrl.href);
    }
    initialRender();
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

function syncProductAdminPayload(result = {}) {
  if (Array.isArray(result.products)) {
    syncProducts(result.products);
  }
  if (Array.isArray(result.admin_products)) {
    cafeApp.admin_products = result.admin_products;
  }
  if (Array.isArray(result.categories)) {
    cafeApp.categories = result.categories;
  }
  if (Array.isArray(result.admin_categories)) {
    cafeApp.admin_categories = result.admin_categories;
  }
}

function syncCampaignPayload(result = {}) {
  if (Array.isArray(result.campaigns)) {
    cafeApp.campaigns = result.campaigns;
    if (cafeApp.dashboard) {
      cafeApp.dashboard.campaigns = result.campaigns;
    }
  }
}

function appendFormIfMissing(formData, key, value) {
  if (formData.has(key) || value === undefined || value === null || value === "") return;
  formData.append(key, value);
}

async function apiForm(endpoint, formData) {
  if (!cafeInstalled) {
    throw new Error("Database chưa sẵn sàng. Hãy chạy install.php trước.");
  }

  const clean = String(endpoint).replace(/^\/?api\/?/, "");
  if (section === "pos" && state.pos.auth) {
    if (!formData.has("staff_id") && !state.pos.user) appendFormIfMissing(formData, "staff_id", state.pos.auth.id);
    appendFormIfMissing(formData, "auth_session_id", state.pos.auth.auth_session_id);
    appendFormIfMissing(formData, "auth_token", state.pos.auth.auth_token);
  }
  if (section === "pos" && state.pos.user) {
    appendFormIfMissing(formData, "staff_id", state.pos.user.id);
    appendFormIfMissing(formData, "pos_session_id", state.pos.user.pos_session_id);
    appendFormIfMissing(formData, "session_token", state.pos.user.session_token);
    appendFormIfMissing(formData, "staff_role", state.pos.user.staff_role);
    appendFormIfMissing(formData, "branch_id", state.pos.user.branch_id);
  }

  const headers = {};
  if (window.CAFE_CSRF_TOKEN) {
    headers["X-CSRF-Token"] = window.CAFE_CSRF_TOKEN;
  }

  const response = await fetch(`${apiBase}?endpoint=${encodeURIComponent(clean)}`, {
    method: "POST",
    headers,
    body: formData,
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

function selectedSiteBranchId() {
  const select = document.querySelector("[data-site-branch]");
  return select ? Number(select.value || 0) : 0;
}

function availableProductQuantity(product, branchId = 0) {
  if (!product) return 0;
  const numericBranchId = Number(branchId || 0);
  if (numericBranchId > 0 && Array.isArray(product.branch_inventory)) {
    const branchStock = product.branch_inventory.find((item) => Number(item.branch_id) === numericBranchId);
    return Math.max(0, Math.floor(Number(branchStock?.stock_quantity || 0)));
  }
  if (!Object.prototype.hasOwnProperty.call(product, "stock_quantity")) return Number.MAX_SAFE_INTEGER;
  return Math.max(0, Math.floor(Number(product.stock_quantity || 0)));
}

function cartRequiredByProduct(cart = state.site.cart) {
  const required = new Map();
  cart.forEach((item) => {
    const productId = Number(item.product_id || 0);
    if (!productId) return;
    required.set(productId, (required.get(productId) || 0) + Number(item.quantity || 0));
  });
  return required;
}

function siteCartStockIssues(branchId, cart = state.site.cart) {
  const numericBranchId = Number(branchId || 0);
  if (!numericBranchId) return [];

  const issues = [];
  cartRequiredByProduct(cart).forEach((required, productId) => {
    const product = productMap.get(Number(productId));
    const available = availableProductQuantity(product, numericBranchId);
    if (available < required) {
      issues.push({
        product_id: productId,
        product_name: product?.product_name || "Sản phẩm",
        available,
        required,
      });
    }
  });
  return issues;
}

function branchNameById(branchId) {
  const branch = (cafeApp.branches || []).find((item) => Number(item.id) === Number(branchId));
  return branch?.branch_name || "chi nhánh phù hợp";
}

function bestSiteBranchForCart(cart = state.site.cart) {
  const selected = selectedSiteBranchId();
  if (selected && !siteCartStockIssues(selected, cart).length) return selected;

  const branches = Array.isArray(cafeApp.branches) ? cafeApp.branches : [];
  const candidate = branches.find((branch) => !siteCartStockIssues(Number(branch.id), cart).length);
  return Number(candidate?.id || selected || branches[0]?.id || 0);
}

function ensureSiteBranchCanFulfill(options = {}) {
  const select = document.querySelector("[data-site-branch]");
  if (!select) return 0;

  const current = selectedSiteBranchId();
  const best = bestSiteBranchForCart();
  if (best && current !== best && !siteCartStockIssues(best).length) {
    select.value = String(best);
    if (options.notify) {
      showToast(`Đã chuyển sang ${branchNameById(best)} vì chi nhánh cũ không đủ tồn kho.`);
    }
  }

  return selectedSiteBranchId();
}

function isProductSellable(product) {
  return Boolean(product)
    && product.status !== "inactive"
    && !product.is_out_of_stock
    && availableProductQuantity(product) > 0;
}

function sanitizeSiteCart(options = {}) {
  const { persist = false, notify = false } = options;
  const branchId = Number(options.branchId ?? selectedSiteBranchId() ?? 0);
  const merged = new Map();
  let changed = false;

  for (const rawItem of state.site.cart) {
    const item = normalizeSiteCartItem(rawItem);
    const product = productMap.get(Number(item.product_id));
    if (!isProductSellable(product)) {
      changed = true;
      continue;
    }

    const key = siteCartMergeKey(item);
    const existing = merged.get(key);
    if (existing) {
      existing.quantity += item.quantity;
      changed = true;
    } else {
      merged.set(key, item);
    }
  }

  const clean = [];
  const remainingByProduct = new Map();
  for (const item of merged.values()) {
    const product = productMap.get(Number(item.product_id));
    if (!remainingByProduct.has(Number(item.product_id))) {
      remainingByProduct.set(Number(item.product_id), availableProductQuantity(product, branchId));
    }
    const remaining = remainingByProduct.get(Number(item.product_id)) || 0;
    const quantity = Math.min(Math.max(1, Number(item.quantity || 1)), remaining);
    if (quantity !== item.quantity) changed = true;
    if (quantity > 0) {
      clean.push({ ...item, quantity });
      remainingByProduct.set(Number(item.product_id), remaining - quantity);
    }
  }

  if (changed || persist) {
    state.site.cart = clean;
    saveSiteCart();
    if (changed && notify) {
      showToast("Giỏ hàng đã được cập nhật theo tồn kho hiện tại.");
    }
  }

  return state.site.cart;
}

function addToCart(scope, productId, options = {}) {
  const product = productMap.get(Number(productId));
  if (!product) return false;

  if (scope === "site") {
    if (!isProductSellable(product)) {
      showToast("Sản phẩm đang tạm hết hoặc ngừng bán.");
      return false;
    }

    sanitizeSiteCart();
    const newItem = normalizeSiteCartItem({
      product_id: Number(productId),
      quantity: 1,
      size: options.size || "M",
      topping: options.topping || "",
    });
    const key = siteCartMergeKey(newItem);
    const existing = state.site.cart.find((item) => siteCartMergeKey(item) === key);
    const branchId = selectedSiteBranchId();
    const available = availableProductQuantity(product, branchId);
    if (available <= 0) {
      showToast(branchId
        ? `${product.product_name} đang hết tại ${branchNameById(branchId)}. Hãy chọn chi nhánh khác.`
        : "Sản phẩm đang tạm hết hoặc ngừng bán.");
      return false;
    }

    if (existing) {
      if (existing.quantity >= available) {
        showToast(`Chỉ còn ${available} phần ${product.product_name}.`);
        return false;
      }
      existing.quantity += 1;
    } else {
      state.site.cart.push(newItem);
    }
    saveSiteCart();
    renderCart("site");
    showToast(`Đã thêm ${product.product_name} vào giỏ hàng.`);
    return true;
  }

  const cart = cartFor(scope);
  if (scope === "pos" && !isProductSellable(product)) {
    showToast(`${product.product_name} đã hết tồn tại chi nhánh hiện tại.`);
    return false;
  }
  if (scope === "pos" && !cart.length && !state.pos.billStartedAt) {
    state.pos.billStartedAt = sqlNow();
  }
  const newItem = normalizePosCartItem({
    product_id: Number(productId),
    quantity: 1,
    size: options.size || "M",
    topping: options.topping || "",
    note: options.note || "",
  });
  const existing = cart.find((item) => posCartMergeKey(item) === posCartMergeKey(newItem));
  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push(newItem);
  }
  persistCart(scope);
  renderCart(scope);
  return true;
}

async function orderNowSiteProduct(productId) {
  if (addToCart("site", productId)) {
    await navigateWebsite(url("checkout"));
  }
}

function updateQuantity(scope, identifier, delta) {
  if (scope === "site") {
    const item = state.site.cart.find((entry) => String(entry.line_id) === String(identifier));
    if (!item) return;

    const product = productMap.get(Number(item.product_id));
    const available = availableProductQuantity(product, selectedSiteBranchId());
    item.quantity += Number(delta);
    if (item.quantity <= 0) {
      state.site.cart = state.site.cart.filter((entry) => String(entry.line_id) !== String(identifier));
    } else if (item.quantity > available) {
      item.quantity = available;
      showToast(`Chỉ còn ${available} phần ${product?.product_name || "sản phẩm này"}.`);
    }
    saveSiteCart();
    renderCart("site");
    return;
  }

  const cart = cartFor(scope);
  const item = cart.find((entry) => String(entry.line_id) === String(identifier))
    || cart.find((entry) => entry.product_id === Number(identifier));
  if (!item) return;

  item.quantity += Number(delta);
  if (item.quantity <= 0) {
    state[scope].cart = cart.filter((entry) => String(entry.line_id) !== String(identifier) && entry.product_id !== Number(identifier));
  }
  if (scope === "pos" && !state.pos.cart.length) {
    state.pos.billStartedAt = "";
  }
  persistCart(scope);
  renderCart(scope);
}

function removeItem(scope, identifier) {
  if (scope === "site") {
    state.site.cart = state.site.cart.filter((entry) => String(entry.line_id) !== String(identifier));
    saveSiteCart();
    renderCart("site");
    return;
  }

  state[scope].cart = cartFor(scope).filter((entry) => String(entry.line_id) !== String(identifier) && entry.product_id !== Number(identifier));
  if (scope === "pos" && !state.pos.cart.length) {
    state.pos.billStartedAt = "";
  }
  persistCart(scope);
  renderCart(scope);
}

function clearSiteCart() {
  state.site.cart = [];
  state.site.voucherId = "";
  saveSiteCart();
  renderCart("site");
  renderVoucherOptions("site");
}

function duplicateSiteCartLine(lineId) {
  const item = state.site.cart.find((entry) => String(entry.line_id) === String(lineId));
  if (!item) return;
  const product = productMap.get(Number(item.product_id));
  if (!isProductSellable(product)) return;

  const available = availableProductQuantity(product);
  const currentQuantity = state.site.cart
    .filter((entry) => Number(entry.product_id) === Number(item.product_id))
    .reduce((sum, entry) => sum + Number(entry.quantity || 0), 0);
  if (currentQuantity >= available) {
    showToast(`Chỉ còn ${available} phần ${product.product_name}.`);
    return;
  }

  state.site.cart.push({
    ...item,
    line_id: newCartLineId(),
    quantity: 1,
    topping: item.topping ? `${item.topping} - riêng` : "Ghi chú riêng",
  });
  saveSiteCart();
  renderCart("site");
}

function updateSiteCartOption(lineId, field, value) {
  const item = state.site.cart.find((entry) => String(entry.line_id) === String(lineId));
  if (!item) return;
  if (field === "size") item.size = normalizeSize(value);
  if (field === "topping") item.topping = normalizeCartNote(value).slice(0, 160);
  saveSiteCart();
  if (field === "size") {
    renderCart("site");
    return;
  }
  renderTotals("site");
}

function updatePosCartOption(lineId, field, value) {
  const item = state.pos.cart.find((entry) => String(entry.line_id) === String(lineId));
  if (!item) return;
  if (field === "size") item.size = normalizeSize(value);
  if (field === "topping") item.topping = normalizeCartNote(value).slice(0, 100);
  if (field === "note") item.note = normalizeCartNote(value).slice(0, 180);
  if (field === "size") {
    renderCart("pos");
    return;
  }
  renderTotals("pos");
}

function selectedVoucher(scope) {
  const voucherId = String(state[scope].voucherId || "");
  const customer = state[scope].customer;
  if (!voucherId || !customer || !Array.isArray(customer.vouchers)) return null;
  return customer.vouchers.find((voucher) => String(voucher.id) === voucherId && voucherUsableOnScope(voucher, scope)) || null;
}

function voucherUsableOnScope(voucher, scope) {
  if (!voucher?.usable) return false;
  if (scope === "site") {
    return voucher.usable_on_website !== false;
  }
  if (scope === "pos") {
    return voucher.usable_on_pos !== false;
  }

  return true;
}

function totalsFor(scope) {
  const subtotal = cartFor(scope).reduce((sum, item) => {
    return sum + cartItemUnitPrice(item) * Number(item.quantity || 0);
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

function selectedSiteFulfillment() {
  const checked = document.querySelector("[data-site-fulfillment]:checked");
  const fallback = document.querySelector("[data-site-fulfillment]");
  return (checked || fallback)?.value || "pickup";
}

function prefillDeliveryRecipient(force = false) {
  const member = state.site.customer || cafeApp.member || null;
  if (!member) return;

  [
    ["[data-site-receiver-email]", member.email],
    ["[data-site-receiver-name]", member.customer_name],
    ["[data-site-receiver-phone]", member.phone_number],
  ].forEach(([selector, value]) => {
    const input = document.querySelector(selector);
    if (!input || value === undefined || value === null) return;
    if (force || !input.value.trim()) input.value = String(value);
  });
}

function readSiteDeliveryPayload() {
  return {
    receiver_email: document.querySelector("[data-site-receiver-email]")?.value?.trim() || "",
    receiver_name: document.querySelector("[data-site-receiver-name]")?.value?.trim() || "",
    receiver_phone: document.querySelector("[data-site-receiver-phone]")?.value?.trim() || "",
    delivery_address: document.querySelector("[data-site-delivery-address]")?.value?.trim() || "",
    city: document.querySelector("[data-site-city]")?.value?.trim() || "",
    district: document.querySelector("[data-site-district]")?.value?.trim() || "",
    ward: document.querySelector("[data-site-ward]")?.value?.trim() || "",
    customer_note: document.querySelector("[data-site-customer-note]")?.value?.trim() || "",
  };
}

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || "").trim());
}

function cartItemCount(scope) {
  return cartFor(scope).reduce((sum, item) => sum + Number(item.quantity || 0), 0);
}

function renderSiteBranchInfo() {
  const target = document.querySelector("[data-site-branch-info]");
  if (!target) return;

  const branchId = selectedSiteBranchId();
  const branch = (cafeApp.branches || []).find((item) => Number(item.id) === branchId);
  if (!branch) {
    target.textContent = "";
    return;
  }

  const baseText = [branch.branch_name, branch.address, branch.district]
    .filter(Boolean)
    .join(" · ");
  const issues = siteCartStockIssues(branchId);
  target.textContent = issues.length
    ? `${baseText} · Không đủ tồn kho: ${issues.map((item) => `${item.product_name} còn ${item.available}, cần ${item.required}`).join("; ")}`
    : baseText;
  target.classList.toggle("danger", issues.length > 0);
}

function renderCheckoutState() {
  const root = document.querySelector("[data-site-checkout-page]");
  if (!root) return;

  const fulfillment = selectedSiteFulfillment();
  const isDelivery = fulfillment === "delivery";
  root.dataset.fulfillment = fulfillment;

  document.querySelectorAll("[data-delivery-only]").forEach((node) => {
    node.hidden = !isDelivery;
  });
  document.querySelectorAll("[data-pickup-only]").forEach((node) => {
    node.hidden = isDelivery;
  });
  prefillDeliveryRecipient();

  const count = cartItemCount("site");
  document.querySelectorAll("[data-site-cart-count]").forEach((node) => {
    node.textContent = String(count);
  });

  const checkoutButton = document.querySelector("[data-site-checkout]");
  const branchIssues = siteCartStockIssues(selectedSiteBranchId());
  if (checkoutButton) checkoutButton.disabled = !cafeInstalled || count <= 0 || branchIssues.length > 0;

  const payment = document.querySelector("[data-site-payment]")?.value || "e_wallet";
  const paymentHint = document.querySelector("[data-site-payment-hint]");
  if (paymentHint) {
    paymentHint.textContent = payment === "cash"
      ? "COD sẽ tạo đơn chờ thanh toán. Voucher được giữ cho đơn này đến khi đơn bị hủy hoặc được xác nhận thanh toán."
      : "MoMo sẽ chuyển bạn sang cổng thanh toán sandbox. Đơn chỉ được xác nhận sau khi MoMo trả kết quả thành công.";
  }
  renderSiteBranchInfo();
}

function renderSiteCart() {
  const target = document.querySelector("[data-site-cart]");
  if (!target) return;

  ensureSiteBranchCanFulfill();
  const branchId = selectedSiteBranchId();
  const cart = sanitizeSiteCart({ persist: true, branchId });
  if (!cart.length) {
    target.innerHTML = `
      <div class="empty-state cart-empty">
        <h3>Giỏ hàng đang trống</h3>
        <p>Chọn món trong menu để bắt đầu đặt hàng.</p>
        <a class="primary-btn" href="${url("menu")}">Xem menu</a>
      </div>
    `;
    renderSiteTotals();
    renderCheckoutState();
    return;
  }

  target.innerHTML = cart.map((item) => {
    const product = productMap.get(Number(item.product_id));
    const price = cartItemUnitPrice(item);
    const lineTotal = price * Number(item.quantity || 0);
    const available = availableProductQuantity(product, branchId);
    const lineId = escapeHtml(item.line_id);
    return `
      <article class="site-cart-item">
        <img src="${escapeHtml(asset(product?.image || "assets/images/coffee-1.png"))}" alt="${escapeHtml(product?.product_name || "Sản phẩm")}">
        <div class="site-cart-main">
          <div class="site-cart-title">
            <div>
              <h3>${escapeHtml(product?.product_name || "Sản phẩm")}</h3>
              <p>${formatMoney(price)} · còn ${available === Number.MAX_SAFE_INTEGER ? "nhiều" : available} phần</p>
            </div>
            <strong>${formatMoney(lineTotal)}</strong>
          </div>
          <div class="site-cart-controls">
            <label>Size
              <select data-site-cart-size data-cart-id="${lineId}">
                ${["S", "M", "L"].map((size) => `<option value="${size}" ${normalizeSize(item.size) === size ? "selected" : ""}>${escapeHtml(sizeOptionLabel(product, size))}</option>`).join("")}
              </select>
            </label>
            <label class="site-cart-note">Ghi chú món
              <input type="text" data-site-cart-note data-cart-id="${lineId}" value="${escapeHtml(item.topping || "")}" placeholder="Ít đá, ít ngọt...">
            </label>
            <div class="qty-control site-qty">
              <button type="button" data-cart-scope="site" data-cart-id="${lineId}" data-delta="-1">-</button>
              <strong>${Number(item.quantity || 0)}</strong>
              <button type="button" data-cart-scope="site" data-cart-id="${lineId}" data-delta="1">+</button>
            </div>
            <button type="button" class="ghost-btn compact" data-site-cart-duplicate="${lineId}">Thêm dòng khác</button>
            <button type="button" class="ghost-btn compact danger" data-cart-scope="site" data-cart-id="${lineId}" data-remove>Xóa</button>
          </div>
        </div>
      </article>
    `;
  }).join("");

  renderSiteTotals();
  renderCheckoutState();
}

function renderCart(scope) {
  if (scope === "site") {
    renderSiteCart();
    return;
  }
  renderPosCart(scope);
  return;
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
    const unitPrice = cartItemUnitPrice(item);
    const lineTotal = unitPrice * Number(item.quantity || 0);
    return `
      <div class="cart-row">
        <div>
          <h4>${escapeHtml(product?.product_name || "Sản phẩm")}</h4>
          <small>${formatMoney(unitPrice)} · Size ${escapeHtml(item.size || "M")}</small>
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

function renderPosCart(scope = "pos") {
  const target = document.querySelector("[data-pos-cart]");
  if (!target) return;

  const cart = cartFor(scope);
  if (!cart.length) {
    target.innerHTML = '<div class="empty-state">Chưa có món trong giỏ.</div>';
    renderTotals(scope);
    return;
  }

  target.innerHTML = cart.map((item) => {
    const product = productMap.get(Number(item.product_id));
    const unitPrice = cartItemUnitPrice(item);
    const lineTotal = unitPrice * Number(item.quantity || 0);
    const lineId = escapeHtml(item.line_id || item.cart_key || String(item.product_id));
    return `
      <div class="cart-row pos-cart-row">
        <div class="pos-cart-main">
          <h4>${escapeHtml(product?.product_name || "Sản phẩm")}</h4>
          <small>${formatMoney(unitPrice)} · Size ${escapeHtml(item.size || "M")}</small>
          <div class="pos-cart-options">
            <label>Size
              <select data-pos-cart-size data-cart-id="${lineId}">
                ${["S", "M", "L"].map((size) => `<option value="${size}" ${normalizeSize(item.size) === size ? "selected" : ""}>${escapeHtml(sizeOptionLabel(product, size))}</option>`).join("")}
              </select>
            </label>
            <label>Tùy chọn pha chế
              <input type="text" data-pos-cart-topping data-cart-id="${lineId}" value="${escapeHtml(item.topping || "")}" placeholder="Ít đá, ít ngọt, không đường">
            </label>
            <label>Ghi chú phục vụ
              <input type="text" data-pos-cart-note data-cart-id="${lineId}" value="${escapeHtml(item.note || "")}" placeholder="Dị ứng, giao trước, tên khách">
            </label>
          </div>
        </div>
        <div class="qty-control">
          <button type="button" data-cart-scope="${scope}" data-cart-id="${lineId}" data-delta="-1">-</button>
          <strong>${Number(item.quantity || 0)}</strong>
          <button type="button" data-cart-scope="${scope}" data-cart-id="${lineId}" data-delta="1">+</button>
        </div>
        <div class="line-total">
          <strong>${formatMoney(lineTotal)}</strong>
          <button type="button" data-cart-scope="${scope}" data-cart-id="${lineId}" data-remove>Ẩn</button>
        </div>
      </div>
    `;
  }).join("");

  renderTotals(scope);
}

function renderSiteTotals() {
  const target = document.querySelector("[data-site-totals]");
  if (!target) return;

  const totals = totalsFor("site");
  target.innerHTML = `
    <div class="total-row"><span>Tạm tính</span><strong>${formatMoney(totals.subtotal)}</strong></div>
    <div class="total-row"><span>Giảm hạng thành viên</span><strong>-${formatMoney(totals.membershipDiscount)}</strong></div>
    <div class="total-row"><span>Giảm voucher</span><strong>-${formatMoney(totals.voucherDiscount)}</strong></div>
    <div class="total-row final"><span>Thanh toán</span><strong>${formatMoney(totals.total)}</strong></div>
    <div class="total-row"><span>Điểm nhận được</span><strong>+${totals.points}</strong></div>
  `;
  renderCheckoutState();
}

function renderTotals(scope) {
  if (scope === "site") {
    renderSiteTotals();
    return;
  }
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

  const usable = state[scope].customer?.vouchers?.filter((voucher) => voucherUsableOnScope(voucher, scope)) || [];
  if (scope === "site") {
    select.innerHTML = '<option value="">Không dùng voucher</option>' + usable.map((voucher) => {
      const value = voucher.discount_type === "percentage" ? `${Number(voucher.discount_value)}%` : formatMoney(voucher.discount_value);
      return `<option value="${voucher.id}">${escapeHtml(voucher.voucher_code)} · ${value}</option>`;
    }).join("");
    if (!usable.some((voucher) => String(voucher.id) === String(state.site.voucherId))) {
      state.site.voucherId = "";
    }
    select.value = state.site.voucherId;
    renderSiteTotals();
    renderCheckoutState();
    return;
  }
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

  const usableCount = customer.vouchers?.filter((voucher) => voucherUsableOnScope(voucher, scope)).length || 0;
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
        <span class="tier-stat">${tierBadge(customer.tier_name, true)}<small>Hạng</small></span>
        <span><strong>${Number(customer.current_points || 0).toLocaleString("vi-VN")}</strong><small>Điểm</small></span>
        <span><strong>${usableCount}</strong><small>Voucher</small></span>
      </div>
    </div>
  `;
}

function tierClassName(tierName) {
  const normalized = String(tierName || "member").trim().toLowerCase();
  if (normalized.includes("gold") || normalized.includes("vàng")) return "gold";
  if (normalized.includes("silver") || normalized.includes("bạc")) return "silver";
  if (normalized.includes("bronze") || normalized.includes("đồng")) return "bronze";
  if (normalized.includes("diamond") || normalized.includes("kim cương")) return "diamond";
  if (normalized.includes("platinum") || normalized.includes("bạch kim")) return "platinum";
  if (normalized.includes("vip")) return "vip";
  return "member";
}

function tierBadge(tierName, compact = false) {
  const label = escapeHtml(tierName || "Member");
  const tier = tierClassName(tierName);
  return `<span class="tier-badge tier-${tier}${compact ? " compact" : ""}"><span>${label}</span></span>`;
}

function setSiteMember(member) {
  state.site.customer = member || null;
  if (member) {
    state.site.webStaff = null;
    savePosAuth(null);
  }
  state.site.voucherId = "";
  renderHeaderPosLink();
  renderCustomerCrmLinks();
  renderMemberNav();
  renderAccountState();
  renderAccountForm();
  renderMemberAccount();
  renderMiniMember("site");
  renderVoucherOptions("site");
  renderSiteProducts();
  prefillDeliveryRecipient(true);
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
  renderCustomerCrmLinks();
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

  const usableCount = member.vouchers?.filter((voucher) => voucherUsableOnScope(voucher, "site")).length || 0;
  target.innerHTML = `
    <div class="profile-head">
      <span class="avatar">${escapeHtml((member.customer_name || "?").slice(0, 1))}</span>
      <div>
        <h3>${escapeHtml(member.customer_name)}</h3>
        <p>${escapeHtml(member.phone_number)} · ${escapeHtml(member.email || "Chưa có email")}</p>
      </div>
    </div>
    <div class="metric-grid">
      <div class="metric tier-metric">${tierBadge(member.tier_name)}<small>Hạng thành viên</small></div>
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
  const status = voucher.display_status || (voucher.usable ? "usable" : voucher.status);
  if (status === "usable") return "good";
  if (["redeemed", "expired", "cancelled"].includes(status)) return "bad";
  return "";
}

function voucherStatusLabel(voucher) {
  const status = voucher.display_status || (voucher.usable ? "usable" : voucher.status);
  return {
    usable: "Khả dụng",
    reserved: "Đã giữ cho đơn chờ",
    redeemed: "Đã dùng",
    expired: "Hết hạn",
    cancelled: "Đã hủy",
    issued: "Đã phát hành",
    active: "Đang hoạt động",
  }[status] || status || "Không khả dụng";
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
      <td><span class="status ${voucherStatusClass(voucher)}">${escapeHtml(voucherStatusLabel(voucher))}</span></td>
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
      <div class="metric tier-metric">${tierBadge(customer.tier_name)}<small>Hạng thành viên</small></div>
      <div class="metric"><strong>${Number(customer.current_points || 0).toLocaleString("vi-VN")}</strong><small>Điểm hiện có</small></div>
      <div class="metric"><strong>${formatMoney(customer.total_spending)}</strong><small>Tổng chi tiêu</small></div>
    </div>
    <div class="favorite-line"><strong>Yêu thích:</strong> ${favoriteNames.length ? favoriteNames.map(escapeHtml).join(", ") : "Chưa có sản phẩm yêu thích"}</div>
    ${canClaimHere ? `
      <form class="lookup-form voucher-code-form" data-voucher-claim-code>
        <label>Nhập mã voucher</label>
        <div class="inline-fields">
          <input name="claim_code" maxlength="50" placeholder="Ví dụ: SUMMER-8F3K" required>
          <button type="submit" class="primary-btn">Claim voucher</button>
        </div>
      </form>
    ` : ""}
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
          <strong>${productPriceRangeLabel(product)}</strong>
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
  if (scope === "site") {
    ensureSiteBranchCanFulfill({ notify: true });
  }
  const cart = scope === "site" ? sanitizeSiteCart({ persist: true, notify: true }) : cartFor(scope);
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
  const payloadItems = extraPayload.order_id
    ? []
    : (scope === "pos" ? cart.map(posCartItemForInvoice) : cart.map(cartItemPayload));
  const payload = {
    sales_channel: scope === "site" ? "website" : "pos",
    staff_id: scope === "site" ? cafeApp.staff?.find((item) => item.staff_role === "cashier")?.id || 2 : user.id || 2,
    branch_id: scope === "site" ? Number(document.querySelector("[data-site-branch]")?.value || cafeApp.branches?.[0]?.id || 1) : user.branch_id || 1,
    customer_id: state[scope].customer?.id || null,
    voucher_id: state[scope].voucherId || null,
    payment_method: paymentSelect?.value || "cash",
    items: payloadItems,
    ...extraPayload,
  };
  if (scope === "site") {
    const stockIssues = siteCartStockIssues(payload.branch_id, cart);
    if (stockIssues.length) {
      showToast(`Chi nhánh ${branchNameById(payload.branch_id)} không đủ tồn kho: ${stockIssues.map((item) => `${item.product_name} còn ${item.available}, cần ${item.required}`).join("; ")}.`);
      renderCart("site");
      return;
    }
    payload.fulfillment_type = selectedSiteFulfillment();
    payload.sales_channel = payload.fulfillment_type === "delivery" ? "delivery" : "website";
    const deliveryPayload = readSiteDeliveryPayload();
    Object.assign(payload, deliveryPayload);
    if (payload.fulfillment_type === "delivery") {
      if (!isValidEmail(deliveryPayload.receiver_email)) {
        showToast("Vui lòng nhập email nhận hàng hợp lệ.");
        return;
      }
      if (!deliveryPayload.city) {
        showToast("Vui lòng nhập tỉnh/thành phố giao hàng.");
        return;
      }
    }
    payload.delivery_address = document.querySelector("[data-site-delivery-address]")?.value?.trim() || "";
    const receiverPhone = document.querySelector("[data-site-receiver-phone]")?.value?.trim() || "";
    if (payload.fulfillment_type === "delivery" && !receiverPhone) {
      showToast("Vui lòng nhập số điện thoại nhận hàng.");
      return;
    }
    if (payload.fulfillment_type === "delivery" && !payload.delivery_address) {
      showToast("Vui lòng nhập địa chỉ giao hàng.");
      return;
    }
    payload.customer_note = [
      receiverPhone ? `SĐT nhận hàng: ${receiverPhone}` : "",
      document.querySelector("[data-site-customer-note]")?.value?.trim() || "",
    ].filter(Boolean).join(" | ");
    payload.customer_note = deliveryPayload.customer_note;
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
    if (result.momo_payment?.pay_url) {
      showToast(`Đơn #${result.invoice_id} đã được tạo. Đang chuyển sang MoMo...`);
      window.location.href = result.momo_payment.pay_url;
      return;
    }
    const statusText = result.status === "pending" ? "Đơn đang chờ thanh toán." : "Đơn đã thanh toán.";
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
  const receiverName = invoice.receiver_name || invoice.customer_name || "";
  const receiverPhone = invoice.receiver_phone || invoice.phone_number || "";
  const receiverAddress = invoice.delivery_address || [invoice.ward, invoice.district, invoice.city]
    .filter(Boolean)
    .join(", ");
  const receiverBlock = invoice.fulfillment_type === "delivery" ? `
      <div class="delivery-detail-box">
        <strong>Thông tin người nhận</strong>
        <span>${escapeHtml(receiverName || "Chưa có tên người nhận")}</span>
        ${invoice.receiver_email ? `<span>Email: ${escapeHtml(invoice.receiver_email)}</span>` : ""}
        ${receiverPhone ? `<span>SĐT: ${escapeHtml(receiverPhone)}</span>` : ""}
        ${receiverAddress ? `<span>Địa chỉ: ${escapeHtml(receiverAddress)}</span>` : ""}
        ${invoice.customer_note ? `<span>Ghi chú: ${escapeHtml(invoice.customer_note)}</span>` : ""}
      </div>
    ` : (invoice.customer_note ? `<p><strong>Ghi chú:</strong> ${escapeHtml(invoice.customer_note)}</p>` : "");
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
      ${receiverBlock}
      ${tableHtml(items, ["Món", "SL", "Size", "Giá", "Tổng"], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${Number(row.quantity || 0)}</td><td>${escapeHtml(row.size || "")}</td><td>${formatMoney(row.unit_price)}</td><td>${formatMoney(row.line_total)}</td></tr>`)}
      <div class="order-action-row">
        <button type="button" class="secondary-btn" data-order-receipt>In / xem receipt</button>
        ${canCancel ? `<button type="button" class="secondary-btn danger" data-website-order-cancel="${escapeHtml(invoice.id || "")}">Hủy đơn đang chờ</button>` : ""}
      </div>
    </article>
  `;
}

async function loadWebsiteOrderDetail() {
  const target = document.querySelector("[data-order-detail]");
  if (!target) return;
  const invoiceId = Number(queryParam("invoice_id") || 0);
  if (!invoiceId) {
    target.innerHTML = '<div class="empty-state">Thiếu mã hóa đơn. Vui lòng mở lại đơn từ hồ sơ hoặc lịch sử đơn hàng.</div>';
    return;
  }
  try {
    const receipt = await api("website-order-detail", { invoice_id: invoiceId });
    renderWebsiteOrderDetail(receipt);
  } catch (error) {
    target.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
  }
}

async function loadMomoPaymentReturn() {
  const target = document.querySelector("[data-payment-return-status]");
  if (!target) return;

  const invoiceId = Number(queryParam("invoice_id") || 0);
  const providerOrderId = queryParam("orderId") || "";
  if (!invoiceId && !providerOrderId) {
    target.innerHTML = `
      <p class="eyebrow">Thiếu thông tin</p>
      <h2>Không tìm thấy mã thanh toán</h2>
      <p>Vui lòng mở lại đơn từ hồ sơ hoặc lịch sử đơn hàng.</p>
      <a class="primary-btn" href="${url("account")}">Về hồ sơ</a>
    `;
    return;
  }

  try {
    const status = await api("payment-status", {
      invoice_id: invoiceId,
      provider_order_id: providerOrderId,
    });
    const paid = status.invoice_status === "paid" || status.payment_status === "paid";
    const failed = ["failed", "cancelled"].includes(status.transaction_status) || ["failed", "cancelled"].includes(status.invoice_status);
    target.innerHTML = `
      <p class="eyebrow">MoMo</p>
      <h2>${paid ? "Thanh toán thành công" : (failed ? "Thanh toán chưa thành công" : "Đang chờ xác nhận")}</h2>
      <div class="receipt-summary-grid">
        <div class="metric"><strong>#${escapeHtml(status.invoice_id || invoiceId)}</strong><small>Hóa đơn</small></div>
        <div class="metric"><strong>${formatMoney(status.total_amount)}</strong><small>Số tiền</small></div>
        <div class="metric"><strong>${escapeHtml(status.payment_status || "")}</strong><small>Payment</small></div>
        <div class="metric"><strong>${escapeHtml(status.transaction_status || "")}</strong><small>MoMo</small></div>
      </div>
      <p>${escapeHtml(status.message || (paid ? "Đơn hàng đã được ghi nhận thanh toán." : "Nếu bạn vừa thanh toán, vui lòng chờ IPN từ MoMo trong vài giây."))}</p>
      <div class="order-action-row">
        <a class="primary-btn" href="${url(`order?invoice_id=${status.invoice_id || invoiceId}`)}">Xem chi tiết đơn</a>
        <a class="secondary-btn" href="${url("menu")}">Tiếp tục mua hàng</a>
      </div>
    `;
  } catch (error) {
    target.innerHTML = `
      <p class="eyebrow">Lỗi kiểm tra</p>
      <h2>Không đọc được trạng thái MoMo</h2>
      <p>${escapeHtml(error.message)}</p>
      <a class="primary-btn" href="${url("account")}">Về hồ sơ</a>
    `;
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

function categoryOptions(selected = "", rows = cafeApp.categories || []) {
  return rows.map((category) =>
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
    inventory: renderInventoryCrudModule,
    reports: renderReportsModule,
    products: renderProductsModule,
    staff: renderStaffCrudModule,
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
  const websitePending = cafeApp.website_orders_pending || [];
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
            <em class="order-item-note">${escapeHtml([item.size ? `Size ${item.size}` : "", item.topping, item.note].filter(Boolean).join(" · ") || "Không có ghi chú")}</em>
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

  const websitePendingSection = canCheckout ? `
    <section class="panel website-pending-orders">
      <div class="panel-head">
        <h2>Đơn website chờ thanh toán</h2>
        <p>Chỉ hiển thị đơn COD pending của chi nhánh ca POS hiện tại.</p>
      </div>
      <div class="order-list">
        ${websitePending.map((order) => `
          <article class="order-card website-order-card">
            <header>
              <div>
                <strong>WEB-${String(order.invoice_id || "").padStart(6, "0")}</strong>
                <small>${escapeHtml(order.customer_name || "Khách lẻ")} · ${escapeHtml(order.phone_number || "Không có SĐT")}</small>
              </div>
              <span class="status">${escapeHtml(order.order_status || "pending")}</span>
            </header>
            <p class="order-meta">${escapeHtml(order.branch_name || "")} · ${escapeHtml(order.fulfillment_type || "pickup")} · ${escapeHtml(order.payment_method || "cash")} / ${escapeHtml(order.payment_status || "pending")}</p>
            <p class="order-meta">Đặt lúc ${escapeHtml(formatDateTime(order.created_at || order.bill_started_at))}${order.requested_at ? ` · Hẹn ${escapeHtml(formatDateTime(order.requested_at))}` : ""}</p>
            ${order.delivery_address ? `<p class="order-meta">Địa chỉ: ${escapeHtml(order.delivery_address)}</p>` : ""}
            ${order.customer_note ? `<p class="order-meta">Ghi chú: ${escapeHtml(order.customer_note)}</p>` : ""}
            ${order.receiver_name || order.receiver_phone ? `<p class="order-meta">Người nhận: ${escapeHtml(order.receiver_name || "Chưa có tên")} ${order.receiver_phone ? `· ${escapeHtml(order.receiver_phone)}` : ""}</p>` : ""}
            ${order.receiver_email ? `<p class="order-meta">Email nhận hàng: ${escapeHtml(order.receiver_email)}</p>` : ""}
            <div class="order-items">
              ${(order.items || []).map((item) => `
                <div>
                  <span>${Number(item.quantity || 0)}× ${escapeHtml(item.product_name || "")}</span>
                  <em class="order-item-note">${escapeHtml([item.size ? `Size ${item.size}` : "", item.topping].filter(Boolean).join(" · ") || "Không có ghi chú")}</em>
                  <small>${formatMoney(item.line_total || 0)}</small>
                </div>
              `).join("")}
            </div>
            <footer>
              <strong>${formatMoney(order.total_amount || 0)}</strong>
              <button type="button" class="primary-btn" data-website-pending-pay="${escapeHtml(order.invoice_id || "")}">Xác nhận đã thanh toán</button>
              <button type="button" class="ghost-btn" data-website-pending-cancel="${escapeHtml(order.invoice_id || "")}">Hủy đơn pending</button>
            </footer>
          </article>
        `).join("") || '<div class="empty-state">Không có đơn website chờ thanh toán tại chi nhánh này.</div>'}
      </div>
    </section>
  ` : "";

  if (!canCreate) {
    return `
      ${websitePendingSection}
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
    ${websitePendingSection}
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

function campaignStatusLabel(status) {
  return {
    draft: "Nháp",
    active: "Đang chạy",
    cancelled: "Đã hủy",
    completed: "Hoàn tất",
  }[status] || status || "";
}

function campaignStatusClass(status) {
  if (status === "active") return "good";
  if (status === "cancelled") return "bad";
  return "";
}

function campaignsTable(rows = cafeApp.campaigns || cafeApp.dashboard?.campaigns || []) {
  return tableHtml(rows, ["Chiến dịch", "Thời gian", "Nhóm", "Cách phát", "Mã claim", "Giảm", "Dùng/Phát", "Doanh thu", "Trạng thái", "Thao tác"], (campaign) => {
    const issued = Number(campaign.issued_vouchers || 0);
    const redeemed = Number(campaign.redeemed_vouchers || 0);
    const rate = issued > 0 ? Math.round((redeemed / issued) * 100) : 0;
    const discount = campaign.discount_type === "percentage" ? `${Number(campaign.discount_value)}%` : formatMoney(campaign.discount_value);
    const distribution = campaign.distribution_type === "auto_issue" ? "Tự phát" : "Claim bằng mã";
    const claimCode = campaign.claim_code || "";
    return `<tr>
      <td>${escapeHtml(campaign.promotion_name)}</td>
      <td>${escapeHtml(campaign.start_date)}<br><small>${escapeHtml(campaign.end_date)}</small></td>
      <td>${escapeHtml(campaign.target_segment)}</td>
      <td>${distribution}</td>
      <td>${claimCode ? `<button type="button" class="secondary-btn compact" data-copy-claim-code="${escapeHtml(claimCode)}">${escapeHtml(claimCode)}</button>` : "<span class=\"muted\">-</span>"}</td>
      <td>${discount}</td>
      <td>${redeemed}/${issued} (${rate}%)</td>
      <td>${formatMoney(campaign.attributed_revenue)}</td>
      <td><span class="status ${campaignStatusClass(campaign.status)}">${escapeHtml(campaignStatusLabel(campaign.status))}</span></td>
      <td>
        <div class="table-actions">
          <button type="button" class="secondary-btn compact" data-edit-campaign="${campaign.id}">Sửa</button>
          ${campaign.status === "cancelled"
            ? `<button type="button" class="secondary-btn compact" data-campaign-restore="${campaign.id}">Khôi phục</button>`
            : `<button type="button" class="secondary-btn compact danger" data-campaign-delete="${campaign.id}">Hủy</button>`}
        </div>
      </td>
    </tr>`;
  });
}

function renderCampaignsModule() {
  const campaigns = cafeApp.campaigns || cafeApp.dashboard?.campaigns || [];
  const editing = campaigns.find((campaign) => Number(campaign.id) === Number(state.pos.editingCampaignId)) || null;
  const selected = (value, expected) => String(value || "") === expected ? "selected" : "";
  const status = editing?.status || "active";
  return `
    <div class="campaign-layout">
      <form class="create-form" data-campaign-create>
        <input type="hidden" name="id" value="${editing ? escapeHtml(editing.id) : ""}">
        <h2>${editing ? "Cập nhật campaign" : "Tạo campaign"}</h2>
        <label>Tên chiến dịch <input name="promotion_name" value="${escapeHtml(editing?.promotion_name || "")}" required></label>
        <label>Mô tả <textarea name="description">${escapeHtml(editing?.description || "")}</textarea></label>
        <label>Ngày bắt đầu <input type="date" name="start_date" value="${escapeHtml(editing?.start_date || sqlDate())}" required></label>
        <label>Ngày kết thúc <input type="date" name="end_date" value="${escapeHtml(editing?.end_date || sqlDate(30))}" required></label>
        <label>Cách phát
          <select name="distribution_type">
            <option value="claim_code" ${selected(editing?.distribution_type || "claim_code", "claim_code")}>Claim bằng mã</option>
            <option value="auto_issue" ${selected(editing?.distribution_type, "auto_issue")}>Tự phát theo segment</option>
          </select>
        </label>
        <label>Kênh dùng voucher
          <select name="campaign_channel">
            <option value="omnichannel" ${selected(editing?.campaign_channel || "omnichannel", "omnichannel")}>Website + POS</option>
            <option value="website" ${selected(editing?.campaign_channel, "website")}>Website</option>
            <option value="pos" ${selected(editing?.campaign_channel, "pos")}>POS</option>
            <option value="email" ${selected(editing?.campaign_channel, "email")}>Email</option>
            <option value="zalo" ${selected(editing?.campaign_channel, "zalo")}>Zalo</option>
            <option value="sms" ${selected(editing?.campaign_channel, "sms")}>SMS</option>
          </select>
        </label>
        <label>Nhóm khách
          <select name="target_segment">
            <option value="all" ${selected(editing?.target_segment || "all", "all")}>Tất cả</option>
            <option value="bronze" ${selected(editing?.target_segment, "bronze")}>Bronze</option>
            <option value="silver" ${selected(editing?.target_segment, "silver")}>Silver</option>
            <option value="gold" ${selected(editing?.target_segment, "gold")}>Gold</option>
            <option value="birthday" ${selected(editing?.target_segment, "birthday")}>Sinh nhật</option>
            <option value="inactive" ${selected(editing?.target_segment, "inactive")}>Khách ngủ đông</option>
          </select>
        </label>
        <label>Loại giảm
          <select name="discount_type">
            <option value="fixed" ${selected(editing?.discount_type || "fixed", "fixed")}>Số tiền</option>
            <option value="percentage" ${selected(editing?.discount_type, "percentage")}>Phần trăm</option>
          </select>
        </label>
        <label>Trạng thái
          <select name="status">
            <option value="draft" ${selected(status, "draft")}>Nháp</option>
            <option value="active" ${selected(status, "active")}>Đang chạy</option>
            <option value="completed" ${selected(status, "completed")}>Hoàn tất</option>
            <option value="cancelled" ${selected(status, "cancelled")}>Đã hủy</option>
          </select>
        </label>
        <label>Giá trị <input type="number" name="discount_value" value="${escapeHtml(editing?.discount_value ?? 20000)}" min="0"></label>
        <label>Số voucher <input type="number" name="voucher_quantity" value="${escapeHtml(editing?.voucher_quantity ?? 5)}" min="0"></label>
        <label>Mỗi khách tối đa <input type="number" name="usage_limit_per_customer" value="${escapeHtml(editing?.usage_limit_per_customer ?? 1)}" min="1"></label>
        <label>Mã claim tùy chọn <input name="claim_code" maxlength="50" value="${escapeHtml(editing?.claim_code || "")}" placeholder="Để trống để tự sinh"></label>
        <div class="form-actions">
          <button class="primary-btn" type="submit">${editing ? "Lưu thay đổi" : "Tạo campaign"}</button>
          ${editing ? '<button class="secondary-btn" type="button" data-campaign-form-reset>Hủy sửa</button>' : ""}
        </div>
      </form>
      <section class="panel"><div class="panel-head"><h2>Hiệu quả campaign</h2><p>Quản lý tạo, sửa, hủy và khôi phục campaign/voucher.</p></div>${campaignsTable(campaigns)}</section>
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

function renderInventoryModule() {
  const inventory = cafeApp.inventory || {};
  const materialCatalog = inventory.material_catalog || inventory.materials || [];
  const materials = inventory.materials || [];
  return `
    <div class="admin-grid">
      <form class="create-form" data-stock-movement>
        <h2>Nhập/xuất nguyên vật liệu</h2>
        <label>Chi nhánh <select name="branch_id">${(cafeApp.branches || []).map((branch) => `<option value="${branch.id}" ${Number(branch.id) === Number(state.pos.user?.branch_id || 1) ? "selected" : ""}>${escapeHtml(branch.branch_name)}</option>`).join("")}</select></label>
        <label>Nguyên vật liệu <select name="material_id">${materialCatalog.map((item) => `<option value="${item.material_id || item.id}">${escapeHtml(item.material_name)} (${escapeHtml(item.unit)})</option>`).join("")}</select></label>
        <label>Loại <select name="movement_type"><option value="import">Nhập kho</option><option value="sales_export">Xuất sử dụng</option><option value="waste_export">Hủy hao hụt</option><option value="adjustment">Điều chỉnh tăng</option></select></label>
        <label>Số lượng <input type="number" name="quantity" value="1" min="0.01" step="0.01"></label>
        <label>Đơn giá <input type="number" name="unit_cost" value="0" min="0"></label>
        <label>Tổng giá trị <input type="number" name="total_amount" value="0" min="0"></label>
        <label>Nhà cung cấp <input name="supplier_name" maxlength="150"></label>
        <label>Mã lô <input name="batch_code" maxlength="80"></label>
        <label>Hạn dùng <input type="date" name="expiry_date"></label>
        <label>Ghi chú <textarea name="note" placeholder="Lý do nhập/xuất/hao hụt..."></textarea></label>
        <button class="primary-btn" type="submit">Ghi nhận kho</button>
      </form>
      <section class="panel"><div class="panel-head"><h2>Tổng quan nguyên vật liệu</h2><p>Kho được quản lý theo chi nhánh và trừ tự động theo recipe khi hóa đơn được ghi nhận.</p></div>${tableHtml(materials, ["Chi nhánh", "Nguyên vật liệu", "ĐVT", "Tồn", "Tối thiểu", "Giá vốn", "Trạng thái"], (row) => {
        const status = row.stock_status === "out" ? "Hết" : (row.stock_status === "low" ? "Thấp" : "Đủ");
        const statusClass = row.stock_status === "ok" ? "good" : "bad";
        return `<tr><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.material_name)}</td><td>${escapeHtml(row.unit)}</td><td>${Number(row.stock_quantity).toFixed(2)}</td><td>${Number(row.min_stock_level).toFixed(2)}</td><td>${formatMoney(row.unit_cost)}</td><td><span class="status ${statusClass}">${status}</span></td></tr>`;
      }, "Chưa có dữ liệu tồn nguyên vật liệu.")}</section>
    </div>
    <div class="dashboard-columns">
      <section class="panel"><h2>Recipe / BOM</h2>${tableHtml(inventory.recipes || [], ["Sản phẩm", "Recipe", "Nguyên vật liệu", "Trạng thái"], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${escapeHtml(row.recipe_name)}</td><td>${escapeHtml(row.materials || "Chưa cấu hình")}</td><td><span class="status ${row.status === "active" ? "good" : "bad"}">${escapeHtml(row.status)}</span></td></tr>`, "Chưa có recipe.")}</section>
      <section class="panel"><h2>Lịch sử kho</h2>${tableHtml(inventory.movements || [], ["Mã", "Chi nhánh", "Loại", "NVL", "SL", "Giá trị", "Nhân viên"], (row) => `<tr><td>${escapeHtml(row.movement_code)}</td><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.movement_type)}</td><td>${escapeHtml(row.material_name)}</td><td>${Number(row.quantity).toFixed(2)}</td><td>${formatMoney(row.total_amount)}</td><td>${escapeHtml(row.staff_name)}</td></tr>`, "Chưa có lịch sử kho.")}</section>
    </div>
  `;
}

function renderInventoryCrudModule() {
  const inventory = cafeApp.inventory || {};
  const catalog = inventory.material_catalog || [];
  const activeCatalog = catalog.filter((item) => (item.status || "active") === "active");
  const stockRows = inventory.materials || [];
  const recipes = inventory.recipes || [];
  const selectAttr = (value, expected) => String(value ?? "") === String(expected ?? "") ? "selected" : "";
  const materialOptions = (selectedId = "") => activeCatalog.map((item) =>
    `<option value="${escapeHtml(item.id)}" ${selectAttr(item.id, selectedId)}>${escapeHtml(item.material_name)} (${escapeHtml(item.unit)})</option>`
  ).join("");
  const productOptions = (selectedId = "") => products.map((product) =>
    `<option value="${escapeHtml(product.id)}" ${selectAttr(product.id, selectedId)}>${escapeHtml(product.product_name)}</option>`
  ).join("");
  const recipeItemRows = Array.from({ length: 5 }, (_, index) => `
    <div class="recipe-item-row">
      <select name="recipe_material_id">${materialOptions()}</select>
      <input type="number" name="recipe_quantity_per_unit" min="0" step="0.0001" placeholder="Lượng / sản phẩm">
      <span>${index === 0 ? "Bắt buộc" : "Tùy chọn"}</span>
    </div>
  `).join("");

  return `
    <div class="inventory-admin-grid">
      <form class="panel create-form" data-material-save>
        <div class="panel-head">
          <div>
            <h2>Danh mục nguyên vật liệu</h2>
            <p>Tạo, sửa hoặc ngừng sử dụng nguyên liệu.</p>
          </div>
        </div>
        <input type="hidden" name="id">
        <label>Tên nguyên vật liệu <input name="material_name" required placeholder="Arabica beans"></label>
        <label>Đơn vị tính <input name="unit" required placeholder="kg, lít, cái..."></label>
        <label>Tồn tối thiểu mặc định <input type="number" name="min_stock_level" min="0" step="0.01" value="0"></label>
        <label>Giá vốn mặc định <input type="number" name="unit_cost" min="0" step="100" value="0"></label>
        <label>Nhà cung cấp <input name="supplier_name" maxlength="150"></label>
        <label>Trạng thái
          <select name="status">
            <option value="active">Đang dùng</option>
            <option value="inactive">Ngừng dùng</option>
          </select>
        </label>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Lưu nguyên vật liệu</button>
          <button class="secondary-btn" type="button" data-material-form-reset>Nhập mới</button>
        </div>
      </form>

      <form class="panel create-form" data-inventory-stock-save>
        <div class="panel-head">
          <div>
            <h2>Tồn kho chi nhánh</h2>
            <p>Cập nhật tồn kiểm kê, định mức và giá vốn theo chi nhánh.</p>
          </div>
        </div>
        <label>Chi nhánh <select name="branch_id">${branchOptions(state.pos.user?.branch_id || 1)}</select></label>
        <label>Nguyên vật liệu <select name="material_id">${materialOptions()}</select></label>
        <label>Tồn hiện tại <input type="number" name="stock_quantity" min="0" step="0.01" value="0"></label>
        <label>Tồn tối thiểu <input type="number" name="min_stock_level" min="0" step="0.01" value="0"></label>
        <label>Giá vốn <input type="number" name="unit_cost" min="0" step="100" value="0"></label>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Lưu tồn kho</button>
          <button class="secondary-btn" type="button" data-stock-form-reset>Nhập mới</button>
        </div>
      </form>

      <form class="panel create-form" data-stock-movement>
        <div class="panel-head">
          <div>
            <h2>Nhập / xuất kho</h2>
            <p>Ghi nhận giao dịch kho. Lịch sử không xóa để giữ audit.</p>
          </div>
        </div>
        <label>Chi nhánh <select name="branch_id">${branchOptions(state.pos.user?.branch_id || 1)}</select></label>
        <label>Nguyên vật liệu <select name="material_id">${materialOptions()}</select></label>
        <label>Loại
          <select name="movement_type">
            <option value="import">Nhập kho</option>
            <option value="sales_export">Xuất sử dụng</option>
            <option value="waste_export">Hủy hao hụt</option>
            <option value="adjustment">Điều chỉnh tăng</option>
          </select>
        </label>
        <label>Số lượng <input type="number" name="quantity" value="1" min="0.01" step="0.01"></label>
        <label>Đơn giá <input type="number" name="unit_cost" value="0" min="0" step="100"></label>
        <label>Tổng giá trị <input type="number" name="total_amount" value="0" min="0" step="100"></label>
        <label>Nhà cung cấp <input name="supplier_name" maxlength="150"></label>
        <label>Mã lô <input name="batch_code" maxlength="80"></label>
        <label>Hạn dùng <input type="date" name="expiry_date"></label>
        <label>Ghi chú <textarea name="note" placeholder="Lý do nhập/xuất/hao hụt..."></textarea></label>
        <button class="primary-btn" type="submit">Ghi nhận giao dịch</button>
      </form>

      <form class="panel create-form recipe-form" data-recipe-save>
        <div class="panel-head">
          <div>
            <h2>Recipe / BOM</h2>
            <p>Cấu hình nguyên liệu tiêu hao cho từng sản phẩm.</p>
          </div>
        </div>
        <input type="hidden" name="id">
        <label>Sản phẩm <select name="product_id">${productOptions()}</select></label>
        <label>Tên recipe <input name="recipe_name" placeholder="Signature Brown Latte recipe"></label>
        <label>Yield <input type="number" name="yield_quantity" min="0.0001" step="0.0001" value="1"></label>
        <label>Trạng thái
          <select name="status">
            <option value="active">Đang dùng</option>
            <option value="inactive">Ngừng dùng</option>
          </select>
        </label>
        <div class="recipe-items-editor">
          <strong>Nguyên vật liệu</strong>
          ${recipeItemRows}
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Lưu recipe</button>
          <button class="secondary-btn" type="button" data-recipe-form-reset>Nhập mới</button>
        </div>
      </form>
    </div>

    <div class="dashboard-columns inventory-data-grid">
      <section class="panel">
        <div class="panel-head"><h2>Nguyên vật liệu</h2><p>Danh mục dùng cho nhập/xuất và recipe.</p></div>
        ${tableHtml(catalog, ["Tên", "ĐVT", "Min", "Giá vốn", "NCC", "Trạng thái", "Thao tác"], (row) => `
          <tr>
            <td>${escapeHtml(row.material_name)}</td>
            <td>${escapeHtml(row.unit)}</td>
            <td>${Number(row.min_stock_level || 0).toFixed(2)}</td>
            <td>${formatMoney(row.unit_cost)}</td>
            <td>${escapeHtml(row.supplier_name || "")}</td>
            <td><span class="status ${row.status === "active" ? "good" : "bad"}">${row.status === "active" ? "Đang dùng" : "Ngừng dùng"}</span></td>
            <td class="table-actions">
              <button type="button" data-edit-material="${escapeHtml(row.id)}">Sửa</button>
              ${row.status === "active"
                ? `<button type="button" data-material-delete="${escapeHtml(row.id)}">Ngừng</button>`
                : `<button type="button" data-material-restore="${escapeHtml(row.id)}">Khôi phục</button>`}
            </td>
          </tr>
        `)}
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Tồn kho theo chi nhánh</h2><p>Chỉ hiển thị nguyên vật liệu, không còn tồn đồ uống.</p></div>
        ${tableHtml(stockRows, ["Chi nhánh", "Nguyên vật liệu", "ĐVT", "Tồn", "Tối thiểu", "Giá vốn", "Trạng thái", "Thao tác"], (row) => {
          const status = row.stock_status === "out" ? "Hết" : (row.stock_status === "low" ? "Thấp" : "Đủ");
          const statusClass = row.stock_status === "ok" ? "good" : "bad";
          return `
            <tr>
              <td>${escapeHtml(row.branch_name)}</td>
              <td>${escapeHtml(row.material_name)}</td>
              <td>${escapeHtml(row.unit)}</td>
              <td>${Number(row.stock_quantity || 0).toFixed(2)}</td>
              <td>${Number(row.min_stock_level || 0).toFixed(2)}</td>
              <td>${formatMoney(row.unit_cost)}</td>
              <td><span class="status ${statusClass}">${status}</span></td>
              <td class="table-actions"><button type="button" data-edit-stock="${escapeHtml(row.branch_id)}:${escapeHtml(row.material_id)}">Sửa tồn</button></td>
            </tr>
          `;
        })}
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Recipe / BOM</h2><p>Recipe active sẽ tự trừ nguyên vật liệu khi hóa đơn paid.</p></div>
        ${tableHtml(recipes, ["Sản phẩm", "Recipe", "Nguyên vật liệu", "Yield", "Trạng thái", "Thao tác"], (row) => `
          <tr>
            <td>${escapeHtml(row.product_name)}</td>
            <td>${escapeHtml(row.recipe_name)}</td>
            <td>${escapeHtml(row.materials || "Chưa cấu hình")}</td>
            <td>${Number(row.yield_quantity || 1).toFixed(2)}</td>
            <td><span class="status ${row.status === "active" ? "good" : "bad"}">${row.status === "active" ? "Đang dùng" : "Ngừng dùng"}</span></td>
            <td class="table-actions">
              <button type="button" data-edit-recipe="${escapeHtml(row.id)}">Sửa</button>
              ${row.status === "active"
                ? `<button type="button" data-recipe-delete="${escapeHtml(row.id)}">Ngừng</button>`
                : `<button type="button" data-recipe-restore="${escapeHtml(row.id)}">Khôi phục</button>`}
            </td>
          </tr>
        `, "Chưa có recipe.")}
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Lịch sử kho</h2><p>Các giao dịch nhập, xuất, hao hụt và tự trừ theo hóa đơn.</p></div>
        ${tableHtml(inventory.movements || [], ["Mã", "Chi nhánh", "Loại", "NVL", "SL", "Giá trị", "Nhân viên", "Ghi chú"], (row) => `
          <tr>
            <td>${escapeHtml(row.movement_code)}</td>
            <td>${escapeHtml(row.branch_name)}</td>
            <td>${escapeHtml(row.movement_type)}</td>
            <td>${escapeHtml(row.material_name)}</td>
            <td>${Number(row.quantity || 0).toFixed(2)}</td>
            <td>${formatMoney(row.total_amount)}</td>
            <td>${escapeHtml(row.staff_name)}</td>
            <td>${escapeHtml(row.note || "")}</td>
          </tr>
        `, "Chưa có lịch sử kho.")}
      </section>
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

function reportFilterPayload() {
  const period = cafeApp.reports?.period || {};
  const now = new Date();
  const pad = (number) => String(number).padStart(2, "0");
  const defaultStart = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
  return {
    start_date: state.pos.reportStartDate || period.start_date || defaultStart,
    end_date: state.pos.reportEndDate || period.end_date || sqlDate(),
  };
}

function renderReportsModule() {
  const reports = cafeApp.reports || {};
  const filters = reportFilterPayload();
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
      <section class="panel span-2">
        <div class="panel-head">
          <div>
            <h2>Báo cáo doanh thu</h2>
            <p>Lọc theo khoảng ngày để xem chi tiết doanh thu theo tháng, chi nhánh, kênh bán và hiệu suất vận hành.</p>
          </div>
          <button type="button" class="primary-btn" data-report-export>Export CSV</button>
        </div>
        <form class="report-filter-form" data-report-filter>
          <label>Từ ngày <input type="date" name="start_date" value="${escapeHtml(filters.start_date)}"></label>
          <label>Đến ngày <input type="date" name="end_date" value="${escapeHtml(filters.end_date)}"></label>
          <button type="submit" class="secondary-btn">Xem báo cáo</button>
        </form>
      </section>
      <section class="panel span-2"><h2>Doanh thu các tháng theo chi nhánh</h2>${tableHtml(reports.branch_monthly_revenue || [], ["Tháng", "Chi nhánh", "Đơn", "Doanh thu", "TB/HĐ", "Giảm voucher"], (row) => `<tr><td>${escapeHtml(row.revenue_month)}</td><td>${escapeHtml(row.branch_name)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td><td>${formatMoney(row.average_invoice_value)}</td><td>${formatMoney(row.voucher_discount)}</td></tr>`, "Chưa có doanh thu trong kỳ.")}</section>
      <section class="panel span-2"><h2>Tổng hợp chi nhánh</h2>${tableHtml(reports.branch_summary || [], ["Chi nhánh", "Đơn", "Doanh thu", "TB/HĐ", "POS", "Website", "Giảm"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td><td>${formatMoney(row.average_invoice_value)}</td><td>${formatMoney(row.pos_revenue)}</td><td>${formatMoney(row.website_revenue)}</td><td>${formatMoney(Number(row.membership_discount || 0) + Number(row.voucher_discount || 0))}</td></tr>`)}</section>
      <section class="panel"><h2>Doanh thu theo kênh</h2>${tableHtml(reports.revenue_by_channel || [], ["Kênh", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.sales_channel)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td></tr>`)}</section>
      <section class="panel"><h2>Thanh toán theo chi nhánh</h2>${tableHtml(reports.payment_by_branch || [], ["Chi nhánh", "Phương thức", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.payment_method)}</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td></tr>`)}</section>
      <section class="panel span-2"><h2>Sản phẩm bán chạy theo chi nhánh</h2>${tableHtml(reports.top_products_by_branch || [], ["Chi nhánh", "Sản phẩm", "SL", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.branch_name)}</td><td>${escapeHtml(row.product_name)}</td><td>${Number(row.quantity_sold || 0)}</td><td>${formatMoney(row.product_revenue)}</td></tr>`)}</section>
      <section class="panel"><h2>Giờ cao điểm</h2>${tableHtml(reports.hourly_revenue || [], ["Giờ", "Đơn", "Doanh thu", "TB/HĐ"], (row) => `<tr><td>${String(row.business_hour).padStart(2, "0")}:00</td><td>${Number(row.paid_invoice_count || 0)}</td><td>${formatMoney(row.net_revenue)}</td><td>${formatMoney(row.average_invoice_value)}</td></tr>`)}</section>
      <section class="panel"><h2>Hiệu suất nhân viên</h2>${tableHtml(reports.staff_performance || [], ["Nhân viên", "Role", "Đơn", "Doanh thu"], (row) => `<tr><td>${escapeHtml(row.staff_name)}</td><td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td><td>${Number(row.orders_processed || 0)}</td><td>${formatMoney(row.revenue_handled)}</td></tr>`)}</section>
      <section class="panel span-2"><h2>Hóa đơn gần nhất</h2>${invoiceActions}</section>
      <section class="panel span-2"><h2>Phiên làm việc POS</h2>${sessionReportsTable()}</section>
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
      <section class="panel"><h2>Danh sách sản phẩm</h2>${tableHtml(products, ["Tên", "Danh mục", "Giá", "Trạng thái", ""], (row) => `<tr><td>${escapeHtml(row.product_name)}</td><td>${escapeHtml(row.category_name || row.category)}</td><td>${productPriceRangeLabel(row)}</td><td><span class="status good">${escapeHtml(row.status)}</span></td><td><button type="button" data-edit-product="${row.id}">Sửa</button></td></tr>`)}</section>
    </div>
  `;
}

function renderProductsModule() {
  const adminProducts = Array.isArray(cafeApp.admin_products) && cafeApp.admin_products.length ? cafeApp.admin_products : products;
  const adminCategories = Array.isArray(cafeApp.admin_categories) && cafeApp.admin_categories.length ? cafeApp.admin_categories : cafeApp.categories || [];
  const search = state.pos.adminProductSearch.trim().toLowerCase();
  const status = state.pos.adminProductStatus || "all";
  const category = state.pos.adminProductCategory || "";
  const filtered = adminProducts.filter((product) => {
    const matchesSearch = !search
      || String(product.product_name || "").toLowerCase().includes(search)
      || String(product.take_note || "").toLowerCase().includes(search);
    const matchesStatus = status === "all" || String(product.status || "") === status;
    const matchesCategory = !category || String(product.category || "") === category;
    return matchesSearch && matchesStatus && matchesCategory;
  });
  const selectedBranch = state.pos.user?.branch_id || state.pos.auth?.branch_id || 1;
  const statusOptions = `
    <option value="active">Đang bán</option>
    <option value="inactive">Ngừng bán</option>
  `;
  return `
    <div class="admin-grid product-admin-layout">
      <form class="create-form product-admin-form" data-product-save enctype="multipart/form-data">
        <div class="panel-head compact">
          <div>
            <p class="eyebrow">Sản phẩm</p>
            <h2>Tạo / sửa sản phẩm</h2>
          </div>
          <button type="button" class="secondary-btn" data-product-form-reset>Nhập mới</button>
        </div>
        <input type="hidden" name="id">
        <label>Tên sản phẩm <input name="product_name" required placeholder="Latte hạt dẻ"></label>
        <label>Danh mục <select name="category">${categoryOptions("coffee", adminCategories)}</select></label>
        <label>Giá bán / Size M <input type="number" name="price" min="0" step="1000" value="45000"></label>
        <div class="form-three size-price-grid">
          <label>Size S <input type="number" name="size_price_s" min="0" step="1000" value="40000"></label>
          <label>Size M <input type="number" name="size_price_m" min="0" step="1000" value="45000"></label>
          <label>Size L <input type="number" name="size_price_l" min="0" step="1000" value="52000"></label>
        </div>
        <label>Trạng thái <select name="status">${statusOptions}</select></label>
        <label>Chi nhánh <select name="branch_id">${branchOptions(selectedBranch)}</select></label>
        <div class="form-two">
          <label>Tồn kho <input type="number" name="stock_quantity" min="0" value="0"></label>
          <label>Tồn tối thiểu <input type="number" name="min_stock_level" min="0" value="0"></label>
        </div>
        <label>Ghi chú <textarea name="take_note" placeholder="Mô tả ngắn, nguyên liệu nổi bật, ghi chú bán hàng"></textarea></label>
        <label>Ảnh chính <input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG hoặc WEBP, tối đa 2MB.</small></label>
        <button class="primary-btn" type="submit">Lưu sản phẩm</button>
      </form>
      <section class="panel">
        <div class="panel-head">
          <div>
            <p class="eyebrow">Danh sách</p>
            <h2>Sản phẩm</h2>
          </div>
          <div class="product-admin-filters">
            <input data-admin-product-search placeholder="Tìm sản phẩm" value="${escapeHtml(state.pos.adminProductSearch)}">
            <select data-admin-product-category>
              <option value="">Tất cả danh mục</option>
              ${adminCategories.map((item) => `<option value="${escapeHtml(item.category_code)}" ${state.pos.adminProductCategory === item.category_code ? "selected" : ""}>${escapeHtml(item.category_name)}</option>`).join("")}
            </select>
            <select data-admin-product-status>
              <option value="all" ${state.pos.adminProductStatus === "all" ? "selected" : ""}>Tất cả trạng thái</option>
              <option value="active" ${state.pos.adminProductStatus === "active" ? "selected" : ""}>Đang bán</option>
              <option value="inactive" ${state.pos.adminProductStatus === "inactive" ? "selected" : ""}>Ngừng bán</option>
            </select>
          </div>
        </div>
        ${tableHtml(filtered, ["Ảnh", "Tên", "Danh mục", "Giá", "Tồn kho", "Trạng thái", ""], (row) => `
          <tr>
            <td><img class="product-admin-thumb" src="${escapeHtml(asset(row.image))}" alt="${escapeHtml(row.product_name)}"></td>
            <td><strong>${escapeHtml(row.product_name)}</strong><small>${escapeHtml(row.take_note || "")}</small></td>
            <td>${escapeHtml(row.category_name || row.category)}</td>
            <td>${productPriceRangeLabel(row)}</td>
            <td>${Number(row.stock_quantity || 0)} <small>Min ${Number(row.min_stock_level || 0)}</small></td>
            <td><span class="status ${row.status === "active" ? "good" : "bad"}">${row.status === "active" ? "Đang bán" : "Ngừng bán"}</span></td>
            <td class="row-actions">
              <button type="button" data-edit-product="${row.id}">Sửa</button>
              <button type="button" data-edit-product="${row.id}" data-focus-image="1">Đổi ảnh</button>
              ${row.status === "active"
                ? `<button type="button" data-product-delete="${row.id}">Ngừng bán</button>`
                : `<button type="button" data-product-restore="${row.id}">Khôi phục</button>`}
            </td>
          </tr>
        `, "Chưa có sản phẩm phù hợp.")}
      </section>
      <section class="panel span-2">
        <div class="panel-head">
          <div>
            <p class="eyebrow">Danh mục</p>
            <h2>Quản lý danh mục</h2>
          </div>
        </div>
        <div class="category-admin-grid">
          <form class="create-form compact" data-category-save>
            <input type="hidden" name="id">
            <label>Mã danh mục <input name="category_code" required placeholder="signature"></label>
            <label>Tên danh mục <input name="category_name" required placeholder="Món đặc trưng"></label>
            <label>Thứ tự <input type="number" name="display_order" min="0" value="0"></label>
            <label>Trạng thái <select name="status">${statusOptions}</select></label>
            <button class="primary-btn" type="submit">Lưu danh mục</button>
          </form>
          ${tableHtml(adminCategories, ["Mã", "Tên", "Thứ tự", "Trạng thái", ""], (row) => `
            <tr>
              <td>${escapeHtml(row.category_code)}</td>
              <td>${escapeHtml(row.category_name)}</td>
              <td>${Number(row.display_order || 0)}</td>
              <td><span class="status ${row.status === "inactive" ? "bad" : "good"}">${row.status === "inactive" ? "Ngừng dùng" : "Đang dùng"}</span></td>
              <td><button type="button" data-edit-category="${row.id}">Sửa</button></td>
            </tr>
          `, "Chưa có danh mục.")}
        </div>
      </section>
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

function renderStaffCrudModule() {
  const staff = cafeApp.staff || [];
  const roles = cafeApp.roles || Object.keys(roleLabels);
  return `
    <div class="admin-grid">
      <form class="create-form" data-staff-save>
        <h2>Nhân viên</h2>
        <input type="hidden" name="id">
        <label>Mã nhân viên <input name="staff_code" placeholder="CASH003" required></label>
        <label>Tên nhân viên <input name="staff_name" required></label>
        <label>Chi nhánh <select name="branch_id">${branchOptions(state.pos.user?.branch_id || 1)}</select></label>
        <label>Role <select name="staff_role">${roles.map((role) => `<option value="${escapeHtml(role)}">${escapeHtml(roleLabels[role] || role)}</option>`).join("")}</select></label>
        <label>Trạng thái
          <select name="status">
            <option value="active">Đang hoạt động</option>
            <option value="inactive">Ngừng hoạt động</option>
          </select>
        </label>
        <label>Số điện thoại <input name="phone_number"></label>
        <label>Email <input type="email" name="email"></label>
        <label>Mật khẩu POS <input name="password" type="password" minlength="6" placeholder="Bắt buộc khi tạo mới, để trống nếu không đổi"></label>
        <label>PIN mở ca <input name="pin" type="password" inputmode="numeric" minlength="4" placeholder="Bắt buộc khi tạo mới, để trống nếu không đổi"></label>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Lưu nhân viên</button>
          <button class="secondary-btn" type="button" data-staff-form-reset>Tạo mới</button>
        </div>
      </form>
      <section class="panel">
        <div class="panel-head">
          <h2>Danh sách nhân viên</h2>
          <p>Tạo, sửa, ngừng hoạt động và khôi phục tài khoản POS.</p>
        </div>
        ${tableHtml(staff, ["Mã", "Tên", "Role", "Chi nhánh", "Liên hệ", "Trạng thái", ""], (row) => `<tr class="${row.status === "inactive" ? "is-muted" : ""}">
          <td>${escapeHtml(row.staff_code || "")}</td>
          <td><strong>${escapeHtml(row.staff_name)}</strong></td>
          <td>${escapeHtml(roleLabels[row.staff_role] || row.staff_role)}</td>
          <td>${escapeHtml(row.branch_name)}</td>
          <td>${escapeHtml([row.email, row.phone_number].filter(Boolean).join(" · "))}</td>
          <td><span class="status ${row.status === "active" ? "good" : "bad"}">${row.status === "active" ? "Đang hoạt động" : "Ngừng hoạt động"}</span></td>
          <td>
            <div class="table-actions">
              <button type="button" class="secondary-btn compact" data-edit-staff="${row.id}">Sửa</button>
              ${row.status === "active"
                ? `<button type="button" class="secondary-btn compact danger" data-staff-delete="${row.id}">Ngừng</button>`
                : `<button type="button" class="secondary-btn compact" data-staff-restore="${row.id}">Khôi phục</button>`}
            </div>
          </td>
        </tr>`)}
      </section>
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
        <strong>${productPriceRangeLabel(product)}</strong>
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
  cafeApp.website_orders_pending = data.website_orders_pending || [];
  cafeApp.kitchen = data.kitchen || [];
  cafeApp.dashboard = data.dashboard || null;
  cafeApp.campaigns = data.campaigns || [];
  cafeApp.inventory = data.inventory || [];
  cafeApp.reports = data.reports || {};
  cafeApp.session_reports = data.session_reports || [];
  syncProductAdminPayload(data);
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
  if (!Array.isArray(data.products)) syncProducts([]);
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
  if (result.website_orders_pending) cafeApp.website_orders_pending = result.website_orders_pending;
  if (result.tables) cafeApp.tables = result.tables;
  if (result.kitchen) cafeApp.kitchen = result.kitchen;
  if (result.product_inventory || result.materials || result.movements) cafeApp.inventory = result;
  syncProductAdminPayload(result);
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
          <span class="status ${isOut ? "bad" : "good"}">${isOut ? "Tạm hết" : "Còn hàng"}</span>
          <div class="product-actions">
            <strong>${productPriceRangeLabel(product)}</strong>
            <a class="secondary-link" href="${url(`product?id=${product.id}`)}">Chi tiết</a>
            <button type="button" class="cart-add-btn" data-site-add="${product.id}" ${isOut ? "disabled" : ""}>Thêm vào giỏ hàng</button>
            <button type="button" class="order-now-btn" data-site-order-now="${product.id}" ${isOut ? "disabled" : ""}>Order now</button>
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
              <span class="step-badge">Bước 1</span>
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
              <span class="step-badge">Bước 2</span>
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
  target.innerHTML = filtered.map((product) => {
    const isOut = Boolean(product.is_out_of_stock) || Number(product.stock_quantity || 0) <= 0;
    return `
      <article class="pos-product-card ${isOut ? "is-out" : ""}">
        <img src="${escapeHtml(asset(product.image))}" alt="${escapeHtml(product.product_name)}">
        <div class="pos-product-body">
          <strong class="product-title">${escapeHtml(product.product_name)}</strong>
          <small>${escapeHtml(product.category_name || product.category)} · Tồn ${Number(product.stock_quantity || 0)}</small>
          <div class="product-foot">
            <span class="price">${productPriceRangeLabel(product)}</span>
            <button type="button" class="add-btn" data-pos-add="${product.id}" ${isOut ? "disabled" : ""}>${isOut ? "Hết" : "+"}</button>
          </div>
        </div>
      </article>
    `;
  }).join("") || '<div class="empty-state">Không có sản phẩm phù hợp.</div>';
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
    const siteOrderNow = event.target.closest("[data-site-order-now]");
    const posAdd = event.target.closest("[data-pos-add]");
    const quantity = event.target.closest("[data-cart-scope][data-delta]");
    const remove = event.target.closest("[data-cart-scope][data-remove]");
    const clearSiteCartButton = event.target.closest("[data-site-cart-clear]");
    const duplicateSiteLine = event.target.closest("[data-site-cart-duplicate]");
    const tableCard = event.target.closest("[data-select-table]");
    const updateItem = event.target.closest("[data-update-item]");
    const voidItem = event.target.closest("[data-void-item]");
    const cancelOrder = event.target.closest("[data-cancel-order]");
    const websitePendingPay = event.target.closest("[data-website-pending-pay]");
    const websitePendingCancel = event.target.closest("[data-website-pending-cancel]");
    const orderCheckout = event.target.closest("[data-order-checkout]");
    const receiptInvoice = event.target.closest("[data-receipt-invoice]");
    const refundInvoice = event.target.closest("[data-refund-invoice]");
    const reportExport = event.target.closest("[data-report-export]");
    const receiptClose = event.target.closest("[data-receipt-close]");
    const receiptPrint = event.target.closest("[data-receipt-print]");
    const claimVoucher = event.target.closest("[data-claim-voucher]");
    const copyClaimCode = event.target.closest("[data-copy-claim-code]");
    const favorite = event.target.closest("[data-favorite-product]");
    const websiteOrderCancel = event.target.closest("[data-website-order-cancel]");
    const websiteOrderReceipt = event.target.closest("[data-order-receipt]");
    const editProduct = event.target.closest("[data-edit-product]");
    const productDelete = event.target.closest("[data-product-delete]");
    const productRestore = event.target.closest("[data-product-restore]");
    const editCampaign = event.target.closest("[data-edit-campaign]");
    const campaignDelete = event.target.closest("[data-campaign-delete]");
    const campaignRestore = event.target.closest("[data-campaign-restore]");
    const campaignFormReset = event.target.closest("[data-campaign-form-reset]");
    const editCategory = event.target.closest("[data-edit-category]");
    const productFormReset = event.target.closest("[data-product-form-reset]");
    const editMaterial = event.target.closest("[data-edit-material]");
    const materialDelete = event.target.closest("[data-material-delete]");
    const materialRestore = event.target.closest("[data-material-restore]");
    const materialFormReset = event.target.closest("[data-material-form-reset]");
    const editStock = event.target.closest("[data-edit-stock]");
    const stockFormReset = event.target.closest("[data-stock-form-reset]");
    const editRecipe = event.target.closest("[data-edit-recipe]");
    const recipeDelete = event.target.closest("[data-recipe-delete]");
    const recipeRestore = event.target.closest("[data-recipe-restore]");
    const recipeFormReset = event.target.closest("[data-recipe-form-reset]");
    const editStaff = event.target.closest("[data-edit-staff]");
    const staffDelete = event.target.closest("[data-staff-delete]");
    const staffRestore = event.target.closest("[data-staff-restore]");
    const staffFormReset = event.target.closest("[data-staff-form-reset]");
    const fillLogin = event.target.closest("[data-fill-login]");
    const passwordToggle = event.target.closest("[data-password-toggle]");
    const memberMenuToggle = event.target.closest("[data-member-menu-toggle]");
    const productThumb = event.target.closest("[data-product-thumb]");
    const memberNav = event.target.closest("[data-member-nav]");

    if (productThumb) {
      const image = document.querySelector(".product-main-image img");
      if (image && productThumb.dataset.productThumb) {
        image.src = productThumb.dataset.productThumb;
        document.querySelectorAll("[data-product-thumb]").forEach((button) => button.classList.remove("is-active"));
        productThumb.classList.add("is-active");
      }
      return;
    }

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
    if (clearSiteCartButton) {
      clearSiteCart();
      return;
    }
    if (duplicateSiteLine) {
      duplicateSiteCartLine(duplicateSiteLine.dataset.siteCartDuplicate);
      return;
    }
    if (siteAdd) {
      addToCart("site", siteAdd.dataset.siteAdd);
      return;
    }
    if (siteOrderNow) {
      await orderNowSiteProduct(siteOrderNow.dataset.siteOrderNow);
      return;
    }
    if (posAdd) {
      addToCart("pos", posAdd.dataset.posAdd);
      return;
    }
    if (quantity) {
      updateQuantity(quantity.dataset.cartScope, quantity.dataset.cartId || quantity.dataset.productId, quantity.dataset.delta);
      return;
    }
    if (remove) {
      removeItem(remove.dataset.cartScope, remove.dataset.cartId || remove.dataset.productId);
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
    if (websitePendingPay) {
      try {
        const result = await api("order-status-update", {
          invoice_id: Number(websitePendingPay.dataset.websitePendingPay),
          status: "paid",
        });
        updatePosCollections(result);
        renderPosApp();
        showToast("Đã xác nhận thanh toán đơn website.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (websitePendingCancel) {
      const reason = window.prompt("Lý do hủy đơn website pending?");
      if (!reason || !reason.trim()) return;
      try {
        const result = await api("order-status-update", {
          invoice_id: Number(websitePendingCancel.dataset.websitePendingCancel),
          status: "cancelled",
          reason: reason.trim(),
        });
        updatePosCollections(result);
        renderPosApp();
        showToast("Đã hủy đơn website pending.");
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
        const result = await api("reports-export", reportFilterPayload());
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
    if (copyClaimCode) {
      const code = copyClaimCode.dataset.copyClaimCode || copyClaimCode.textContent.trim();
      try {
        await navigator.clipboard?.writeText(code);
        showToast(`Đã copy mã ${code}.`);
      } catch (error) {
        window.prompt("Copy mã voucher", code);
      }
      return;
    }
    if (editCampaign) {
      state.pos.editingCampaignId = editCampaign.dataset.editCampaign || "";
      renderPosApp();
      window.setTimeout(() => document.querySelector("[data-campaign-create]")?.scrollIntoView({ behavior: "smooth", block: "start" }), 0);
      return;
    }
    if (campaignFormReset) {
      state.pos.editingCampaignId = "";
      renderPosApp();
      return;
    }
    if (campaignDelete) {
      const reason = window.prompt("Lý do hủy campaign?");
      if (!reason || !reason.trim()) return;
      try {
        const result = await api("campaign-delete", {
          id: campaignDelete.dataset.campaignDelete,
          reason: reason.trim(),
        });
        syncCampaignPayload(result);
        if (String(state.pos.editingCampaignId) === String(campaignDelete.dataset.campaignDelete)) {
          state.pos.editingCampaignId = "";
        }
        renderPosApp();
        showToast("Đã hủy campaign và voucher chưa dùng.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (campaignRestore) {
      try {
        const result = await api("campaign-restore", { id: campaignRestore.dataset.campaignRestore });
        syncCampaignPayload(result);
        renderPosApp();
        showToast("Đã khôi phục campaign.");
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
    if (productFormReset) {
      const form = document.querySelector("[data-product-save]");
      if (form) form.reset();
      const id = form?.elements?.id;
      if (id) id.value = "";
      return;
    }
    if (productDelete) {
      if (!window.confirm("Ngừng bán sản phẩm này? Website sẽ không còn hiển thị sản phẩm.")) return;
      try {
        const result = await api("product-delete", {
          id: productDelete.dataset.productDelete,
          branch_id: state.pos.user?.branch_id || 1,
        });
        syncProductAdminPayload(result);
        renderPosApp();
        showToast("Đã ngừng bán sản phẩm.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (productRestore) {
      try {
        const result = await api("product-restore", {
          id: productRestore.dataset.productRestore,
          branch_id: state.pos.user?.branch_id || 1,
        });
        syncProductAdminPayload(result);
        renderPosApp();
        showToast("Đã khôi phục sản phẩm.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (editCategory) {
      const category = (cafeApp.admin_categories || cafeApp.categories || []).find((item) => String(item.id) === String(editCategory.dataset.editCategory));
      const form = document.querySelector("[data-category-save]");
      if (category && form) {
        form.elements.id.value = category.id;
        form.elements.category_code.value = category.category_code || "";
        form.elements.category_name.value = category.category_name || "";
        form.elements.display_order.value = category.display_order || 0;
        form.elements.status.value = category.status || "active";
        form.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      return;
    }
    if (editProduct) {
      const productRows = Array.isArray(cafeApp.admin_products) && cafeApp.admin_products.length ? cafeApp.admin_products : products;
      const product = productRows.find((item) => String(item.id) === String(editProduct.dataset.editProduct));
      const form = document.querySelector("[data-product-save]");
      if (product && form) {
        form.elements.id.value = product.id;
        form.elements.product_name.value = product.product_name;
        form.elements.category.value = product.category;
        form.elements.price.value = product.price;
        const sizePrices = productSizePrices(product);
        if (form.elements.size_price_s) form.elements.size_price_s.value = sizePrices.S;
        if (form.elements.size_price_m) form.elements.size_price_m.value = sizePrices.M;
        if (form.elements.size_price_l) form.elements.size_price_l.value = sizePrices.L;
        form.elements.take_note.value = product.take_note || "";
        form.elements.status.value = product.status || "active";
        if (form.elements.branch_id) form.elements.branch_id.value = product.branch_id || state.pos.user?.branch_id || 1;
        if (form.elements.stock_quantity) form.elements.stock_quantity.value = product.stock_quantity || 0;
        if (form.elements.min_stock_level) form.elements.min_stock_level.value = product.min_stock_level || 0;
        const target = editProduct.dataset.focusImage ? form.elements.image : form.elements.product_name;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
        if (target) target.focus();
      }
      return;
    }
    if (materialFormReset) {
      const form = document.querySelector("[data-material-save]");
      if (form) {
        form.reset();
        if (form.elements.id) form.elements.id.value = "";
        if (form.elements.status) form.elements.status.value = "active";
      }
      return;
    }
    if (editMaterial) {
      const material = (cafeApp.inventory?.material_catalog || []).find((item) => String(item.id) === String(editMaterial.dataset.editMaterial));
      const form = document.querySelector("[data-material-save]");
      if (material && form) {
        form.elements.id.value = material.id || "";
        form.elements.material_name.value = material.material_name || "";
        form.elements.unit.value = material.unit || "";
        form.elements.min_stock_level.value = material.min_stock_level || 0;
        form.elements.unit_cost.value = material.unit_cost || 0;
        form.elements.supplier_name.value = material.supplier_name || "";
        form.elements.status.value = material.status || "active";
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      return;
    }
    if (materialDelete) {
      if (!window.confirm("Ngừng sử dụng nguyên vật liệu này? Lịch sử kho vẫn được giữ lại.")) return;
      try {
        cafeApp.inventory = await api("material-delete", { id: materialDelete.dataset.materialDelete });
        renderPosApp();
        showToast("Đã ngừng sử dụng nguyên vật liệu.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (materialRestore) {
      try {
        cafeApp.inventory = await api("material-restore", { id: materialRestore.dataset.materialRestore });
        renderPosApp();
        showToast("Đã khôi phục nguyên vật liệu.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (stockFormReset) {
      const form = document.querySelector("[data-inventory-stock-save]");
      if (form) form.reset();
      return;
    }
    if (editStock) {
      const [branchId, materialId] = String(editStock.dataset.editStock || "").split(":");
      const row = (cafeApp.inventory?.materials || []).find((item) => String(item.branch_id) === branchId && String(item.material_id) === materialId);
      const form = document.querySelector("[data-inventory-stock-save]");
      if (row && form) {
        form.elements.branch_id.value = row.branch_id;
        form.elements.material_id.value = row.material_id;
        form.elements.stock_quantity.value = row.stock_quantity || 0;
        form.elements.min_stock_level.value = row.min_stock_level || 0;
        form.elements.unit_cost.value = row.unit_cost || 0;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      return;
    }
    if (recipeFormReset) {
      const form = document.querySelector("[data-recipe-save]");
      if (form) {
        form.reset();
        if (form.elements.id) form.elements.id.value = "";
        form.querySelectorAll(".recipe-item-row").forEach((row) => {
          const qty = row.querySelector('[name="recipe_quantity_per_unit"]');
          if (qty) qty.value = "";
        });
      }
      return;
    }
    if (editRecipe) {
      const recipe = (cafeApp.inventory?.recipes || []).find((item) => String(item.id) === String(editRecipe.dataset.editRecipe));
      const form = document.querySelector("[data-recipe-save]");
      if (recipe && form) {
        form.elements.id.value = recipe.id || "";
        form.elements.product_id.value = recipe.product_id || "";
        form.elements.recipe_name.value = recipe.recipe_name || "";
        form.elements.yield_quantity.value = recipe.yield_quantity || 1;
        form.elements.status.value = recipe.status || "active";
        const rows = Array.from(form.querySelectorAll(".recipe-item-row"));
        rows.forEach((row, index) => {
          const item = (recipe.items || [])[index] || {};
          const material = row.querySelector('[name="recipe_material_id"]');
          const qty = row.querySelector('[name="recipe_quantity_per_unit"]');
          if (material && item.material_id) material.value = item.material_id;
          if (qty) qty.value = item.quantity_per_unit || "";
        });
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      return;
    }
    if (recipeDelete) {
      if (!window.confirm("Ngừng sử dụng recipe này? Sản phẩm sẽ không tự trừ nguyên liệu theo recipe này nữa.")) return;
      try {
        cafeApp.inventory = await api("recipe-delete", { id: recipeDelete.dataset.recipeDelete });
        renderPosApp();
        showToast("Đã ngừng sử dụng recipe.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (recipeRestore) {
      try {
        cafeApp.inventory = await api("recipe-restore", { id: recipeRestore.dataset.recipeRestore });
        renderPosApp();
        showToast("Đã khôi phục recipe.");
      } catch (error) {
        showToast(error.message);
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
        if (form.elements.status) form.elements.status.value = staff.status || "active";
        form.elements.phone_number.value = staff.phone_number || "";
        form.elements.email.value = staff.email || "";
        if (form.elements.password) form.elements.password.value = "";
        if (form.elements.pin) form.elements.pin.value = "";
        form.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      return;
    }
    if (staffFormReset) {
      const form = document.querySelector("[data-staff-save]");
      if (form) {
        form.reset();
        if (form.elements.id) form.elements.id.value = "";
        if (form.elements.status) form.elements.status.value = "active";
      }
      return;
    }
    if (staffDelete) {
      const staff = (cafeApp.staff || []).find((item) => String(item.id) === String(staffDelete.dataset.staffDelete));
      if (!window.confirm(`Ngừng hoạt động nhân viên ${staff?.staff_name || ""}? Nhân viên này sẽ không đăng nhập POS được nữa.`)) return;
      try {
        const result = await api("staff-delete", {
          id: staffDelete.dataset.staffDelete,
          reason: "Disabled from POS staff module",
        });
        cafeApp.staff = result.staff || [];
        renderPosApp();
        showToast("Đã ngừng hoạt động nhân viên.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (staffRestore) {
      try {
        const result = await api("staff-restore", { id: staffRestore.dataset.staffRestore });
        cafeApp.staff = result.staff || [];
        renderPosApp();
        showToast("Đã khôi phục nhân viên.");
      } catch (error) {
        showToast(error.message);
      }
      return;
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
    const voucherClaimCodeForm = event.target.closest("[data-voucher-claim-code]");
    const memberFeedbackForm = event.target.closest("[data-member-feedback]");
    const createForm = event.target.closest("[data-customer-create]");
    const newsletterForm = event.target.closest("[data-newsletter-form]");
    const offerLoginForm = event.target.closest("[data-offer-login-form]");
    const reportFilterForm = event.target.closest("[data-report-filter]");
    const serviceOrderForm = event.target.closest("[data-service-order-create]");
    const campaignForm = event.target.closest("[data-campaign-create]");
    const materialForm = event.target.closest("[data-material-save]");
    const inventoryStockForm = event.target.closest("[data-inventory-stock-save]");
    const stockForm = event.target.closest("[data-stock-movement]");
    const recipeForm = event.target.closest("[data-recipe-save]");
    const cashForm = event.target.closest("[data-cash-transaction]");
    const productForm = event.target.closest("[data-product-save]");
    const categoryForm = event.target.closest("[data-category-save]");
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
    if (voucherClaimCodeForm) {
      event.preventDefault();
      if (!state.site.customer) {
        showToast("Vui lòng đăng nhập thành viên để claim voucher.");
        await navigateWebsite(url("login"));
        return;
      }
      try {
        const result = await api("voucher-claim-code", Object.fromEntries(new FormData(voucherClaimCodeForm)));
        setSiteMember(result.member);
        renderProfile("portal", result.member);
        renderProfile("account", result.member);
        voucherClaimCodeForm.reset();
        showToast(`Đã claim voucher ${result.voucher_code}.`);
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (memberFeedbackForm) {
      event.preventDefault();
      if (!state.site.customer) {
        setMessage("[data-feedback-message]", "Vui lòng đăng nhập thành viên trước khi gửi phản hồi.", true);
        showToast("Vui lòng đăng nhập để gửi feedback.");
        await navigateWebsite(url("login"));
        return;
      }
      const submitButton = memberFeedbackForm.querySelector("button[type='submit']");
      if (submitButton) submitButton.disabled = true;
      try {
        const result = await api("member-feedback", Object.fromEntries(new FormData(memberFeedbackForm)));
        memberFeedbackForm.reset();
        setMessage("[data-feedback-message]", `Đã gửi đánh giá tới ${result.recipient}. Cafe Connect sẽ kiểm tra sớm.`);
        showToast("Đã gửi đánh giá.");
      } catch (error) {
        setMessage("[data-feedback-message]", error.message, true);
        showToast(error.message);
      } finally {
        if (submitButton) submitButton.disabled = false;
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
    if (offerLoginForm) {
      event.preventDefault();
      const target = state.site.customer ? "account" : "login";
      showToast(state.site.customer ? "Mở hồ sơ để nhận và quản lý voucher." : "Vui lòng đăng nhập để nhận ưu đãi.");
      await navigateWebsite(url(target));
      return;
    }
    if (reportFilterForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(reportFilterForm));
        state.pos.reportStartDate = payload.start_date || "";
        state.pos.reportEndDate = payload.end_date || "";
        cafeApp.reports = await api("reports", payload);
        renderPosApp();
        showToast("Đã cập nhật báo cáo.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (serviceOrderForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(serviceOrderForm));
        payload.items = state.pos.cart.map(posCartItemForInvoice);
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
        const payload = Object.fromEntries(new FormData(campaignForm));
        const wasEditing = Boolean(payload.id);
        if (!payload.id) delete payload.id;
        const result = await api("campaign-save", payload);
        syncCampaignPayload(result);
        state.pos.editingCampaignId = "";
        renderPosApp();
        if (wasEditing) {
          showToast("Đã cập nhật campaign.");
        } else {
          showToast(result.distribution_type === "claim_code"
            ? `Đã tạo campaign. Mã claim: ${result.claim_code}.`
            : `Đã tạo campaign và phát hành ${result.issued_count} voucher.`);
        }
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (materialForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(materialForm));
        if (!payload.id) delete payload.id;
        cafeApp.inventory = await api("material-save", payload);
        materialForm.reset();
        if (materialForm.elements.id) materialForm.elements.id.value = "";
        renderPosApp();
        showToast("Đã lưu nguyên vật liệu.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (inventoryStockForm) {
      event.preventDefault();
      try {
        const payload = Object.fromEntries(new FormData(inventoryStockForm));
        cafeApp.inventory = await api("inventory-stock-save", payload);
        renderPosApp();
        showToast("Đã cập nhật tồn kho chi nhánh.");
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
        payload.branch_id = Number(payload.branch_id || state.pos.user?.branch_id || 1);
        cafeApp.inventory = await api("stock-movement", payload);
        renderPosApp();
        showToast("Đã ghi nhận nhập/xuất kho.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (recipeForm) {
      event.preventDefault();
      try {
        const formData = new FormData(recipeForm);
        const payload = Object.fromEntries(formData);
        if (!payload.id) delete payload.id;
        const materialIds = formData.getAll("recipe_material_id");
        const quantities = formData.getAll("recipe_quantity_per_unit");
        payload.items_json = JSON.stringify(materialIds.map((materialId, index) => ({
          material_id: materialId,
          quantity_per_unit: quantities[index],
        })).filter((item) => Number(item.material_id) > 0 && Number(item.quantity_per_unit) > 0));
        delete payload.recipe_material_id;
        delete payload.recipe_quantity_per_unit;
        cafeApp.inventory = await api("recipe-save", payload);
        recipeForm.reset();
        if (recipeForm.elements.id) recipeForm.elements.id.value = "";
        renderPosApp();
        showToast("Đã lưu recipe/BOM.");
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
        const formData = new FormData(productForm);
        const imageFile = productForm.elements.image?.files?.[0] || null;
        formData.delete("image");
        let result = await apiForm("product-save", formData);
        if (imageFile) {
          const imageData = new FormData();
          imageData.append("product_id", result.id);
          imageData.append("image", imageFile);
          imageData.append("alt_text", productForm.elements.product_name?.value || "");
          imageData.append("is_primary", "1");
          imageData.append("branch_id", productForm.elements.branch_id?.value || state.pos.user?.branch_id || 1);
          result = await apiForm("product-image-upload", imageData);
        }
        syncProductAdminPayload(result);
        productForm.reset();
        if (productForm.elements.id) productForm.elements.id.value = "";
        renderPosApp();
        showToast("Đã lưu sản phẩm.");
      } catch (error) {
        showToast(error.message);
      }
      return;
    }
    if (categoryForm) {
      event.preventDefault();
      try {
        const result = await api("category-save", Object.fromEntries(new FormData(categoryForm)));
        syncProductAdminPayload(result);
        categoryForm.reset();
        if (categoryForm.elements.id) categoryForm.elements.id.value = "";
        renderPosApp();
        showToast("Đã lưu danh mục.");
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
    const siteFulfillment = event.target.closest("[data-site-fulfillment]");
    const sitePayment = event.target.closest("[data-site-payment]");
    const siteBranch = event.target.closest("[data-site-branch]");
    const siteCartSize = event.target.closest("[data-site-cart-size]");
    const posCartSize = event.target.closest("[data-pos-cart-size]");
    const adminStatusFilter = event.target.closest("[data-admin-product-status]");
    const adminCategoryFilter = event.target.closest("[data-admin-product-category]");
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
      return;
    }
    if (siteBranch) {
      renderCart("site");
      return;
    }
    if (siteFulfillment || sitePayment) {
      renderCheckoutState();
      renderSiteTotals();
      return;
    }
    if (siteCartSize) {
      updateSiteCartOption(siteCartSize.dataset.cartId, "size", siteCartSize.value);
      return;
    }
    if (posCartSize) {
      updatePosCartOption(posCartSize.dataset.cartId, "size", posCartSize.value);
      return;
    }
    if (adminStatusFilter) {
      state.pos.adminProductStatus = adminStatusFilter.value || "all";
      renderPosApp();
      return;
    }
    if (adminCategoryFilter) {
      state.pos.adminProductCategory = adminCategoryFilter.value || "";
      renderPosApp();
    }
  });

  document.addEventListener("input", (event) => {
    const productSearch = event.target.closest("[data-product-search]");
    const siteProductSearch = event.target.closest("[data-site-product-search]");
    const siteCartNote = event.target.closest("[data-site-cart-note]");
    const posCartTopping = event.target.closest("[data-pos-cart-topping]");
    const posCartNote = event.target.closest("[data-pos-cart-note]");
    const adminProductSearch = event.target.closest("[data-admin-product-search]");
    if (productSearch) {
      state.pos.productFilter = productSearch.value;
      renderPosProducts();
    }
    if (siteProductSearch) {
      renderSiteProducts();
      return;
    }
    if (siteCartNote) {
      updateSiteCartOption(siteCartNote.dataset.cartId, "topping", siteCartNote.value);
      return;
    }
    if (posCartTopping) {
      updatePosCartOption(posCartTopping.dataset.cartId, "topping", posCartTopping.value);
      return;
    }
    if (posCartNote) {
      updatePosCartOption(posCartNote.dataset.cartId, "note", posCartNote.value);
      return;
    }
    if (adminProductSearch) {
      state.pos.adminProductSearch = adminProductSearch.value || "";
      renderPosApp();
      window.setTimeout(() => {
        const input = document.querySelector("[data-admin-product-search]");
        if (input) {
          input.focus();
          input.setSelectionRange(input.value.length, input.value.length);
        }
      }, 0);
    }
  });
}

function initialRender() {
  renderHeaderPosLink();
  renderCustomerCrmLinks();
  renderMemberNav();
  renderAccountState();
  renderAccountForm();
  renderMemberAccount();
  renderSiteProducts();
  renderReviews();
  renderCart("site");
  loadWebsiteOrderDetail();
  loadMomoPaymentReturn();
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
