<?php
require_once '../config/database.php';
require_once 'auth.php';

// 認証チェック
requireAuth();

// データベース接続
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

    // レポートの取得
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
        ':type' => $reportType,
        ':date' => $date
    ]);
    $report = $stmt->fetch();

    // 自動送信設定の取得
    $sql = "
        SELECT rs.*, a.name as created_by_name
        FROM report_schedules rs
        JOIN admins a ON rs.created_by = a.id
        WHERE rs.is_active = TRUE
        ORDER BY rs.type, rs.email
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $schedules = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $error = 'データベースエラーが発生しました。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レポート管理 - 管理画面</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>レポート管理</h1>
            <nav class="admin-nav">
                <a href="dashboard.php">ダッシュボード</a>
                <a href="feedback.php">フィードバック</a>
                <a href="reports.php" class="active">レポート</a>
                <a href="settings.php">設定</a>
                <a href="logout.php">ログアウト</a>
            </nav>
        </header>

        <main class="admin-main">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="report-controls">
                <div class="report-type-selector">
                    <button class="btn <?php echo $reportType === 'daily' ? 'btn-primary' : 'btn-secondary'; ?>" 
                            onclick="changeReportType('daily')">日次</button>
                    <button class="btn <?php echo $reportType === 'weekly' ? 'btn-primary' : 'btn-secondary'; ?>" 
                            onclick="changeReportType('weekly')">週次</button>
                    <button class="btn <?php echo $reportType === 'monthly' ? 'btn-primary' : 'btn-secondary'; ?>" 
                            onclick="changeReportType('monthly')">月次</button>
                </div>

                <div class="date-selector">
                    <input type="date" id="report-date" value="<?php echo $date; ?>" 
                           onchange="changeReportDate(this.value)">
                </div>

                <div class="report-actions">
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-sync"></i> レポート生成
                    </button>
                    <button class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-file-export"></i> エクスポート
                    </button>
                </div>
            </div>

            <?php if ($report): ?>
                <div class="report-content">
                    <div class="report-summary">
                        <h2>レポート概要</h2>
                        <div class="summary-cards">
                            <?php
                            $data = json_decode($report['data'], true);
                            $summary = $data[$reportType . '_summary'] ?? $data;
                            ?>
                            <div class="card">
                                <h3>総フィードバック数</h3>
                                <p class="number"><?php echo $summary['total_feedback']; ?></p>
                            </div>
                            <div class="card">
                                <h3>対応済み</h3>
                                <p class="number"><?php echo $summary['completed_count']; ?></p>
                            </div>
                            <div class="card">
                                <h3>対応中</h3>
                                <p class="number"><?php echo $summary['in_progress_count']; ?></p>
                            </div>
                            <div class="card">
                                <h3>未対応</h3>
                                <p class="number"><?php echo $summary['pending_count']; ?></p>
                            </div>
                            <div class="card">
                                <h3>平均対応時間</h3>
                                <p class="number"><?php echo round($summary['avg_response_time'], 1); ?>時間</p>
                            </div>
                        </div>
                    </div>

                    <div class="report-charts">
                        <div class="chart-container">
                            <h2>フィードバックタイプ別集計</h2>
                            <canvas id="typeChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h2>日次トレンド</h2>
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-report">
                    <p>選択された日付のレポートはありません。</p>
                    <button class="btn btn-primary" onclick="generateReport()">
                        レポートを生成する
                    </button>
                </div>
            <?php endif; ?>

            <div class="report-schedules">
                <h2>自動送信設定</h2>
                <button class="btn btn-primary" onclick="showScheduleModal()">
                    <i class="fas fa-plus"></i> 新規設定
                </button>

                <table class="table">
                    <thead>
                        <tr>
                            <th>タイプ</th>
                            <th>メールアドレス</th>
                            <th>ステータス</th>
                            <th>作成者</th>
                            <th>作成日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['type']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $schedule['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $schedule['is_active'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['created_by_name']); ?></td>
                                <td><?php echo $schedule['created_at']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="toggleSchedule(<?php echo $schedule['id']; ?>)">
                                        <?php echo $schedule['is_active'] ? '無効化' : '有効化'; ?>
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                        削除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- スケジュール設定モーダル -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <h2>自動送信設定</h2>
            <form id="scheduleForm" onsubmit="return saveSchedule(event)">
                <div class="form-group">
                    <label for="scheduleType">レポートタイプ</label>
                    <select id="scheduleType" name="type" required>
                        <option value="daily">日次</option>
                        <option value="weekly">週次</option>
                        <option value="monthly">月次</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="scheduleEmail">メールアドレス</label>
                    <input type="email" id="scheduleEmail" name="email" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        // レポートタイプの変更
        function changeReportType(type) {
            window.location.href = `reports.php?type=${type}&date=${document.getElementById('report-date').value}`;
        }

        // レポート日付の変更
        function changeReportDate(date) {
            window.location.href = `reports.php?type=${getUrlParameter('type')}&date=${date}`;
        }

        // レポートの生成
        function generateReport() {
            const type = getUrlParameter('type');
            const date = document.getElementById('report-date').value;
            
            fetch(`api/reports.php?type=${type}&date=${date}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('レポートの生成に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('レポートの生成中にエラーが発生しました。');
            });
        }

        // レポートのエクスポート
        function exportReport() {
            const type = getUrlParameter('type');
            const date = document.getElementById('report-date').value;
            window.location.href = `api/export.php?type=report&report_type=${type}&date=${date}`;
        }

        // スケジュール設定モーダルの表示
        function showScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'block';
        }

        // スケジュール設定モーダルの閉じる
        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
        }

        // スケジュールの保存
        function saveSchedule(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            fetch('api/schedules.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('設定の保存に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('設定の保存中にエラーが発生しました。');
            });

            return false;
        }

        // スケジュールの有効/無効切り替え
        function toggleSchedule(id) {
            if (!confirm('この設定のステータスを変更しますか？')) return;

            fetch('api/schedules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: 'toggle',
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('ステータスの変更に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('ステータスの変更中にエラーが発生しました。');
            });
        }

        // スケジュールの削除
        function deleteSchedule(id) {
            if (!confirm('この設定を削除しますか？')) return;

            fetch('api/schedules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('設定の削除に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('設定の削除中にエラーが発生しました。');
            });
        }

        // URLパラメータの取得
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            const results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        // チャートの初期化
        <?php if ($report): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const data = <?php echo $report['data']; ?>;
            
            // タイプ別集計チャート
            const typeCtx = document.getElementById('typeChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: data.type_breakdown.map(item => item.type),
                    datasets: [{
                        data: data.type_breakdown.map(item => item.count),
                        backgroundColor: [
                            '#4e73df',
                            '#1cc88a',
                            '#36b9cc',
                            '#f6c23e',
                            '#e74a3b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            // トレンドチャート
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: data.daily_trends.map(item => item.date),
                    datasets: [{
                        label: '総フィードバック数',
                        data: data.daily_trends.map(item => item.total_feedback),
                        borderColor: '#4e73df',
                        tension: 0.1
                    }, {
                        label: '対応済み数',
                        data: data.daily_trends.map(item => item.resolved_count),
                        borderColor: '#1cc88a',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html> 