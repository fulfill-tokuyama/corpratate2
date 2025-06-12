<?php
require_once '../config/database.php';
require_once 'auth.php';

// 認証チェック
requireAuth();

// ページネーション設定
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 検索条件
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

    // 検索条件の構築
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(content LIKE :search OR name LIKE :search OR email LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($type) {
        $where[] = "type = :type";
        $params[':type'] = $type;
    }

    if ($status) {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    if ($dateFrom) {
        $where[] = "created_at >= :date_from";
        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo) {
        $where[] = "created_at <= :date_to";
        $params[':date_to'] = $dateTo . ' 23:59:59';
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // 総件数の取得
    $countSql = "SELECT COUNT(*) as total FROM feedback {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // フィードバック一覧の取得
    $sql = "
        SELECT f.*, 
               GROUP_CONCAT(fh.status ORDER BY fh.created_at DESC) as status_history,
               GROUP_CONCAT(fh.notes ORDER BY fh.created_at DESC) as notes_history
        FROM feedback f
        LEFT JOIN feedback_history fh ON f.id = fh.feedback_id
        {$whereClause}
        GROUP BY f.id
        ORDER BY f.created_at DESC
        LIMIT :offset, :per_page
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $feedback = $stmt->fetchAll();

    // フィードバックタイプの取得
    $stmt = $pdo->query("SELECT DISTINCT type FROM feedback ORDER BY type");
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ステータスの取得
    $stmt = $pdo->query("SELECT DISTINCT status FROM feedback ORDER BY status");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title>フィードバック管理 - 株式会社和風RC</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>フィードバック管理</h1>
            <div class="admin-user">
                <span><?php echo htmlspecialchars($admin['username']); ?></span>
                <a href="logout.php" class="btn btn-logout">ログアウト</a>
            </div>
        </header>

        <nav class="admin-nav">
            <ul>
                <li><a href="dashboard.php">ダッシュボード</a></li>
                <li><a href="feedback.php" class="active">フィードバック管理</a></li>
                <li><a href="settings.php">設定</a></li>
            </ul>
        </nav>

        <main class="admin-main">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <section class="feedback-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="検索..." class="filter-input">
                    </div>

                    <div class="filter-group">
                        <select name="type" class="filter-select">
                            <option value="">タイプを選択</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo $t; ?>" <?php echo $type === $t ? 'selected' : ''; ?>>
                                    <?php echo $t; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">ステータスを選択</option>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>>
                                    <?php echo $s; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>" 
                               class="filter-input" placeholder="開始日">
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>" 
                               class="filter-input" placeholder="終了日">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">検索</button>
                        <a href="feedback.php" class="btn btn-secondary">リセット</a>
                    </div>
                </form>
            </section>

            <section class="feedback-actions">
                <div class="bulk-actions">
                    <select id="bulk-action" class="bulk-select">
                        <option value="">一括操作</option>
                        <option value="status_pending">ステータス：未対応</option>
                        <option value="status_in_progress">ステータス：対応中</option>
                        <option value="status_completed">ステータス：完了</option>
                        <option value="status_rejected">ステータス：却下</option>
                        <option value="delete">削除</option>
                    </select>
                    <button id="apply-bulk-action" class="btn btn-primary">適用</button>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-primary export-btn" data-type="csv">
                        <i class="fas fa-file-csv"></i> CSVエクスポート
                    </button>
                    <button class="btn btn-success export-btn" data-type="excel">
                <div class="export-actions">
                    <button onclick="exportData('csv')" class="btn btn-secondary">CSVエクスポート</button>
                    <button onclick="exportData('excel')" class="btn btn-secondary">Excelエクスポート</button>
                </div>
            </section>

            <section class="feedback-list">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all"></th>
                                <th>日時</th>
                                <th>タイプ</th>
                                <th>名前</th>
                                <th>メール</th>
                                <th>内容</th>
                                <th>ステータス</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedback as $item): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="feedback-select" 
                                               value="<?php echo $item['id']; ?>">
                                    </td>
                                    <td><?php echo date('Y/m/d H:i', strtotime($item['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['type']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['email']); ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($item['content'], 0, 50)) . '...'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $item['status']; ?>">
                                            <?php echo $item['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="showFeedbackDetail(<?php echo $item['id']; ?>)" 
                                                class="btn btn-small">詳細</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">前へ</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                               class="btn btn-small <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="btn btn-small">次へ</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- フィードバック詳細モーダル -->
    <div id="feedback-modal" class="modal">
        <div class="modal-content">
            <h2>フィードバック詳細</h2>
            <div id="feedback-detail"></div>
            <div class="modal-actions">
                <button onclick="closeFeedbackModal()" class="btn btn-secondary">閉じる</button>
            </div>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        // フィードバック詳細の表示
        async function showFeedbackDetail(id) {
            try {
                const response = await fetch(`api/feedback.php?id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const detail = document.getElementById('feedback-detail');
                    detail.innerHTML = `
                        <div class="feedback-detail-content">
                            <p><strong>日時：</strong>${data.feedback.created_at}</p>
                            <p><strong>タイプ：</strong>${data.feedback.type}</p>
                            <p><strong>名前：</strong>${data.feedback.name}</p>
                            <p><strong>メール：</strong>${data.feedback.email}</p>
                            <p><strong>内容：</strong>${data.feedback.content}</p>
                            <p><strong>ステータス：</strong>${data.feedback.status}</p>
                            
                            <h3>対応履歴</h3>
                            <div class="feedback-history">
                                ${data.history.map(h => `
                                    <div class="history-item">
                                        <p><strong>日時：</strong>${h.created_at}</p>
                                        <p><strong>ステータス：</strong>${h.status}</p>
                                        <p><strong>メモ：</strong>${h.notes}</p>
                                    </div>
                                `).join('')}
                            </div>

                            <div class="feedback-actions">
                                <select id="status-select" class="status-select">
                                    <option value="pending">未対応</option>
                                    <option value="in_progress">対応中</option>
                                    <option value="completed">完了</option>
                                    <option value="rejected">却下</option>
                                </select>
                                <textarea id="status-notes" class="status-notes" 
                                          placeholder="対応メモを入力"></textarea>
                                <button onclick="updateFeedbackStatus(${id})" 
                                        class="btn btn-primary">更新</button>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('feedback-modal').style.display = 'block';
                } else {
                    showAlert('フィードバックの取得に失敗しました。', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('エラーが発生しました。', 'error');
            }
        }

        // モーダルを閉じる
        function closeFeedbackModal() {
            document.getElementById('feedback-modal').style.display = 'none';
        }

        // フィードバックステータスの更新
        async function updateFeedbackStatus(id) {
            const status = document.getElementById('status-select').value;
            const notes = document.getElementById('status-notes').value;

            try {
                const response = await fetch('api/feedback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        id: id,
                        status: status,
                        notes: notes
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showAlert('ステータスを更新しました。', 'success');
                    closeFeedbackModal();
                    refreshPage();
                } else {
                    showAlert('ステータスの更新に失敗しました。', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('エラーが発生しました。', 'error');
            }
        }

        // 一括操作の適用
        document.getElementById('apply-bulk-action').addEventListener('click', async () => {
            const action = document.getElementById('bulk-action').value;
            if (!action) {
                showAlert('操作を選択してください。', 'error');
                return;
            }

            const selected = Array.from(document.querySelectorAll('.feedback-select:checked'))
                                .map(cb => cb.value);

            if (selected.length === 0) {
                showAlert('フィードバックを選択してください。', 'error');
                return;
            }

            if (action === 'delete' && !confirm('選択したフィードバックを削除しますか？')) {
                return;
            }

            try {
                const response = await fetch('api/feedback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        action: action,
                        ids: selected
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showAlert('一括操作を適用しました。', 'success');
                    refreshPage();
                } else {
                    showAlert('一括操作の適用に失敗しました。', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('エラーが発生しました。', 'error');
            }
        });

        // 全選択/解除
        document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('.feedback-select').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    </script>
</body>
</html> 