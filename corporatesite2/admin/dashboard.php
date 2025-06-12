<?php
require_once '../config/database.php';
require_once 'auth.php';

// 認証チェック
requireAuth();

// 管理者情報の取得
$admin = getAdminInfo();
$loginHistory = getLoginHistory();

// フィードバックの統計情報を取得
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

    // 総フィードバック数
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback");
    $totalFeedback = $stmt->fetch()['total'];

    // 未対応のフィードバック数
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM feedback WHERE status = 'pending'");
    $pendingFeedback = $stmt->fetch()['pending'];

    // フィードバックタイプ別の集計
    $stmt = $pdo->query("
        SELECT type, COUNT(*) as count
        FROM feedback
        GROUP BY type
    ");
    $feedbackByType = $stmt->fetchAll();

    // 最近のフィードバック
    $stmt = $pdo->query("
        SELECT *
        FROM feedback
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentFeedback = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "データベースエラーが発生しました。";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ダッシュボード - 株式会社和風RC</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>管理者ダッシュボード</h1>
            <div class="admin-user">
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php" class="btn btn-logout">ログアウト</a>
            </div>
        </header>

        <nav class="admin-nav">
            <ul>
                <li><a href="dashboard.php" class="active">ダッシュボード</a></li>
                <li><a href="feedback.php">フィードバック管理</a></li>
                <li><a href="settings.php">設定</a></li>
            </ul>
        </nav>

        <main class="admin-main">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <section class="dashboard-stats">
                <div class="stat-card">
                    <h3>総フィードバック数</h3>
                    <p class="stat-number"><?php echo number_format($totalFeedback); ?></p>
                </div>
                <div class="stat-card">
                    <h3>未対応フィードバック</h3>
                    <p class="stat-number"><?php echo number_format($pendingFeedback); ?></p>
                </div>
                <div class="stat-card">
                    <h3>対応率</h3>
                    <p class="stat-number">
                        <?php
                        echo $totalFeedback > 0
                            ? number_format(($totalFeedback - $pendingFeedback) / $totalFeedback * 100, 1)
                            : 0;
                        ?>%
                    </p>
                </div>
            </section>

            <section class="dashboard-charts">
                <div class="chart-container">
                    <h3>フィードバックタイプ別集計</h3>
                    <canvas id="feedbackTypeChart"></canvas>
                </div>
            </section>

            <section class="dashboard-recent">
                <h3>最近のフィードバック</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>タイプ</th>
                                <th>内容</th>
                                <th>ステータス</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentFeedback as $feedback): ?>
                                <tr>
                                    <td><?php echo date('Y/m/d H:i', strtotime($feedback['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($feedback['type']); ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($feedback['content'], 0, 50)) . '...'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                            <?php echo $feedback['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="feedback.php?id=<?php echo $feedback['id']; ?>" class="btn btn-small">詳細</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-login-history">
                <h3>ログイン履歴</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>IPアドレス</th>
                                <th>ユーザーエージェント</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loginHistory as $login): ?>
                                <tr>
                                    <td><?php echo date('Y/m/d H:i:s', strtotime($login['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($login['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($login['user_agent']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        // フィードバックタイプ別のグラフ
        const ctx = document.getElementById('feedbackTypeChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($feedbackByType, 'type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($feedbackByType, 'count')); ?>,
                    backgroundColor: [
                        '#4CAF50',
                        '#2196F3',
                        '#FFC107',
                        '#F44336'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 