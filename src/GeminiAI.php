<?php

class GeminiAI
{
    private string $apiKey;
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
    }

    /**
     * Phân tích ảnh bill → trả về array đã parse JSON
     */
    public function analyzeBill(string $imageData, string $mimeType = 'image/jpeg'): ?array
    {
        $base64 = base64_encode($imageData);
        $prompt = $this->getPrompt();

        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => $base64,
                        ]
                    ]
                ]
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 1024,
            ]
        ];

        $url = "{$this->endpoint}?key={$this->apiKey}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$res || $code !== 200) {
            error_log("Gemini API error: HTTP {$code} - {$res}");
            return null;
        }

        $resJson = json_decode($res, true);

        if (isset($resJson['error'])) {
            error_log("Gemini error: " . json_encode($resJson['error']));
            return null;
        }

        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $rawText = trim($rawText);

        // Xóa markdown code block nếu có
        $rawText = preg_replace('/^```json\s*/m', '', $rawText);
        $rawText = preg_replace('/^```\s*/m', '', $rawText);
        $rawText = trim($rawText);

        $data = json_decode($rawText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON parse error: " . $rawText);
            return null;
        }

        return $data;
    }

    private function getPrompt(): string
    {
        return <<<PROMPT
Bạn là chuyên gia OCR phân tích bill/hóa đơn tài chính.
Hãy phân tích ảnh này và xác định đây là loại bill gì, sau đó trích xuất thông tin.

Nếu là BILL CHUYỂN KHOẢN (bank transfer), trả về JSON:
{
  "loai": "chuyen_khoan",
  "nguoi_gui": "tên người/tài khoản gửi",
  "nguoi_nhan": "tên người/tài khoản nhận",
  "ngay_chuyen": "dd/mm/yyyy hoặc dd/mm/yyyy hh:mm",
  "noi_dung": "nội dung/mô tả chuyển khoản",
  "so_tien": 0
}

Nếu là BILL TÍNH TIỀN / HÓA ĐƠN MUA HÀNG (invoice/receipt), trả về JSON:
{
  "loai": "tinh_tien",
  "ngay_mua": "dd/mm/yyyy",
  "items": [
    {
      "ten_san_pham": "tên sản phẩm hoặc dịch vụ",
      "so_luong": 1,
      "don_gia": 0,
      "thanh_tien": 0
    }
  ]
}

Nếu không thể xác định: { "loai": "khong_xac_dinh", "ly_do": "lý do" }

QUAN TRỌNG:
- Chỉ trả về JSON thuần túy, không có markdown, không có giải thích
- so_tien, don_gia, thanh_tien là số nguyên (VNĐ), không có dấu phẩy
- Nếu không rõ thông tin nào thì để chuỗi rỗng "" hoặc 0
PROMPT;
    }
}
