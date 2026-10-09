<?php
session_start();
if (!isset($_SESSION['user_id']) || !$_SESSION['is_super_admin']) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/db.php';

$message = $_GET['msg'] ?? '';

// جلب المسابقات النشطة (غير المؤرشفة)
$active_competitions = $pdo->query("SELECT * FROM competitions WHERE is_archived = 0 ORDER BY created_at DESC")->fetchAll();

// جلب المسابقات المؤرشفة
$archived_competitions = $pdo->query("SELECT * FROM competitions WHERE is_archived = 1 ORDER BY archived_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الرئيسية - منصة المسابقات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .archived-section {
            margin-top: 3rem;
            opacity: 0.8;
        }
        .archived-section .section-label h2 {
            color: var(--text3);
        }
        .archived-section .competition-card {
            opacity: 0.7;
            filter: grayscale(0.3);
        }
        .archived-section .competition-card:hover {
            opacity: 0.9;
            filter: grayscale(0.1);
        }
        .archived-badge {
            background: rgba(216,90,48,0.15);
            color: var(--coral-l);
            border: 1px solid rgba(216,90,48,0.3);
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            margin-right: 8px;
        }
        .restore-btn {
            background: rgba(239,159,39,0.15);
            color: var(--gold-l);
            border: 1px solid rgba(239,159,39,0.3);
        }
        .restore-btn:hover {
            background: rgba(239,159,39,0.3);
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">🏆</div>
                لوحة التحكم
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="../contestant/change_password.php" class="btn btn-secondary btn-sm">🔐 تغيير كلمة المرور</a>
                <a href="../logout.php">تسجيل الخروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">👋 مرحباً أدمن</div>
            <h1>إدارة المسابقات</h1>
            <p>قم بإدارة المسابقات الحالية أو إضافة مسابقة جديدة</p>
            <a href="add_competition.php" class="btn btn-primary" style="margin-top: 1rem;">➕ إضافة مسابقة جديدة</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- المسابقات النشطة -->
        <div class="section-label reveal">
            <span class="section-num">📋</span>
            <h2>المسابقات النشطة</h2>
            <div class="line"></div>
        </div>

        <div class="competitions-grid reveal">
            <?php if (empty($active_competitions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <p>لا توجد مسابقات نشطة حالياً</p>
                    <a href="add_competition.php" class="btn btn-primary btn-sm" style="margin-top: 1rem;">➕ إضافة مسابقة جديدة</a>
                </div>
            <?php else: ?>
                <?php foreach ($active_competitions as $comp): ?>
                <div class="competition-card">
                    <div class="competition-logo">
                        <img src="../uploads/<?php echo $comp['logo']; ?>" alt="<?php echo $comp['name']; ?>">
                    </div>
                    <div class="competition-info">
                        <h3><?php echo htmlspecialchars($comp['name']); ?></h3>
                        <?php if ($comp['description']): ?>
                            <p class="competition-desc"><?php echo htmlspecialchars(substr($comp['description'], 0, 60)); ?>...</p>
                        <?php endif; ?>
                        <div class="competition-meta">
                            <span class="badge badge-t">📅 <?php echo date('Y-m-d', strtotime($comp['created_at'])); ?></span>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                            <a href="competition_home.php?id=<?php echo $comp['id']; ?>" class="btn btn-primary btn-sm">إدارة المسابقة ←</a>
                            <a href="competition_settings.php?id=<?php echo $comp['id']; ?>" class="btn btn-secondary btn-sm">⚙️ إعدادات</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- المسابقات المؤرشفة -->
        <?php if (!empty($archived_competitions)): ?>
        <div class="archived-section">
            <div class="section-label reveal">
                <span class="section-num">🗄️</span>
                <h2>المسابقات المؤرشفة</h2>
                <div class="line"></div>
            </div>

            <div class="competitions-grid reveal">
                <?php foreach ($archived_competitions as $comp): ?>
                <div class="competition-card">
                    <div class="competition-logo">
                        <img src="../uploads/<?php echo $comp['logo']; ?>" alt="<?php echo $comp['name']; ?>">
                    </div>
                    <div class="competition-info">
                        <h3>
                            <?php echo htmlspecialchars($comp['name']); ?>
                            <span class="archived-badge">🗄️ مؤرشفة</span>
                        </h3>
                        <?php if ($comp['description']): ?>
                            <p class="competition-desc"><?php echo htmlspecialchars(substr($comp['description'], 0, 60)); ?>...</p>
                        <?php endif; ?>
                        <div class="competition-meta">
                            <span class="badge badge-c">📅 تمت الأرشفة: <?php echo date('Y-m-d', strtotime($comp['archived_at'])); ?></span>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                            <a href="competition_home.php?id=<?php echo $comp['id']; ?>" class="btn btn-secondary btn-sm">👁️ عرض (قراءة فقط)</a>
                            <a href="competition_settings.php?id=<?php echo $comp['id']; ?>" class="btn restore-btn btn-sm">📂 إلغاء الأرشفة</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">لوحة تحكم الأدمن</span></p>
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