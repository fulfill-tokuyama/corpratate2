<?php
require_once '../../config/database.php';
require_once '../auth.php';

// エラーログの設定
error_log("レポート自動送信バッチを開始します。");

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

    // 現在の日時を取得
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $dayOfWeek = $now->format('N'); // 1 (月曜) から 7 (日曜)
    $dayOfMonth = $now->format('j'); // 1 から 31

    // 送信対象のスケジュールを取得
    $sql = "
        SELECT rs.*, a.name as admin_name
        FROM report_schedules rs
        JOIN admins a ON rs.created_by = a.id
        WHERE rs.is_active = TRUE
        AND (
            (rs.type = 'daily')
            OR (rs.type = 'weekly' AND :day_of_week = 1)
            OR (rs.type = 'monthly' AND :day_of_month = 1)
        )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':day_of_week' => $dayOfWeek,
        ':day_of_month' => $dayOfMonth
    ]);
    $schedules = $stmt->fetchAll();

    foreach ($schedules as $schedule) {
        try {
            // レポートタイプに応じた日付を設定
            switch ($schedule['type']) {
                case 'daily':
                    $reportDate = $today;
                    break;
                case 'weekly':
                    $reportDate = date('Y-m-d', strtotime($today . ' -6 days'));
                    break;
                case 'monthly':
                    $reportDate = date('Y-m-01', strtotime($today . ' -1 month'));
                    break;
            }

            // レポートの生成
            $sql = "
                SELECT r.*, a.name as created_by_name
                FROM reports r
                JOIN admins a ON r.created_by = a.id
                WHERE r.type = :type
                AND r.date = :date
                ORDER BY r.created_at DESC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type' => $schedule['type'],
                ':date' => $reportDate
            ]);
            $report = $stmt->fetch();

            if (!$report) {
                // レポートが存在しない場合は生成
                $reportData = generateReport($pdo, $schedule['type'], $reportDate);
                $report = [
                    'type' => $schedule['type'],
                    'date' => $reportDate,
                    'data' => json_encode($reportData)
                ];
            }

            // メールの送信
            $subject = sprintf(
                '[%s] %sレポート - %s',
                SITE_NAME,
                getReportTypeName($schedule['type']),
                $reportDate
            );

            $body = generateEmailBody($report, $schedule['admin_name']);
            $headers = [
                'From: ' . ADMIN_EMAIL,
                'Content-Type: text/html; charset=UTF-8'
            ];

            if (mail($schedule['email'], $subject, $body, implode("\r\n", $headers))) {
                error_log(sprintf(
                    "レポートを送信しました: %s, %s, %s",
                    $schedule['type'],
                    $reportDate,
                    $schedule['email']
                ));
            } else {
                throw new Exception('メールの送信に失敗しました。');
            }

        } catch (Exception $e) {
            error_log(sprintf(
                "レポート送信エラー: %s, %s, %s - %s",
                $schedule['type'],
                $reportDate,
                $schedule['email'],
                $e->getMessage()
            ));
            continue;
        }
    }

    error_log("レポート自動送信バッチを完了しました。");

} catch (Exception $e) {
    error_log("レポート自動送信バッチでエラーが発生しました: " . $e->getMessage());
}

// レポート生成関数
function generateReport($pdo, $type, $date) {
    switch ($type) {
        case 'daily':
            return generateDailyReport($pdo, $date);
        case 'weekly':
            return generateWeeklyReport($pdo, $date);
        case 'monthly':
            return generateMonthlyReport($pdo, $date);
        default:
            throw new Exception('無効なレポートタイプです。');
    }
}

// 日次レポート生成
function generateDailyReport($pdo, $date) {
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

    return $report;
}

// 週次レポート生成
function generateWeeklyReport($pdo, $startDate) {
    $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));

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

    return $report;
}

// 月次レポート生成
function generateMonthlyReport($pdo, $date) {
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

    return $report;
}

// レポートタイプ名の取得
function getReportTypeName($type) {
    switch ($type) {
        case 'daily':
            return '日次';
        case 'weekly':
            return '週次';
        case 'monthly':
            return '月次';
        default:
            return $type;
    }
}

// メール本文の生成
function generateEmailBody($report, $adminName) {
    $data = json_decode($report['data'], true);
    $type = $report['type'];
    $date = $report['date'];
    $summary = $data[$type . '_summary'] ?? $data;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: sans-serif; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: #f8f9fa; padding: 20px; }
            .content { padding: 20px; }
            .summary { margin-bottom: 20px; }
            .summary-item { margin-bottom: 10px; }
            .footer { background: #f8f9fa; padding: 20px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php echo getReportTypeName($type); ?>レポート</h1>
                <p>期間: <?php echo $date; ?></p>
            </div>
            <div class="content">
                <div class="summary">
                    <h2>サマリー</h2>
                    <div class="summary-item">
                        <strong>総フィードバック数:</strong>
                        <?php echo $summary['total_feedback']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>対応済み:</strong>
                        <?php echo $summary['completed_count']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>対応中:</strong>
                        <?php echo $summary['in_progress_count']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>未対応:</strong>
                        <?php echo $summary['pending_count']; ?>
                    </div>
                    <div class="summary-item">
                        <strong>平均対応時間:</strong>
                        <?php echo round($summary['avg_response_time'], 1); ?>時間
                    </div>
                </div>

                <?php if (isset($data['type_breakdown'])): ?>
                <div class="type-breakdown">
                    <h2>タイプ別集計</h2>
                    <table>
                        <tr>
                            <th>タイプ</th>
                            <th>件数</th>
                            <th>対応済み</th>
                        </tr>
                        <?php foreach ($data['type_breakdown'] as $item): ?>
                        <tr>
                            <td><?php echo $item['type']; ?></td>
                            <td><?php echo $item['count']; ?></td>
                            <td><?php echo $item['resolved_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (isset($data['daily_trends'])): ?>
                <div class="trends">
                    <h2>日次トレンド</h2>
                    <table>
                        <tr>
                            <th>日付</th>
                            <th>総数</th>
                            <th>対応済み</th>
                        </tr>
                        <?php foreach ($data['daily_trends'] as $item): ?>
                        <tr>
                            <td><?php echo $item['date']; ?></td>
                            <td><?php echo $item['total_feedback']; ?></td>
                            <td><?php echo $item['resolved_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="footer">
                <p>このレポートは自動生成されました。</p>
                <p>生成者: <?php echo $adminName; ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
} 