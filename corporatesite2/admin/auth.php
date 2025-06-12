<?php
session_start();

// 認証チェック
function requireAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // セッションの有効期限チェック（2時間）
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }

    // 最終アクティビティ時間の更新
    $_SESSION['last_activity'] = time();

    // CSRFトークンの生成（存在しない場合）
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// CSRFトークンの検証
function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('CSRFトークンが無効です。');
    }
}

// 管理者情報の取得
function getAdminInfo() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $stmt = $pdo->prepare("
            SELECT id, username, email, last_login
            FROM admins
            WHERE id = :id AND status = 'active'
        ");
        
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        return $stmt->fetch();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null;
    }
}

// ログイン履歴の取得
function getLoginHistory($limit = 10) {
    if (!isset($_SESSION['admin_id'])) {
        return [];
    }

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $stmt = $pdo->prepare("
            SELECT ip_address, user_agent, created_at
            FROM admin_login_history
            WHERE admin_id = :admin_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':admin_id', $_SESSION['admin_id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

// セキュリティヘッダーの設定
function setSecurityHeaders() {
    // XSS対策
    header('X-XSS-Protection: 1; mode=block');
    
    // クリックジャッキング対策
    header('X-Frame-Options: DENY');
    
    // MIMEタイプスニッフィング対策
    header('X-Content-Type-Options: nosniff');
    
    // HSTS設定
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;");
}

// セキュリティヘッダーの設定
setSecurityHeaders(); 