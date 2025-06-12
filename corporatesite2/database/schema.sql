-- データベースの作成
CREATE DATABASE IF NOT EXISTS corporate_site
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE corporate_site;

-- フィードバックテーブル
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_type VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    name VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    admin_notes TEXT,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理者テーブル
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- フィードバック履歴テーブル
CREATE TABLE IF NOT EXISTS feedback_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理者ログイン履歴テーブル
CREATE TABLE IF NOT EXISTS admin_login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理者権限テーブル
CREATE TABLE admin_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    permissions JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理者テーブルの更新
ALTER TABLE admins
ADD COLUMN role_id INT AFTER email,
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER role_id,
ADD COLUMN last_login_at TIMESTAMP NULL AFTER is_active,
ADD COLUMN password_changed_at TIMESTAMP NULL AFTER last_login_at,
ADD COLUMN two_factor_secret VARCHAR(32) NULL AFTER password_changed_at,
ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE AFTER two_factor_secret,
ADD FOREIGN KEY (role_id) REFERENCES admin_roles(id);

-- 管理者アクティビティログテーブル
CREATE TABLE admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(50) NULL,
    target_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    INDEX idx_admin_action (admin_id, action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理者パスワード履歴テーブル
CREATE TABLE admin_password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    INDEX idx_admin_created (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期権限の作成
INSERT INTO admin_roles (name, description, permissions) VALUES
('super_admin', 'システム管理者', '{"all": true}'),
('admin', '管理者', '{
    "feedback": {
        "view": true,
        "edit": true,
        "delete": true
    },
    "reports": {
        "view": true,
        "generate": true,
        "export": true
    },
    "settings": {
        "view": true
    }
}'),
('operator', 'オペレーター', '{
    "feedback": {
        "view": true,
        "edit": true
    },
    "reports": {
        "view": true
    }
}');

-- 初期管理者アカウントの作成（パスワード: admin123）
INSERT INTO admins (username, password, email, created_at, updated_at)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin@example.com',
    NOW(),
    NOW()
);

-- レポートテーブル
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    date DATE NOT NULL,
    data JSON NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_type_date (type, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- レポート自動送信設定テーブル
CREATE TABLE report_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_type_email (type, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 