<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

// Check permissions - judges cannot modify roles
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
// معالجة رفع CSV
// ============================================
if (isset($_POST['upload_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
    $filename = $_FILES['csv_file']['tmp_name'];
    
    // التحقق من نوع الملف
    $file_info = pathinfo($_FILES['csv_file']['name']);
    if (strtolower($file_info['extension']) != 'csv') {
        $error = "الرجاء رفع ملف CSV فقط";
    } else {
        $file = fopen($filename, "r");
        $row_count = 0;
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        // قراءة السطر الأول (العناوين) وتخطيه
        $header = fgetcsv($file, 1000, ",");
        
        // التحقق من صحة العناوين
        $expected_header = ['الاسم الكامل', 'اسم المستخدم', 'كلمة المرور', 'رقم الفريق', 'الدور'];
        if (!$header || count($header) < 5) {
            $error = "تنسيق الملف غير صحيح. يجب أن يكون: الاسم الكامل, اسم المستخدم, كلمة المرور, رقم الفريق, الدور";
        } else {
            // معالجة الصفوف
            while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
                $row_count++;
                
                // تخطي الصفوف الفارغة
                if (empty($data[0]) || empty($data[1]) || empty($data[2])) {
                    $error_count++;
                    $errors[] = "الصف $row_count: بيانات ناقصة";
                    continue;
                }
                
                $full_name = trim($data[0]);
                $username = trim($data[1]);
                $password = trim($data[2]);
                $team_id = isset($data[3]) ? (int)trim($data[3]) : null;
                $role = isset($data[4]) ? trim($data[4]) : 'contestant';
                
                // التحقق من صحة الدور
                $valid_roles = ['judge', 'contestant', 'spectator', 'team_leader'];
                if (!in_array($role, $valid_roles)) {
                    $role = 'contestant';
                }
                
                // إذا كان رقم الفريق 0 أو فارغ، نضعه NULL
                if ($team_id == 0 || empty($team_id)) {
                    $team_id = null;
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // التحقق من وجود المستخدم
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $existing_user = $stmt->fetch();
                    
                    if ($existing_user) {
                        $u_id = $existing_user['id'];
                    } else {
                        // إنشاء مستخدم جديد
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
                        $stmt->execute([$username, $hashed_password, $full_name]);
                        $u_id = $pdo->lastInsertId();
                    }
                    
                    // ربط المستخدم بالمسابقة
                    $stmt = $pdo->prepare("
                        INSERT INTO competition_users (competition_id, user_id, team_id, role, mass_score, choir_score, prayer_score, interaction_score) 
                        VALUES (?, ?, ?, ?, 0, 0, 0, 0)
                        ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), role = VALUES(role)
                    ");
                    $stmt->execute([$comp_id, $u_id, $team_id, $role]);
                    
                    $pdo->commit();
                    $success_count++;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_count++;
                    $errors[] = "الصف $row_count: " . $e->getMessage();
                }
            }
        }
        fclose($file);
        
        if ($success_count > 0) {
            $message = "تم استيراد $success_count مستخدم بنجاح";
            if ($error_count > 0) {
                $message .= "، فشل $error_count مستخدم (تحقق من الأخطاء أدناه)";
                $error = implode('<br>', $errors);
            }
        } else {
            $error = "فشل استيراد جميع المستخدمين. تأكد من تنسيق الملف: الاسم الكامل, اسم المستخدم, كلمة المرور, رقم الفريق, الدور";
            if (!empty($errors)) {
                $error .= '<br>' . implode('<br>', $errors);
            }
        }
    }
}

// ============================================
// باقي المعالجات (إضافة مستخدم فردي، تغيير دور، إلخ)
// ============================================

// Handle Single User Addition
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $team_id = $_POST['team_id'] ?: null;
    $role = $_POST['role'] ?? 'contestant';
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $full_name]);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $u_id = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("INSERT INTO competition_users (competition_id, user_id, team_id, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$comp_id, $u_id, $team_id, $role]);
        
        $pdo->commit();
        $message = "تم إضافة المستخدم للمسابقة";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطأ: " . $e->getMessage();
    }
}

