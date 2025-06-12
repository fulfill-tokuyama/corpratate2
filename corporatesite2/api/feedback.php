<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// データベース接続設定
require_once '../config/database.php';

// エラーハンドリング
function handleError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// 入力値の検証
function validateInput($data) {
    $errors = [];
    
    // フィードバックタイプの検証
    if (empty($data['feedback-type'])) {
        $errors[] = 'フィードバックの種類は必須です';
    }
    
    // フィードバック内容の検証
    if (empty($data['feedback-content'])) {
        $errors[] = 'フィードバック内容は必須です';
    } elseif (strlen($data['feedback-content']) < 10 || strlen($data['feedback-content']) > 1000) {
        $errors[] = 'フィードバック内容は10文字以上1000文字以内で入力してください';
    }
    
    // メールアドレスの検証
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください';
    }
    
    // 電話番号の検証
    if (!empty($data['phone']) && !preg_match('/^[0-9-+()]*$/', $data['phone'])) {
        $errors[] = '有効な電話番号を入力してください';
    }
    
    return $errors;
}

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // JSONデータの取得
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            handleError('無効なJSONデータです');
        }
        
        // 入力値の検証
        $errors = validateInput($data);
        if (!empty($errors)) {
            handleError(implode("\n", $errors));
        }
        
        // データベース接続
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // フィードバックの保存
        $stmt = $pdo->prepare("
            INSERT INTO feedback (
                feedback_type,
                content,
                name,
                email,
                phone,
                created_at
            ) VALUES (
                :feedback_type,
                :content,
                :name,
                :email,
                :phone,
                NOW()
            )
        ");
        
        $stmt->execute([
            ':feedback_type' => $data['feedback-type'],
            ':content' => $data['feedback-content'],
            ':name' => $data['name'] ?? null,
            ':email' => $data['email'] ?? null,
            ':phone' => $data['phone'] ?? null
        ]);
        
        // 管理者へのメール通知
        $to = ADMIN_EMAIL;
        $subject = '新しいフィードバックが届きました';
        $message = "フィードバックの種類: {$data['feedback-type']}\n";
        $message .= "内容: {$data['feedback-content']}\n";
        if (!empty($data['name'])) $message .= "名前: {$data['name']}\n";
        if (!empty($data['email'])) $message .= "メール: {$data['email']}\n";
        if (!empty($data['phone'])) $message .= "電話: {$data['phone']}\n";
        
        $headers = 'From: ' . ADMIN_EMAIL . "\r\n" .
                  'Reply-To: ' . ADMIN_EMAIL . "\r\n" .
                  'X-Mailer: PHP/' . phpversion();
        
        mail($to, $subject, $message, $headers);
        
        // 成功レスポンス
        echo json_encode([
            'success' => true,
            'message' => 'フィードバックを送信しました。ご協力ありがとうございます。'
        ]);
        
    } catch (PDOException $e) {
        handleError('データベースエラーが発生しました', 500);
    } catch (Exception $e) {
        handleError('予期せぬエラーが発生しました', 500);
    }
} else {
    handleError('不正なリクエストです', 405);
} 