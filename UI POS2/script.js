const app = document.querySelector("#app");

const roles = {
  waiter: {
    label: "Nhân viên phục vụ",
    short: "PV",
    defaultView: "waiter",
    description: "Nhận bàn, theo dõi order và trả món cho khách.",
    nav: [
      { id: "waiter", label: "Màn hình phục vụ" },
      { id: "serveOrders", label: "Order trả món" },
    ],
  },
  cashier: {
    label: "NV thu ngân",
    short: "TN",
    defaultView: "cashier",
    description: "Bán hàng, lập hóa đơn và thanh toán.",
    nav: [
      { id: "cashier", label: "Màn hình thu ngân" },
      { id: "addDish", label: "Thêm món mới" },
    ],
  },
  owner: {
    label: "Chủ cửa hàng",
    short: "CH",
    defaultView: "owner",
    description: "Theo dõi doanh thu, món bán chạy và vận hành.",
    nav: [
      { id: "owner", label: "Tổng quan" },
      { id: "menuAdmin", label: "Quản lý món" },
      { id: "addDish", label: "Thêm món mới" },
    ],
  },
  barista: {
    label: "NV pha chế",
    short: "PC",
    defaultView: "barista",
    description: "Xem danh sách món chế biến và cập nhật trạng thái.",
    nav: [{ id: "barista", label: "Danh sách món chế biến" }],
  },
};

const categories = [
  { id: "all", label: "Tất cả" },
  { id: "coffee", label: "Cà phê" },
  { id: "tea", label: "Trà" },
  { id: "cake", label: "Bánh" },
  { id: "juice", label: "Nước ép" },
];

const products = [
  {
    id: 1,
    name: "Cà phê sữa đá",
    category: "coffee",
    price: 29000,
    stock: 42,
    image: "https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 2,
    name: "Bạc xỉu",
    category: "coffee",
    price: 32000,
    stock: 35,
    image: "https://images.unsplash.com/photo-1517701604599-bb29b565090c?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 3,
    name: "Espresso nóng",
    category: "coffee",
    price: 35000,
    stock: 28,
    image: "https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 4,
    name: "Trà đào cam sả",
    category: "tea",
    price: 39000,
    stock: 31,
    image: "https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 5,
    name: "Matcha đá xay",
    category: "tea",
    price: 45000,
    stock: 19,
    image: "https://images.unsplash.com/photo-1515823064-d6e0c04616a7?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 6,
    name: "Trà chanh mật ong",
    category: "tea",
    price: 34000,
    stock: 26,
    image: "https://images.unsplash.com/photo-1547825407-2d060104b7f8?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 7,
    name: "Tiramisu cacao",
    category: "cake",
    price: 42000,
    stock: 17,
    image: "https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 8,
    name: "Croissant bơ",
    category: "cake",
    price: 28000,
    stock: 23,
    image: "https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80",
  },
  {
    id: 9,
    name: "Nước ép cam",
    category: "juice",
    price: 36000,
    stock: 21,
    image: "https://images.unsplash.com/photo-1600271886742-f049cd451bba?auto=format&fit=crop&w=600&q=80",
  },
];

const orders = [
  {
    id: "OD-1026",
    table: "Bàn 05",
    customer: "Khách lẻ",
    waiter: "Lan",
    time: "09:18",
    status: "ready",
    items: [
      { productId: 1, quantity: 1, status: "ready" },
      { productId: 4, quantity: 2, status: "ready" },
      { productId: 8, quantity: 1, status: "served" },
    ],
  },
  {
    id: "OD-1027",
    table: "Bàn 02",
    customer: "Anh Minh",
    waiter: "Huy",
    time: "09:24",
    status: "preparing",
    items: [
      { productId: 2, quantity: 2, status: "preparing" },
      { productId: 7, quantity: 1, status: "waiting" },
    ],
  },
  {
    id: "OD-1028",
    table: "Bàn 08",
    customer: "Chị Mai",
    waiter: "Lan",
    time: "09:31",
    status: "ready",
    items: [
      { productId: 5, quantity: 1, status: "ready" },
      { productId: 6, quantity: 1, status: "preparing" },
    ],
  },
  {
    id: "OD-1029",
    table: "Mang đi",
    customer: "Grab",
    waiter: "Thu ngân",
    time: "09:40",
    status: "paid",
    items: [
      { productId: 3, quantity: 1, status: "served" },
      { productId: 9, quantity: 1, status: "served" },
    ],
  },
];

