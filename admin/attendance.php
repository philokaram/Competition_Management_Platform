<?php
session_start();
require_once '../config/db.php';

// منع الكاش عشان يضمن عرض البيانات الحديثة
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

// التحقق من صلاحيات الحكم أو الأدمن
$user_id = $_SESSION['user_id'];
$is_super = $_SESSION['is_super_admin'] ?? false;

$stmt = $pdo->prepare("SELECT role FROM competition_users WHERE user_id = ? AND competition_id = ?");
$stmt->execute([$user_id, $comp_id]);
$my_role = $stmt->fetchColumn();

if (!$is_super && $my_role !== 'judge') {
    header('Location: ../select_competition.php');
    exit;
}

// جلب معلومات المسابقة
$comp = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$comp->execute([$comp_id]);
$competition = $comp->fetch();

// ============================================
// جلب الإعدادات (درجات الحضور)
// ============================================
$settings = [];
$res = $pdo->query("SELECT * FROM settings");
foreach ($res->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$mass_pts = (int)($settings['mass_points'] ?? 10);
$choir_pts = (int)($settings['choir_points'] ?? 5);
$prayer_pts = (int)($settings['prayer_points'] ?? 7);

// ============================================
// معالجة تحديث الإعدادات
// ============================================
if (isset($_POST['update_settings'])) {
    $new_mass = (int)$_POST['mass_points'];
    $new_choir = (int)$_POST['choir_points'];
    $new_prayer = (int)$_POST['prayer_points'];
    
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'mass_points'")->execute([$new_mass]);
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'choir_points'")->execute([$new_choir]);
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'prayer_points'")->execute([$new_prayer]);
    
    $mass_pts = $new_mass;
    $choir_pts = $new_choir;
    $prayer_pts = $new_prayer;
    
    // تحديث المتغيرات المحلية
    $_SESSION['message'] = "تم تحديث إعدادات الدرجات";
    header("Location: attendance_new.php?comp_id=$comp_id&date=" . ($_GET['date'] ?? date('Y-m-d')) . "&type=" . ($_GET['type'] ?? 'mass'));
    exit;
}

// ============================================
// تحديد التاريخ والنوع المختار
// ============================================
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_type = $_GET['type'] ?? 'mass'; // mass, choir, prayer

// التحقق من صحة النوع
$valid_types = ['mass', 'choir', 'prayer'];
if (!in_array($selected_type, $valid_types)) {
    $selected_type = 'mass';
}

// جلب الفرق للفلتر
$teams = $pdo->prepare("SELECT id, name FROM teams WHERE competition_id = ? ORDER BY name");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();

$selected_team = $_GET['team'] ?? 'all';

// ============================================
// جلب المتسابقين (مع فلتر الفريق)
// ============================================
$sql = "
    SELECT u.id, u.full_name, t.name as team_name, t.id as team_id,
    cu.mass_score, cu.choir_score, cu.prayer_score, cu.interaction_score
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE cu.competition_id = ? AND cu.role = 'contestant'
";

if ($selected_team !== 'all') {
    $sql .= " AND cu.team_id = " . (int)$selected_team;
}

$sql .= " ORDER BY COALESCE(t.name, 'بدون فريق'), u.full_name";

$users = $pdo->prepare($sql);
$users->execute([$comp_id]);
$contestants = $users->fetchAll();

// ============================================
// جلب بيانات الحضور لليوم المختار
// ============================================
$attendance_data = [];
$stmt = $pdo->prepare("
    SELECT user_id, type 
    FROM attendance 
    WHERE competition_id = ? AND date = ?
");
$stmt->execute([$comp_id, $selected_date]);
foreach ($stmt->fetchAll() as $att) {
    $attendance_data[$att['user_id']][$att['type']] = true;
}

// ============================================
// معالجة AJAX requests (تسجيل/إلغاء الحضور + إضافة درجات)
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $target_user_id = (int)$_POST['user_id'];
    $att_type = $_POST['type'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');
    $points = (int)($_POST['points'] ?? 0);
    
    $response = ['success' => false, 'message' => 'حدث خطأ غير متوقع'];
    
    if ($action === 'mark_attendance' && in_array($att_type, $valid_types)) {
        // تسجيل الحضور
        $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND competition_id = ? AND date = ? AND type = ?");
        $check->execute([$target_user_id, $comp_id, $date, $att_type]);
        
        if (!$check->fetch()) {
            // إضافة سجل الحضور
            $pdo->prepare("INSERT INTO attendance (user_id, competition_id, date, type) VALUES (?, ?, ?, ?)")
                ->execute([$target_user_id, $comp_id, $date, $att_type]);
            
            // تحديد عدد النقاط حسب النوع
            $pts = match($att_type) {
                'mass' => $mass_pts,
                'choir' => $choir_pts,
                'prayer' => $prayer_pts,
                default => 0
            };
            
            // جلب القيمة القديمة
            $score_col = $att_type . '_score';
            $stmt = $pdo->prepare("SELECT $score_col FROM competition_users WHERE user_id = ? AND competition_id = ?");
            $stmt->execute([$target_user_id, $comp_id]);
            $old_val = (int)$stmt->fetchColumn();
            $new_val = $old_val + $pts;
            
            // تحديث الدرجة
            $pdo->prepare("UPDATE competition_users SET $score_col = $score_col + ? WHERE user_id = ? AND competition_id = ?")
                ->execute([$pts, $target_user_id, $comp_id]);
            
            // تسجيل في history
            $type_names = ['mass' => 'القداس', 'choir' => 'الخورس', 'prayer' => 'التسبحة'];
            $pdo->prepare("INSERT INTO score_history (competition_id, user_id, admin_id, score_type, points_change, old_value, new_value, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$comp_id, $target_user_id, $_SESSION['user_id'], $att_type, $pts, $old_val, $new_val, 
                          "تسجيل حضور " . $type_names[$att_type] . " بتاريخ $date"]);
            
            $response = ['success' => true, 'message' => 'تم تسجيل الحضور بنجاح', 'attended' => true];
        } else {
            $response = ['success' => false, 'message' => 'الحضور مسجل مسبقاً لهذا اليوم', 'attended' => true];
        }
    } 
    elseif ($action === 'cancel_attendance' && in_array($att_type, $valid_types)) {
        // إلغاء الحضور
        $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND competition_id = ? AND date = ? AND type = ?");
        $check->execute([$target_user_id, $comp_id, $date, $att_type]);
        $att_id = $check->fetchColumn();
        
        if ($att_id) {
            // حذف سجل الحضور
            $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$att_id]);
            
            // تحديد عدد النقاط حسب النوع
            $pts = match($att_type) {
                'mass' => $mass_pts,
                'choir' => $choir_pts,
                'prayer' => $prayer_pts,
                default => 0
            };
            
            // جلب القيمة القديمة
            $score_col = $att_type . '_score';
            $stmt = $pdo->prepare("SELECT $score_col FROM competition_users WHERE user_id = ? AND competition_id = ?");
            $stmt->execute([$target_user_id, $comp_id]);
            $old_val = (int)$stmt->fetchColumn();
            $new_val = $old_val - $pts;
            
            // تحديث الدرجة
            $pdo->prepare("UPDATE competition_users SET $score_col = $score_col - ? WHERE user_id = ? AND competition_id = ?")
                ->execute([$pts, $target_user_id, $comp_id]);
            
            // تسجيل في history
            $type_names = ['mass' => 'القداس', 'choir' => 'الخورس', 'prayer' => 'التسبحة'];
            $pdo->prepare("INSERT INTO score_history (competition_id, user_id, admin_id, score_type, points_change, old_value, new_value, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$comp_id, $target_user_id, $_SESSION['user_id'], $att_type, -$pts, $old_val, $new_val, 
                          "إلغاء حضور " . $type_names[$att_type] . " بتاريخ $date"]);
            
            $response = ['success' => true, 'message' => 'تم إلغاء الحضور', 'attended' => false];
        } else {
            $response = ['success' => false, 'message' => 'لا يوجد حضور مسجل لإلغائه', 'attended' => false];
        }
    }
    elseif ($action === 'add_bonus') {
        // إضافة درجات إضافية (بونص/تفاعل)
        $score_col = 'interaction_score';
        $stmt = $pdo->prepare("SELECT interaction_score FROM competition_users WHERE user_id = ? AND competition_id = ?");
        $stmt->execute([$target_user_id, $comp_id]);
        $old_val = (int)$stmt->fetchColumn();
        $new_val = $old_val + $points;
        
        $pdo->prepare("UPDATE competition_users SET interaction_score = interaction_score + ? WHERE user_id = ? AND competition_id = ?")
            ->execute([$points, $target_user_id, $comp_id]);
        
        // تسجيل في history
        $pdo->prepare("INSERT INTO score_history (competition_id, user_id, admin_id, score_type, points_change, old_value, new_value, notes) 
                      VALUES (?, ?, ?, 'interaction', ?, ?, ?, ?)")
            ->execute([$comp_id, $target_user_id, $_SESSION['user_id'], $points, $old_val, $new_val, 
                      "إضافة درجات إضافية بقيمة $points نقطة"]);
        
        $new_total = $new_val;
        $response = ['success' => true, 'message' => "تم إضافة $points نقطة بنجاح", 'new_total' => $new_total];
    }
    elseif ($action === 'mass_absence') {
        // غياب جماعي للجميع في النوع المختار
        $att_type = $_POST['type'] ?? 'mass';
        $date = $_POST['date'] ?? date('Y-m-d');
        $pts = match($att_type) {
            'mass' => $mass_pts,
            'choir' => $choir_pts,
            'prayer' => $prayer_pts,
            default => 0
        };
        
        // حذف كل سجلات الحضور لهذا النوع في هذا اليوم
        $pdo->prepare("DELETE FROM attendance WHERE competition_id = ? AND date = ? AND type = ?")
            ->execute([$comp_id, $date, $att_type]);
        
        // إعادة تعيين الدرجات للجميع (نخصم النقاط)
        $score_col = $att_type . '_score';
        
        // جلب كل المستخدمين أولاً
        $all_users = $pdo->prepare("SELECT user_id FROM competition_users WHERE competition_id = ? AND role = 'contestant'");
        $all_users->execute([$comp_id]);
        foreach ($all_users->fetchAll() as $u) {
            $stmt = $pdo->prepare("SELECT $score_col FROM competition_users WHERE user_id = ? AND competition_id = ?");
            $stmt->execute([$u['user_id'], $comp_id]);
            $old_val = (int)$stmt->fetchColumn();
            $new_val = 0; // إعادة تعيين للصفر (غياب يعني صفر)
            
            $pdo->prepare("UPDATE competition_users SET $score_col = 0 WHERE user_id = ? AND competition_id = ?")
                ->execute([$u['user_id'], $comp_id]);
            
            $type_names = ['mass' => 'القداس', 'choir' => 'الخورس', 'prayer' => 'التسبحة'];
            $pdo->prepare("INSERT INTO score_history (competition_id, user_id, admin_id, score_type, points_change, old_value, new_value, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$comp_id, $u['user_id'], $_SESSION['user_id'], $att_type, -$old_val, $old_val, $new_val, 
                          "تعيين غياب جماعي لـ " . $type_names[$att_type] . " بتاريخ $date - تم إلغاء كل الدرجات"]);
        }
        
        $response = ['success' => true, 'message' => 'تم تسجيل الغياب الجماعي بنجاح'];
    }
    
    echo json_encode($response);
    exit;
}

