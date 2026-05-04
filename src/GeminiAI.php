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
     * Trả về array với key 'error' nếu có lỗi từ Gemini
     */
    public function analyzeBill(string $imageData, string $mimeType = 'image/jpeg'): ?array
    {
        $base64 = base64_encode($imageData);

        // Normalize mime type — Gemini chỉ chấp nhận image/jpeg, image/png, image/webp, image/heic
        $mimeType = $this->normalizeMime($mimeType);

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
                'maxOutputTokens' => 2048,
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

        if (!$res) {
            error_log("Gemini curl error: no response");
            return ['error' => 'Không kết nối được Gemini AI. Thử lại sau.'];
        }

        $resJson = json_decode($res, true);

        if ($code !== 200 || isset($resJson['error'])) {
            $errMsg = $resJson['error']['message'] ?? "HTTP {$code}";
            error_log("Gemini API error: {$errMsg} - " . $res);
            return ['error' => "Gemini AI lỗi: {$errMsg}"];
        }

        // Kiểm tra finish reason
        $finishReason = $resJson['candidates'][0]['finishReason'] ?? '';
        if ($finishReason === 'SAFETY') {
            return ['error' => 'Ảnh bị Gemini từ chối vì lý do an toàn. Thử ảnh khác.'];
        }

        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $rawText = trim($rawText);

        // Xóa markdown code block nếu có
        $rawText = preg_replace('/```json\s*/m', '', $rawText);
        $rawText = preg_replace('/```\s*/m', '', $rawText);
        $rawText = trim($rawText);

        $data = json_decode($rawText, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON parse error: " . $rawText);
            return ['error' => 'Gemini trả về dữ liệu không hợp lệ. Thử lại.'];
        }

        return $data;
    }

    private function normalizeMime(string $mime): string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        if (in_array($mime, $allowed)) return $mime;
        return 'image/jpeg'; // fallback
    }

    private function getPrompt(): string
    {
        $p  = "You are an expert OCR for financial documents. Analyze the image.

";
        $p .= "Bill may be in ANY language (Vietnamese, Korean, English, etc.).

";
        $p .= "CASE 1 - BANK TRANSFER - return ONLY this JSON:
";
        $p .= '{"loai":"chuyen_khoan","nguoi_gui":"","nguoi_nhan":"","ngay_chuyen":"","noi_dung":"","so_tien":0}' . "

";
        $p .= "CASE 2 - SHOPPING RECEIPT - return ONLY this JSON:
";
        $p .= '{"loai":"tinh_tien","ngay_mua":"dd/mm/yyyy","items":[{"ten_san_pham":"name","so_luong":1,"don_gia":0,"thanh_tien":0}]}' . "

";
        $p .= "CASE 3 - Cannot determine:
";
        $p .= '{"loai":"khong_xac_dinh","ly_do":"reason"}' . "

";
        $p .= "STRICT RULES:
";
        $p .= "- Output ONLY raw JSON. No markdown, no text before or after
";
        $p .= "- All monetary values must be plain integers (no commas, no symbols)
";
        $p .= "- For Korean receipts: translate product names to Vietnamese
";
        $p .= "- Unknown fields: use empty string or 0";
        return $p;
    }
}