const state = {
  user: null,
  view: "login",
  loginRole: "cashier",
  category: "all",
  search: "",
  cart: new Map([
    [1, 1],
    [4, 2],
  ]),
  selectedServeOrderId: "OD-1026",
  servedOrderId: null,
  modal: null,
  toast: "",
};

const icons = {
  search:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.15a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>',
  logout:
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="m16 17 5-5-5-5" /><path d="M21 12H9" /></svg>',
};

function money(value) {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
    maximumFractionDigits: 0,
  }).format(value);
}

function getProduct(id) {
  return products.find((product) => product.id === id);
}

function orderTotal(order) {
  return order.items.reduce((total, item) => total + getProduct(item.productId).price * item.quantity, 0);
}

function statusLabel(status) {
  return {
    empty: "Trống",
    occupied: "Có khách",
    waiting: "Chờ pha chế",
    preparing: "Đang làm",
    ready: "Chờ trả món",
    served: "Đã trả món",
    paid: "Đã thanh toán",
  }[status];
}

function readyItemCount(order) {
  return order.items.filter((item) => item.status === "ready").reduce((total, item) => total + item.quantity, 0);
}

function updateOrderStatus(order) {
  if (order.status === "paid") return;
  const statuses = order.items.map((item) => item.status);
  if (statuses.every((status) => status === "served")) order.status = "served";
  else if (statuses.some((status) => status === "ready")) order.status = "ready";
  else if (statuses.some((status) => status === "preparing")) order.status = "preparing";
  else order.status = "waiting";
}

function shell(content) {
  const role = roles[state.user.role];
  return `
    <div class="app">
      <header class="topbar">
        <div class="brand">
          <span class="logo-mark">${role.short[0]}</span>
          <div>
            <p>Coffee Manager</p>
            <strong>${role.label}</strong>
          </div>
        </div>
        <nav class="topnav" aria-label="Điều hướng">
          ${role.nav
            .map(
              (item) => `
                <button class="nav-item ${state.view === item.id ? "active" : ""}" data-view="${item.id}">
                  ${item.label}
                </button>
              `,
            )
            .join("")}
        </nav>
        <div class="top-actions">
          <button class="icon-btn" data-logout aria-label="Đăng xuất">${icons.logout}</button>
          <button class="user-chip">
            <span class="avatar">${role.short}</span>
            <span>${state.user.name}</span>
          </button>
        </div>
      </header>
      ${content}
      ${state.modal ? renderModal() : ""}
      ${state.toast ? `<div class="toast">${state.toast}</div>` : ""}
    </div>
  `;
}

function pageHeading(eyebrow, title, actions = "") {
  return `
    <div class="page-heading">
      <div>
        <span class="eyebrow">${eyebrow}</span>
        <h1>${title}</h1>
      </div>
      ${actions ? `<div class="toolbar">${actions}</div>` : ""}
    </div>
  `;
}

function renderLogin() {
  return `
    <div class="login-page">
      <section class="login-card">
        <div class="logo-lockup">
          <span class="logo-mark">C</span>
          <div>
            <p>Đồ án hệ thống quản lý quán cà phê</p>
            <strong>Coffee POS Manager</strong>
          </div>
        </div>
        <h1>Đăng nhập hệ thống</h1>
        <p>Chọn vai trò để mở đúng màn hình nghiệp vụ: phục vụ, thu ngân, chủ cửa hàng hoặc pha chế.</p>
        <form class="login-form" data-login-form>
          <label class="field">
            <span>Tài khoản</span>
            <input name="username" value="demo@coffee.vn" autocomplete="username" />
          </label>
          <label class="field">
            <span>Mật khẩu</span>
            <input name="password" type="password" value="123456" autocomplete="current-password" />
          </label>
          <div class="role-grid">
            ${Object.entries(roles)
              .map(
                ([id, role]) => `
                  <button class="role-card ${state.loginRole === id ? "active" : ""}" type="button" data-role="${id}">
                    <strong>${role.label}</strong>
                    <span>${role.description}</span>
                  </button>
                `,
              )
              .join("")}
          </div>
          <button class="primary-btn" type="submit">Đăng nhập</button>
        </form>
      </section>
      <aside class="login-aside">
        <div class="login-preview">
          <div class="preview-bar">
            <span class="preview-dot"></span>
            <span class="preview-dot"></span>
            <span class="preview-dot"></span>
          </div>
          <div class="preview-body">
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
    </div>
  `;
}

