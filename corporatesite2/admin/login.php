<?php
session_start();
require_once '../config/database.php';

// すでにログインしている場合はリダイレクト
if (isset($_SESSION['admin_id'])) {
    header('Location: feedback.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

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
            SELECT id, email, password, status
            FROM admins
            WHERE email = :email
        ");
        
        $stmt->execute([':email' => $email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            if ($admin['status'] === 'active') {
                // ログイン成功
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                
                // 最終ログイン時間の更新
                $stmt = $pdo->prepare("
                    UPDATE admins
                    SET last_login = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $admin['id']]);

                // ログイン履歴の記録
                $stmt = $pdo->prepare("
                    INSERT INTO admin_login_history (
                        admin_id,
                        ip_address,
                        user_agent,
                        created_at
                    ) VALUES (
                        :admin_id,
                        :ip_address,
                        :user_agent,
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    ':admin_id' => $admin['id'],
                    ':ip_address' => $_SERVER['REMOTE_ADDR'],
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT']
                ]);

                header('Location: feedback.php');
                exit;
            } else {
                $error = 'アカウントが無効化されています。';
            }
        } else {
            $error = 'メールアドレスまたはパスワードが正しくありません。';
        }
    } catch (PDOException $e) {
        $error = 'システムエラーが発生しました。時間をおいて再度お試しください。';
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン - 株式会社和風RC</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 24px;
        }

        .login-header img {
            display: block;
            margin: 0 auto 10px auto;
        }

        .login-form {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }

        .error-message {
            color: var(--danger-color);
            font-size: 14px;
            margin-top: 5px;
        }

        .login-button {
            background: #f7931e;
            color: #fff;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-button:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        .login-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        @media (prefers-color-scheme: dark) {
            .login-container {
                background: #2a2a2a;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="../images/ap-logo.png" alt="アポトル ロゴ" style="height:60px; margin-bottom: 10px;">
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="login-button">ログイン</button>
        </form>
    </div>

    <script>
        // フォーム送信時のローディング表示
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            const button = this.querySelector('.login-button');
            button.disabled = true;
            button.textContent = 'ログイン中...';
        });
    </script>
</body>
</html> 