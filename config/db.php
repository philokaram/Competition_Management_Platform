<?php
require_once __DIR__ . '/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create default super admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_super_admin = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password, full_name, is_super_admin) VALUES ('admin', '$password', 'مدير النظام الرئيسي', 1)");
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>