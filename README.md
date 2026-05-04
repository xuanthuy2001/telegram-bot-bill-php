# 🤖 Telegram Bill Bot — PHP + Railway

## Cấu trúc project
```
telegram-bot/
├── index.php          # Webhook handler chính
├── setup.php          # Đăng ký webhook (chạy 1 lần)
├── nixpacks.toml      # Cấu hình Railway
├── config/
│   └── config.php     # Cấu hình (đọc từ env vars)
└── src/
    ├── TelegramBot.php  # Gửi/nhận Telegram
    ├── GeminiAI.php     # Phân tích ảnh
    ├── GoogleSheets.php # Lưu vào Sheets
    └── BillHandler.php  # Điều phối xử lý
```

---

## 🚀 Hướng dẫn deploy lên Railway

### Bước 1 — Chuẩn bị Google Service Account

1. Vào [console.cloud.google.com](https://console.cloud.google.com)
2. Tạo project mới (hoặc dùng project cũ)
3. Bật **Google Sheets API**: APIs & Services → Enable APIs → tìm "Google Sheets API" → Enable
4. Tạo Service Account: APIs & Services → Credentials → Create Credentials → Service Account
5. Đặt tên → Create → Done
6. Click vào Service Account vừa tạo → Keys → Add Key → JSON → Download file JSON

### Bước 2 — Chia sẻ Google Sheet với Service Account

1. Mở Google Sheet của bạn (tạo mới hoặc dùng sheet cũ)
2. Tạo 2 sheet tab: **"Bill Chuyển Khoản"** và **"Bill Tính Tiền"**
3. Thêm header row cho mỗi sheet:
   - Bill Chuyển Khoản: `STT | Ngày Giờ Lưu | Người Gửi | Người Nhận | Ngày Chuyển | Nội Dung | Số Tiền | Nguồn`
   - Bill Tính Tiền: `STT | Ngày Giờ Lưu | Tên Sản Phẩm | Số Lượng | Đơn Giá | Thành Tiền | Ngày Mua | Nguồn`
4. Nhấn Share → dán email của Service Account (dạng `xxx@xxx.iam.gserviceaccount.com`) → Editor → Send
5. Copy **Spreadsheet ID** từ URL: `https://docs.google.com/spreadsheets/d/**SPREADSHEET_ID**/edit`

### Bước 3 — Deploy lên Railway

1. Tạo repo GitHub, push toàn bộ code lên
2. Vào [railway.app](https://railway.app) → New Project → Deploy from GitHub
3. Chọn repo → Deploy

### Bước 4 — Cấu hình Environment Variables trên Railway

Vào project → Variables → thêm các biến sau:

| Variable | Giá trị |
|---|---|
| `TELEGRAM_TOKEN` | Token từ @BotFather |
| `GEMINI_API_KEY` | API key từ Google AI Studio |
| `SPREADSHEET_ID` | ID của Google Sheet |
| `GOOGLE_CREDENTIALS` | Toàn bộ nội dung file JSON Service Account (1 dòng) |

> ⚠️ `GOOGLE_CREDENTIALS`: mở file JSON → Copy All → Paste vào Railway (giữ nguyên format JSON)

### Bước 5 — Đăng ký Webhook

Sau khi deploy xong, Railway sẽ cấp cho bạn 1 URL dạng `https://xxx.railway.app`

Truy cập trình duyệt:
```
https://xxx.railway.app/setup.php
```

Sẽ thấy kết quả:
```json
{
  "webhook_url": "https://xxx.railway.app/index.php",
  "telegram_response": { "ok": true, "result": true }
}
```

### Bước 6 — Test

Mở Telegram → gửi `/start` → bot phản hồi → gửi ảnh bill! 🎉

---

## 🔧 Debug

- Xem logs: Railway → project → Deployments → View Logs
- Test server: truy cập `https://xxx.railway.app/` → phải thấy `{"status":"ok"}`
