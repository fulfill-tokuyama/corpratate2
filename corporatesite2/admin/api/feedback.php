<?php
require_once '../../config/database.php';
require_once '../auth.php';

// 認証チェック
requireAuth();

// CSRFトークンの検証
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
}

header('Content-Type: application/json');

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

    // GETリクエスト：フィードバック詳細の取得
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM feedback
            WHERE id = :id
        ");
        $stmt->execute([':id' => $_GET['id']]);
        $feedback = $stmt->fetch();

        if (!$feedback) {
            throw new Exception('フィードバックが見つかりません。');
        }

        // 履歴の取得
        $stmt = $pdo->prepare("
            SELECT *
            FROM feedback_history
            WHERE feedback_id = :feedback_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':feedback_id' => $_GET['id']]);
        $history = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'feedback' => $feedback,
            'history' => $history
        ]);
        exit;
    }

    // POSTリクエスト：フィードバックの更新
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        // 一括操作
        if (isset($data['action']) && isset($data['ids'])) {
            $action = $data['action'];
            $ids = $data['ids'];

            if ($action === 'delete') {
                // 削除
                $stmt = $pdo->prepare("
                    DELETE FROM feedback
                    WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                ");
                $stmt->execute($ids);
            } else {
                // ステータス更新
                $status = str_replace('status_', '', $action);
                $stmt = $pdo->prepare("
                    UPDATE feedback
                    SET status = :status
                    WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                ");
                $params = array_merge([$status], $ids);
                $stmt->execute($params);

                // 履歴の記録
                foreach ($ids as $id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO feedback_history (feedback_id, status, notes, admin_id)
                        VALUES (:feedback_id, :status, :notes, :admin_id)
                    ");
                    $stmt->execute([
                        ':feedback_id' => $id,
                        ':status' => $status,
                        ':notes' => '一括更新',
                        ':admin_id' => $_SESSION['admin_id']
                    ]);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => '一括操作を適用しました。'
            ]);
            exit;
        }

        // 個別更新
        if (isset($data['id']) && isset($data['status'])) {
            // フィードバックの更新
            $stmt = $pdo->prepare("
                UPDATE feedback
                SET status = :status
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $data['id'],
                ':status' => $data['status']
            ]);

            // 履歴の記録
            $stmt = $pdo->prepare("
                INSERT INTO feedback_history (feedback_id, status, notes, admin_id)
                VALUES (:feedback_id, :status, :notes, :admin_id)
            ");
            $stmt->execute([
                ':feedback_id' => $data['id'],
                ':status' => $data['status'],
                ':notes' => $data['notes'] ?? '',
                ':admin_id' => $_SESSION['admin_id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'ステータスを更新しました。'
            ]);
            exit;
        }
    }

    throw new Exception('無効なリクエストです。');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 