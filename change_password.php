<?php
session_start();
require_once '../config/db.php';

// التحقق من أن المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// معرفة دور المستخدم للعودة للصفحة المناسبة
$comp_id = $_SESSION['comp_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'contestant';

if ($user_role == 'judge') {
    $back_link = "../admin/competition_home.php?id=$comp_id";
} else {
    $back_link = "profile.php";
}

// معالجة تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    // جلب كلمة المرور الحالية من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // التحقق من صحة البيانات
    if (!password_verify($current, $user['password'])) {
        $error = "❌ كلمة المرور الحالية غير صحيحة";
    } elseif (strlen($new) < 4) {
        $error = "❌ كلمة المرور الجديدة يجب أن تكون 4 أحرف على الأقل";
    } elseif ($new !== $confirm) {
        $error = "❌ كلمة المرور الجديدة وتأكيدها غير متطابقين";
    } else {
        // تغيير كلمة المرور
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        $success = "✅ تم تغيير كلمة المرور بنجاح";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور - منصة المسابقات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-card {
            max-width: 500px;
            margin: 0 auto;
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 2rem;
        }
        .field-group {
            margin-bottom: 1.5rem;
        }
        .field-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #c4c4e0;
            font-weight: 600;
        }
        .field-input {
            width: 100%;
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 10px;
            padding: 12px;
            color: white;
            font-family: 'Cairo', sans-serif;
        }
        .field-input:focus {
            outline: none;
            border-color: #7F77DD;
        }
        .alert-success {
            background: rgba(29,158,117,0.15);
            border: 1px solid rgba(29,158,117,0.3);
            color: #2dcea0;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: #e97a55;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7F77DD, #534AB7);
            color: white;
            padding: 12px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #a09aed;
            text-decoration: none;
        }
        .mobile-menu-btn {
            display: none;
        }
        @media (max-width: 768px) {
            .form-card {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            .mobile-menu-btn {
                display: block;
                background: none;
                border: none;
                color: white;
                font-size: 24px;
            }
            nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🔐</div>
                تغيير كلمة المرور
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="<?php echo $back_link; ?>">← العودة</a>
                <a href="../logout.php">تسجيل الخروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">🔐 الأمان</div>
            <h1>تغيير كلمة المرور</h1>
            <p>قم بتحديث كلمة المرور الخاصة بحسابك</p>
        </div>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field-group">
                    <label class="field-label">🔑 كلمة المرور الحالية</label>
                    <input type="password" name="current_password" class="field-input" required autocomplete="off">
                </div>
                <div class="field-group">
                    <label class="field-label">🆕 كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="field-input" required autocomplete="off">
                    <small style="color: #8a8aaa; font-size: 11px;">يجب أن تكون 4 أحرف على الأقل</small>
                </div>
                <div class="field-group">
                    <label class="field-label">✓ تأكيد كلمة المرور الجديدة</label>
                    <input type="password" name="confirm_password" class="field-input" required autocomplete="off">
                </div>
                <button type="submit" class="btn-primary">تغيير كلمة المرور</button>
            </form>
            
            <a href="<?php echo $back_link; ?>" class="back-link">← العودة إلى الصفحة السابقة</a>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:#a09aed">تغيير كلمة المرور</span></p>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>