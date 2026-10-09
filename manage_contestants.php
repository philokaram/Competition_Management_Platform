<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

// التحقق من الصلاحيات
$user_id = $_SESSION['user_id'];
$is_super = $_SESSION['is_super_admin'] ?? false;

$stmt = $pdo->prepare("SELECT role FROM competition_users WHERE user_id = ? AND competition_id = ?");
$stmt->execute([$user_id, $comp_id]);
$my_role = $stmt->fetchColumn();

if (!$is_super && $my_role !== 'judge') {
    header('Location: ../select_competition.php');
    exit;
}

$comp = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$comp->execute([$comp_id]);
$competition = $comp->fetch();

$message = '';
$error = '';

// ============================================
// معالجة تحديث بيانات المتسابق
// ============================================
if ($is_super && isset($_POST['update_contestant'])) {
    $target_uid = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $team_id = $_POST['team_id'] ? (int)$_POST['team_id'] : null;
    $role = $_POST['role'];
    
    $errors = [];
    
    if (empty($full_name)) $errors[] = "الاسم الكامل مطلوب";
    if (empty($username)) $errors[] = "اسم المستخدم مطلوب";
    
    if (empty($errors)) {
        // التحقق من عدم تكرار اسم المستخدم
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $target_uid]);
        if ($stmt->fetch()) {
            $errors[] = "اسم المستخدم '$username' مستخدم بالفعل";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // تحديث بيانات المستخدم
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $hashed, $target_uid]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $target_uid]);
            }
            
            // تحديث بيانات المتسابق في المسابقة
            $stmt = $pdo->prepare("UPDATE competition_users SET team_id = ?, role = ? WHERE user_id = ? AND competition_id = ?");
            $stmt->execute([$team_id, $role, $target_uid, $comp_id]);
            
            $pdo->commit();
            $message = "تم تحديث بيانات المتسابق بنجاح";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطأ: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// ============================================
