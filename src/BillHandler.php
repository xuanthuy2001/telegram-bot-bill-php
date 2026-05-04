<?php

class BillHandler
{
    private TelegramBot $bot;
    private GeminiAI    $gemini;
    private GoogleSheets $sheets;

    public function __construct()
    {
        $this->bot    = new TelegramBot();
        $this->gemini = new GeminiAI();
        $this->sheets = new GoogleSheets();
    }

    public function handle(array $update): void
    {
        $message = $update['message'] ?? $update['channel_post'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];

        // Ảnh (photo)
        if (!empty($message['photo'])) {
            $this->bot->sendMessage($chatId, '⏳ Đang phân tích ảnh bill, vui lòng chờ...');
            $imageData = $this->bot->downloadPhoto($message['photo']);
            $this->processImage($chatId, $imageData, 'image/jpeg');
            return;
        }

        // File ảnh (document)
        if (!empty($message['document'])) {
            $mime = $message['document']['mime_type'] ?? '';
            if (str_starts_with($mime, 'image/')) {
                $this->bot->sendMessage($chatId, '⏳ Đang phân tích ảnh bill, vui lòng chờ...');
                $imageData = $this->bot->downloadDocument($message['document']);
                $this->processImage($chatId, $imageData, $mime);
                return;
            }
        }

        // Text / commands
        if (!empty($message['text'])) {
            $this->handleText($chatId, trim($message['text']));
        }
    }

    // ===================== XỬ LÝ ẢNH =====================

    private function processImage(int|string $chatId, ?string $imageData, string $mime): void
    {
        if (!$imageData) {
            $this->bot->sendMessage($chatId, '❌ Không thể tải ảnh. Vui lòng thử lại.');
            return;
        }

        $data = $this->gemini->analyzeBill($imageData, $mime);

        if (!$data) {
            $this->bot->sendMessage($chatId, '❌ Gemini AI không phân tích được. Vui lòng gửi ảnh rõ hơn.');
            return;
        }

        $this->saveAndReply($chatId, $data);
    }

    // ===================== LƯU VÀO SHEETS =====================

    private function saveAndReply(int|string $chatId, array $data): void
    {
        $loai = $data['loai'] ?? 'khong_xac_dinh';

        if ($loai === 'chuyen_khoan') {
            $ok = $this->sheets->appendChuyenKhoan($data);

            if ($ok) {
                $reply = "✅ *Đã lưu Bill Chuyển Khoản!*\n\n"
                    . "👤 Người gửi: " . ($data['nguoi_gui']   ?: 'Không rõ') . "\n"
                    . "👤 Người nhận: " . ($data['nguoi_nhan'] ?: 'Không rõ') . "\n"
                    . "📅 Ngày: "       . ($data['ngay_chuyen'] ?: 'Không rõ') . "\n"
                    . "📝 Nội dung: "   . ($data['noi_dung']   ?: 'Không rõ') . "\n"
                    . "💰 Số tiền: *"   . number_format((int)($data['so_tien'] ?? 0), 0, ',', '.') . " VNĐ*\n\n"
                    . "📊 Gõ /sheet để xem Google Sheets";
            } else {
                $reply = '❌ Phân tích OK nhưng lỗi khi lưu vào Google Sheets. Kiểm tra logs.';
            }

        } elseif ($loai === 'tinh_tien') {
            $ok      = $this->sheets->appendTinhTien($data);
            $tongTien = 0;
            $lines    = [];

            foreach (($data['items'] ?? []) as $item) {
                $tt       = (int)($item['thanh_tien'] ?? ($item['so_luong'] * $item['don_gia']) ?? 0);
                $tongTien += $tt;
                $lines[]  = "• " . ($item['ten_san_pham'] ?: '?') . " — *" . number_format($tt, 0, ',', '.') . " VNĐ*";
            }

            if ($ok) {
                $reply = "✅ *Đã lưu Bill Tính Tiền!*\n\n"
                    . "📅 Ngày mua: " . ($data['ngay_mua'] ?: 'Không rõ') . "\n"
                    . "🛒 Sản phẩm:\n" . implode("\n", $lines) . "\n\n"
                    . "💰 Tổng: *" . number_format($tongTien, 0, ',', '.') . " VNĐ*\n\n"
                    . "📊 Gõ /sheet để xem Google Sheets";
            } else {
                $reply = '❌ Phân tích OK nhưng lỗi khi lưu vào Google Sheets. Kiểm tra logs.';
            }

        } else {
            $reply = "❌ Không nhận diện được loại bill.\n\n" . ($data['ly_do'] ?? 'Vui lòng gửi ảnh rõ hơn.');
        }

        $this->bot->sendMessage($chatId, $reply);
    }

    // ===================== XỬ LÝ TEXT =====================

    private function handleText(int|string $chatId, string $text): void
    {
        switch ($text) {
            case '/start':
                $this->bot->sendMessage($chatId, $this->getWelcomeMessage());
                break;

            case '/sheet':
                $ssId  = SPREADSHEET_ID;
                $reply = "📊 Link Google Sheet của bạn:\nhttps://docs.google.com/spreadsheets/d/{$ssId}";
                $this->bot->sendMessage($chatId, $reply);
                break;

            default:
                $this->bot->sendMessage($chatId, "📷 Vui lòng gửi ảnh bill chuyển khoản hoặc bill tính tiền.\n\nGõ /start để xem hướng dẫn.");
        }
    }

    private function getWelcomeMessage(): string
    {
        return "🤖 *Xin chào! Tôi là Bot Quản Lý Bill*\n\n"
            . "📷 Gửi ảnh bill để tôi tự động lưu vào Google Sheets:\n\n"
            . "💳 *Bill Chuyển Khoản* — tôi trích xuất:\n"
            . "  • Người gửi / Người nhận\n"
            . "  • Ngày chuyển, Nội dung, Số tiền\n\n"
            . "🧾 *Bill Tính Tiền / Hóa Đơn* — tôi trích xuất:\n"
            . "  • Tên sản phẩm, Số lượng\n"
            . "  • Đơn giá, Thành tiền, Ngày mua\n\n"
            . "📊 Gõ /sheet để xem link Google Sheets";
    }
}
