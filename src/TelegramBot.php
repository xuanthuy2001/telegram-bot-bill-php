<?php

class TelegramBot
{
    private string $token;
    private string $apiBase;

    public function __construct()
    {
        $this->token   = TELEGRAM_TOKEN;
        $this->apiBase = "https://api.telegram.org/bot{$this->token}";
    }

    // Gửi tin nhắn text
    public function sendMessage(int|string $chatId, string $text, string $parseMode = 'Markdown'): void
    {
        $this->call('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ]);
    }

    // Tải file ảnh từ Telegram → trả về binary string
    public function downloadPhoto(array $photoArray): ?string
    {
        // Lấy ảnh chất lượng cao nhất (phần tử cuối)
        $fileId   = end($photoArray)['file_id'];
        return $this->downloadFile($fileId);
    }

    public function downloadDocument(array $document): ?string
    {
        return $this->downloadFile($document['file_id']);
    }

    private function downloadFile(string $fileId): ?string
    {
        $res = $this->call('getFile', ['file_id' => $fileId]);
        if (!$res || !$res['ok']) return null;

        $filePath = $res['result']['file_path'];
        $fileUrl  = "https://api.telegram.org/file/bot{$this->token}/{$filePath}";

        $ch = curl_init($fileUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data ?: null;
    }

    private function call(string $method, array $params = []): ?array
    {
        $url = "{$this->apiBase}/{$method}";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? json_decode($res, true) : null;
    }

    // Đăng ký webhook (chạy 1 lần)
    public static function setWebhook(string $token, string $url): array
    {
        $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['url' => $url]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true) ?? [];
    }
}
