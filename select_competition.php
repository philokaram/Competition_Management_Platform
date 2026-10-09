<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT c.*, cu.role 
    FROM competitions c 
    JOIN competition_users cu ON c.id = cu.competition_id 
    WHERE cu.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$my_competitions = $stmt->fetchAll();

if (isset($_GET['select'])) {
    $comp_id = $_GET['select'];
    $stmt = $pdo->prepare("SELECT role FROM competition_users WHERE user_id = ? AND competition_id = ?");
    $stmt->execute([$user_id, $comp_id]);
    $role = $stmt->fetchColumn();
    
    if ($role) {
        $_SESSION['comp_id'] = $comp_id;
        $_SESSION['user_role'] = $role;
        
        if ($role == 'judge') {
            header("Location: admin/competition_home.php?id=$comp_id");
        } elseif ($role == 'team_leader') {
            header("Location: contestant/team_leader.php");
        } else {
            header("Location: contestant/profile.php");
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختر المسابقة - منصة المسابقات</title>
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🏆</div>
                منصة المسابقات
            </a>
            <nav>
                <a href="../contestant/change_password.php" class="btn btn-secondary btn-sm">🔐 تغيير كلمة المرور</a>
                <a href="logout.php">تسجيل الخروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">✨ مرحباً بك</div>
            <h1>اختر المسابقة</h1>
            <p>اختر المسابقة التي تود الدخول إليها من القائمة أدناه</p>
        </div>

        <div class="competitions-grid">
            <?php if (empty($my_competitions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <p>أنت غير مسجل في أي مسابقة حالياً.</p>
                </div>
            <?php else: ?>
                <?php foreach ($my_competitions as $comp): ?>
                <div class="competition-card">
                    <div class="competition-logo">
                        <img src="uploads/<?php echo $comp['logo']; ?>" alt="<?php echo htmlspecialchars($comp['name']); ?>">
                    </div>
                    <div class="competition-info">
                        <h3><?php echo htmlspecialchars($comp['name']); ?></h3>
                        <?php if ($comp['description']): ?>
                            <p class="competition-desc"><?php echo htmlspecialchars(substr($comp['description'], 0, 50)); ?>...</p>
                        <?php endif; ?>
                        <div class="competition-role">
                            <span class="badge badge-p">
                                <?php 
                                    $roles = ['judge'=>'🛡 حكم', 'contestant'=>'🏃 متسابق', 'spectator'=>'👁 متفرج', 'team_leader'=>'👑 قائد فريق'];
                                    echo $roles[$comp['role']]; 
                                ?>
                            </span>
                        </div>
                        <a href="?select=<?php echo $comp['id']; ?>" class="btn btn-primary btn-sm" style="margin-top: 1rem;">دخول ←</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">اختر المسابقة</span></p>
    </footer>

    <script src="assets/js/script.js"></script>
    

</body>
</html>