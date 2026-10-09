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

$comp = $pdo->prepare("SELECT * FROM competitions WHERE id = ?");
$comp->execute([$comp_id]);
$competition = $comp->fetch();

// رابط لوجو المسابقة
$logo_url = "../uploads/" . $competition['logo'];

// أنواع الأسئلة مع ألوان هادئة (تناسب الاستايل الداكن)
$question_types = [
    'none' => ['name' => 'بدون نوع', 'icon' => '', 'color' => '#6c757d', 'accent' => '#8a8aaa', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #16213e)', 'show_label' => false],
    'bible' => ['name' => 'كتاب مقدس', 'icon' => '📖', 'color' => '#4A6FA5', 'accent' => '#7F77DD', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #2a1a3e)', 'show_label' => true],
    'doctrine' => ['name' => 'عقيدة', 'icon' => '✝️', 'color' => '#5B6EAE', 'accent' => '#7F77DD', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #1e2a3e)', 'show_label' => true],
    'hymn' => ['name' => 'ألحان', 'icon' => '🎵', 'color' => '#8B5F7C', 'accent' => '#a09aed', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #2a1a2a)', 'show_label' => true],
    'coptic' => ['name' => 'قبطى', 'icon' => '🇪🇬', 'color' => '#C7794D', 'accent' => '#f5b84d', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #2a1a15)', 'show_label' => true],
    'ritual' => ['name' => 'طقس', 'icon' => '🕯️', 'color' => '#9B6BA5', 'accent' => '#a09aed', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #201a2e)', 'show_label' => true],
    'speed' => ['name' => 'سرعة', 'icon' => '⚡', 'color' => '#C94F4F', 'accent' => '#e97a55', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #2a1515)', 'show_label' => true],
    'culture' => ['name' => 'ثقافى', 'icon' => '🌍', 'color' => '#4F9E7A', 'accent' => '#2dcea0', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #152a20)', 'show_label' => true],
    'math' => ['name' => 'حساب', 'icon' => '🧮', 'color' => '#D4A343', 'accent' => '#f5b84d', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #2a2015)', 'show_label' => true],
    'general' => ['name' => 'متنوع', 'icon' => '🎯', 'color' => '#7B8A6B', 'accent' => '#8a8aaa', 'bg_gradient' => 'linear-gradient(135deg, #1a1a2e, #1a2215)', 'show_label' => true]
];

$generated_html = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_text = trim($_POST['question_text']);
    $template_type = $_POST['template_type'] ?? 'modern';
    $question_type = $_POST['question_type'] ?? 'none';
    $question_date = $_POST['question_date'] ?? date('Y-m-d');
    $custom_bg_color = $_POST['custom_bg_color'] ?? '';
    $custom_accent_color = $_POST['custom_accent_color'] ?? '';
    
    if (empty($question_text)) {
        $error = "الرجاء إدخال نص السؤال";
    } else {
        $formatted_text = nl2br(htmlspecialchars($question_text));
        $type_info = $question_types[$question_type] ?? $question_types['none'];
        
        // استخدام الألوان المخصصة إذا وجدت
        $bg_gradient = $type_info['bg_gradient'];
        $accent_color = $type_info['accent'];
        
        if (!empty($custom_bg_color)) {
            $bg_gradient = "linear-gradient(135deg, {$custom_bg_color}, " . adjustBrightness($custom_bg_color, -20) . ")";
        }
        if (!empty($custom_accent_color)) {
            $accent_color = $custom_accent_color;
        }
        
        // تنسيق التاريخ العربي
        $date_obj = new DateTime($question_date);
        $formatted_date = $date_obj->format('d') . ' / ' . $date_obj->format('m') . ' / ' . $date_obj->format('Y');
        
        // عرض النوع فقط إذا لم يكن "بدون نوع"
        $type_label_html = '';
        if ($type_info['show_label']) {
            $type_label_html = '
                <div style="
                    position: absolute;
                    top: 25px;
                    left: 25px;
                    background: rgba(0,0,0,0.4);
                    padding: 8px 20px;
                    border-radius: 40px;
                    font-size: 14px;
                    font-weight: bold;
                    color: ' . $accent_color . ';
                    backdrop-filter: blur(5px);
                ">' . $type_info['icon'] . ' ' . $type_info['name'] . '</div>';
        }
        
        // قوالب
        $templates_styles = [
            'modern' => '
                <div style="
                    width: 800px;
                    height: 800px;
                    background: ' . $bg_gradient . ';
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    border-radius: 40px;
                    font-family: \'Cairo\', sans-serif;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                ">
                    <div style="
                        position: absolute;
                        top: -50%;
                        right: -50%;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
                        transform: rotate(30deg);
                    "></div>
                    <div style="
                        position: absolute;
                        top: 25px;
                        right: 25px;
                        background: rgba(0,0,0,0.4);
                        padding: 8px 20px;
                        border-radius: 40px;
                        font-size: 14px;
                        color: ' . $accent_color . ';
                        backdrop-filter: blur(5px);
                    ">📅 ' . $formatted_date . '</div>
                    ' . $type_label_html . '
                    <div style="
                        position: absolute;
                        bottom: 25px;
                        left: 25px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    ">
                        <img src="' . $logo_url . '" style="height: 35px; width: auto; max-width: 100px; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.6;">
                        <span style="font-size: 12px; color: rgba(255,255,255,0.5);">' . htmlspecialchars($competition['name']) . '</span>
                    </div>
                    <div style="
                        background: rgba(0,0,0,0.5);
                        padding: 2rem 2.5rem;
                        border-radius: 60px;
                        margin: 1rem;
                        backdrop-filter: blur(8px);
                        max-width: 85%;
                    ">
                        <div style="
                            font-size: 46px;
                            font-weight: 900;
                            color: #ffffff;
                            margin: 0;
                            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                            line-height: 1.5;
                            word-wrap: break-word;
                            white-space: normal;
                            max-width: 100%;
                        ">' . $formatted_text . '</div>
                    </div>
                </div>
            ',
            'minimal' => '
                <div style="
                    width: 800px;
                    height: 800px;
                    background: ' . $bg_gradient . ';
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    font-family: \'Cairo\', sans-serif;
                    position: relative;
                ">
                    <div style="
                        border: 2px solid ' . $accent_color . ';
                        border-radius: 30px;
                        padding: 2rem;
                        margin: 2rem;
                        width: 85%;
                        height: 85%;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        background: rgba(0,0,0,0.6);
                        backdrop-filter: blur(5px);
                    ">
                        <img src="' . $logo_url . '" style="height: 45px; width: auto; margin-bottom: 15px; filter: brightness(0) invert(1); opacity: 0.6;">
                        ' . ($type_info['show_label'] ? '<div style="font-size: 18px; font-weight: bold; color: ' . $accent_color . '; margin-bottom: 10px;">' . $type_info['icon'] . ' ' . $type_info['name'] . '</div>' : '') . '
                        <div style="
                            font-size: 14px;
                            color: ' . $accent_color . ';
                            margin-bottom: 20px;
                        ">📅 ' . $formatted_date . '</div>
                        <div style="
                            font-size: 38px;
                            font-weight: bold;
                            color: #ffffff;
                            margin: 0;
                            line-height: 1.5;
                            word-wrap: break-word;
                            white-space: normal;
                            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                        ">' . $formatted_text . '</div>
                    </div>
                </div>
            ',
            'gradient' => '
                <div style="
                    width: 800px;
                    height: 800px;
                    background: ' . $bg_gradient . ';
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    border-radius: 30px;
                    font-family: \'Cairo\', sans-serif;
                    position: relative;
                ">
                    <div style="
                        background: rgba(0,0,0,0.5);
                        padding: 2rem;
                        border-radius: 30px;
                        margin: 1rem;
                        max-width: 85%;
                        backdrop-filter: blur(10px);
                    ">
                        <div style="
                            display: flex;
                            justify-content: center;
                            gap: 15px;
                            margin-bottom: 15px;
                            flex-wrap: wrap;
                        ">
                            ' . ($type_info['show_label'] ? '<span style="background: rgba(0,0,0,0.4); padding: 5px 15px; border-radius: 30px; font-size: 14px; color: ' . $accent_color . ';">' . $type_info['icon'] . ' ' . $type_info['name'] . '</span>' : '') . '
                            <span style="background: rgba(0,0,0,0.4); padding: 5px 15px; border-radius: 30px; font-size: 14px; color: ' . $accent_color . ';">📅 ' . $formatted_date . '</span>
                        </div>
                        <div style="
                            font-size: 44px;
                            font-weight: 900;
                            color: #ffffff;
                            margin: 0;
                            line-height: 1.5;
                            word-wrap: break-word;
                            white-space: normal;
                            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                        ">' . $formatted_text . '</div>
                    </div>
                    <img src="' . $logo_url . '" style="
                        position: absolute;
                        bottom: 25px;
                        left: 25px;
                        height: 35px;
                        width: auto;
                        opacity: 0.5;
                        filter: brightness(0) invert(1);
                    ">
                </div>
            ',
            'card' => '
                <div style="
                    width: 800px;
                    height: 800px;
                    background: linear-gradient(135deg, #0f0f1a, #1a1a2e);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: \'Cairo\', sans-serif;
                    padding: 40px;
                ">
                    <div style="
                        background: #1a1a2e;
                        border-radius: 40px;
                        padding: 2rem;
                        width: 100%;
                        height: 100%;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        text-align: center;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                        position: relative;
                        border: 1px solid rgba(255,255,255,0.05);
                    ">
                        <div style="
                            background: ' . $accent_color . ';
                            width: 70px;
                            height: 70px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 15px;
                            font-size: 32px;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                        ">' . ($type_info['show_label'] ? $type_info['icon'] : '📅') . '</div>
                        ' . ($type_info['show_label'] ? '<div style="font-size: 18px; font-weight: bold; color: ' . $accent_color . '; margin-bottom: 5px;">' . $type_info['name'] . '</div>' : '') . '
                        <div style="
                            font-size: 14px;
                            color: ' . $accent_color . ';
                            margin-bottom: 20px;
                        ">📅 ' . $formatted_date . '</div>
                        <div style="
                            font-size: 36px;
                            font-weight: 900;
                            color: #e8e6ff;
                            margin: 0 0 20px 0;
                            line-height: 1.4;
                            word-wrap: break-word;
                            white-space: normal;
                        ">' . $formatted_text . '</div>
                        <div style="
                            position: absolute;
                            bottom: 20px;
                            left: 20px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                        ">
                            <img src="' . $logo_url . '" style="height: 25px; width: auto; opacity: 0.3; filter: brightness(0) invert(1);">
                            <span style="font-size: 10px; color: rgba(255,255,255,0.3);">' . htmlspecialchars($competition['name']) . '</span>
                        </div>
                    </div>
                </div>
            '
        ];
        
        $generated_html = $templates_styles[$template_type] ?? $templates_styles['modern'];
    }
}

// دالة لتعديل سطوع اللون
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 1, 2));
    $b = hexdec(substr($hex, 2, 2));
    
    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء سؤال اليوم - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        .question-creator {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 2rem;
        }
        .form-section, .preview-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text2);
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            color: var(--text);
            font-family: var(--font-ar);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .color-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .color-row input[type="color"] {
            width: 50px;
            height: 40px;
            padding: 4px;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        .preview-container {
            background: var(--bg2);
            border-radius: 16px;
            padding: 1rem;
            overflow-x: auto;
            display: flex;
            justify-content: center;
        }
        .download-btn {
            display: inline-block;
            margin-top: 1rem;
            background: var(--teal);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .template-option {
            display: inline-block;
            padding: 8px 16px;
            margin: 4px;
            background: var(--bg2);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .template-option.selected {
            background: var(--purple);
            color: white;
        }
        .type-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin: 5px;
            background: var(--bg2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .type-option.selected {
            border-color: var(--purple);
            background: rgba(127,119,221,0.1);
        }
        .type-option:hover {
            background: rgba(127,119,221,0.05);
        }
        .types-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: var(--coral-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .template-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .help-text {
            font-size: 11px;
            color: var(--text3);
            margin-top: 4px;
        }
        @media (max-width: 900px) {
            .question-creator {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">📅</div>
                سؤال اليوم
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
            <div class="hero-tag">📅 سؤال اليوم</div>
            <h1>إنشاء سؤال اليوم</h1>
            <p>صمم سؤالاً بقالب احترافي وتحكم كامل بالألوان</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="question-creator">
            <div class="form-section">
                <h3>✏️ تصميم السؤال</h3>
                <form method="POST" id="questionForm">
                    <div class="form-group">
                        <label>📝 نص السؤال</label>
                        <textarea name="question_text" id="questionText" placeholder="اكتب سؤال اليوم هنا..." required><?php echo htmlspecialchars($_POST['question_text'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>📅 تاريخ السؤال</label>
                        <input type="date" name="question_date" id="questionDate" value="<?php echo $_POST['question_date'] ?? date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>🏷️ نوع السؤال</label>
                        <input type="hidden" name="question_type" id="questionType" value="<?php echo $_POST['question_type'] ?? 'none'; ?>">
                        <div class="types-grid">
                            <?php foreach ($question_types as $key => $type): ?>
                                <div class="type-option <?php echo ($_POST['question_type'] ?? 'none') == $key ? 'selected' : ''; ?>" 
                                     data-type="<?php echo $key; ?>" 
                                     onclick="selectType('<?php echo $key; ?>')"
                                     style="border-right: 3px solid <?php echo $type['color']; ?>">
                                    <?php if ($type['icon']): ?>
                                        <span><?php echo $type['icon']; ?></span>
                                    <?php else: ?>
                                        <span>❓</span>
                                    <?php endif; ?>
                                    <span style="flex:1"><?php echo $type['name']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>🎨 اختر القالب</label>
                        <div class="template-buttons">
                            <button type="button" class="template-option selected" data-template="modern" onclick="selectTemplate('modern')">✨ عصري</button>
                            <button type="button" class="template-option" data-template="minimal" onclick="selectTemplate('minimal')">🎯 بسيط</button>
                            <button type="button" class="template-option" data-template="gradient" onclick="selectTemplate('gradient')">🌈 متدرج</button>
                            <button type="button" class="template-option" data-template="card" onclick="selectTemplate('card')">💳 بطاقة</button>
                        </div>
                        <input type="hidden" name="template_type" id="templateType" value="modern">
                    </div>
                    
                    <div class="form-group">
                        <label>🎨 تخصيص لون الخلفية (اختياري)</label>
                        <div class="color-row">
                            <input type="color" name="custom_bg_color" id="customBgColor" value="<?php echo $_POST['custom_bg_color'] ?? ''; ?>">
                            <input type="text" class="field-input" id="customBgColorText" placeholder="اتركه فارغاً للون الافتراضي" value="<?php echo $_POST['custom_bg_color'] ?? ''; ?>">
                        </div>
                        <div class="help-text">اختر لونك المفضل للخلفية (سيطبق على القوالب: عصري، بسيط، متدرج)</div>
                    </div>
                    
                    <div class="form-group">
                        <label>⭐ تخصيص لون اللمسات (اختياري)</label>
                        <div class="color-row">
                            <input type="color" name="custom_accent_color" id="customAccentColor" value="<?php echo $_POST['custom_accent_color'] ?? ''; ?>">
                            <input type="text" class="field-input" id="customAccentColorText" placeholder="اتركه فارغاً للون الافتراضي" value="<?php echo $_POST['custom_accent_color'] ?? ''; ?>">
                        </div>
                        <div class="help-text">اختر لونك المفضل للحدود والنصوص البارزة</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🎨 معاينة التصميم</button>
                </form>
            </div>
            
            <div class="preview-section">
                <h3>👁️ معاينة الصورة</h3>
                <?php if ($generated_html): ?>
                    <div class="preview-container">
                        <div id="questionCard">
                            <?php echo $generated_html; ?>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1rem;">
                        <button onclick="downloadImage()" class="download-btn">
                            📥 تحميل الصورة (PNG)
                        </button>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text3);">
                        🖼️ سيظهر هنا معاينة السؤال بعد تصميمه
                        <br>
                        <small>اختر القالب والألوان ثم اضغط "معاينة التصميم"</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">سؤال اليوم</span></p>
    </footer>

    <script>
        function syncColor(input, text) {
            if (input && text) {
                input.addEventListener('input', function() { text.value = this.value; });
                text.addEventListener('input', function() { 
                    input.value = this.value;
                    // تحديث التصميم تلقائياً عند تغيير اللون
                    if (this.value && this.value.length === 7) {
                        document.getElementById('questionForm').submit();
                    }
                });
            }
        }
        syncColor(document.getElementById('customBgColor'), document.getElementById('customBgColorText'));
        syncColor(document.getElementById('customAccentColor'), document.getElementById('customAccentColorText'));
        
        function selectTemplate(type) {
            document.getElementById('templateType').value = type;
            document.querySelectorAll('.template-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.template === type) {
                    opt.classList.add('selected');
                }
            });
            // تحديث المعاينة عند اختيار قالب جديد
            document.getElementById('questionForm').submit();
        }
        
        function selectType(type) {
            document.getElementById('questionType').value = type;
            document.querySelectorAll('.type-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.type === type) {
                    opt.classList.add('selected');
                }
            });
            // تحديث المعاينة عند اختيار نوع جديد
            document.getElementById('questionForm').submit();
        }
        
        function downloadImage() {
            const element = document.getElementById('questionCard');
            if (!element) return;
            
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '⏳ جاري المعالجة...';
            btn.disabled = true;
            
            html2canvas(element, {
                scale: 2.5,
                backgroundColor: null,
                useCORS: true,
                logging: false
            }).then(canvas => {
                const link = document.createElement('a');
                const date = new Date();
                const dateStr = document.getElementById('questionDate')?.value || date.toISOString().slice(0,10);
                link.download = 'question_' + dateStr + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                btn.textContent = originalText;
                btn.disabled = false;
            }).catch(err => {
                console.error('Error:', err);
                alert('حدث خطأ في إنشاء الصورة');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>