from pathlib import Path
from xml.sax.saxutils import escape
from PIL import Image, ImageDraw, ImageFont


OUT_DIR = Path(__file__).resolve().parent
PNG = OUT_DIR / "Cafe_Connect_State_Machine_Diagram.png"
SVG = OUT_DIR / "Cafe_Connect_State_Machine_Diagram.svg"

FONT = "C:/Windows/Fonts/arial.ttf"
BOLD = "C:/Windows/Fonts/arialbd.ttf"
font_title = ImageFont.truetype(BOLD, 34)
font_group = ImageFont.truetype(BOLD, 25)
font_state = ImageFont.truetype(BOLD, 21)
font_small = ImageFont.truetype(FONT, 17)

W, H = 1900, 2300
BG = "#f7f1ea"
BROWN = "#2f1b14"
LINE = "#6f4a32"
CREAM = "#fffaf3"
GREEN = "#dcefe4"
ORANGE = "#ffe4c2"
RED = "#ffe0da"
BLUE = "#dce9f7"


groups = [
    {
        "title": "Voucher Lifecycle (vouchers.status)",
        "y": 130,
        "states": [
            ("Issued", "Đã phát hành", 90, ORANGE),
            ("Active", "Có thể sử dụng", 410, GREEN),
            ("Reserved", "Giữ khi checkout", 730, BLUE),
            ("Redeemed", "Đã dùng", 1050, GREEN),
            ("Expired", "Hết hạn", 1370, RED),
            ("Cancelled", "Bị hủy", 1590, RED),
        ],
        "arrows": [(0, 1, "release_date"), (1, 2, "select"), (2, 3, "paid / redeem"), (1, 4, "expired"), (1, 5, "cancel")],
    },
    {
        "title": "Service Order & Kitchen Lifecycle",
        "y": 520,
        "states": [
            ("Draft", "Order mới", 90, CREAM),
            ("Preparing", "Bếp xử lý", 410, ORANGE),
            ("Ready", "Món sẵn sàng", 730, BLUE),
            ("Served", "Đã phục vụ", 1050, GREEN),
            ("Paid", "Đã thanh toán", 1370, GREEN),
            ("Cancelled", "Đã hủy", 1590, RED),
        ],
        "arrows": [(0, 1, "waiter creates order"), (1, 2, "ready_at"), (2, 3, "served_at"), (3, 4, "checkout"), (1, 5, "cancel")],
    },
    {
        "title": "Invoice / Bill Lifecycle (bill_started_at + paid_at)",
        "y": 910,
        "states": [
            ("Cart Started", "Bắt đầu bill", 90, CREAM),
            ("Pending Payment", "Chờ thanh toán", 410, ORANGE),
            ("Paid", "paid_at", 730, GREEN),
            ("Cancelled", "Hủy checkout", 1050, RED),
            ("Refunded", "Hoàn tiền", 1370, RED),
        ],
        "arrows": [(0, 1, "submit checkout"), (1, 2, "payment success"), (1, 3, "failed/cancel"), (2, 4, "refund")],
    },
    {
        "title": "POS Session Lifecycle (pos_sessions.status + closed_reason)",
        "y": 1300,
        "states": [
            ("Login Selected", "Chọn staff/role", 90, CREAM),
            ("Open", "session_token", 410, GREEN),
            ("Active", "heartbeat 60s", 730, BLUE),
            ("Closed Manual", "logout", 1050, GREEN),
            ("Closed Timeout", ">30 phút", 1370, RED),
            ("Closed System", "login mới", 1590, RED),
        ],
        "arrows": [(0, 1, "pos-session-login"), (1, 2, "heartbeat"), (2, 3, "logout"), (2, 4, "no heartbeat"), (1, 5, "same staff relogin")],
    },
    {
        "title": "Customer Membership Lifecycle (customers.status + membership_tiers)",
        "y": 1690,
        "states": [
            ("Guest", "Chưa có hồ sơ", 90, CREAM),
            ("Bronze", "Hạng Đồng", 410, GREEN),
            ("Silver", "Hạng Bạc", 730, GREEN),
            ("Gold", "Hạng Vàng", 1050, GREEN),
            ("Inactive", "Không quay lại", 1370, ORANGE),
            ("Blocked", "Bị khóa", 1590, RED),
        ],
        "arrows": [(0, 1, "register/create"), (1, 2, "spending threshold"), (2, 3, "spending threshold"), (3, 4, "inactive period"), (4, 1, "return"), (3, 5, "admin blocks")],
    },
]


def state_box(draw, x, y, title, subtitle, fill):
    draw.rounded_rectangle((x, y, x + 230, y + 105), radius=18, fill=fill, outline=LINE, width=3)
    draw.text((x + 115, y + 22), title, fill=BROWN, font=font_state, anchor="mm")
    draw.text((x + 115, y + 66), subtitle, fill="#5b4638", font=font_small, anchor="mm")


