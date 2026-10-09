<?php
session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['is_super_admin']) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: select_competition.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_super_admin'] = $user['is_super_admin'];
        
        if ($user['is_super_admin']) {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: select_competition.php');
        }
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - منصة المسابقات</title>    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Password toggle button styles */
        .password-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 1rem;
        }
        .toggle-password {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            background: none;
            border: none;
            font-size: 18px;
            padding: 0;
            z-index: 10;
        }
        .password-wrapper .field-input {
            padding-left: 40px;
        }
        .password-wrapper .input-icon {
            right: 13px;
            left: auto;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-logo-circle">🏆</div>
        <div class="login-title">منصة المسابقات</div>
        <div class="login-sub">سجل دخولك للمتابعة</div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="input-wrap">
                <input type="text" name="username" class="field-input with-icon" placeholder="اسم المستخدم" required>
                <span class="input-icon">👤</span>
            </div>
            <div class="password-wrapper">
                <input type="password" name="password" id="passwordField" class="field-input with-icon" placeholder="كلمة المرور" required>
                <button type="button" class="toggle-password" id="togglePassword">👁️</button>
                <span class="input-icon">🔒</span>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">دخول ←</button>
        </form>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('passwordField');
        
        if (togglePassword && passwordField) {
            togglePassword.addEventListener('click', function() {
                // Toggle the type attribute
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle the eye icon
                this.textContent = type === 'password' ? '👁️' : '🙈';
            });
        }
    </script>
</body>
</html>