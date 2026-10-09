-- Drop existing tables to recreate with new structure
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS competitions;
DROP TABLE IF EXISTS competition_users;
DROP TABLE IF EXISTS score_history;
SET FOREIGN_KEY_CHECKS = 1;

-- Competitions Table (مع وصف)
CREATE TABLE IF NOT EXISTS competitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    logo VARCHAR(255) DEFAULT 'default_logo.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teams Table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    bonus_score INT DEFAULT 0,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    is_super_admin BOOLEAN DEFAULT FALSE,
    profile_pic VARCHAR(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Competition Users
CREATE TABLE IF NOT EXISTS competition_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    team_id INT NULL,
    role ENUM('judge', 'contestant', 'spectator') NOT NULL,
    mass_score INT DEFAULT 0,
    choir_score INT DEFAULT 0,
    prayer_score INT DEFAULT 0,
    interaction_score INT DEFAULT 0,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    UNIQUE KEY (competition_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Table (مع نوع جديد للتسبحة)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    type ENUM('mass', 'choir', 'prayer') NOT NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Score History Table
CREATE TABLE IF NOT EXISTS score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    score_type ENUM('mass', 'choir', 'prayer', 'interaction', 'team_bonus') NOT NULL,
    points_change INT NOT NULL,
    old_value INT NOT NULL,
    new_value INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Global Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
('mass_points', '10'), 
('choir_points', '5'),
('prayer_points', '7');

-- إضافة لوجو للفرق
ALTER TABLE teams ADD COLUMN logo VARCHAR(255) DEFAULT 'default_team.png';

-- إضافة عمود لتغيير كلمة المرور للمستخدمين (موجود بالفعل ولكن سنستخدمه)
-- جدول users عنده password 
-- 1. إضافة عمود تاريخ التعديل للفرق (لو مش موجود)
ALTER TABLE teams ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. التأكد من وجود جدول score_history للتعديلات
CREATE TABLE IF NOT EXISTS score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    score_type ENUM('mass', 'choir', 'prayer', 'interaction', 'team_bonus') NOT NULL,
    points_change INT NOT NULL,
    old_value INT NOT NULL,
    new_value INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. التأكد من وجود prayer_score في competition_users
ALTER TABLE competition_users ADD COLUMN IF NOT EXISTS prayer_score INT DEFAULT 0 AFTER choir_score;

-- 4. إضافة إعدادات التسبحة لو مش موجودة
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('prayer_points', '7');

-- 1. تعديل عمود role في جدول competition_users لدعم القيم الجديدة
ALTER TABLE competition_users 
MODIFY COLUMN role ENUM('judge', 'contestant', 'spectator', 'team_leader') NOT NULL;

-- 2. التأكد من أن قائد الفريق ليس له درجات (ستكون 0 دائماً)
-- 
-- إنشاء جدول الإشعارات
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    competition_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('score', 'rank', 'attendance', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة جدول إعدادات المسابقة
CREATE TABLE IF NOT EXISTS competition_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL UNIQUE,
    allow_attendance BOOLEAN DEFAULT TRUE,
    allow_bonus BOOLEAN DEFAULT TRUE,
    allow_team_edit BOOLEAN DEFAULT TRUE,
    auto_approve_users BOOLEAN DEFAULT FALSE,
    max_members_per_team INT DEFAULT 10,
    min_members_per_team INT DEFAULT 1,
    start_date DATE NULL,
    end_date DATE NULL,
    registration_deadline DATE NULL,
    theme_color VARCHAR(7) DEFAULT '#7F77DD',
    custom_footer TEXT NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- إضافة إعدادات افتراضية للمسابقات الموجودة
INSERT INTO competition_settings (competition_id, allow_attendance, allow_bonus, allow_team_edit)
SELECT id, 1, 1, 1 FROM competitions
ON DUPLICATE KEY UPDATE competition_id = competition_id;

ALTER TABLE competitions ADD COLUMN theme_color VARCHAR(7) DEFAULT '#7F77DD';

-- إضافة عمود للحالة (archived)
ALTER TABLE competitions ADD COLUMN is_archived TINYINT(1) DEFAULT 0;

-- إضافة عمود تاريخ الأرشفة
ALTER TABLE competitions ADD COLUMN archived_at TIMESTAMP NULL;

-- ✅ فقط تأكد من وجود العمود (لو مش موجود هيضيفه، لو موجود هيتخطى)
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT 'default_avatar.png';

ALTER TABLE teams ADD COLUMN IF NOT EXISTS logo VARCHAR(255) DEFAULT 'default_team.png';

-- جدول المحادثات (الرسائل)
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    sender_id INT NOT NULL,
    team_id INT NULL COMMENT 'NULL = للجميع, رقم = لفريق محدد',
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    INDEX idx_competition_team (competition_id, team_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول قراءة الرسائل (لمتابعة من قرأ الرسالة)
CREATE TABLE IF NOT EXISTS chat_read_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_read (message_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول قوالب الأسئلة
CREATE TABLE IF NOT EXISTS question_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    template_image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الأسئلة المرسلة
CREATE TABLE IF NOT EXISTS speed_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_image VARCHAR(255) DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NOT NULL,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;