<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    header('Location: dashboard.php');
    exit;
}

// التحقق من الصلاحيات
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

// جلب تاريخ بداية المسابقة
$start_date = $competition['created_at'];
$total_days = (strtotime(date('Y-m-d')) - strtotime($start_date)) / (60 * 60 * 24);
$total_days = max(1, round($total_days));

// جلب إحصائيات الحضور لكل متسابق
$stats = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        t.name as team_name,
        COUNT(DISTINCT CASE WHEN a.type = 'mass' THEN a.date END) as mass_count,
        COUNT(DISTINCT CASE WHEN a.type = 'choir' THEN a.date END) as choir_count,
        COUNT(DISTINCT CASE WHEN a.type = 'prayer' THEN a.date END) as prayer_count
    FROM users u
    JOIN competition_users cu ON u.id = cu.user_id
    LEFT JOIN teams t ON cu.team_id = t.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.competition_id = ?
    WHERE cu.competition_id = ? AND cu.role = 'contestant'
    GROUP BY u.id
    ORDER BY u.full_name
");
$stats->execute([$comp_id, $comp_id]);
$contestants = $stats->fetchAll();

// جلب إعدادات الدرجات
$settings = [];
$res = $pdo->query("SELECT * FROM settings");
foreach ($res->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>إحصائيات الحضور - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
        }
        .summary-card .number {
            font-size: 32px;
            font-weight: 700;
            font-family: 'Space Mono', monospace;
        }
        .summary-card.mass { border-top: 3px solid var(--purple); }
        .summary-card.choir { border-top: 3px solid var(--teal); }
        .summary-card.prayer { border-top: 3px solid var(--gold); }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s;
        }
        .progress-fill.mass { background: var(--purple); }
        .progress-fill.choir { background: var(--teal); }
        .progress-fill.prayer { background: var(--gold); }
        .attendance-cell {
            min-width: 100px;
        }
        .percentage {
            font-weight: bold;
            margin-top: 4px;
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .data-table th, .data-table td {
                padding: 8px;
                font-size: 11px;
            }
            .summary-card .number {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">📊</div>
                إحصائيات الحضور
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
            <div class="hero-tag">📊 إحصائيات الحضور</div>
            <h1><?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>نسبة حضور المتسابقين للقداس، الخورس، والتسبحة</p>
        </div>

        <!-- ملخص الإحصائيات -->
        <div class="summary-cards">
            <?php
            $total_mass = 0;
            $total_choir = 0;
            $total_prayer = 0;
            foreach ($contestants as $c) {
                $total_mass += $c['mass_count'];
                $total_choir += $c['choir_count'];
                $total_prayer += $c['prayer_count'];
            }
            $avg_mass = count($contestants) > 0 ? round($total_mass / count($contestants), 1) : 0;
            $avg_choir = count($contestants) > 0 ? round($total_choir / count($contestants), 1) : 0;
            $avg_prayer = count($contestants) > 0 ? round($total_prayer / count($contestants), 1) : 0;
            ?>
            <div class="summary-card mass">
                <div class="number"><?php echo $avg_mass; ?></div>
                <div class="stat-label">⛪ متوسط حضور القداس (يوم)</div>
            </div>
            <div class="summary-card choir">
                <div class="number"><?php echo $avg_choir; ?></div>
                <div class="stat-label">🎵 متوسط حضور الخورس (يوم)</div>
            </div>
            <div class="summary-card prayer">
                <div class="number"><?php echo $avg_prayer; ?></div>
                <div class="stat-label">🕊️ متوسط حضور التسبحة (يوم)</div>
            </div>
        </div>

        <!-- جدول الإحصائيات -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المتسابق</th>
                        <th>الفريق</th>
                        <th class="attendance-cell">⛪ القداس<br><small><?php echo $total_days; ?> يوم</small></th>
                        <th class="attendance-cell">🎵 الخورس<br><small><?php echo $total_days; ?> يوم</small></th>
                        <th class="attendance-cell">🕊️ التسبحة<br><small><?php echo $total_days; ?> يوم</small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contestants as $c): 
                        $mass_percent = round(($c['mass_count'] / $total_days) * 100);
                        $choir_percent = round(($c['choir_count'] / $total_days) * 100);
                        $prayer_percent = round(($c['prayer_count'] / $total_days) * 100);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                        <td><?php echo $c['team_name'] ? htmlspecialchars($c['team_name']) : '—'; ?></td>
                        <td class="attendance-cell">
                            <div style="font-weight: bold;"><?php echo $c['mass_count']; ?> / <?php echo $total_days; ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill mass" style="width: <?php echo $mass_percent; ?>%"></div>
                            </div>
                            <div class="percentage <?php echo $mass_percent >= 80 ? 'points-up' : ($mass_percent >= 50 ? '' : 'points-down'); ?>">
                                <?php echo $mass_percent; ?>%
                            </div>
                        </td>
                        <td class="attendance-cell">
                            <div style="font-weight: bold;"><?php echo $c['choir_count']; ?> / <?php echo $total_days; ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill choir" style="width: <?php echo $choir_percent; ?>%"></div>
                            </div>
                            <div class="percentage <?php echo $choir_percent >= 80 ? 'points-up' : ($choir_percent >= 50 ? '' : 'points-down'); ?>">
                                <?php echo $choir_percent; ?>%
                            </div>
                        </td>
                        <td class="attendance-cell">
                            <div style="font-weight: bold;"><?php echo $c['prayer_count']; ?> / <?php echo $total_days; ?></div>
                            <div class="progress-bar">
                                <div class="progress-fill prayer" style="width: <?php echo $prayer_percent; ?>%"></div>
                            </div>
                            <div class="percentage <?php echo $prayer_percent >= 80 ? 'points-up' : ($prayer_percent >= 50 ? '' : 'points-down'); ?>">
                                <?php echo $prayer_percent; ?>%
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($contestants)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">لا يوجد متسابقون في هذه المسابقة</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">إحصائيات الحضور</span></p>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>