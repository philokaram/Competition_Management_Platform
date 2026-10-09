<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

// Check permissions
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

// Add Team
if (isset($_POST['add_team'])) {
    $team_name = trim($_POST['team_name']);
    if (!empty($team_name)) {
        $pdo->prepare("INSERT INTO teams (competition_id, name) VALUES (?, ?)")->execute([$comp_id, $team_name]);
    }
    header("Location: competition_teams.php?id=" . $comp_id);
    exit;
}

// Update Team Name
if (isset($_POST['update_team_name'])) {
    $team_id = (int)$_POST['team_id'];
    $new_name = trim($_POST['team_name']);
    if (!empty($new_name)) {
        $pdo->prepare("UPDATE teams SET name = ? WHERE id = ? AND competition_id = ?")->execute([$new_name, $team_id, $comp_id]);
    }
    header("Location: competition_teams.php?id=" . $comp_id);
    exit;
}

// Update Team Logo
if (isset($_FILES['team_logo']) && $_FILES['team_logo']['name']) {
    $team_id = (int)$_POST['team_id_logo'];
    $ext = pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION);
    $logo_name = 'team_' . $team_id . '_' . time() . '.' . $ext;
    
    if (!file_exists("../uploads/teams")) {
        mkdir("../uploads/teams", 0777, true);
    }
    move_uploaded_file($_FILES['team_logo']['tmp_name'], "../uploads/teams/" . $logo_name);
    $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?")->execute([$logo_name, $team_id]);
    header("Location: competition_teams.php?id=" . $comp_id);
    exit;
}

// Delete Team
if (isset($_GET['delete_team'])) {
    $pdo->prepare("DELETE FROM teams WHERE id = ? AND competition_id = ?")->execute([$_GET['delete_team'], $comp_id]);
    header("Location: competition_teams.php?id=" . $comp_id);
    exit;
}