// Handle Change User Team 
if (isset($_POST['change_user_team'])) {
    $target_uid = (int)$_POST['user_id'];
    $new_team_id = $_POST['team_id'] ? (int)$_POST['team_id'] : null;
    $stmt = $pdo->prepare("UPDATE competition_users SET team_id = ? WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$new_team_id, $target_uid, $comp_id]);
    $message = "تم تغيير فريق المتسابق";
}

// Handle User Role Update - Only Super Admin can change roles
if ($is_super && isset($_POST['update_user_role'])) {
    $target_uid = $_POST['target_user_id'];
    $new_role = $_POST['new_role'];
    $stmt = $pdo->prepare("UPDATE competition_users SET role = ? WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$new_role, $target_uid, $comp_id]);
    $message = "تم تحديث دور المستخدم";
}

// Handle User Deletion
if (isset($_GET['delete_user'])) {
    $stmt = $pdo->prepare("DELETE FROM competition_users WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$_GET['delete_user'], $comp_id]);
    $message = "تم حذف المستخدم من المسابقة";
}

$users = $pdo->prepare("
    SELECT u.id, u.full_name, u.username, cu.role, cu.team_id, t.name as team_name,
    (cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score) as total_score
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE cu.competition_id = ?
    ORDER BY FIELD(cu.role, 'judge', 'team_leader', 'contestant', 'spectator'), u.full_name
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

$teams = $pdo->prepare("SELECT * FROM teams WHERE competition_id = ? ORDER BY name");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .upload-area:hover {
            border-color: var(--purple);
            background: rgba(127,119,221,0.05);
        }
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: var(--coral-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">👥</div>
                إدارة المستخدمين
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="competition_home.php?id=<?php echo $comp_id; ?>">← العودة للمسابقة</a>
                <a href="dashboard.php">📋 كل المسابقات</a>
                <a href="competition_teams.php?id=<?php echo $comp_id; ?>">🏷️ الفرق</a>
                <a href="competition_users.php?id=<?php echo $comp_id; ?>">👥 المستخدمين</a>
                <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">✅ الحضور</a>
                <a href="score_history.php?comp_id=<?php echo $comp_id; ?>">📜 السجل</a>
                <a href="messages.php?comp_id=<?php echo $comp_id; ?>">💬 المحادثات</a>
                <a href="create_question.php?comp_id=<?php echo $comp_id; ?>">📅 سؤال اليوم</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">👥 إدارة</div>
            <h1>المستخدمين في <?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>إضافة مستخدمين جدد أو تعديل أدوارهم</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Manual Add -->
            <div class="comp-card c-purple reveal">
                <div class="comp-title">✏️ إضافة مستخدم جديد</div>
                <form method="POST">
                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="text" name="full_name" class="field-input with-icon" placeholder="الاسم الكامل" required>
                            <span class="input-icon">👤</span>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="text" name="username" class="field-input with-icon" placeholder="اسم المستخدم" required>
                            <span class="input-icon">🔑</span>
                        </div>
                    </div>
                    <div class="field-group">
                        <div class="input-wrap">
                            <input type="password" name="password" class="field-input with-icon" placeholder="كلمة المرور" required>
                            <span class="input-icon">🔒</span>
                        </div>
                    </div>
                    <div class="field-group">
                        <select name="team_id" class="field-input">
                            <option value="">بدون فريق</option>
                            <?php foreach ($teams_list as $team): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <select name="role" class="field-input">
                            <option value="contestant">🏃 متسابق</option>
                            <option value="team_leader">👑 قائد فريق</option>
                            <option value="judge">🛡 حكم</option>
                            <option value="spectator">👁 متفرج</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-success" style="width:100%;">+ إضافة للمسابقة</button>
                </form>
            </div>

            <!-- CSV Upload -->
            <div class="comp-card c-teal reveal">
                <div class="comp-title">📁 رفع من ملف (CSV)</div>
                <p class="helper-text">التنسيق: الاسم الكامل، اسم المستخدم، كلمة المرور، رقم الفريق، الدور</p>
                <p class="helper-text" style="color: var(--gold-l);">مثال: مريم غالي,mariam_ghaly,123456,1,contestant</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" onclick="document.getElementById('csvInput').click()">
                        📂 اضغط هنا لاختيار ملف CSV<br>
                        <small>يدعم ملفات CSV فقط</small>
                    </div>
                    <input type="file" name="csv_file" id="csvInput" accept=".csv" style="display:none" onchange="this.closest('form').submit();">
                    <button type="submit" name="upload_csv" class="btn btn-primary" style="width:100%;">📤 رفع الملف</button>
                </form>
            </div>
        </div>

        <div class="section-label reveal" style="margin-top: 2rem;">
            <span class="section-num">📋</span>
            <h2>قائمة المستخدمين</h2>
            <div class="line"></div>
        </div>

        <div class="table-responsive reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الفريق</th>
                        <th>الدور</th>
                        <th>إجمالي النقاط</th>
                        <?php if ($is_super): ?>
                        <th>تغيير الدور</th>
                        <th>تغيير الفريق</th>
                        <th>حذف</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users_list as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                        <td><?php echo $user['team_name'] ? htmlspecialchars($user['team_name']) : '—'; ?></td>
                        <td>
                            <span class="badge <?php 
                                echo $user['role'] == 'judge' ? 'badge-p' : ($user['role'] == 'contestant' ? 'badge-t' : ($user['role'] == 'team_leader' ? 'badge-g' : 'badge-c')); 
                            ?>">
                                <?php 
                                    $role_names = ['judge'=>'🛡 حكم', 'contestant'=>'🏃 متسابق', 'spectator'=>'👁 متفرج', 'team_leader' => '👑 قائد فريق'];
                                    echo $role_names[$user['role']];
                                ?>
                            </span>
                        </td>
                        <td class="score-cell"><?php echo number_format($user['total_score']); ?></td>
                        <?php if ($is_super): ?>
                        <td>
                            <form method="POST" class="form-inline-small">
                                <input type="hidden" name="target_user_id" value="<?php echo $user['id']; ?>">
                                <select name="new_role" class="field-input-sm">
                                    <option value="contestant" <?php if($user['role']=='contestant') echo 'selected'; ?>>متسابق</option>
                                    <option value="team_leader" <?php if($user['role']=='team_leader') echo 'selected'; ?>>قائد فريق</option>
                                    <option value="judge" <?php if($user['role']=='judge') echo 'selected'; ?>>حكم</option>
                                    <option value="spectator" <?php if($user['role']=='spectator') echo 'selected'; ?>>متفرج</option>
                                </select>
                                <button type="submit" name="update_user_role" class="btn btn-primary btn-sm">تحديث</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" class="form-inline-small">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="team_id" class="field-input-sm">
                                    <option value="">بدون فريق</option>
                                    <?php foreach ($teams_list as $team): ?>
                                        <option value="<?php echo $team['id']; ?>" <?php echo ($user['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="change_user_team" class="btn btn-primary btn-sm">تغيير</button>
                            </form>
                        </td>
                        <td>
                            <a href="?id=<?php echo $comp_id; ?>&delete_user=<?php echo $user['id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('هل أنت متأكد من حذف <?php echo htmlspecialchars($user['full_name']); ?> من المسابقة؟')">
                                🗑
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">إدارة المستخدمين</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>