// معالجة حذف المتسابق
// ============================================
if (isset($_GET['delete_user'])) {
    $stmt = $pdo->prepare("DELETE FROM competition_users WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$_GET['delete_user'], $comp_id]);
    $message = "تم حذف المتسابق من المسابقة";
}

// ============================================
// جلب المتسابقين
// ============================================
$users = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, cu.role, cu.team_id, t.name as team_name,
    cu.mass_score, cu.choir_score, cu.prayer_score, cu.interaction_score,
    (cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score) as total_score
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE cu.competition_id = ? AND cu.role IN ('contestant', 'team_leader')
    ORDER BY u.full_name
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

// جلب الفرق
$teams = $pdo->prepare("SELECT * FROM teams WHERE competition_id = ? ORDER BY name");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();

// متغير لتحديد المتسابق الجاري تعديله
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, cu.role, cu.team_id
        FROM users u
        JOIN competition_users cu ON u.id = cu.user_id
        WHERE u.id = ? AND cu.competition_id = ?
    ");
    $stmt->execute([$_GET['edit'], $comp_id]);
    $edit_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المتسابقين - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .edit-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .edit-panel h3 {
            color: var(--purple-l);
            margin-bottom: 1rem;
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .form-grid-2 .full-width {
            grid-column: span 2;
        }
        .field-group {
            margin-bottom: 1rem;
        }
        .field-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text2);
            font-size: 13px;
        }
        .field-group input, .field-group select {
            width: 100%;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            color: var(--text);
            font-family: var(--font-ar);
        }
        .field-group .help-text {
            font-size: 11px;
            color: var(--text3);
            margin-top: 4px;
        }
        .btn-edit {
            background: var(--warning);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-edit:hover {
            opacity: 0.8;
        }
        .score-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: var(--bg2);
            border: 1px solid var(--border);
        }
        .edit-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }
            .form-grid-2 .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">👤</div>
                إدارة المتسابقين
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="competition_home.php?id=<?php echo $comp_id; ?>">← العودة للمسابقة</a>
                <a href="dashboard.php">📋 كل المسابقات</a>
                <a href="competition_teams.php?id=<?php echo $comp_id; ?>">🏷️ الفرق</a>
                <a href="competition_users.php?id=<?php echo $comp_id; ?>">👥 المستخدمين</a>
                <a href="manage_contestants.php?comp_id=<?php echo $comp_id; ?>">👤 المتسابقين</a>
                <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">✅ الحضور</a>
                <a href="score_history.php?comp_id=<?php echo $comp_id; ?>">📜 السجل</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">👤 إدارة المتسابقين</div>
            <h1>المتسابقين في <?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>عرض وتعديل بيانات جميع المتسابقين وقادة الفرق</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($is_super && $edit_user): ?>
        <!-- لوحة التعديل -->
        <div class="edit-panel">
            <h3>✏️ تعديل بيانات: <?php echo htmlspecialchars($edit_user['full_name']); ?></h3>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <div class="form-grid-2">
                    <div class="field-group">
                        <label>👤 الاسم الكامل</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
                    </div>
                    <div class="field-group">
                        <label>🔑 اسم المستخدم</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                    </div>
                    <div class="field-group">
                        <label>🔒 كلمة المرور الجديدة</label>
                        <input type="text" name="password" placeholder="اتركه فارغاً إذا لم ترد التغيير">
                        <div class="help-text">أدخل كلمة مرور جديدة لتغييرها</div>
                    </div>
                    <div class="field-group">
                        <label>🏷️ الفريق</label>
                        <select name="team_id">
                            <option value="">بدون فريق</option>
                            <?php foreach ($teams_list as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo ($edit_user['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group full-width">
                        <label>👑 الدور</label>
                        <select name="role">
                            <option value="contestant" <?php echo ($edit_user['role'] == 'contestant') ? 'selected' : ''; ?>>🏃 متسابق</option>
                            <option value="team_leader" <?php echo ($edit_user['role'] == 'team_leader') ? 'selected' : ''; ?>>👑 قائد فريق</option>
                        </select>
                        <div class="help-text">يمكنك تغيير دور المتسابق إلى قائد فريق والعكس</div>
                    </div>
                </div>
                <div class="edit-actions">
                    <button type="submit" name="update_contestant" class="btn btn-primary">💾 حفظ التغييرات</button>
                    <a href="?comp_id=<?php echo $comp_id; ?>" class="btn btn-secondary">❌ إلغاء</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- جدول المتسابقين -->
        <div class="table-responsive reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الفريق</th>
                        <th>الدور</th>
                        <th>القداس</th>
                        <th>الخورس</th>
                        <th>التسبحة</th>
                        <th>التفاعل</th>
                        <th>الإجمالي</th>
                        <?php if ($is_super): ?>
                        <th>الإجراءات</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="<?php echo $is_super ? 11 : 10; ?>" style="text-align: center; padding: 40px; color: var(--text3);">
                                📭 لا يوجد متسابقين في هذه المسابقة
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $index => $user): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                            <td><?php echo $user['team_name'] ? htmlspecialchars($user['team_name']) : '—'; ?></td>
                            <td>
                                <span class="badge <?php echo $user['role'] == 'team_leader' ? 'badge-g' : 'badge-t'; ?>">
                                    <?php echo $user['role'] == 'team_leader' ? '👑 قائد فريق' : '🏃 متسابق'; ?>
                                </span>
                            </td>
                            <td><?php echo number_format($user['mass_score']); ?></td>
                            <td><?php echo number_format($user['choir_score']); ?></td>
                            <td><?php echo number_format($user['prayer_score']); ?></td>
                            <td><?php echo number_format($user['interaction_score']); ?></td>
                            <td class="score-cell"><?php echo number_format($user['total_score']); ?></td>
                            <?php if ($is_super): ?>
                            <td>
                                <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                                    <a href="?comp_id=<?php echo $comp_id; ?>&edit=<?php echo $user['id']; ?>" class="btn-edit">✏️ تعديل</a>
                                    <a href="?comp_id=<?php echo $comp_id; ?>&delete_user=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('هل أنت متأكد من حذف <?php echo htmlspecialchars($user['full_name']); ?> من المسابقة؟')">
                                        🗑
                                    </a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">إدارة المتسابقين</span></p>
    </footer>

    <script>
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>