// Update Bonus
if (isset($_POST['update_bonus'])) {
    $team_id = (int)$_POST['team_id'];
    $points = (int)$_POST['points'];
    $team_name = $_POST['team_name'] ?? '';
    
    // Get old value
    $stmt = $pdo->prepare("SELECT bonus_score FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $old_bonus = (int)$stmt->fetchColumn();
    $new_bonus = $old_bonus + $points;
    
    // Update team bonus
    $stmt = $pdo->prepare("UPDATE teams SET bonus_score = ? WHERE id = ?");
    $stmt->execute([$new_bonus, $team_id]);
    
    $admin_id = $_SESSION['user_id'];
    
    // Insert into history
    $stmt = $pdo->prepare("INSERT INTO score_history (competition_id, user_id, admin_id, score_type, points_change, old_value, new_value, notes) 
                          VALUES (?, ?, ?, 'team_bonus', ?, ?, ?, ?)");
    $stmt->execute([
        $comp_id, 
        $admin_id,
        $admin_id, 
        $points, 
        $old_bonus, 
        $new_bonus, 
        "تعديل بونص فريق: " . $team_name . " (" . ($points > 0 ? '+' : '') . $points . " نقطة)"
    ]);
    // بعد تحديث البونص، أضف إشعار لقائد الفريق
    // جلب قائد الفريق أولاً
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, t.name as team_name
        FROM users u
        JOIN competition_users cu ON u.id = cu.user_id
        JOIN teams t ON cu.team_id = t.id
        WHERE cu.team_id = ? AND cu.role = 'team_leader' AND cu.competition_id = ?
    ");
    $stmt->execute([$team_id, $comp_id]);
    $team_leader = $stmt->fetch();

    if ($team_leader) {
        sendNotification($pdo, $team_leader['user_id'], $comp_id,
            "🏷️ تغيير في بونص فريق " . $team_name,
            "تم " . ($points > 0 ? 'إضافة' : 'خصم') . " " . abs($points) . " نقطة " . ($points > 0 ? 'لـ' : 'من') . " بونص فريق " . $team_name,
            'rank'
        );
    }
    header("Location: competition_teams.php?id=" . $comp_id);
    exit;
}

// ✅ Fetch teams - MODIFIED: Exclude team_leader from members_count and members_score
$teams = $pdo->prepare("
    SELECT t.id, t.name, t.logo, t.bonus_score,
    COALESCE((
        SELECT SUM(cu.mass_score + cu.choir_score + COALESCE(cu.prayer_score, 0) + cu.interaction_score) 
        FROM competition_users cu 
        WHERE cu.team_id = t.id AND cu.role = 'contestant'
    ), 0) as members_score,
    COUNT(CASE WHEN cu.role = 'contestant' THEN 1 END) as members_count
    FROM teams t
    LEFT JOIN competition_users cu ON t.id = cu.team_id
    WHERE t.competition_id = ?
    GROUP BY t.id
    ORDER BY (t.bonus_score + COALESCE((
        SELECT SUM(cu.mass_score + cu.choir_score + COALESCE(cu.prayer_score, 0) + cu.interaction_score) 
        FROM competition_users cu 
        WHERE cu.team_id = t.id AND cu.role = 'contestant'
    ), 0)) DESC
");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفرق - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-card { background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem; }
        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .field-input { background: #0f0f1a; border: 1px solid #3a3a55; border-radius: 10px; padding: 10px; color: white; width: 100%; }
        .data-table { width: 100%; border-collapse: collapse; background: #1a1a2e; border-radius: 16px; }
        .data-table th, .data-table td { padding: 12px; text-align: center; border-bottom: 1px solid #2d2d44; }
        .team-logo { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .btn-warning { background: #EF9F27; color: white; padding: 5px 10px; border-radius: 8px; border: none; cursor: pointer; }
        .field-input-sm { background: #0f0f1a; border: 1px solid #3a3a55; border-radius: 8px; padding: 5px; color: white; width: 70px; }
        .score-cell { color: #f5b84d; font-weight: bold; }
        .rank-1-row { background: rgba(239,159,39,0.1); }
        .mobile-menu-btn { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        @media (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            nav { display: none; position: fixed; top: 60px; left: 0; right: 0; background: #1a1a2e; flex-direction: column; padding: 1rem; z-index: 1000; }
            nav.show { display: flex; }
            .table-responsive { overflow-x: auto; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo"><div class="logo-icon">🏷️</div> إدارة الفرق</a>
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
            <h1>الفرق في <?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>إضافة فرق جديدة، تعديل الأسماء، إضافة لوجو، أو تعديل البونص</p>
        </div>
        <div class="form-card">
            <h3>➕ إضافة فريق جديد</h3>
            <form method="POST" class="form-inline">
                <input type="text" name="team_name" class="field-input" style="flex:1;" placeholder="اسم الفريق" required>
                <button type="submit" name="add_team" class="btn btn-success">+ إضافة</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>اللوجو</th><th>اسم الفريق</th><th>الأعضاء</th><th>البونص</th><th>الإجمالي</th><th>تعديل الاسم</th><th>اللوجو</th><th>تعديل البونص</th><th>حذف</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($teams_list as $i => $t): ?>
                    <tr class="<?php echo $i==0?'rank-1-row':''; ?>">
                        <td><?php if($i==0) echo '🥇'; elseif($i==1) echo '🥈'; elseif($i==2) echo '🥉'; else echo $i+1; ?></td>
                        <td><?php if($t['logo'] && file_exists("../uploads/teams/".$t['logo'])): ?><img src="../uploads/teams/<?php echo $t['logo']; ?>" class="team-logo"><?php else: ?>🏷️<?php endif; ?></td>
                        <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                        <td><?php echo $t['members_count']; ?></td>
                        <td><?php echo $t['bonus_score']; ?></td>
                        <td class="score-cell"><?php echo number_format($t['bonus_score'] + $t['members_score']); ?></td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:5px;">
                                <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                <input type="text" name="team_name" class="field-input-sm" value="<?php echo htmlspecialchars($t['name']); ?>" style="width:100px;">
                                <button type="submit" name="update_team_name" class="btn-warning">✏️</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="team_id_logo" value="<?php echo $t['id']; ?>">
                                <input type="file" name="team_logo" accept="image/*" style="display:none;" id="logo_<?php echo $t['id']; ?>">
                                <label for="logo_<?php echo $t['id']; ?>" class="btn btn-primary btn-sm">📷</label>
                                <button type="submit" name="update_team_logo" style="display:none;"></button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:5px;">
                                <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                <input type="hidden" name="team_name" value="<?php echo htmlspecialchars($t['name']); ?>">
                                <input type="number" name="points" class="field-input-sm" required>
                                <button type="submit" name="update_bonus" class="btn btn-primary btn-sm">تحديث</button>
                            </form>
                        </td>
                        <td><a href="?id=<?php echo $comp_id; ?>&delete_team=<?php echo $t['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف الفريق؟')">🗑</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() { this.closest('form').submit(); });
        });
    </script>
</body>
</html>