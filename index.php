<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/TelegramBot.php';
require_once __DIR__ . '/src/GeminiAI.php';
require_once __DIR__ . '/src/GoogleSheets.php';
require_once __DIR__ . '/src/BillHandler.php';

// Chỉ xử lý POST request từ Telegram
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    if ($update) {
        $handler = new BillHandler();
        $handler->handle($update);
    }
}

// GET request - kiểm tra server còn sống
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'ok', 'message' => 'Telegram Bill Bot đang chạy!']);
}