function productTabs() {
  return `
    <div class="tabs">
      ${categories
        .map(
          (category) => `
            <button class="tab ${state.category === category.id ? "active" : ""}" data-category="${category.id}">
              ${category.label}
            </button>
          `,
        )
        .join("")}
    </div>
  `;
}

function filteredProducts() {
  const keyword = state.search.trim().toLowerCase();
  return products.filter((product) => {
    const matchesCategory = state.category === "all" || product.category === state.category;
    const matchesSearch = product.name.toLowerCase().includes(keyword);
    return matchesCategory && matchesSearch;
  });
}

function renderProductGrid({ addMode = true } = {}) {
  return `
    <div class="product-grid">
      ${filteredProducts()
        .map(
          (product) => `
            <article class="product-card">
              <img src="${product.image}" alt="${product.name}" loading="lazy" />
              <div class="product-body">
                <div class="product-title">${product.name}</div>
                <div class="product-foot">
                  <span class="price">${money(product.price)}</span>
                  ${
                    addMode
                      ? `<button class="add-btn" data-add-cart="${product.id}" aria-label="Thêm ${product.name}">+</button>`
                      : `<span class="mini-badge">${product.stock} phần</span>`
                  }
                </div>
              </div>
            </article>
          `,
        )
        .join("")}
    </div>
  `;
}

function renderCashier() {
  return shell(`
    <main class="page">
      ${pageHeading(
        "Màn hình chính NV thu ngân",
        "Bán hàng và thanh toán",
        '<button class="secondary-btn" data-open-add-dish>+ Thêm món mới</button>',
      )}
      <section class="screen-grid wide-right">
        <section class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Thực đơn</span>
              <h2>Chọn món cho hóa đơn</h2>
            </div>
            <label class="search-box">
              ${icons.search}
              <input data-search value="${state.search}" placeholder="Tìm món, mã sản phẩm..." />
            </label>
          </div>
          ${productTabs()}
          ${renderProductGrid()}
        </section>
        ${renderCartPanel()}
      </section>
    </main>
  `);
}

function renderCartPanel() {
  const items = [...state.cart.entries()].map(([id, quantity]) => ({ product: getProduct(id), quantity }));
  const subtotal = items.reduce((total, item) => total + item.product.price * item.quantity, 0);
  const discount = subtotal >= 150000 ? 10000 : 0;
  const tax = Math.round((subtotal - discount) * 0.08);
  const total = subtotal - discount + tax;

  return `
    <aside class="panel order-panel">
      <div class="panel-head">
        <div>
          <span class="eyebrow">Hóa đơn</span>
          <h2>HD-000126</h2>
        </div>
        <button class="secondary-btn" data-clear-cart>Tạm lưu</button>
      </div>
      <div class="order-meta">
        <label class="field">
          <span>Bàn</span>
          <select>
            <option>Bàn 01</option>
            <option>Bàn 02</option>
            <option selected>Bàn 05</option>
            <option>Mang đi</option>
          </select>
        </label>
        <label class="field">
          <span>Khách hàng</span>
          <input value="Khách lẻ" />
        </label>
      </div>
      <div class="order-table">
        <div class="order-row header">
          <span>Món</span>
          <span>SL</span>
          <span>Tổng</span>
        </div>
        ${
          items.length
            ? items
                .map(
                  ({ product, quantity }) => `
                    <div class="order-row">
                      <span class="item-name">
                        <strong>${product.name}</strong>
                        <small>${money(product.price)}</small>
                      </span>
                      <span class="quantity">
                        <button class="qty-btn" data-cart-minus="${product.id}">-</button>
                        <span>${quantity}</span>
                        <button class="qty-btn" data-cart-plus="${product.id}">+</button>
                      </span>
                      <span class="row-total">${money(product.price * quantity)}</span>
                    </div>
                  `,
                )
                .join("")
            : `<div class="empty-state">Chưa có món trong hóa đơn.</div>`
        }
      </div>
      <div class="summary">
        <div><span>Tạm tính</span><strong>${money(subtotal)}</strong></div>
        <div><span>Giảm giá</span><strong>${money(discount)}</strong></div>
        <div><span>VAT 8%</span><strong>${money(tax)}</strong></div>
        <div class="total-line"><span>Thanh toán</span><strong>${money(total)}</strong></div>
      </div>
      <div class="payment-actions">
        <button class="secondary-btn" data-clear-cart>Hủy đơn</button>
        <button class="primary-btn" data-checkout ${items.length ? "" : "disabled"}>Thanh toán</button>
      </div>
    </aside>
  `;
}

