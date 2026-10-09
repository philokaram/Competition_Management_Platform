<?php
session_start();
require_once '../config/db.php';

$comp_id = $_SESSION['comp_id'] ?? null;
if (!$comp_id) { header('Location: ../select_competition.php'); exit; }

// Individual Rankings
$users = $pdo->prepare("
    SELECT u.id, u.full_name, t.name as team_name, 
    (cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score) as total 
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE cu.competition_id = ? AND cu.role = 'contestant' 
    ORDER BY total DESC
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

$current_user_id = $_SESSION['user_id'];
$user_rank = 0;
foreach ($users_list as $index => $u) {
    if ($u['id'] == $current_user_id) {
        $user_rank = $index + 1;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الترتيب العام - منصة المسابقات</title>
    
     <!-- ✅ أكواد PWA -->
    <link rel="manifest" href="../assets/manifest.json">
    <meta name="theme-color" content="#7F77DD">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="مسابقات">
    <link rel="apple-touch-icon" href="../assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="../assets/images/icon-512.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🏆</div>
                الترتيب العام
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="../select_competition.php">تغيير المسابقة</a>
                <?php if ($_SESSION['user_role'] !== 'spectator'): ?>
                    <a href="profile.php">بروفايلي</a>
                <?php endif; ?>
                <a href="ranking.php">ترتيب المتسابقين</a>
                <a href="team_ranking.php">ترتيب الفرق</a>
                <a href="score_report.php">📊 تقرير درجاتي</a>
                <a href="messages.php">💬 المحادثات</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">🏆 لوحة الشرف</div>
            <h1>الترتيب العام للمتسابقين</h1>
            <p>ترتيب جميع المتسابقين في هذه المسابقة</p>
        </div>

        <?php if ($user_rank > 0): ?>
        <div class="user-rank-card reveal">
            <span class="user-rank-label">ترتيبي الشخصي</span>
            <span class="user-rank-number">#<?php echo $user_rank; ?></span>
        </div>
        <?php endif; ?>

        <div class="comp-card c-purple reveal">
            <div class="comp-title">👤 ترتيب الأشخاص</div>
            <div class="ranking-list">
                <?php foreach ($users_list as $index => $u): ?>
                <div class="ranking-item <?php echo $index == 0 ? 'first' : ($index == 1 ? 'second' : ($index == 2 ? 'third' : '')); ?> <?php echo ($u['id'] == $current_user_id) ? 'current-user' : ''; ?>">
                    <div class="rank-number">
                        <?php 
                            if ($index == 0) echo '🥇';
                            elseif ($index == 1) echo '🥈';
                            elseif ($index == 2) echo '🥉';
                            else echo $index + 1;
                        ?>
                    </div>
                    <div class="rank-name">
                        <?php echo htmlspecialchars($u['full_name']); ?>
                        <?php if ($u['team_name']): ?>
                            <span class="rank-team">(<?php echo htmlspecialchars($u['team_name']); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="rank-score"><?php echo number_format($u['total']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">الترتيب العام</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
