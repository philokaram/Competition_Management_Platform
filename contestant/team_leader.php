<?php
session_start();
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$comp_id = $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    header('Location: ../select_competition.php');
    exit;
}

$profile_message = '';

// ============================================
// معالجة تحديث صورة البروفايل الشخصية
// ============================================
if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        if (!file_exists("../uploads/avatars")) {
            mkdir("../uploads/avatars", 0777, true);
        }
        
        $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
        $target = "../uploads/avatars/" . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $old_pic = $stmt->fetchColumn();
            if ($old_pic && $old_pic != 'default_avatar.png' && file_exists("../uploads/avatars/" . $old_pic)) {
                unlink("../uploads/avatars/" . $old_pic);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$new_filename, $user_id]);
            $profile_message = "تم تحديث الصورة الشخصية بنجاح";
        }
    } else {
        $profile_message = "صيغة الملف غير مدعومة";
    }
}

// ============================================
// معالجة تحديث بيانات الفريق
// ============================================
$team_message = '';
if (isset($_POST['update_team'])) {
    $team_name = trim($_POST['team_name']);
    
    // جلب معرف الفريق الحالي
    $stmt = $pdo->prepare("SELECT team_id FROM competition_users WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$user_id, $comp_id]);
    $team_id = $stmt->fetchColumn();
    
    if ($team_id && !empty($team_name)) {
        // تحديث اسم الفريق
        $stmt = $pdo->prepare("UPDATE teams SET name = ? WHERE id = ?");
        $stmt->execute([$team_name, $team_id]);
        $team_message = "تم تحديث اسم الفريق بنجاح";
        
        // تحديث المتغير المحلي
        $user['team_name'] = $team_name;
    }
}

// ============================================
// معالجة تحديث لوجو الفريق
// ============================================
if (isset($_POST['update_team_logo']) && isset($_FILES['team_logo']) && $_FILES['team_logo']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['team_logo']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        // جلب معرف الفريق الحالي
        $stmt = $pdo->prepare("SELECT team_id FROM competition_users WHERE user_id = ? AND competition_id = ?");
        $stmt->execute([$user_id, $comp_id]);
        $team_id = $stmt->fetchColumn();
        
        if ($team_id) {
            if (!file_exists("../uploads/teams")) {
                mkdir("../uploads/teams", 0777, true);
            }
            
            $new_logo = 'team_' . $team_id . '_' . time() . '.' . $ext;
            $target = "../uploads/teams/" . $new_logo;
            
            if (move_uploaded_file($_FILES['team_logo']['tmp_name'], $target)) {
                // حذف اللوجو القديم
                $stmt = $pdo->prepare("SELECT logo FROM teams WHERE id = ?");
                $stmt->execute([$team_id]);
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && $old_logo != 'default_team.png' && file_exists("../uploads/teams/" . $old_logo)) {
                    unlink("../uploads/teams/" . $old_logo);
                }
                
                $stmt = $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?");
                $stmt->execute([$new_logo, $team_id]);
                $team_message = "تم تحديث لوجو الفريق بنجاح";
            }
        }
    } else {
        $team_message = "صيغة الملف غير مدعومة";
    }
}