function renderWaiter() {
  const readyOrders = orders.filter((order) => readyItemCount(order) > 0);
  return shell(`
    <main class="page">
      ${pageHeading("Màn hình chính nhân viên phục vụ", "Sơ đồ bàn và order cần xử lý")}
      <section class="screen-grid">
        <section class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Khu vực phục vụ</span>
              <h2>Sơ đồ bàn</h2>
            </div>
            <button class="primary-btn" data-new-waiter-order>Tạo order</button>
          </div>
          <div class="table-map">
            ${renderTableCards()}
          </div>
        </section>
        <aside class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Cần trả món</span>
              <h2>${readyOrders.length} order sẵn sàng</h2>
            </div>
          </div>
          <div class="list">
            ${
              readyOrders.length
                ? readyOrders.map((order) => renderOrderSummary(order, true)).join("")
                : `<div class="empty-state">Chưa có order nào sẵn sàng trả món.</div>`
            }
          </div>
        </aside>
      </section>
    </main>
  `);
}

function renderTableCards() {
  const tableNames = ["Bàn 01", "Bàn 02", "Bàn 03", "Bàn 04", "Bàn 05", "Bàn 06", "Bàn 07", "Bàn 08"];
  return tableNames
    .map((table) => {
      const order = orders.find((item) => item.table === table && item.status !== "paid");
      const status = order ? order.status : "empty";
      return `
        <article class="table-card ${status === "empty" ? "empty" : status === "ready" ? "ready" : "occupied"}">
          <h3>
            <span>${table}</span>
            <span class="status-badge ${status}">${statusLabel(status)}</span>
          </h3>
          ${
            order
              ? `<p class="muted">${order.id} · ${order.customer}<br />${order.items.length} dòng món · ${money(orderTotal(order))}</p>`
              : `<p class="muted">Sẵn sàng nhận khách mới.</p>`
          }
          ${
            order
              ? `<button class="secondary-btn" data-select-serve="${order.id}">Xem order</button>`
              : `<button class="ghost-btn" data-new-waiter-order="${table}">Tạo order</button>`
          }
        </article>
      `;
    })
    .join("");
}

function renderOrderSummary(order, showAction = false) {
  return `
    <article class="list-item">
      <div class="list-top">
        <div>
          <strong>${order.id} · ${order.table}</strong>
          <div class="muted">${order.customer} · ${order.time} · PV ${order.waiter}</div>
        </div>
        <span class="status-badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <div class="line-items">
        ${order.items
          .map((item) => {
            const product = getProduct(item.productId);
            return `
              <div class="line-item">
                <span>${product.name}</span>
                <strong>x${item.quantity}</strong>
                <span class="mini-badge ${item.status}">${statusLabel(item.status)}</span>
              </div>
            `;
          })
          .join("")}
      </div>
      ${
        showAction
          ? `<button class="primary-btn" data-select-serve="${order.id}">Click chọn order trả món</button>`
          : ""
      }
    </article>
  `;
}

function renderServeOrders() {
  const selected = orders.find((order) => order.id === state.selectedServeOrderId) || orders.find((order) => readyItemCount(order));
  if (selected && selected.id !== state.selectedServeOrderId) state.selectedServeOrderId = selected.id;

  return shell(`
    <main class="page">
      ${pageHeading("Click chọn order trả món", "Chi tiết order sẵn sàng phục vụ")}
      <section class="screen-grid">
        <aside class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Danh sách order</span>
              <h2>Chọn order cần trả</h2>
            </div>
          </div>
          <div class="list">
            ${orders
              .filter((order) => order.status !== "paid")
              .map((order) => renderOrderSummary(order, true))
              .join("")}
          </div>
        </aside>
        <section class="serve-detail">
          ${
            selected
              ? renderServeDetail(selected)
              : `<div class="success-screen"><div><h2>Không có order cần trả</h2><p class="muted">Danh sách hiện tại chưa có món nào ở trạng thái sẵn sàng.</p></div></div>`
          }
        </section>
      </section>
    </main>
  `);
}