// جلب عدد الحضور لليوم الحالي للإحصائيات
$today_stats = [];
$stmt = $pdo->prepare("
    SELECT type, COUNT(DISTINCT user_id) as count 
    FROM attendance 
    WHERE competition_id = ? AND date = ?
    GROUP BY type
");
$stmt->execute([$comp_id, $selected_date]);
foreach ($stmt->fetchAll() as $stat) {
    $today_stats[$stat['type']] = $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تسجيل الحضور والغياب - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        * {
            box-sizing: border-box;
        }
        
        :root {
            --success: #1D9E75;
            --danger: #D85A30;
            --warning: #EF9F27;
            --primary: #7F77DD;
        }
        
        body {
            background: #0a0a15;
        }
        
        .main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem 3rem;
        }
        
        /* Settings Card */
        .settings-card {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .settings-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .settings-field {
            flex: 1;
            min-width: 100px;
        }
        
        .settings-field label {
            display: block;
            font-size: 12px;
            color: #a09aed;
            margin-bottom: 0.25rem;
        }
        
        .settings-field input {
            width: 100%;
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 10px;
            padding: 8px 12px;
            color: white;
        }
        
        /* Filters Bar */
        .filters-bar {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
        }
        
        .filter-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group label {
            color: #c4c4e0;
            font-size: 14px;
        }
        
        .filter-select, .date-input {
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 10px;
            padding: 8px 12px;
            color: white;
            font-family: 'Cairo', sans-serif;
        }
        
        .type-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .type-btn {
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 30px;
            padding: 8px 20px;
            color: #c4c4e0;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .type-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .type-btn.mass.active { background: var(--primary); }
        .type-btn.choir.active { background: var(--success); }
        .type-btn.prayer.active { background: var(--warning); }
        
        .mass-absence-btn {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: #e97a55;
            padding: 8px 16px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .mass-absence-btn:hover {
            background: rgba(216,90,48,0.3);
        }
        
        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-card.mass { border-top: 3px solid var(--primary); }
        .stat-card.choir { border-top: 3px solid var(--success); }
        .stat-card.prayer { border-top: 3px solid var(--warning); }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Space Mono', monospace;
        }
        
        .stat-label {
            font-size: 12px;
            color: #8a8aaa;
        }
        
        /* Table */
        .table-container {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            overflow-x: auto;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid #2d2d44;
            vertical-align: middle;
        }
        
        .attendance-table th {
            background: #0f0f1a;
            color: #a09aed;
            font-weight: 600;
        }
        
        .contestant-name {
            font-weight: 600;
            text-align: right;
        }
        
        .team-name {
            font-size: 11px;
            color: #8a8aaa;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-mark {
            background: linear-gradient(135deg, var(--success), #085041);
            border: none;
            color: white;
            padding: 6px 14px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .btn-mark.attended {
            background: #0f0f1a;
            border: 1px solid var(--success);
            color: var(--success);
            cursor: default;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, var(--danger), #4A1B0C);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-bonus {
            background: linear-gradient(135deg, var(--warning), #633806);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .bonus-input {
            width: 55px;
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 8px;
            padding: 4px 6px;
            color: white;
            text-align: center;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .toast-message {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #1a1a2e;
            border: 1px solid var(--success);
            color: var(--success);
            padding: 10px 20px;
            border-radius: 30px;
            z-index: 1000;
            animation: fadeOut 3s ease forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .main {
                padding: 0 1rem 2rem;
            }
            .mobile-menu-btn {
                display: block;
            }
            nav {
                display: none;
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: #1a1a2e;
                flex-direction: column;
                padding: 1rem;
                z-index: 1000;
            }
            nav.show {
                display: flex;
            }
            .settings-form {
                flex-direction: column;
            }
            .settings-field {
                width: 100%;
            }
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                justify-content: space-between;
            }
            .type-buttons {
                justify-content: center;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
    
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">✅</div>
                تسجيل الحضور والغياب
            </a>
<button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>            <nav>
                <a href="competition_home.php?id=<?php echo $comp_id; ?>">← العودة للمسابقة</a>
                <a href="dashboard.php">📋 كل المسابقات</a>
                <a href="competition_teams.php?id=<?php echo $comp_id; ?>">🏷️  الفرق</a>
                <a href="competition_users.php?id=<?php echo $comp_id; ?>">👥  المستخدمين</a>
                <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">✅ تسجيل الحضور</a>
                <a href="attendance_stats.php?comp_id=<?php echo $comp_id; ?>">📊 إحصائيات الحضور</a>
                <a href="score_history.php?comp_id=<?php echo $comp_id; ?>">📜 السجل </a>
                <a href="messages.php?comp_id=<?php echo $comp_id; ?>">💬 المحادثات</a>
                <a href="create_question.php?comp_id=<?php echo $comp_id; ?>">📅 سؤال اليوم</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 1rem 0 0.5rem;">
            <div class="hero-tag">✅ إدارة الحضور</div>
            <h1><?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>تسجيل وإلغاء حضور المتسابقين للقداس، الخورس، أو التسبحة</p>
        </div>

        <!-- Settings Card -->
        <div class="settings-card">
            <form method="POST" class="settings-form" id="settingsForm">
                <div class="settings-field">
                    <label>⛪ درجة القداس</label>
                    <input type="number" name="mass_points" value="<?php echo $mass_pts; ?>" required>
                </div>
                <div class="settings-field">
                    <label>🎵 درجة الخورس</label>
                    <input type="number" name="choir_points" value="<?php echo $choir_pts; ?>" required>
                </div>
                <div class="settings-field">
                    <label>🕊️ درجة التسبحة/العشية</label>
                    <input type="number" name="prayer_points" value="<?php echo $prayer_pts; ?>" required>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary" style="padding: 8px 20px;">حفظ الإعدادات</button>
            </form>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filter-group">
                <label>📅 التاريخ:</label>
                <input type="date" id="datePicker" class="date-input" value="<?php echo $selected_date; ?>">
            </div>
            
            <div class="filter-group">
                <label>🏷️ الفريق:</label>
                <select id="teamFilter" class="filter-select">
                    <option value="all" <?php echo $selected_team == 'all' ? 'selected' : ''; ?>>كل الفرق</option>
                    <?php foreach ($teams_list as $team): ?>
                        <option value="<?php echo $team['id']; ?>" <?php echo $selected_team == $team['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($team['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="type-buttons">
                <a href="?comp_id=<?php echo $comp_id; ?>&date=<?php echo $selected_date; ?>&type=mass&team=<?php echo $selected_team; ?>" 
                   class="type-btn mass <?php echo $selected_type == 'mass' ? 'active' : ''; ?>">⛪ قداس</a>
                <a href="?comp_id=<?php echo $comp_id; ?>&date=<?php echo $selected_date; ?>&type=choir&team=<?php echo $selected_team; ?>" 
                   class="type-btn choir <?php echo $selected_type == 'choir' ? 'active' : ''; ?>">🎵 خورس</a>
                <a href="?comp_id=<?php echo $comp_id; ?>&date=<?php echo $selected_date; ?>&type=prayer&team=<?php echo $selected_team; ?>" 
                   class="type-btn prayer <?php echo $selected_type == 'prayer' ? 'active' : ''; ?>">🕊️ تسبحة/عشية</a>
            </div>
            
            <button id="massAbsenceBtn" class="mass-absence-btn">❌ غياب <?php echo $selected_type == 'mass' ? 'قداس' : ($selected_type == 'choir' ? 'خورس' : 'تسبحة'); ?> للجميع</button>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card mass">
                <div class="stat-number"><?php echo $today_stats['mass'] ?? 0; ?></div>
                <div class="stat-label">سجلوا القداس</div>
            </div>
            <div class="stat-card choir">
                <div class="stat-number"><?php echo $today_stats['choir'] ?? 0; ?></div>
                <div class="stat-label">سجلوا الخورس</div>
            </div>
            <div class="stat-card prayer">
                <div class="stat-number"><?php echo $today_stats['prayer'] ?? 0; ?></div>
                <div class="stat-label">سجلوا التسبحة</div>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>المتسابق</th>
                        <th>الفريق</th>
                        <th><?php echo $selected_type == 'mass' ? '⛪ القداس' : ($selected_type == 'choir' ? '🎵 الخورس' : '🕊️ التسبحة/العشية'); ?> (<?php echo $selected_type == 'mass' ? $mass_pts : ($selected_type == 'choir' ? $choir_pts : $prayer_pts); ?> نقطة)</th>
                        <th>إجراءات إضافية</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <?php foreach ($contestants as $contestant): 
                        $is_attended = isset($attendance_data[$contestant['id']][$selected_type]);
                        $total_score = $contestant['mass_score'] + $contestant['choir_score'] + $contestant['prayer_score'] + $contestant['interaction_score'];
                    ?>
                    <tr data-user-id="<?php echo $contestant['id']; ?>">
                        <td class="contestant-name">
                            <?php echo htmlspecialchars($contestant['full_name']); ?>
                        </td>
                        <td class="team-name">
                            <?php echo $contestant['team_name'] ? htmlspecialchars($contestant['team_name']) : '—'; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($is_attended): ?>
                                    <button class="btn-mark attended" disabled>✅ تم التسجيل</button>
                                    <button class="btn-cancel cancel-attendance" data-user-id="<?php echo $contestant['id']; ?>">❌ إلغاء الحضور</button>
                                <?php else: ?>
                                    <button class="btn-mark mark-attendance" data-user-id="<?php echo $contestant['id']; ?>">✅ تسجيل حضور</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <input type="number" id="bonus_<?php echo $contestant['id']; ?>" class="bonus-input" placeholder="نقاط" value="0" step="5">
                                <button class="btn-bonus add-bonus" data-user-id="<?php echo $contestant['id']; ?>">➕ إضافة</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($contestants)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px;">
                            لا يوجد متسابقون <?php echo $selected_team != 'all' ? 'في هذا الفريق' : ''; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:#a09aed">تسجيل الحضور والغياب</span></p>
    </footer>

   <script>
    // DOM Elements
    const datePicker = document.getElementById('datePicker');
    const teamFilter = document.getElementById('teamFilter');
    const massAbsenceBtn = document.getElementById('massAbsenceBtn');
    const selectedType = '<?php echo $selected_type; ?>';
    const compId = '<?php echo $comp_id; ?>';
    
    // Helper function to show toast message
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.style.borderColor = isError ? '#D85A30' : '#1D9E75';
        toast.style.color = isError ? '#e97a55' : '#2dcea0';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Helper function to update UI after action
 function updateRowAfterAction(userId, isAttended) {
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (!row) return;
    
    const actionsCell = row.querySelector('td:nth-child(3) .action-buttons');
    if (actionsCell) {
        if (isAttended) {
            actionsCell.innerHTML = `
                <button class="btn-mark attended" disabled>✅ تم التسجيل</button>
                <button class="btn-cancel cancel-attendance" data-user-id="${userId}">❌ إلغاء الحضور</button>
            `;
        } else {
            actionsCell.innerHTML = `
                <button class="btn-mark mark-attendance" data-user-id="${userId}">✅ تسجيل حضور</button>
            `;
        }
        
        attachEventListenersToRow(row);
    }
    
    // ✅ كمان نحدث عدد الحضور في الإحصائيات من غير ريلود
    updateStatsCount(isAttended);
}

// ✅添加 دالة جديدة لتحديث الإحصائيات
function updateStatsCount(isAdding) {
    const selectedType = '<?php echo $selected_type; ?>';
    let statCard, statNumber;
    
    if (selectedType === 'mass') {
        statCard = document.querySelector('.stat-card.mass .stat-number');
    } else if (selectedType === 'choir') {
        statCard = document.querySelector('.stat-card.choir .stat-number');
    } else {
        statCard = document.querySelector('.stat-card.prayer .stat-number');
    }
    
    if (statCard) {
        let currentCount = parseInt(statCard.textContent) || 0;
        let newCount = isAdding ? currentCount + 1 : Math.max(0, currentCount - 1);
        statCard.textContent = newCount;
    }
}
    
    // Function to attach event listeners to buttons in a row
    function attachEventListenersToRow(row) {
        const markBtn = row.querySelector('.mark-attendance');
        const cancelBtn = row.querySelector('.cancel-attendance');
        const addBonusBtn = row.querySelector('.add-bonus');
        const bonusInput = row.querySelector('.bonus-input');
        
        if (markBtn) {
            markBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const userId = this.dataset.userId;
                markAttendance(userId);
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const userId = this.dataset.userId;
                cancelAttendance(userId);
            });
        }
        
        if (addBonusBtn && bonusInput) {
            addBonusBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const userId = this.dataset.userId;
                const points = parseInt(bonusInput.value) || 0;
                if (points !== 0) {
                    addBonusPoints(userId, points);
                } else {
                    showToast('الرجاء إدخال عدد النقاط', true);
                }
            });
        }
    }
    
    // AJAX Functions
    function markAttendance(userId) {
        fetch(window.location.pathname + '?comp_id=' + compId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=mark_attendance&user_id=${userId}&type=${selectedType}&date=${datePicker.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                updateRowAfterAction(userId, true);
            } else {
                showToast(data.message, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ في الاتصال', true);
        });
    }
    
    function cancelAttendance(userId) {
        fetch(window.location.pathname + '?comp_id=' + compId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=cancel_attendance&user_id=${userId}&type=${selectedType}&date=${datePicker.value}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                updateRowAfterAction(userId, false);
            } else {
                showToast(data.message, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ في الاتصال', true);
        });
    }
    
    function addBonusPoints(userId, points) {
        fetch(window.location.pathname + '?comp_id=' + compId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=add_bonus&user_id=${userId}&points=${points}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message);
                // Update total score without reload
                const row = document.querySelector(`tr[data-user-id="${userId}"]`);
                const scoreDiv = row.querySelector('.current-score');
                if (scoreDiv) {
                    const currentText = scoreDiv.textContent;
                    const match = currentText.match(/\d+/);
                    if (match) {
                        const newTotal = parseInt(match[0]) + points;
                        scoreDiv.textContent = `إجمالي نقاطه: ${newTotal}`;
                    }
                }
                // Clear bonus input
                const bonusInput = document.querySelector(`#bonus_${userId}`);
                if (bonusInput) bonusInput.value = '0';
            } else {
                showToast(data.message, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ في الاتصال', true);
        });
    }
    
   function massAbsence() {
    if (!confirm('⚠️ هل أنت متأكد من تسجيل غياب الجميع؟ هذا سيحذف كل درجات الحضور لهذا اليوم لهذا النوع.')) {
        return;
    }
    
    fetch(window.location.pathname + '?comp_id=' + compId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=mass_absence&type=${selectedType}&date=${datePicker.value}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            // 🔴 امسحي السطر ده
            // location.reload();
            
            // ✅ بدلاً من كده، نحدث كل الصفوف
            document.querySelectorAll('#attendanceTableBody tr').forEach(row => {
                const userId = row.dataset.userId;
                const actionsCell = row.querySelector('td:nth-child(3) .action-buttons');
                if (actionsCell) {
                    actionsCell.innerHTML = `<button class="btn-mark mark-attendance" data-user-id="${userId}">✅ تسجيل حضور</button>`;
                    attachEventListenersToRow(row);
                }
            });
            
            // تحديث الإحصائيات للصفر
            const statCard = document.querySelector('.stat-card.' + selectedType + ' .stat-number');
            if (statCard) statCard.textContent = '0';
        } else {
            showToast(data.message, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('حدث خطأ في الاتصال', true);
    });
}
    
    // Event Listeners
    if (datePicker) {
        datePicker.addEventListener('change', function() {
            window.location.href = `attendance_new.php?comp_id=${compId}&date=${this.value}&type=${selectedType}&team=${teamFilter.value}`;
        });
    }
    
    if (teamFilter) {
        teamFilter.addEventListener('change', function() {
            window.location.href = `attendance_new.php?comp_id=${compId}&date=${datePicker.value}&type=${selectedType}&team=${this.value}`;
        });
    }
    
    if (massAbsenceBtn) {
        massAbsenceBtn.addEventListener('click', massAbsence);
    }
    
    // Attach event listeners to existing buttons on page load
    document.querySelectorAll('#attendanceTableBody tr').forEach(row => {
        attachEventListenersToRow(row);
    });
    
    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.querySelector('nav');
    
    if (mobileBtn && navMenu) {
        mobileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function(event) {
            if (!navMenu.contains(event.target) && !mobileBtn.contains(event.target)) {
                navMenu.classList.remove('show');
            }
        });
    }
</script>
</body>
</html>