// ============================================
// جلب بيانات القائد وفريقه
// ============================================
$stmt = $pdo->prepare("
    SELECT u.*, t.id as team_id, t.name as team_name, t.logo as team_logo
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE u.id = ? AND cu.competition_id = ? AND cu.role = 'team_leader'
");
$stmt->execute([$user_id, $comp_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../select_competition.php');
    exit;
}

// جلب أعضاء الفريق
$members = $pdo->prepare("
    SELECT u.id, u.full_name, u.profile_pic
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    WHERE cu.team_id = ? AND cu.role = 'contestant'
    ORDER BY u.full_name
");
$members->execute([$user['team_id']]);
$team_members = $members->fetchAll();

// جلب ترتيب الفريق
$team_rank = $pdo->prepare("
    SELECT t.id,
    (t.bonus_score + IFNULL(SUM(cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score), 0)) as total 
    FROM teams t 
    LEFT JOIN competition_users cu ON t.id = cu.team_id 
    WHERE t.competition_id = ?
    GROUP BY t.id
    ORDER BY total DESC
");
$team_rank->execute([$comp_id]);
$ranks = $team_rank->fetchAll();
$team_position = 0;
foreach ($ranks as $index => $rank) {
    if ($rank['id'] == $user['team_id']) {
        $team_position = $index + 1;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة قائد الفريق - <?php echo htmlspecialchars($user['team_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .team-avatar {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            cursor: pointer;
        }
        .team-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--border2);
        }
        .team-avatar .upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--purple);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        .profile-avatar-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
        }
        .profile-avatar-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--border2);
        }
        .profile-avatar-wrapper .upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--purple);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .member-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-bottom: 1px solid var(--border);
        }
        .member-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        .member-name {
            flex: 1;
        }
        .edit-team-form {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .toast-msg {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--card);
            border: 1px solid var(--teal);
            color: var(--teal-l);
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text2);
        }
        .form-group input {
            width: 100%;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--text);
        }
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">👑</div>
                لوحة القيادة
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="../select_competition.php">تغيير المسابقة</a>
                <a href="team_leader.php">بروفايلي</a>
                <a href="ranking.php">ترتيب المتسابقين</a>
                <a href="team_ranking.php">ترتيب الفرق</a>
                <a href="score_report.php">📊 تقرير الدرجات</a>
                <a href="messages.php">💬 المحادثات</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">👑 مرحباً قائد الفريق</div>
            <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
            <p>فريق: <strong><?php echo htmlspecialchars($user['team_name']); ?></strong> | الترتيب الحالي: <strong>#<?php echo $team_position; ?></strong></p>
        </div>

        <?php if ($profile_message): ?>
            <div class="toast-msg"><?php echo $profile_message; ?></div>
        <?php endif; ?>
        <?php if ($team_message): ?>
            <div class="toast-msg"><?php echo $team_message; ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- بطاقة ترتيب الفريق -->
            <div class="comp-card c-purple">
                <div class="comp-title">🏆 ترتيب الفريق</div>
                <div class="team-score" style="font-size: 48px; text-align: center;">#<?php echo $team_position; ?></div>
            </div>

            <!-- بطاقة تعديل بيانات الفريق -->
            <div class="comp-card c-gold">
                <div class="comp-title">✏️ تعديل بيانات الفريق</div>
                
                <!-- تعديل لوجو الفريق -->
                <form method="POST" enctype="multipart/form-data" style="text-align: center;">
                    <div class="team-avatar" onclick="document.getElementById('teamLogoInput').click()">
                        <?php 
                            $team_logo = $user['team_logo'] ?? 'default_team.png';
                            $team_logo_path = "../uploads/teams/" . $team_logo;
                            if (!file_exists($team_logo_path) || empty($team_logo)) {
                                $team_logo_path = "../uploads/teams/default_team.png";
                            }
                        ?>
                        <img src="<?php echo $team_logo_path; ?>" alt="لوجو الفريق" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%237F77DD\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'50\' text-anchor=\'middle\' fill=\'white\'%3E🏷️%3C/text%3E%3C/svg%3E'">
                        <div class="upload-btn" onclick="event.stopPropagation(); document.getElementById('teamLogoInput').click()">📷</div>
                    </div>
                    <input type="file" name="team_logo" id="teamLogoInput" accept="image/*" style="display:none" onchange="this.closest('form').submit();">
                    <input type="hidden" name="update_team_logo" value="1">
                </form>

                <!-- تعديل اسم الفريق -->
                <form method="POST" class="edit-team-form">
                    <div class="form-group">
                        <label>🏷️ اسم الفريق</label>
                        <input type="text" name="team_name" value="<?php echo htmlspecialchars($user['team_name']); ?>" required>
                    </div>
                    <button type="submit" name="update_team" class="btn btn-primary btn-sm">💾 حفظ اسم الفريق</button>
                </form>
            </div>

            <!-- بطاقة تعديل الصورة الشخصية -->
            <div class="comp-card c-teal">
                <div class="comp-title">👤 صورتي الشخصية</div>
                <form method="POST" enctype="multipart/form-data" style="text-align: center;">
                    <div class="profile-avatar-wrapper" onclick="document.getElementById('profilePicInput').click()">
                        <?php 
                            $profile_pic = $user['profile_pic'] ?? 'default_avatar.png';
                            $profile_pic_path = "../uploads/avatars/" . $profile_pic;
                            if (!file_exists($profile_pic_path) || empty($profile_pic)) {
                                $profile_pic_path = "../uploads/avatars/default_avatar.png";
                            }
                        ?>
                        <img src="<?php echo $profile_pic_path; ?>" alt="صورتي الشخصية" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%237F77DD\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'50\' text-anchor=\'middle\' fill=\'white\'%3E👤%3C/text%3E%3C/svg%3E'">
                        <div class="upload-btn" onclick="event.stopPropagation(); document.getElementById('profilePicInput').click()">📷</div>
                    </div>
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" style="display:none" onchange="this.closest('form').submit();">
                    <input type="hidden" name="update_profile_pic" value="1">
                </form>
                <p style="text-align: center; margin-top: 10px; font-size: 12px; color: var(--text3);">اضغط على الصورة لتغييرها</p>
            </div>

            <!-- بطاقة أعضاء الفريق -->
            <div class="comp-card">
                <div class="comp-title">👥 أعضاء الفريــق (<?php echo count($team_members); ?>)</div>
                <div class="members-list">
                    <?php if (empty($team_members)): ?>
                        <p class="empty-text">لا يوجد أعضاء في الفريق حتى الآن</p>
                    <?php else: ?>
                        <?php foreach ($team_members as $member): ?>
                            <div class="member-item">
                                <?php 
                                    $member_pic = $member['profile_pic'] ?? 'default_avatar.png';
                                    $member_pic_path = "../uploads/avatars/" . $member_pic;
                                    if (!file_exists($member_pic_path) || empty($member_pic)) {
                                        $member_pic_path = "../uploads/avatars/default_avatar.png";
                                    }
                                ?>
                                <img src="<?php echo $member_pic_path; ?>" class="member-avatar" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%237F77DD\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'50\' text-anchor=\'middle\' fill=\'white\'%3E👤%3C/text%3E%3C/svg%3E'">
                                <span class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">لوحة قائد الفريق</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Auto-hide toast messages
        setTimeout(() => {
            document.querySelectorAll('.toast-msg').forEach(toast => toast.remove());
        }, 3000);
        
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>