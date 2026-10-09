<?php
session_start();
require_once '../config/db.php';

// التأكد من أن المستخدم أدمن
if (!isset($_SESSION['user_id']) || !$_SESSION['is_super_admin']) {
    die('❌ غير مصرح به - فقط الأدمن يمكنه استخدام هذه الصفحة');
}

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    $stmt = $pdo->query("SELECT id FROM competitions ORDER BY id DESC LIMIT 1");
    $comp_id = $stmt->fetchColumn();
    if (!$comp_id) {
        die('❌ لا توجد مسابقة');
    }
}

$message = '';
$error = '';
$results = [];

// جلب اسم المسابقة
$stmt = $pdo->prepare("SELECT name FROM competitions WHERE id = ?");
$stmt->execute([$comp_id]);
$comp_name = $stmt->fetchColumn();

// جلب جميع المستخدمين في المسابقة
$users = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, cu.role
    FROM users u
    JOIN competition_users cu ON u.id = cu.user_id
    WHERE cu.competition_id = ?
    ORDER BY u.full_name
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

// معالجة إعادة تعيين كلمات المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_passwords'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 4) {
        $error = "⚠️ كلمة المرور الجديدة يجب أن تكون 4 أحرف على الأقل";
    } elseif ($new_password !== $confirm_password) {
        $error = "⚠️ كلمة المرور وتأكيدها غير متطابقين";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        $success_count = 0;
        foreach ($users_list as $user) {
            try {
                $stmt->execute([$hashed, $user['id']]);
                $success_count++;
                $results[] = "✅ " . htmlspecialchars($user['full_name']) . " (" . htmlspecialchars($user['username']) . ")";
            } catch (Exception $e) {
                $results[] = "❌ " . htmlspecialchars($user['full_name']) . " - فشل";
            }
        }
        
        $message = "✅ تم إعادة تعيين كلمات المرور لـ <strong>$success_count</strong> مستخدم بنجاح إلى: <strong style='color:var(--gold-l);'>$new_password</strong>";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمات المرور - <?php echo htmlspecialchars($comp_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .reset-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .warning-box {
            background: rgba(216,90,48,0.1);
            border: 1px solid rgba(216,90,48,0.3);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .warning-box h3 {
            color: var(--coral-l);
            margin-bottom: 0.5rem;
        }
        .warning-box ul {
            color: var(--text2);
            padding-right: 1.5rem;
            margin: 0.5rem 0;
        }
        .user-count {
            background: var(--bg2);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }
        .user-count .number {
            font-size: 32px;
            font-weight: 700;
            font-family: 'Space Mono', monospace;
            color: var(--purple-l);
        }
        .result-list {
            max-height: 300px;
            overflow-y: auto;
            background: var(--bg2);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border);
        }
        .result-list .success {
            color: var(--teal-l);
        }
        .result-list .error {
            color: var(--coral-l);
        }
        .password-form {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
        }
        .password-form .form-group {
            margin-bottom: 1.5rem;
        }
        .password-form .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text2);
        }
        .password-form .form-group input {
            width: 100%;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            color: var(--text);
            font-size: 16px;
        }
        .btn-danger-custom {
            background: linear-gradient(135deg, var(--coral), var(--coral-l));
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-danger-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(216,90,48,0.4);
        }
        .btn-danger-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .alert-success {
            background: rgba(29,158,117,0.15);
            border: 1px solid rgba(29,158,117,0.3);
            color: var(--teal-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: var(--coral-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🔑</div>
                إعادة تعيين كلمات المرور
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="competition_home.php?id=<?php echo $comp_id; ?>">← العودة للمسابقة</a>
                <a href="dashboard.php">📋 كل المسابقات</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="reset-container">
            <div class="hero" style="padding: 2rem 2rem 1rem;">
                <div class="hero-tag">🔑 إدارة كلمات المرور</div>
                <h1>إعادة تعيين كلمات المرور</h1>
                <p><?php echo htmlspecialchars($comp_name); ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- تحذير -->
            <div class="warning-box">
                <h3>⚠️ تحذير هام</h3>
                <p>هذا الإجراء سيقوم بإعادة تعيين كلمات مرور <strong>جميع المستخدمين</strong> في هذه المسابقة إلى كلمة مرور واحدة.</p>
                <ul>
                    <li>سيتم تغيير كلمة المرور للجميع (متسابقين، حكام، قادة فرق، متفرجين)</li>
                    <li>سيتم إعلام المستخدمين بكلمة المرور الجديدة</li>
                    <li>لا يمكن التراجع عن هذا الإجراء</li>
                </ul>
            </div>

            <!-- عدد المستخدمين -->
            <div class="user-count">
                <div class="number"><?php echo count($users_list); ?></div>
                <div style="color: var(--text3);">عدد المستخدمين في هذه المسابقة</div>
            </div>

            <!-- نموذج تغيير كلمة المرور -->
            <div class="password-form">
                <form method="POST">
                    <div class="form-group">
                        <label>🔑 كلمة المرور الجديدة</label>
                        <input type="text" name="new_password" id="new_password" placeholder="أدخل كلمة المرور الجديدة" required minlength="4">
                    </div>
                    <div class="form-group">
                        <label>✓ تأكيد كلمة المرور</label>
                        <input type="text" name="confirm_password" id="confirm_password" placeholder="أعد كتابة كلمة المرور" required>
                    </div>
                    <button type="submit" name="reset_passwords" class="btn-danger-custom" onclick="return confirm('⚠️ هل أنت متأكد من إعادة تعيين كلمات مرور جميع المستخدمين؟');">
                        🔄 إعادة تعيين كلمات المرور للجميع
                    </button>
                </form>
            </div>

            <!-- عرض النتائج -->
            <?php if (!empty($results)): ?>
                <div style="margin-top: 2rem;">
                    <h3>📋 قائمة المستخدمين</h3>
                    <div class="result-list">
                        <?php foreach ($results as $result): ?>
                            <div><?php echo $result; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">إعادة تعيين كلمات المرور</span></p>
    </footer>

    <script>
        // التحقق من تطابق كلمة المرور أثناء الكتابة
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        
        if (newPass && confirmPass) {
            confirmPass.addEventListener('input', function() {
                if (this.value !== newPass.value) {
                    this.style.borderColor = 'var(--coral)';
                } else {
                    this.style.borderColor = 'var(--teal)';
                }
            });
        }
        
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>