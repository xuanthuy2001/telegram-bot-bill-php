<?php
// ============================================================
//  CẤU HÌNH - điền các giá trị vào Railway Environment Variables
// ============================================================

define('TELEGRAM_TOKEN',    getenv('TELEGRAM_TOKEN')    ?: 'YOUR_TELEGRAM_TOKEN');
define('GEMINI_API_KEY',    getenv('GEMINI_API_KEY')    ?: 'YOUR_GEMINI_API_KEY');
define('SPREADSHEET_ID',    getenv('SPREADSHEET_ID')    ?: 'YOUR_SPREADSHEET_ID');

// Google Service Account credentials (JSON string)
define('GOOGLE_CREDENTIALS', getenv('GOOGLE_CREDENTIALS') ?: '');

// Tên 2 sheet trong Google Sheets
define('SHEET_CHUYEN_KHOAN', 'Bill Chuyển Khoản');
define('SHEET_TINH_TIEN',    'Bill Tính Tiền');

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');
