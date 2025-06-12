<?php
// データベース接続情報
define('DB_HOST', 'localhost');
define('DB_NAME', 'corporate_site');
define('DB_USER', 'root');
define('DB_PASS', '');

// 管理者メールアドレス
define('ADMIN_EMAIL', 'admin@example.com');

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// エラー表示設定（本番環境では0に設定）
ini_set('display_errors', 1);
error_reporting(E_ALL);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 文字エンコーディング設定
mb_internal_encoding('UTF-8'); 