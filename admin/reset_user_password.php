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
$selected_user = null;

// جلب اسم المسابقة
$stmt = $pdo->prepare("SELECT name FROM competitions WHERE id = ?");
$stmt->execute([$comp_id]);
$comp_name = $stmt->fetchColumn();

// جلب جميع المستخدمين في المسابقة للاختيار
$users = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, cu.role
    FROM users u
    JOIN competition_users cu ON u.id = cu.user_id
    WHERE cu.competition_id = ?
    ORDER BY u.full_name
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

// إذا تم اختيار مستخدم
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username, cu.role
        FROM users u
        JOIN competition_users cu ON u.id = cu.user_id
        WHERE u.id = ? AND cu.competition_id = ?
    ");
    $stmt->execute([$user_id, $comp_id]);
    $selected_user = $stmt->fetch();
}

// معالجة تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 4) {
        $error = "⚠️ كلمة المرور الجديدة يجب أن تكون 4 أحرف على الأقل";
    } elseif ($new_password !== $confirm_password) {
        $error = "⚠️ كلمة المرور وتأكيدها غير متطابقين";
    } else {
        // جلب اسم المستخدم للتأكيد
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_name = $stmt->fetchColumn();
        
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        
        $message = "✅ تم تغيير كلمة مرور <strong>" . htmlspecialchars($user_name) . "</strong> إلى: <strong style='color:var(--gold-l);'>$new_password</strong>";
        
        // تحديث بيانات المستخدم المختار
        $stmt = $pdo->prepare("
            SELECT u.id, u.full_name, u.username, cu.role
            FROM users u
            JOIN competition_users cu ON u.id = cu.user_id
            WHERE u.id = ? AND cu.competition_id = ?
        ");
        $stmt->execute([$user_id, $comp_id]);
        $selected_user = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة مرور مستخدم - <?php echo htmlspecialchars($comp_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .user-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 1rem 0;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            background: var(--bg2);
            border-radius: 16px;
            border: 1px solid var(--border);
        }
        .user-item {
            padding: 10px 12px;
            border-radius: 10px;
            background: var(--card);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            color: var(--text);
        }
        .user-item:hover {
            border-color: var(--purple);
            transform: translateX(-3px);
        }
        .user-item.active {
            border-color: var(--purple);
            background: rgba(127,119,221,0.1);
        }
        .user-item .role-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 12px;
            background: var(--bg2);
        }
        .user-item .username {
            font-size: 12px;
            color: var(--text3);
        }
        .password-form {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 1.5rem;
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
        .selected-user-info {
            background: var(--bg2);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--purple), var(--purple-d));
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
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127,119,221,0.4);
        }
        .search-box {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🔑</div>
                تغيير كلمة مرور مستخدم
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
        <div class="container">
            <div class="hero" style="padding: 2rem 2rem 1rem;">
                <div class="hero-tag">🔑 تغيير كلمة المرور</div>
                <h1>تغيير كلمة مرور مستخدم</h1>
                <p><?php echo htmlspecialchars($comp_name); ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- اختيار المستخدم -->
            <h3>👤 اختر المستخدم</h3>
            <input type="text" class="search-box" id="searchUser" placeholder="🔍 ابحث عن مستخدم...">
            
            <div class="user-list" id="userList">
                <?php foreach ($users_list as $user): 
                    $role_icons = ['contestant' => '🏃', 'team_leader' => '👑', 'judge' => '⚖️', 'spectator' => '👁️'];
                    $role_names = ['contestant' => 'متسابق', 'team_leader' => 'قائد فريق', 'judge' => 'حكم', 'spectator' => 'متفرج'];
                    $is_active = ($selected_user && $selected_user['id'] == $user['id']) ? 'active' : '';
                ?>
                    <a href="?comp_id=<?php echo $comp_id; ?>&user_id=<?php echo $user['id']; ?>" 
                       class="user-item <?php echo $is_active; ?>">
                        <div><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></div>
                        <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="role-badge"><?php echo $role_icons[$user['role']] ?? ''; ?> <?php echo $role_names[$user['role']] ?? $user['role']; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- نموذج تغيير كلمة المرور -->
            <?php if ($selected_user): 
                $role_names = ['contestant' => 'متسابق', 'team_leader' => 'قائد فريق', 'judge' => 'حكم', 'spectator' => 'متفرج'];
                $role_icons = ['contestant' => '🏃', 'team_leader' => '👑', 'judge' => '⚖️', 'spectator' => '👁️'];
            ?>
                <div class="password-form">
                    <div class="selected-user-info">
                        <div>
                            <strong><?php echo htmlspecialchars($selected_user['full_name']); ?></strong>
                            <span style="color: var(--text3); font-size: 13px;">(@<?php echo htmlspecialchars($selected_user['username']); ?>)</span>
                        </div>
                        <span class="badge badge-p"><?php echo $role_icons[$selected_user['role']] ?? ''; ?> <?php echo $role_names[$selected_user['role']] ?? $selected_user['role']; ?></span>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                        
                        <div class="form-group">
                            <label>🔑 كلمة المرور الجديدة</label>
                            <input type="text" name="new_password" id="new_password" placeholder="أدخل كلمة المرور الجديدة" required minlength="4">
                        </div>
                        <div class="form-group">
                            <label>✓ تأكيد كلمة المرور</label>
                            <input type="text" name="confirm_password" id="confirm_password" placeholder="أعد كتابة كلمة المرور" required>
                        </div>
                        <button type="submit" name="reset_password" class="btn-primary-custom" onclick="return confirm('⚠️ هل أنت متأكد من تغيير كلمة مرور <?php echo htmlspecialchars($selected_user['full_name']); ?>؟');">
                            🔄 تغيير كلمة المرور
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text3);">
                    👆 اختر مستخدم من القائمة أعلاه لتغيير كلمة مروره
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">تغيير كلمة مرور مستخدم</span></p>
    </footer>

    <script>
        // البحث عن المستخدمين
        const searchInput = document.getElementById('searchUser');
        const userItems = document.querySelectorAll('.user-item');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                userItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // التحقق من تطابق كلمة المرور
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