def arrow(draw, x1, y1, x2, y2, label):
    import math
    draw.line((x1, y1, x2, y2), fill=LINE, width=3)
    ang = math.atan2(y2 - y1, x2 - x1)
    size = 13
    pts = [
        (x2, y2),
        (x2 - size * math.cos(ang - 0.45), y2 - size * math.sin(ang - 0.45)),
        (x2 - size * math.cos(ang + 0.45), y2 - size * math.sin(ang + 0.45)),
    ]
    draw.polygon(pts, fill=LINE)
    mx, my = (x1 + x2) / 2, (y1 + y2) / 2 - 18
    draw.rounded_rectangle((mx - 95, my - 14, mx + 95, my + 16), radius=8, fill=BG, outline="#c9b8aa")
    draw.text((mx, my), label, fill=BROWN, font=font_small, anchor="mm")


def svg_text(x, y, text, size=18, weight="normal", anchor="middle", color=BROWN):
    return f'<text x="{x}" y="{y}" font-family="Arial" font-size="{size}" font-weight="{weight}" text-anchor="{anchor}" fill="{color}">{escape(text)}</text>'


def render():
    img = Image.new("RGB", (W, H), BG)
    draw = ImageDraw.Draw(img)
    draw.text((W / 2, 45), "Cafe Connect CRM + POS + Website", fill=BROWN, font=font_title, anchor="mm")
    draw.text((W / 2, 88), "State Machine Diagram based on current PHP MVC / MySQL project", fill="#6f4a32", font=font_small, anchor="mm")

    svg = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
        f'<rect width="{W}" height="{H}" fill="{BG}"/>',
        svg_text(W / 2, 50, "Cafe Connect CRM + POS + Website", 34, "bold"),
        svg_text(W / 2, 90, "State Machine Diagram based on current PHP MVC / MySQL project", 18),
    ]

    for group in groups:
        y = group["y"]
        draw.rounded_rectangle((45, y - 60, W - 45, y + 240), radius=24, fill="#ffffff", outline="#d6c7b9", width=2)
        draw.text((75, y - 28), group["title"], fill=BROWN, font=font_group)
        svg.append(f'<rect x="45" y="{y-60}" width="{W-90}" height="300" rx="24" fill="#ffffff" stroke="#d6c7b9" stroke-width="2"/>')
        svg.append(svg_text(75, y - 23, group["title"], 25, "bold", "start"))
        coords = []
        for title, subtitle, x, fill in group["states"]:
            state_box(draw, x, y + 40, title, subtitle, fill)
            coords.append((x, y + 40))
            svg.append(f'<rect x="{x}" y="{y+40}" width="230" height="105" rx="18" fill="{fill}" stroke="{LINE}" stroke-width="3"/>')
            svg.append(svg_text(x + 115, y + 72, title, 21, "bold"))
            svg.append(svg_text(x + 115, y + 116, subtitle, 17, "normal", "middle", "#5b4638"))
        for src, dst, label in group["arrows"]:
            x1, y1 = coords[src][0] + 230, coords[src][1] + 52
            x2, y2 = coords[dst][0], coords[dst][1] + 52
            if dst < src:
                y1 += 62
                y2 += 62
            arrow(draw, x1, y1, x2, y2, label)
            svg.append(f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{LINE}" stroke-width="3" marker-end="url(#arrow)"/>')
            svg.append(svg_text((x1+x2)/2, (y1+y2)/2 - 16, label, 16))

    draw.rounded_rectangle((60, 2070, W - 60, 2220), radius=22, fill="#fffaf3", outline=LINE, width=2)
    notes = [
        "Improvement included: invoice/bill lifecycle added because current project stores bill_started_at and paid_at.",
        "POS write APIs require staff_id + pos_session_id + session_token; actions are logged to pos_activity_logs.",
        "Lifecycle states are grounded in current schema: vouchers, service_orders, service_order_items, invoices, payments, pos_sessions, customers.",
    ]
    draw.text((90, 2105), "Notes", fill=BROWN, font=font_group)
    for i, note in enumerate(notes):
        draw.text((95, 2140 + i * 24), f"- {note}", fill="#5b4638", font=font_small)
    svg.append(f'<rect x="60" y="2070" width="{W-120}" height="150" rx="22" fill="#fffaf3" stroke="{LINE}" stroke-width="2"/>')
    svg.append(svg_text(90, 2110, "Notes", 25, "bold", "start"))
    for i, note in enumerate(notes):
        svg.append(svg_text(95, 2145 + i * 24, "- " + note, 17, "normal", "start", "#5b4638"))

    svg.insert(1, '<defs><marker id="arrow" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#6f4a32"/></marker></defs>')
    svg.append("</svg>")

    img.save(PNG)
    SVG.write_text("\n".join(svg), encoding="utf-8")
    print(PNG)
    print(SVG)


if __name__ == "__main__":
    render()