function renderServeDetail(order) {
  const readyCount = readyItemCount(order);
  return `
    <article class="receipt-card">
      <div class="receipt-title">
        <div>
          <span class="eyebrow">Order đang chọn</span>
          <h2>${order.id} · ${order.table}</h2>
        </div>
        <span class="status-badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <div class="receipt-body">
        <div class="timeline">
          <span class="timeline-step active">1. Nhận order</span>
          <span class="timeline-step ${order.status === "preparing" || order.status === "ready" || order.status === "served" ? "active" : ""}">2. Pha chế</span>
          <span class="timeline-step ${order.status === "ready" || order.status === "served" ? "active" : ""}">3. Trả món</span>
        </div>
        <div class="line-items">
          ${order.items
            .map((item) => {
              const product = getProduct(item.productId);
              return `
                <div class="line-item">
                  <span>${product.name}</span>
                  <strong>x${item.quantity}</strong>
                  <span class="mini-badge ${item.status}">${statusLabel(item.status)}</span>
                </div>
              `;
            })
            .join("")}
        </div>
        <div class="summary">
          <div><span>Khách hàng</span><strong>${order.customer}</strong></div>
          <div><span>Nhân viên phục vụ</span><strong>${order.waiter}</strong></div>
          <div><span>Số món sẵn sàng</span><strong>${readyCount}</strong></div>
          <div class="total-line"><span>Tổng order</span><strong>${money(orderTotal(order))}</strong></div>
        </div>
        <button class="success-btn" data-serve-order="${order.id}" ${readyCount ? "" : "disabled"}>Trả món</button>
      </div>
    </article>
  `;
}

function renderServedDone() {
  const order = orders.find((item) => item.id === state.servedOrderId);
  return shell(`
    <main class="page">
      ${pageHeading("Màn hình sau khi nhấn trả món", "Hoàn tất trả món")}
      <section class="success-screen">
        <div>
          <div class="success-icon">✓</div>
          <h1>Đã trả món thành công</h1>
          <p class="muted">
            ${order ? `${order.id} tại ${order.table} đã được cập nhật trạng thái.` : "Order đã được cập nhật."}
          </p>
          <div class="toolbar" style="justify-content:center; margin-top:18px">
            <button class="primary-btn" data-view="serveOrders">Quay lại danh sách order</button>
            <button class="secondary-btn" data-view="waiter">Về màn hình phục vụ</button>
          </div>
        </div>
      </section>
    </main>
  `);
}

function renderBarista() {
  const groups = [
    { id: "waiting", title: "Chờ pha chế" },
    { id: "preparing", title: "Đang chế biến" },
    { id: "ready", title: "Đã xong chờ trả món" },
  ];

  return shell(`
    <main class="page">
      ${pageHeading("Màn hình chính Danh sách món chế biến của NV pha chế", "Bảng điều phối pha chế")}
      <section class="station-grid">
        ${groups.map((group) => renderStationColumn(group)).join("")}
      </section>
    </main>
  `);
}

function renderStationColumn(group) {
  const tickets = [];
  orders
    .filter((order) => order.status !== "paid")
    .forEach((order) => {
      order.items
        .filter((item) => item.status === group.id)
        .forEach((item) => tickets.push({ order, item, product: getProduct(item.productId) }));
    });

  return `
    <section class="station-column">
      <div class="panel-head">
        <div>
          <span class="eyebrow">${group.title}</span>
          <h2>${tickets.length} món</h2>
        </div>
      </div>
      <div class="list">
        ${
          tickets.length
            ? tickets
                .map(
                  ({ order, item, product }) => `
                    <article class="kitchen-ticket">
                      <div class="list-top">
                        <strong>${order.id} · ${order.table}</strong>
                        <span class="mini-badge ${item.status}">${statusLabel(item.status)}</span>
                      </div>
                      <div class="ticket-item">
                        <span class="ticket-qty">x${item.quantity}</span>
                        <div>
                          <strong>${product.name}</strong>
                          <div class="muted">Order lúc ${order.time} · PV ${order.waiter}</div>
                        </div>
                      </div>
                      ${
                        item.status === "waiting"
                          ? `<button class="primary-btn" data-start-item="${order.id}:${item.productId}">Bắt đầu làm</button>`
                          : item.status === "preparing"
                            ? `<button class="success-btn" data-finish-item="${order.id}:${item.productId}">Hoàn thành món</button>`
                            : `<button class="secondary-btn" disabled>Đang chờ phục vụ trả món</button>`
                      }
                    </article>
                  `,
                )
                .join("")
            : `<div class="empty-state">Không có món trong cột này.</div>`
        }
      </div>
    </section>
  `;
}

