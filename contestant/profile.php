<?php
session_start();
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$comp_id = $_SESSION['comp_id'] ?? null;

if (!$comp_id) { header('Location: ../select_competition.php'); exit; }

// ✅ If team_leader, redirect to their specific page
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'team_leader') {
    header('Location: team_leader.php');
    exit;
}

// ✅ Handle Profile Picture Upload
$profile_message = '';
if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $filename = $_FILES['profile_pic']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
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
            $profile_message = "تم تحديث الصورة بنجاح";
        }
    } else {
        $profile_message = "صيغة الملف غير مدعومة. استخدم JPG, PNG, GIF أو WEBP";
    }
}

// Fetch user data
$stmt = $pdo->prepare("
    SELECT u.*, cu.role, cu.team_id, cu.mass_score, cu.choir_score, cu.prayer_score, cu.interaction_score, t.name as team_name 
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE u.id = ? AND cu.competition_id = ?
");
$stmt->execute([$user_id, $comp_id]);
$user = $stmt->fetch();

if (!$user) { header('Location: ../select_competition.php'); exit; }

if ($user['role'] == 'spectator') {
    header('Location: ranking.php');
    exit;
}

// Fetch team leader name
$team_leader_name = '';
if ($user['team_id']) {
    $stmt = $pdo->prepare("
        SELECT u.full_name FROM users u 
        JOIN competition_users cu ON u.id = cu.user_id 
        WHERE cu.team_id = ? AND cu.role = 'team_leader'
    ");
    $stmt->execute([$user['team_id']]);
    $team_leader = $stmt->fetch();
    $team_leader_name = $team_leader ? $team_leader['full_name'] : 'لا يوجد';
}

// Team Score
$team_score = 0;
if ($user['team_id']) {
    $stmt = $pdo->prepare("
        SELECT (t.bonus_score + IFNULL(SUM(cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score), 0)) as total 
        FROM teams t 
        LEFT JOIN competition_users cu ON t.id = cu.team_id 
        WHERE t.id = ?
    ");
    $stmt->execute([$user['team_id']]);
    $team_score = (int)$stmt->fetchColumn();
}

// User ranking
$users_rank = $pdo->prepare("
    SELECT user_id, (mass_score + choir_score + prayer_score + interaction_score) as total 
    FROM competition_users WHERE competition_id = ? AND role = 'contestant' 
    ORDER BY total DESC
");
$users_rank->execute([$comp_id]);
$ranks = $users_rank->fetchAll();
$user_rank = 0;
foreach ($ranks as $index => $r) { if ($r['user_id'] == $user_id) { $user_rank = $index + 1; break; } }

$total_score = $user['mass_score'] + $user['choir_score'] + $user['prayer_score'] + $user['interaction_score'];
$score_percentage = min(100, round(($total_score / 2000) * 100));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>بروفايلي - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .profile-avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
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
            bottom: 5px;
            left: 5px;
            background: var(--purple);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid var(--bg);
        }
        .profile-avatar-wrapper .upload-btn:hover {
            transform: scale(1.1);
        }
        #profilePicInput {
            display: none;
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
            font-size: 14px;
            animation: fadeOut 3s ease forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; transform: translateX(-50%) translateY(0); }
            70% { opacity: 1; }
            100% { opacity: 0; transform: translateX(-50%) translateY(20px); visibility: hidden; }
        }
        .profile-sidebar {
            text-align: center;
        }
        
        /* تحسينات الموبايل للصفحة */
        @media (max-width: 768px) {
            .profile-grid {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .profile-scores .comp-card {
                padding: 1rem;
            }
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .stat-num {
                font-size: 20px;
            }
            .stat-label {
                font-size: 10px;
            }
            .score-value {
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
            .hero h1 {
                font-size: 1.5rem;
            }
            .hero p {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">👤</div>
                ملفي الشخصي
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="../select_competition.php">تغيير المسابقة</a>
                <a href="profile.php">بروفايلي</a>
                <a href="ranking.php">ترتيب المتسابقين</a>
                <a href="team_ranking.php">ترتيب الفرق</a>
                <a href="score_report.php">📊 تقرير درجاتي</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">👤 الملف الشخصي</div>
            <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
            <p>درجاتك وترتيبك في المسابقة</p>
        </div>

        <div class="profile-grid">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar reveal">
                <form method="POST" enctype="multipart/form-data" id="profilePicForm">
                    <div class="profile-avatar-wrapper">
                        <?php 
                            $profile_pic = $user['profile_pic'] ?? 'default_avatar.png';
                            $profile_pic_path = "../uploads/avatars/" . $profile_pic;
                            if (!file_exists($profile_pic_path) || empty($profile_pic)) {
                                $profile_pic_path = "../uploads/avatars/default_avatar.png";
                            }
                        ?>
                        <img src="<?php echo $profile_pic_path; ?>" 
                             alt="صورة البروفايل"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%237F77DD\'/%3E%3Ctext x=\'50\' y=\'70\' font-size=\'50\' text-anchor=\'middle\' fill=\'white\'%3E👤%3C/text%3E%3C/svg%3E'">
                        <div class="upload-btn" onclick="document.getElementById('profilePicInput').click()">
                            📷
                        </div>
                    </div>
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
                    <input type="hidden" name="update_profile_pic" value="1">
                </form>
                
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="profile-role">
                    <span class="badge badge-p">
                        <?php 
                            if ($user['role'] == 'contestant') echo '🏃 متسابق';
                            elseif ($user['role'] == 'judge') echo '🛡 حكم';
                            elseif ($user['role'] == 'team_leader') echo '👑 قائد فريق';
                            else echo '👁 متفرج';
                        ?>
                    </span>
                </div>
                
                <?php if ($user['team_name']): ?>
                <div style="margin-top: 1rem;">
                    <span class="badge badge-t">🏷️ <?php echo htmlspecialchars($user['team_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Score Details -->
            <div class="profile-scores reveal">
                <div class="comp-card c-purple">
                    <div class="comp-title">📊 درجاتي في هذه المسابقة</div>
                    
                    <div class="score-item">
                        <div class="score-header">
                            <span class="score-label">⛪ القداس</span>
                            <span class="score-value v-p"><?php echo number_format($user['mass_score']); ?> نقطة</span>
                        </div>
                        <div class="score-track">
                            <div class="score-fill sf-p" style="width: <?php echo min(100, ($user['mass_score'] / 500) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="score-item">
                        <div class="score-header">
                            <span class="score-label">🎵 الخورس</span>
                            <span class="score-value v-t"><?php echo number_format($user['choir_score']); ?> نقطة</span>
                        </div>
                        <div class="score-track">
                            <div class="score-fill sf-t" style="width: <?php echo min(100, ($user['choir_score'] / 400) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="score-item">
                        <div class="score-header">
                            <span class="score-label">🕊️ التسبحة/العشية</span>
                            <span class="score-value v-p"><?php echo number_format($user['prayer_score']); ?> نقطة</span>
                        </div>
                        <div class="score-track">
                            <div class="score-fill sf-p" style="width: <?php echo min(100, ($user['prayer_score'] / 500) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="score-item">
                        <div class="score-header">
                            <span class="score-label">💬 التفاعل</span>
                            <span class="score-value v-g"><?php echo number_format($user['interaction_score']); ?> نقطة</span>
                        </div>
                        <div class="score-track">
                            <div class="score-fill sf-g" style="width: <?php echo min(100, ($user['interaction_score'] / 300) * 100); ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="score-total">
                        <div class="score-header">
                            <span class="score-label">🏆 الإجمالي</span>
                            <span class="score-value" style="color: var(--gold); font-size: 24px;"><?php echo number_format($total_score); ?></span>
                        </div>
                        <div class="score-track">
                            <div class="score-fill sf-g" style="width: <?php echo $score_percentage; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-row" style="margin-top: 1rem;">
                    <div class="stat-card s-g">
                        <div class="stat-icon">🥇</div>
                        <div class="stat-num sn-g">#<?php echo $user_rank; ?></div>
                        <div class="stat-label">ترتيبي الشخصي</div>
                    </div>
                    <?php if ($user['team_id']): ?>
                    <div class="stat-card s-p">
                        <div class="stat-icon">👥</div>
                        <div class="stat-num sn-p"><?php echo number_format($team_score); ?></div>
                        <div class="stat-label">درجة فريقي</div>
                    </div>
                    <div class="stat-card s-t">
                        <div class="stat-icon">👑</div>
                        <div class="stat-num sn-t" style="font-size: 14px;"><?php echo htmlspecialchars($team_leader_name); ?></div>
                        <div class="stat-label">قائد الفريق</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">ملف المتسابق الشخصي</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
    <script>
        // Auto-upload profile picture when selected
        const picInput = document.getElementById('profilePicInput');
        if (picInput) {
            picInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    this.closest('form').submit();
                }
            });
        }
        
        // Show toast message if exists
        <?php if ($profile_message): ?>
            (function() {
                const toast = document.createElement('div');
                toast.className = 'toast-msg';
                toast.textContent = '<?php echo $profile_message; ?>';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            })();
        <?php endif; ?>
        
        // Mobile menu toggle
        const mobileBtn = document.querySelector('.mobile-menu-btn');
        if (mobileBtn) {
            mobileBtn.addEventListener('click', function() {
                document.querySelector('nav').classList.toggle('show');
            });
        }
        
        // Simple mobile menu toggle backup
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            var nav = document.querySelector('nav');
            if (nav.style.display === 'flex') {
                nav.style.display = 'none';
            } else {
                nav.style.display = 'flex';
            }
        });
    </script>
</body>
</html>