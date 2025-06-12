<?php
require_once '../../config/database.php';
require_once '../auth.php';

// 認証チェック
requireAuth();

// CSRFトークンの検証
verifyCsrfToken();

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

    // レポートタイプの取得
    $reportType = $_GET['type'] ?? 'daily';
    $date = $_GET['date'] ?? date('Y-m-d');

    // レポートデータの取得
    switch ($reportType) {
        case 'daily':
            // 日次レポート
            $sql = "
                SELECT 
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    COUNT(DISTINCT type) as type_count,
                    COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) as resolved_count
                FROM feedback
                WHERE DATE(created_at) = :date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $date]);
            $report = $stmt->fetch();

            // タイプ別集計
            $sql = "
                SELECT 
                    type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as resolved_count
                FROM feedback
                WHERE DATE(created_at) = :date
                GROUP BY type
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $date]);
            $report['type_breakdown'] = $stmt->fetchAll();

            // 対応時間の集計
            $sql = "
                SELECT 
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time
                FROM feedback
                WHERE DATE(created_at) = :date
                AND status = 'completed'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':date' => $date]);
            $report['response_time'] = $stmt->fetch();

            break;

        case 'weekly':
            // 週次レポート
            $startDate = date('Y-m-d', strtotime($date . ' -6 days'));
            $endDate = $date;

            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as resolved_count
                FROM feedback
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(created_at)
                ORDER BY date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $report['daily_trends'] = $stmt->fetchAll();

            // 週間集計
            $sql = "
                SELECT 
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time
                FROM feedback
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $report['weekly_summary'] = $stmt->fetch();

            break;

        case 'monthly':
            // 月次レポート
            $startDate = date('Y-m-01', strtotime($date));
            $endDate = date('Y-m-t', strtotime($date));

            $sql = "
                SELECT 
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_response_time
                FROM feedback
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $report['monthly_summary'] = $stmt->fetch();

            // 週次トレンド
            $sql = "
                SELECT 
                    YEARWEEK(created_at) as week,
                    COUNT(*) as total_feedback,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as resolved_count
                FROM feedback
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY YEARWEEK(created_at)
                ORDER BY week
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $report['weekly_trends'] = $stmt->fetchAll();

            break;

        default:
            throw new Exception('無効なレポートタイプです。');
    }

    // レポートの保存
    $sql = "
        INSERT INTO reports (
            type,
            date,
            data,
            created_by
        ) VALUES (
            :type,
            :date,
            :data,
            :created_by
        )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type' => $reportType,
        ':date' => $date,
        ':data' => json_encode($report),
        ':created_by' => $_SESSION['admin_id']
    ]);

    // レスポンス
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'report' => $report
    ]);

} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'error' => 'レポートの生成中にエラーが発生しました。'
    ]);
} 