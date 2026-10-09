<?php
header('Content-Type: text/css');
session_start();
require_once '../../config/db.php';

// الحصول على معرف المسابقة من الـ URL أو الجلسة
$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;

// اللون الافتراضي
$theme_color = '#7F77DD';

if ($comp_id) {
    $stmt = $pdo->prepare("SELECT theme_color FROM competitions WHERE id = ?");
    $stmt->execute([$comp_id]);
    $color = $stmt->fetchColumn();
    if ($color && $color != '') {
        $theme_color = $color;
    }
}

// تحويل اللون إلى RGB
$r = hexdec(substr($theme_color, 1, 2));
$g = hexdec(substr($theme_color, 3, 2));
$b = hexdec(substr($theme_color, 5, 2));
?>

:root {
    --competition-color: <?php echo $theme_color; ?>;
    --competition-color-rgb: <?php echo "$r, $g, $b"; ?>;
    --competition-color-d: <?php echo $theme_color; ?>d;
}

/* ===========================
   تطبيق اللون على العناصر
=========================== */

/* الأزرار الرئيسية */
.btn-primary {
    background: linear-gradient(135deg, var(--competition-color), var(--competition-color-d));
    box-shadow: 0 4px 16px rgba(var(--competition-color-rgb), 0.35);
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 24px rgba(var(--competition-color-rgb), 0.45);
}

/* أيقونة الشعار والـ Section Num */
.logo-icon,
.section-num {
    background: linear-gradient(135deg, var(--competition-color), var(--competition-color-d));
}

/* البادجات */
.badge-p {
    background: rgba(var(--competition-color-rgb), 0.15);
    color: var(--competition-color);
    border: 1px solid rgba(var(--competition-color-rgb), 0.2);
}

/* حدود البطاقات عند التمرير */
.competition-card:hover {
    border-color: var(--competition-color);
}

/* أزرار النوع النشطة */
.type-btn.mass.active {
    background: var(--competition-color);
    border-color: var(--competition-color);
}

/* البطاقات الإحصائية */
.stat-card.mass {
    border-top-color: var(--competition-color);
}

/* الروابط عند التمرير */
a:not(.btn):hover {
    color: var(--competition-color);
}

/* شريط التقدم */
.score-fill.sf-p {
    background: linear-gradient(90deg, var(--competition-color-d), var(--competition-color));
}

/* إضاءة الخلفية */
.hero-glow {
    background: radial-gradient(ellipse, rgba(var(--competition-color-rgb), 0.18) 0%, transparent 70%);
}

/* حد البطاقات */
.comp-card.c-purple::before {
    background: linear-gradient(90deg, var(--competition-color), transparent);
}

/* تحديد النص في الهيرو */
.hero h1 {
    background: linear-gradient(135deg, #fff 30%, var(--competition-color) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}