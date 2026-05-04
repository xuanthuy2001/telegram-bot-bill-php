<?php
/**
 * Chạy file này 1 lần để đăng ký webhook với Telegram
 * Truy cập: https://your-railway-url/setup.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/TelegramBot.php';

// Tự detect URL của server hiện tại
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$webhookUrl = "{$protocol}://{$host}/index.php";

$result = TelegramBot::setWebhook(TELEGRAM_TOKEN, $webhookUrl);

header('Content-Type: application/json');
echo json_encode([
    'webhook_url' => $webhookUrl,
    'telegram_response' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