function renderOwner() {
  const revenue = orders.reduce((total, order) => total + orderTotal(order), 0);
  return shell(`
    <main class="page">
      ${pageHeading("Màn hình chính của chủ cửa hàng", "Tổng quan vận hành")}
      <section class="owner-grid">
        ${metricCard("Doanh thu hôm nay", money(revenue), "+12% so với hôm qua")}
        ${metricCard("Order đang mở", orders.filter((order) => order.status !== "paid").length, "Theo dõi thời gian thực")}
        ${metricCard("Món đang bán", products.length, "Bao gồm đồ uống và bánh")}
        ${metricCard("Món chờ trả", orders.reduce((sum, order) => sum + readyItemCount(order), 0), "Cần phục vụ xử lý")}
      </section>
      <section class="screen-grid">
        <div class="panel panel-pad">
          <div class="panel-head" style="padding:0 0 14px; border-bottom:0">
            <div>
              <span class="eyebrow">Thống kê</span>
              <h2>Doanh thu 7 ngày</h2>
            </div>
          </div>
          <div class="chart">
            ${[52, 66, 44, 81, 72, 93, 88]
              .map((height, index) => `<div class="bar"><span style="height:${height}%"></span><span>T${index + 2}</span></div>`)
              .join("")}
          </div>
        </div>
        <div class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Bán chạy</span>
              <h2>Top món hôm nay</h2>
            </div>
          </div>
          <div class="list">
            ${products
              .slice(0, 5)
              .map(
                (product, index) => `
                  <div class="list-item">
                    <div class="list-top">
                      <strong>${index + 1}. ${product.name}</strong>
                      <span class="price">${money(product.price)}</span>
                    </div>
                    <div class="muted">Đã bán ${34 - index * 4} phần · Còn ${product.stock} phần</div>
                  </div>
                `,
              )
              .join("")}
          </div>
        </div>
      </section>
    </main>
  `);
}

function metricCard(label, value, note) {
  return `
    <article class="metric-card">
      <div class="metric-top">
        <span class="eyebrow">${label}</span>
        <span class="mini-badge ready">Live</span>
      </div>
      <strong class="metric-value">${value}</strong>
      <span class="muted">${note}</span>
    </article>
  `;
}

function renderMenuAdmin() {
  return shell(`
    <main class="page">
      ${pageHeading("Quản lý thực đơn", "Danh sách món của cửa hàng", '<button class="primary-btn" data-open-add-dish>+ Thêm món mới</button>')}
      <section class="admin-layout">
        <div class="panel">
          <div class="panel-head">
            <label class="search-box">
              ${icons.search}
              <input data-search value="${state.search}" placeholder="Tìm món..." />
            </label>
          </div>
          ${productTabs()}
          ${renderProductGrid({ addMode: false })}
        </div>
        <aside class="panel">
          <div class="panel-head">
            <div>
              <span class="eyebrow">Tồn kho</span>
              <h2>Cảnh báo nhanh</h2>
            </div>
          </div>
          <div class="list">
            ${products
              .slice()
              .sort((a, b) => a.stock - b.stock)
              .slice(0, 6)
              .map(
                (product) => `
                  <div class="list-item">
                    <div class="list-top">
                      <strong>${product.name}</strong>
                      <span class="mini-badge ${product.stock < 20 ? "preparing" : "ready"}">${product.stock} phần</span>
                    </div>
                    <div class="muted">${money(product.price)} · ${categories.find((category) => category.id === product.category)?.label}</div>
                  </div>
                `,
              )
              .join("")}
          </div>
        </aside>
      </section>
    </main>
  `);
}

function renderAddDishView() {
  return shell(`
    <main class="page">
      ${pageHeading("Thêm món mới", "Cập nhật thực đơn")}
      <section class="panel panel-pad">
        ${dishForm()}
      </section>
    </main>
  `);
}

