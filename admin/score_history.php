<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['comp_id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

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

// ============================================
// جلب الفلاتر من URL
// ============================================
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_user = $_GET['filter_user'] ?? 'all';
$filter_admin = $_GET['filter_admin'] ?? 'all';
$filter_team = $_GET['filter_team'] ?? 'all';
$filter_date = $_GET['filter_date'] ?? '';

// ✅ جلب المتسابقين فقط للفلتر (استبعاد الأدمن)
$users_list_filter = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u
    JOIN score_history sh ON u.id = sh.user_id
    JOIN competition_users cu ON u.id = cu.user_id
    WHERE sh.competition_id = ? 
        AND sh.user_id != 0
        AND cu.role = 'contestant'
        AND cu.competition_id = ?
    ORDER BY u.full_name
");
$users_list_filter->execute([$comp_id, $comp_id]);
$users_for_filter = $users_list_filter->fetchAll();

// ✅ جلب الأدمن للفلتر (اللي قاموا بالتعديلات)
$admins_list_filter = $pdo->prepare("
    SELECT DISTINCT a.id, a.full_name 
    FROM users a
    JOIN score_history sh ON a.id = sh.admin_id
    WHERE sh.competition_id = ?
    ORDER BY a.full_name
");
$admins_list_filter->execute([$comp_id]);
$admins_for_filter = $admins_list_filter->fetchAll();

// ✅ جلب الفرق للفلتر
$teams_list_filter = $pdo->prepare("
    SELECT DISTINCT t.id, t.name 
    FROM teams t
    JOIN competition_users cu ON t.id = cu.team_id
    JOIN score_history sh ON cu.user_id = sh.user_id
    WHERE sh.competition_id = ? AND cu.role = 'contestant'
    ORDER BY t.name
");
$teams_list_filter->execute([$comp_id]);
$teams_for_filter = $teams_list_filter->fetchAll();

// ============================================
// بناء الاستعلام مع الفلاتر
// ============================================
$history_sql = "
    SELECT h.*, u.full_name as user_name, a.full_name as admin_name, t.name as team_name
    FROM score_history h
    LEFT JOIN users u ON h.user_id = u.id
    LEFT JOIN users a ON h.admin_id = a.id
    LEFT JOIN competition_users cu ON u.id = cu.user_id AND cu.competition_id = ?
    LEFT JOIN teams t ON cu.team_id = t.id
    WHERE h.competition_id = ?
";
$params = [$comp_id, $comp_id];

if ($filter_type !== 'all') {
    $history_sql .= " AND h.score_type = ?";
    $params[] = $filter_type;
}
if ($filter_user !== 'all') {
    $history_sql .= " AND h.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_admin !== 'all') {
    $history_sql .= " AND h.admin_id = ?";
    $params[] = $filter_admin;
}
if ($filter_team !== 'all') {
    $history_sql .= " AND cu.team_id = ?";
    $params[] = $filter_team;
}
if (!empty($filter_date)) {
    $history_sql .= " AND DATE(h.created_at) = ?";
    $params[] = $filter_date;
}

$history_sql .= " ORDER BY h.created_at DESC LIMIT 500";

$history = $pdo->prepare($history_sql);
$history->execute($params);
$history_list = $history->fetchAll();

$type_names = [
    'mass' => 'القداس',
    'choir' => 'الخورس',
    'prayer' => 'التسبحة',
    'interaction' => 'التفاعل',
    'team_bonus' => 'بونص فريق'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل التعديلات - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Filters bar styles */
        .filters-bar {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .filter-group label {
            font-size: 12px;
            color: #a09aed;
        }
        .filter-select {
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 10px;
            padding: 8px 12px;
            color: white;
            font-family: 'Cairo', sans-serif;
        }
        .filter-select option {
            background: #1a1a2e;
        }
        @media (max-width: 768px) {
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                width: 100%;
            }
            .filter-select {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">📜</div>
                سجل التعديلات
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
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
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">📜 التاريخ</div>
            <h1>سجل تعديلات الدرجات</h1>
            <p>متابعة جميع التغييرات التي تمت على درجات المتسابقين والفرق</p>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <form method="GET" class="filters-form">
                <input type="hidden" name="comp_id" value="<?php echo $comp_id; ?>">
                
                <div class="filter-group">
                    <label>📋 نوع التعديل:</label>
                    <select name="filter_type" class="filter-select">
                        <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="mass" <?php echo $filter_type == 'mass' ? 'selected' : ''; ?>>⛪ القداس</option>
                        <option value="choir" <?php echo $filter_type == 'choir' ? 'selected' : ''; ?>>🎵 الخورس</option>
                        <option value="prayer" <?php echo $filter_type == 'prayer' ? 'selected' : ''; ?>>🕊️ التسبحة</option>
                        <option value="interaction" <?php echo $filter_type == 'interaction' ? 'selected' : ''; ?>>💬 التفاعل</option>
                        <option value="team_bonus" <?php echo $filter_type == 'team_bonus' ? 'selected' : ''; ?>>🏷️ بونص فريق</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>🏷️ الفريق:</label>
                    <select name="filter_team" class="filter-select">
                        <option value="all" <?php echo $filter_team == 'all' ? 'selected' : ''; ?>>كل الفرق</option>
                        <?php foreach ($teams_for_filter as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $filter_team == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>👤 المتسابق:</label>
                    <select name="filter_user" class="filter-select">
                        <option value="all" <?php echo $filter_user == 'all' ? 'selected' : ''; ?>>الكل</option>
                        <?php foreach ($users_for_filter as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>👨‍💼 تم بواسطة:</label>
                    <select name="filter_admin" class="filter-select">
                        <option value="all" <?php echo $filter_admin == 'all' ? 'selected' : ''; ?>>الكل</option>
                        <?php foreach ($admins_for_filter as $a): ?>
                            <option value="<?php echo $a['id']; ?>" <?php echo $filter_admin == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>📅 التاريخ:</label>
                    <input type="date" name="filter_date" class="filter-select" value="<?php echo $filter_date; ?>">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary btn-sm">🔍 بحث</button>
                        <a href="?comp_id=<?php echo $comp_id; ?>" class="btn btn-secondary btn-sm">🗑️ إلغاء</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المتسابق</th>
                        <th>الفريق</th>
                        <th>نوع التعديل</th>
                        <th>التغيير</th>
                        <th>القيمة القديمة</th>
                        <th>القيمة الجديدة</th>
                        <th>تم بواسطة</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history_list as $h): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($h['created_at'])); ?></td>
                        <td><?php echo $h['user_id'] ? htmlspecialchars($h['user_name']) : 'فريق'; ?></td>
                        <td><?php echo $h['team_name'] ? htmlspecialchars($h['team_name']) : '—'; ?></td>
                        <td>
                            <span class="badge badge-p">
                                <?php echo $type_names[$h['score_type']] ?? $h['score_type']; ?>
                            </span>
                        </td>
                        <td class="<?php echo $h['points_change'] > 0 ? 'score-cell' : 'text-danger'; ?>">
                            <?php echo $h['points_change'] > 0 ? '+' : ''; ?><?php echo $h['points_change']; ?>
                        </td>
                        <td><?php echo $h['old_value']; ?></td>
                        <td><?php echo $h['new_value']; ?></td>
                        <td><?php echo htmlspecialchars($h['admin_name'] ?? 'نظام'); ?></td>
                        <td><?php echo htmlspecialchars($h['notes'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($history_list)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">لا توجد تعديلات تطابق معايير البحث</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">سجل التعديلات</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>