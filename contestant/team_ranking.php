<?php
session_start();
require_once '../config/db.php';

$comp_id = $_SESSION['comp_id'] ?? null;
if (!$comp_id) { header('Location: ../select_competition.php'); exit; }

// ✅ MODIFIED: Exclude team_leader from members_count and total score calculation
$teams = $pdo->prepare("
    SELECT t.id, t.name, t.bonus_score,
    (t.bonus_score + IFNULL(SUM(cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score), 0)) as total,
    COUNT(CASE WHEN cu.role = 'contestant' THEN 1 END) as members_count
    FROM teams t 
    LEFT JOIN competition_users cu ON t.id = cu.team_id 
    WHERE t.competition_id = ?
    GROUP BY t.id, t.name, t.bonus_score
    ORDER BY total DESC
");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();

// Get current user's team if exists
$current_user_id = $_SESSION['user_id'];
// ✅ MODIFIED: Exclude team_leader from total score calculation for user's team
$user_team = $pdo->prepare("
    SELECT t.name, t.id, (t.bonus_score + IFNULL(SUM(cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score), 0)) as total
    FROM teams t
    JOIN competition_users cu ON t.id = cu.team_id
    WHERE cu.user_id = ? AND cu.competition_id = ? AND cu.role = 'contestant'
    GROUP BY t.id
");
$user_team->execute([$current_user_id, $comp_id]);
$my_team = $user_team->fetch();

$team_rank = 0;
foreach ($teams_list as $index => $t) {
    if ($my_team && $t['id'] == $my_team['id']) {
        $team_rank = $index + 1;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ترتيب الفرق - منصة المسابقات</title>
    
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
                <div class="logo-icon">🏷️</div>
                ترتيب الفرق
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
            <div class="hero-tag">🏷️ سباق الفرق</div>
            <h1>ترتيب الفرق</h1>
            <p>ترتيب جميع الفرق المشاركة في هذه المسابقة</p>
        </div>

        <?php if ($my_team && $team_rank > 0): ?>
        <div class="user-rank-card reveal">
            <span class="user-rank-label">ترتيب فريقي</span>
            <span class="user-rank-number">#<?php echo $team_rank; ?></span>
            <span class="user-rank-label" style="font-size: 14px;"><?php echo htmlspecialchars($my_team['name']); ?></span>
        </div>
        <?php endif; ?>

        <div class="comp-card c-gold reveal">
            <div class="comp-title">🏷️ ترتيب الفرق</div>
            <div class="ranking-list">
                <?php foreach ($teams_list as $index => $team): ?>
                <div class="ranking-item <?php echo $index == 0 ? 'first' : ($index == 1 ? 'second' : ($index == 2 ? 'third' : '')); ?> <?php echo ($my_team && $team['id'] == $my_team['id']) ? 'current-user' : ''; ?>">
                    <div class="rank-number">
                        <?php 
                            if ($index == 0) echo '🥇';
                            elseif ($index == 1) echo '🥈';
                            elseif ($index == 2) echo '🥉';
                            else echo $index + 1;
                        ?>
                    </div>
                    <div class="rank-name">
                        <?php echo htmlspecialchars($team['name']); ?>
                        <span class="rank-team">(<?php echo $team['members_count']; ?> عضو)</span>
                    </div>
                    <div class="rank-score"><?php echo number_format($team['total']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">ترتيب الفرق</span></p>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>