function dishForm() {
  return `
    <form data-add-dish-form>
      <div class="form-grid">
        <label class="field">
          <span>Tên món</span>
          <input name="name" required placeholder="Ví dụ: Latte hạt dẻ" />
        </label>
        <label class="field">
          <span>Giá bán</span>
          <input name="price" type="number" min="1000" step="1000" required placeholder="45000" />
        </label>
        <label class="field">
          <span>Danh mục</span>
          <select name="category">
            <option value="coffee">Cà phê</option>
            <option value="tea">Trà</option>
            <option value="cake">Bánh</option>
            <option value="juice">Nước ép</option>
          </select>
        </label>
        <label class="field">
          <span>Số lượng tồn</span>
          <input name="stock" type="number" min="0" value="20" />
        </label>
        <label class="field full">
          <span>Ảnh món</span>
          <input name="image" placeholder="URL ảnh, có thể bỏ trống để dùng ảnh mặc định" />
        </label>
        <label class="field full">
          <span>Mô tả</span>
          <textarea name="description" placeholder="Ghi chú công thức hoặc mô tả ngắn..."></textarea>
        </label>
      </div>
      <div class="toolbar" style="justify-content:flex-end; margin-top:14px">
        <button class="secondary-btn" type="reset">Xóa nhập liệu</button>
        <button class="primary-btn" type="submit">Lưu món mới</button>
      </div>
    </form>
  `;
}

function renderModal() {
  if (state.modal !== "addDish") return "";
  return `
    <div class="modal-backdrop" data-close-modal>
      <section class="modal" role="dialog" aria-modal="true" aria-label="Thêm món mới">
        <div class="modal-head">
          <div>
            <span class="eyebrow">Thêm món mới</span>
            <h2>Tạo món trong thực đơn</h2>
          </div>
          <button class="close-btn" data-close-modal aria-label="Đóng">×</button>
        </div>
        <div class="modal-body">${dishForm()}</div>
      </section>
    </div>
  `;
}

function render() {
  if (!state.user) {
    app.innerHTML = renderLogin();
    return;
  }

  const views = {
    cashier: renderCashier,
    waiter: renderWaiter,
    serveOrders: renderServeOrders,
    servedDone: renderServedDone,
    barista: renderBarista,
    owner: renderOwner,
    menuAdmin: renderMenuAdmin,
    addDish: renderAddDishView,
  };

  app.innerHTML = (views[state.view] || views[roles[state.user.role].defaultView] || renderCashier)();
}

function login(roleId) {
  const role = roles[roleId] || roles.cashier;
  state.user = {
    role: roleId,
    name:
      roleId === "owner"
        ? "Chủ cửa hàng"
        : roleId === "barista"
          ? "Pha chế"
          : roleId === "waiter"
            ? "Phục vụ"
            : "Thu ngân",
  };
  state.view = role.defaultView;
  showToast(`Đã đăng nhập: ${role.label}`);
  render();
}

function showToast(message) {
  state.toast = message;
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => {
    state.toast = "";
    render();
  }, 2200);
}

function addToCart(id) {
  state.cart.set(id, (state.cart.get(id) || 0) + 1);
  showToast(`Đã thêm ${getProduct(id).name}`);
  render();
}

function changeCart(id, delta) {
  const quantity = (state.cart.get(id) || 0) + delta;
  if (quantity <= 0) state.cart.delete(id);
  else state.cart.set(id, quantity);
  render();
}

function parseTicketKey(value) {
  const [orderId, productId] = value.split(":");
  return { order: orders.find((item) => item.id === orderId), productId: Number(productId) };
}

function handleAddDish(form) {
  const data = new FormData(form);
  const product = {
    id: Math.max(...products.map((item) => item.id)) + 1,
    name: String(data.get("name")).trim(),
    category: String(data.get("category")),
    price: Number(data.get("price")),
    stock: Number(data.get("stock")) || 0,
    image:
      String(data.get("image")).trim() ||
      "https://images.unsplash.com/photo-1442512595331-e89e73853f31?auto=format&fit=crop&w=600&q=80",
  };
  products.unshift(product);
  state.category = "all";
  state.modal = null;
  showToast(`Đã thêm món mới: ${product.name}`);
  render();
}

