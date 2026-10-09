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

// Get stats
$teams_count = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE competition_id = ?");
$teams_count->execute([$comp_id]);
$teams_total = $teams_count->fetchColumn();

$users_count = $pdo->prepare("SELECT COUNT(*) FROM competition_users WHERE competition_id = ? AND role = 'contestant'");
$users_count->execute([$comp_id]);
$contestants_total = $users_count->fetchColumn();

// Get top team
$top_team = $pdo->prepare("
    SELECT t.name, (t.bonus_score + IFNULL(SUM(cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score), 0)) as total 
    FROM teams t 
    LEFT JOIN competition_users cu ON t.id = cu.team_id 
    WHERE t.competition_id = ?
    GROUP BY t.id
    ORDER BY total DESC
    LIMIT 1
");
$top_team->execute([$comp_id]);
$top_team_data = $top_team->fetch();

// Get top contestant
$top_user = $pdo->prepare("
    SELECT u.full_name, (cu.mass_score + cu.choir_score + cu.prayer_score + cu.interaction_score) as total 
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    WHERE cu.competition_id = ? AND cu.role = 'contestant'
    ORDER BY total DESC
    LIMIT 1
");
$top_user->execute([$comp_id]);
$top_user_data = $top_user->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($competition['name']); ?> - الصفحة الرئيسية</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🏠</div>
                <?php echo htmlspecialchars($competition['name']); ?>
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <!-- روابط الأدمن -->
                <a href="dashboard.php">📋 كل المسابقات</a>
                
                <!-- روابط إدارة المسابقة -->
                <a href="competition_teams.php?id=<?php echo $comp_id; ?>">🏷️ الفرق</a>
                <a href="competition_users.php?id=<?php echo $comp_id; ?>">👥 المستخدمين</a>
               
                <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">✅ الحضور</a>
                <a href="attendance_stats.php?comp_id=<?php echo $comp_id; ?>">📊 إحصائيات الحضور</a>
                <a href="score_history.php?comp_id=<?php echo $comp_id; ?>">📜 السجل</a>
                <a href="messages.php?comp_id=<?php echo $comp_id; ?>">💬 المحادثات</a>
                <a href="create_question.php?comp_id=<?php echo $comp_id; ?>">📅 سؤال اليوم</a>
                
                <!-- روابط إدارة المستخدمين (للأدمن فقط) -->
                <?php if ($is_super): ?>
                <a href="add_all_contestants.php?comp_id=<?php echo $comp_id; ?>">➕ إضافة متسابقين</a>
                <a href="reset_user_password.php?comp_id=<?php echo $comp_id; ?>">🔑 تغيير كلمة المرور</a>
               
                <a href="change_username.php?comp_id=<?php echo $comp_id; ?>">✏️ تغيير اسم المستخدم</a>
                <a href="competition_settings.php?id=<?php echo $comp_id; ?>">⚙️ إعدادات المسابقة</a>
                <?php endif; ?>
                
                <!-- خروج -->
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">🏆 المسابقة</div>
            <h1><?php echo htmlspecialchars($competition['name']); ?></h1>
            <?php if ($competition['description']): ?>
                <p><?php echo nl2br(htmlspecialchars($competition['description'])); ?></p>
            <?php endif; ?>
        </div>

        <!-- Stats Row -->
        <div class="stats-row reveal">
            <div class="stat-card s-p">
                <div class="stat-icon">🏷️</div>
                <div class="stat-num sn-p"><?php echo $teams_total; ?></div>
                <div class="stat-label">عدد الفرق</div>
            </div>
            <div class="stat-card s-t">
                <div class="stat-icon">👥</div>
                <div class="stat-num sn-t"><?php echo $contestants_total; ?></div>
                <div class="stat-label">عدد المتسابقين</div>
            </div>
            <div class="stat-card s-g">
                <div class="stat-icon">🥇</div>
                <div class="stat-num sn-g"><?php echo $top_team_data ? htmlspecialchars($top_team_data['name']) : '—'; ?></div>
                <div class="stat-label">أفضل فريق</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-grid reveal">
            <a href="competition_teams.php?id=<?php echo $comp_id; ?>" class="comp-card c-purple" style="text-decoration: none;">
                <div class="comp-title">🏷️ إدارة الفرق</div>
                <p style="color: var(--text2);">إضافة، تعديل، أو حذف الفرق في هذه المسابقة</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">إدارة الفرق ←</span>
            </a>
            
            <a href="competition_users.php?id=<?php echo $comp_id; ?>" class="comp-card c-teal" style="text-decoration: none;">
                <div class="comp-title">👥 إدارة المستخدمين</div>
                <p style="color: var(--text2);">إضافة متسابقين، حكام، أو متفرجين للمسابقة</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">إدارة المستخدمين ←</span>
            </a>
            
           
            
            <a href="attendance.php?comp_id=<?php echo $comp_id; ?>" class="comp-card c-gold" style="text-decoration: none;">
                <div class="comp-title">✅ تسجيل الحضور</div>
                <p style="color: var(--text2);">تسجيل حضور القداس، الخورس، والتسبحة</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">تسجيل الحضور ←</span>
            </a>
            
            <a href="attendance_stats.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--gold);">
                <div class="comp-title">📊 إحصائيات الحضور</div>
                <p style="color: var(--text2);">نسبة حضور المتسابقين للقداس والخورس والتسبحة</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">عرض الإحصائيات ←</span>
            </a>
            
            <a href="score_history.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--purple);">
                <div class="comp-title">📜 سجل التعديلات</div>
                <p style="color: var(--text2);">متابعة تاريخ تعديلات الدرجات</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">عرض السجل ←</span>
            </a>
            
            <a href="messages.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--teal);">
                <div class="comp-title">💬 المحادثات</div>
                <p style="color: var(--text2);">تواصل مع الفرق والمشاركين</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">فتح المحادثات ←</span>
            </a>
            
            <a href="create_question.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--pink);">
                <div class="comp-title">📅 سؤال اليوم</div>
                <p style="color: var(--text2);">إنشاء سؤال بقالب جذاب للمشاركين</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">إنشاء سؤال ←</span>
            </a>

            <?php if ($is_super): ?>
            <!-- روابط إضافية للأدمن فقط -->
            
            <a href="reset_password.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--coral);">
                <div class="comp-title">🔑 تغيير كلمة المرور</div>
                <p style="color: var(--text2);">تغيير كلمة مرور مستخدم محدد</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">تغيير ←</span>
            </a>
            
            <a href="change_username.php?comp_id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--teal);">
                <div class="comp-title">✏️ تغيير اسم المستخدم</div>
                <p style="color: var(--text2);">تغيير اسم مستخدم محدد</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">تغيير ←</span>
            </a>
            
            <a href="competition_settings.php?id=<?php echo $comp_id; ?>" class="comp-card" style="text-decoration: none; border-top-color: var(--gold);">
                <div class="comp-title">⚙️ إعدادات المسابقة</div>
                <p style="color: var(--text2);">تغيير اسم، وصف، ولوجو المسابقة</p>
                <span class="btn btn-primary btn-sm" style="margin-top: 1rem;">إعدادات ←</span>
            </a>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">الصفحة الرئيسية للمسابقة</span></p>
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