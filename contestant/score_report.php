<?php
session_start();
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$comp_id = $_SESSION['comp_id'] ?? null;

if (!$user_id || !$comp_id) {
    header('Location: ../select_competition.php');
    exit;
}

// جلب دور المستخدم وفريقه
$stmt = $pdo->prepare("
    SELECT cu.role, cu.team_id, t.name as team_name
    FROM competition_users cu
    LEFT JOIN teams t ON cu.team_id = t.id
    WHERE cu.user_id = ? AND cu.competition_id = ?
");
$stmt->execute([$user_id, $comp_id]);
$user_info = $stmt->fetch();
$user_role = $user_info['role'] ?? 'contestant';
$user_team_id = $user_info['team_id'] ?? null;
$user_team_name = $user_info['team_name'] ?? '';

// فلتر العضو المختار (للقائد فقط)
$selected_member_id = $_GET['member'] ?? $user_id;
$is_team_leader = ($user_role == 'team_leader');

// إذا كان قائد فريق، جلب أعضاء فريقه للفلتر
$team_members = [];
if ($is_team_leader && $user_team_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name
        FROM users u
        JOIN competition_users cu ON u.id = cu.user_id
        WHERE cu.team_id = ? AND cu.competition_id = ? AND cu.role = 'contestant'
        ORDER BY u.full_name
    ");
    $stmt->execute([$user_team_id, $comp_id]);
    $team_members = $stmt->fetchAll();
    
    // التأكد من أن العضو المختار موجود في الفريق
    $member_exists = false;
    foreach ($team_members as $m) {
        if ($m['id'] == $selected_member_id) $member_exists = true;
    }
    if (!$member_exists && !empty($team_members)) {
        $selected_member_id = $team_members[0]['id'];
    }
}

// جلب بيانات المتسابق المختار
$stmt = $pdo->prepare("
    SELECT u.full_name, t.name as team_name,
    cu.mass_score, cu.choir_score, cu.prayer_score, cu.interaction_score,
    cu.role
    FROM users u
    JOIN competition_users cu ON u.id = cu.user_id
    LEFT JOIN teams t ON cu.team_id = t.id
    WHERE u.id = ? AND cu.competition_id = ?
");
$stmt->execute([$selected_member_id, $comp_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    header('Location: ../select_competition.php');
    exit;
}

$total_score = ($current_user['mass_score'] ?? 0) + 
               ($current_user['choir_score'] ?? 0) + 
               ($current_user['prayer_score'] ?? 0) + 
               ($current_user['interaction_score'] ?? 0);

// جلب آخر 50 تعديل يخص هذا المتسابق (سجل الدرجات)
$stmt = $pdo->prepare("
    SELECT h.*, a.full_name as admin_name
    FROM score_history h
    LEFT JOIN users a ON h.admin_id = a.id
    WHERE h.competition_id = ? AND h.user_id = ?
    ORDER BY h.created_at DESC
    LIMIT 50
");
$stmt->execute([$comp_id, $selected_member_id]);
$score_history = $stmt->fetchAll();

// إحصائيات سريعة
$total_added = 0;
$total_deducted = 0;
foreach ($score_history as $s) {
    if ($s['points_change'] > 0) {
        $total_added += $s['points_change'];
    } else {
        $total_deducted += abs($s['points_change']);
    }
}

$type_names = [
    'mass' => 'القداس',
    'choir' => 'الخورس',
    'prayer' => 'التسبحة',
    'interaction' => 'تفاعل',
    'team_bonus' => 'بونص فريق'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير درجاتي - <?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
        }
        .stat-summary-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Space Mono', monospace;
        }
        .stat-summary-card .stat-label {
            font-size: 12px;
            color: var(--text3);
            margin-top: 5px;
        }
        .stat-summary-card.total { border-top: 3px solid var(--gold); }
        .stat-summary-card.added { border-top: 3px solid var(--teal); }
        .stat-summary-card.deducted { border-top: 3px solid var(--coral); }
        .stat-summary-card.rank { border-top: 3px solid var(--purple); }
        
        .score-table-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow-x: auto;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        .score-table th,
        .score-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        .score-table th {
            background: var(--bg3);
            color: var(--text2);
            font-weight: 600;
        }
        .score-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .badge-mass { background: rgba(127,119,221,0.15); color: var(--purple-l); }
        .badge-choir { background: rgba(29,158,117,0.15); color: var(--teal-l); }
        .badge-prayer { background: rgba(239,159,39,0.15); color: var(--gold-l); }
        .badge-interaction { background: rgba(216,90,48,0.15); color: var(--coral-l); }
        .badge-team_bonus { background: rgba(239,159,39,0.15); color: var(--gold-l); }
        
        .points-positive { color: var(--teal-l); font-weight: bold; }
        .points-negative { color: var(--coral-l); font-weight: bold; }
        
        .current-scores {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .current-score-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
        }
        .current-score-card .score-icon { font-size: 24px; margin-bottom: 5px; }
        .current-score-card .score-value { font-size: 22px; font-weight: 700; }
        .current-score-card .score-label { font-size: 11px; color: var(--text3); }
        
        .member-filter {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .member-filter label {
            font-weight: 600;
            color: var(--text2);
        }
        .member-filter select {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 15px;
            color: var(--text);
            font-family: var(--font-ar);
            min-width: 200px;
        }
        .team-badge {
            background: var(--purple);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            .current-scores {
                grid-template-columns: repeat(2, 1fr);
            }
            .member-filter {
                flex-direction: column;
                align-items: stretch;
            }
            .member-filter select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">📊</div>
                تقرير درجاتي
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="../select_competition.php">تغيير المسابقة</a>
                <a href="profile.php">بروفايلي</a>
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
            <div class="hero-glow"></div>
            <div class="hero-tag">📊 تقرير شامل</div>
            <h1>تقرير درجاتي</h1>
            <p>
                <?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?> 
                <?php if ($current_user['team_name']): ?>
                    - فريق <?php echo htmlspecialchars($current_user['team_name']); ?>
                <?php endif; ?>
                <?php if ($is_team_leader): ?>
                    <span class="team-badge">👑 قائد الفريق</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- فلتر الأعضاء (لقائد الفريق فقط) -->
        <?php if ($is_team_leader && count($team_members) > 0): ?>
        <div class="member-filter">
            <label>👥 اختر العضو:</label>
            <form method="GET" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                <select name="member" onchange="this.form.submit()">
                    <option value="<?php echo $user_id; ?>" <?php echo $selected_member_id == $user_id ? 'selected' : ''; ?>>
                        👑 <?php echo htmlspecialchars($user_info['full_name'] ?? 'أنا'); ?> (قائد الفريق)
                    </option>
                    <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>" <?php echo $selected_member_id == $member['id'] ? 'selected' : ''; ?>>
                            🏃 <?php echo htmlspecialchars($member['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="help-text" style="font-size: 11px;">
                يمكنك مشاهدة تقارير درجات جميع أعضاء فريقك
            </div>
        </div>
        <?php endif; ?>

        <!-- الملخص السريع -->
        <div class="stats-summary">
            <div class="stat-summary-card total">
                <div class="stat-value"><?php echo number_format($total_score); ?></div>
                <div class="stat-label">🏆 إجمالي النقاط</div>
            </div>
            <div class="stat-summary-card added">
                <div class="stat-value">+<?php echo number_format($total_added); ?></div>
                <div class="stat-label">➕ إجمالي ما أضيف</div>
            </div>
            <div class="stat-summary-card deducted">
                <div class="stat-value">-<?php echo number_format($total_deducted); ?></div>
                <div class="stat-label">➖ إجمالي ما خُصم</div>
            </div>
            <div class="stat-summary-card rank">
                <div class="stat-value"><?php echo number_format(count($score_history)); ?></div>
                <div class="stat-label">📋 عدد التعديلات</div>
            </div>
        </div>

        <!-- الدرجات الحالية -->
        <div class="current-scores">
            <div class="current-score-card">
                <div class="score-icon">⛪</div>
                <div class="score-value"><?php echo number_format($current_user['mass_score'] ?? 0); ?></div>
                <div class="score-label">درجة القداس</div>
            </div>
            <div class="current-score-card">
                <div class="score-icon">🎵</div>
                <div class="score-value"><?php echo number_format($current_user['choir_score'] ?? 0); ?></div>
                <div class="score-label">درجة الخورس</div>
            </div>
            <div class="current-score-card">
                <div class="score-icon">🕊️</div>
                <div class="score-value"><?php echo number_format($current_user['prayer_score'] ?? 0); ?></div>
                <div class="score-label">درجة التسبحة</div>
            </div>
            <div class="current-score-card">
                <div class="score-icon">💬</div>
                <div class="score-value"><?php echo number_format($current_user['interaction_score'] ?? 0); ?></div>
                <div class="score-label">درجة التفاعل</div>
            </div>
        </div>

        <!-- سجل الدرجات -->
        <div class="score-table-container">
            <table class="score-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>نوع التعديل</th>
                        <th>التغيير</th>
                        <th>القيمة القديمة</th>
                        <th>القيمة الجديدة</th>
                        <th>بواسطة</th>
                        <th>الملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($score_history)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                📭 لا توجد تعديلات في درجات هذا العضو حتى الآن
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($score_history as $s): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($s['created_at'])); ?></td>
                                <td>
                                    <span class="score-type-badge badge-<?php echo $s['score_type']; ?>">
                                        <?php echo $type_names[$s['score_type']] ?? $s['score_type']; ?>
                                    </span>
                                </td>
                                <td class="<?php echo $s['points_change'] >= 0 ? 'points-positive' : 'points-negative'; ?>">
                                    <?php echo $s['points_change'] >= 0 ? '+' : ''; ?><?php echo $s['points_change']; ?>
                                </td>
                                <td><?php echo $s['old_value']; ?></td>
                                <td><?php echo $s['new_value']; ?></td>
                                <td><?php echo htmlspecialchars($s['admin_name'] ?? 'النظام'); ?></td>
                                <td><?php echo htmlspecialchars($s['notes'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($is_team_leader && count($team_members) > 1): ?>
        <div class="help-text" style="text-align: center; margin-top: 1rem; padding: 0.5rem;">
            💡 يمكنك استخدام الفلتر أعلاه لمشاهدة تقارير جميع أعضاء فريقك
        </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">تقرير درجاتي</span></p>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>