document.addEventListener("click", (event) => {
  const roleButton = event.target.closest("[data-role]");
  if (roleButton) {
    state.loginRole = roleButton.dataset.role;
    render();
    return;
  }

  const viewButton = event.target.closest("[data-view]");
  if (viewButton) {
    state.view = viewButton.dataset.view;
    render();
    return;
  }

  if (event.target.closest("[data-logout]")) {
    state.user = null;
    state.view = "login";
    render();
    return;
  }

  const category = event.target.closest("[data-category]");
  if (category) {
    state.category = category.dataset.category;
    render();
    return;
  }

  const addCart = event.target.closest("[data-add-cart]");
  if (addCart) {
    addToCart(Number(addCart.dataset.addCart));
    return;
  }

  const cartPlus = event.target.closest("[data-cart-plus]");
  if (cartPlus) {
    changeCart(Number(cartPlus.dataset.cartPlus), 1);
    return;
  }

  const cartMinus = event.target.closest("[data-cart-minus]");
  if (cartMinus) {
    changeCart(Number(cartMinus.dataset.cartMinus), -1);
    return;
  }

  if (event.target.closest("[data-clear-cart]")) {
    state.cart.clear();
    showToast("Đã làm trống hóa đơn");
    render();
    return;
  }

  if (event.target.closest("[data-checkout]")) {
    state.cart.clear();
    showToast("Thanh toán thành công");
    render();
    return;
  }

  if (event.target.closest("[data-open-add-dish]")) {
    state.modal = "addDish";
    render();
    return;
  }

  const closeModal = event.target.closest("[data-close-modal]");
  if (closeModal && (event.target === closeModal || event.target.closest(".close-btn"))) {
    state.modal = null;
    render();
    return;
  }

  const selectServe = event.target.closest("[data-select-serve]");
  if (selectServe) {
    state.selectedServeOrderId = selectServe.dataset.selectServe;
    state.view = "serveOrders";
    render();
    return;
  }

  const serveOrder = event.target.closest("[data-serve-order]");
  if (serveOrder) {
    const order = orders.find((item) => item.id === serveOrder.dataset.serveOrder);
    if (order) {
      order.items.forEach((item) => {
        if (item.status === "ready") item.status = "served";
      });
      updateOrderStatus(order);
      state.servedOrderId = order.id;
      state.view = "servedDone";
      showToast(`Đã trả món cho ${order.table}`);
      render();
    }
    return;
  }

  const startItem = event.target.closest("[data-start-item]");
  if (startItem) {
    const { order, productId } = parseTicketKey(startItem.dataset.startItem);
    const item = order?.items.find((line) => line.productId === productId);
    if (item) item.status = "preparing";
    if (order) updateOrderStatus(order);
    showToast("Đã chuyển món sang đang chế biến");
    render();
    return;
  }

  const finishItem = event.target.closest("[data-finish-item]");
  if (finishItem) {
    const { order, productId } = parseTicketKey(finishItem.dataset.finishItem);
    const item = order?.items.find((line) => line.productId === productId);
    if (item) item.status = "ready";
    if (order) updateOrderStatus(order);
    showToast("Món đã xong, chờ phục vụ trả món");
    render();
    return;
  }

  if (event.target.closest("[data-new-waiter-order]")) {
    showToast("Chức năng tạo order nhanh đã sẵn sàng trong prototype");
  }
});

document.addEventListener("submit", (event) => {
  const loginForm = event.target.closest("[data-login-form]");
  if (loginForm) {
    event.preventDefault();
    login(state.loginRole);
    return;
  }

  const dishFormEl = event.target.closest("[data-add-dish-form]");
  if (dishFormEl) {
    event.preventDefault();
    handleAddDish(dishFormEl);
  }
});

document.addEventListener("input", (event) => {
  const search = event.target.closest("[data-search]");
  if (!search) return;
  const cursor = search.selectionStart ?? search.value.length;
  state.search = search.value;
  render();
  const nextSearch = document.querySelector("[data-search]");
  if (nextSearch) {
    const nextCursor = Math.min(cursor, nextSearch.value.length);
    nextSearch.focus();
    nextSearch.setSelectionRange(nextCursor, nextCursor);
  }
});

function initFromQuery() {
  const params = new URLSearchParams(window.location.search);
  const roleId = params.get("role");
  if (!roleId || !roles[roleId]) return;

  const role = roles[roleId];
  state.user = {
    role: roleId,
    name:
      roleId === "owner"
        ? "Chủ cửa hàng"
        : roleId === "barista"
          ? "Pha chế"
          : roleId === "waiter"
            ? "Phục vụ"
            : "Thu ngân",
  };
  state.view = params.get("view") || role.defaultView;
}

initFromQuery();
render();
