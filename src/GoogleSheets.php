<?php

class GoogleSheets
{
    private string $spreadsheetId;
    private string $accessToken;

    public function __construct()
    {
        $this->spreadsheetId = SPREADSHEET_ID;
        $this->accessToken   = $this->getAccessToken();
    }

    // ===================== APPEND ROW =====================

    public function appendChuyenKhoan(array $data): bool
    {
        $now    = date('d/m/Y H:i:s');
        $rowNum = $this->getLastRow(SHEET_CHUYEN_KHOAN);

        $values = [[
            $rowNum,                          // STT
            $now,                             // Ngày giờ lưu
            $data['nguoi_gui']   ?? '',       // Người gửi
            $data['nguoi_nhan']  ?? '',       // Người nhận
            $data['ngay_chuyen'] ?? '',       // Ngày chuyển
            $data['noi_dung']    ?? '',       // Nội dung
            (int)($data['so_tien'] ?? 0),     // Số tiền
            'Telegram Bot',                   // Nguồn
        ]];

        return $this->append(SHEET_CHUYEN_KHOAN, $values);
    }

    public function appendTinhTien(array $data): bool
    {
        $now    = date('d/m/Y H:i:s');
        $ok     = true;

        foreach (($data['items'] ?? []) as $item) {
            $rowNum    = $this->getLastRow(SHEET_TINH_TIEN);
            $thanhTien = (int)($item['thanh_tien'] ?? ($item['so_luong'] * $item['don_gia']) ?? 0);

            $values = [[
                $rowNum,
                $now,
                $item['ten_san_pham'] ?? '',
                (int)($item['so_luong'] ?? 1),
                (int)($item['don_gia']  ?? 0),
                $thanhTien,
                $data['ngay_mua'] ?? '',
                'Telegram Bot',
            ]];

            if (!$this->append(SHEET_TINH_TIEN, $values)) {
                $ok = false;
            }
        }

        return $ok;
    }

    // ===================== INTERNAL =====================

    private function append(string $sheetName, array $values): bool
    {
        $range   = urlencode("{$sheetName}!A:H");
        $url     = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

        $payload = json_encode(['values' => $values]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            error_log("Sheets append error: HTTP {$code} - {$res}");
            return false;
        }

        return true;
    }

    private function getLastRow(string $sheetName): int
    {
        $range = urlencode("{$sheetName}!A:A");
        $url   = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->accessToken}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        return count($data['values'] ?? []);  // số hàng hiện có (kể cả header)
    }

    // ===================== AUTH =====================

    /**
     * Lấy OAuth2 Access Token từ Service Account
     */
    private function getAccessToken(): string
    {
        $credJson = GOOGLE_CREDENTIALS;
        if (!$credJson) {
            error_log("GOOGLE_CREDENTIALS chưa được cấu hình!");
            return '';
        }

        $cred = json_decode($credJson, true);
        if (!$cred) {
            error_log("GOOGLE_CREDENTIALS không phải JSON hợp lệ!");
            return '';
        }

        $now    = time();
        $claims = [
            'iss'   => $cred['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = $this->createJWT($claims, $cred['private_key']);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $token = json_decode($res, true);
        return $token['access_token'] ?? '';
    }

    private function createJWT(array $claims, string $privateKey): string
    {
        $header  = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64url_encode(json_encode($claims));
        $data    = "{$header}.{$payload}";

        openssl_sign($data, $signature, $privateKey, 'SHA256');

        return "{$data}." . base64url_encode($signature);
    }
}

// Helper: base64url encode
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
