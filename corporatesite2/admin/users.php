<?php
require_once '../config/database.php';
require_once 'auth.php';

// 認証チェック
requireAuth();

// 権限チェック
if (!hasPermission('admin_users')) {
    header('Location: dashboard.php');
    exit;
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

    // 管理者一覧の取得
    $sql = "
        SELECT a.*, r.name as role_name
        FROM admins a
        LEFT JOIN admin_roles r ON a.role_id = r.id
        ORDER BY a.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $admins = $stmt->fetchAll();

    // 権限一覧の取得
    $sql = "SELECT * FROM admin_roles ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $roles = $stmt->fetchAll();

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
    <title>管理者アカウント管理 - 管理画面</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>管理者アカウント管理</h1>
            <nav class="admin-nav">
                <a href="dashboard.php">ダッシュボード</a>
                <a href="feedback.php">フィードバック</a>
                <a href="reports.php">レポート</a>
                <a href="users.php" class="active">ユーザー管理</a>
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

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="showUserModal()">
                    <i class="fas fa-user-plus"></i> 新規管理者追加
                </button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名前</th>
                            <th>メールアドレス</th>
                            <th>権限</th>
                            <th>ステータス</th>
                            <th>最終ログイン</th>
                            <th>2段階認証</th>
                            <th>作成日時</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['role_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $admin['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $admin['is_active'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $admin['last_login_at'] ? date('Y-m-d H:i', strtotime($admin['last_login_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $admin['two_factor_enabled'] ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $admin['two_factor_enabled'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($admin['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="showUserModal(<?php echo $admin['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="toggleUserStatus(<?php echo $admin['id']; ?>)">
                                        <i class="fas fa-power-off"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $admin['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- ユーザー編集モーダル -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <h2>管理者情報</h2>
            <form id="userForm" onsubmit="return saveUser(event)">
                <input type="hidden" id="userId" name="id">
                
                <div class="form-group">
                    <label for="userName">名前</label>
                    <input type="text" id="userName" name="name" required>
                </div>

                <div class="form-group">
                    <label for="userEmail">メールアドレス</label>
                    <input type="email" id="userEmail" name="email" required>
                </div>

                <div class="form-group">
                    <label for="userRole">権限</label>
                    <select id="userRole" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="userPassword">パスワード</label>
                    <input type="password" id="userPassword" name="password" 
                           minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                           title="8文字以上で、数字、小文字、大文字を含める必要があります">
                    <small class="form-text">新規作成時は必須、編集時は変更する場合のみ入力</small>
                </div>

                <div class="form-group">
                    <label for="userPasswordConfirm">パスワード（確認）</label>
                    <input type="password" id="userPasswordConfirm" name="password_confirm">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="userTwoFactor" name="two_factor_enabled">
                        2段階認証を有効にする
                    </label>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/admin.js"></script>
    <script>
        // ユーザーモーダルの表示
        function showUserModal(userId = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const passwordField = document.getElementById('userPassword');
            const passwordConfirmField = document.getElementById('userPasswordConfirm');

            if (userId) {
                // 編集モード
                fetch(`api/users.php?id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const user = data.user;
                            document.getElementById('userId').value = user.id;
                            document.getElementById('userName').value = user.name;
                            document.getElementById('userEmail').value = user.email;
                            document.getElementById('userRole').value = user.role_id;
                            document.getElementById('userTwoFactor').checked = user.two_factor_enabled;

                            // パスワードフィールドを任意に
                            passwordField.removeAttribute('required');
                            passwordConfirmField.removeAttribute('required');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('ユーザー情報の取得に失敗しました。');
                    });
            } else {
                // 新規作成モード
                form.reset();
                document.getElementById('userId').value = '';
                passwordField.setAttribute('required', 'required');
                passwordConfirmField.setAttribute('required', 'required');
            }

            modal.style.display = 'block';
        }

        // ユーザーモーダルの閉じる
        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        // ユーザーの保存
        function saveUser(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            // パスワードの確認
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');
            if (password && password !== passwordConfirm) {
                alert('パスワードが一致しません。');
                return false;
            }

            fetch('api/users.php', {
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
                    alert(data.error || 'ユーザーの保存に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('ユーザーの保存中にエラーが発生しました。');
            });

            return false;
        }

        // ユーザーのステータス切り替え
        function toggleUserStatus(userId) {
            if (!confirm('このユーザーのステータスを変更しますか？')) return;

            fetch('api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: 'toggle',
                    id: userId
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

        // ユーザーの削除
        function deleteUser(userId) {
            if (!confirm('このユーザーを削除しますか？')) return;

            fetch('api/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('ユーザーの削除に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('ユーザーの削除中にエラーが発生しました。');
            });
        }
    </script>
</body>
</html> 