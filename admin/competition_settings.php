<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['id'] ?? null;
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

$message = '';
$error = '';

// حفظ التغييرات الأساسية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $theme_color = $_POST['theme_color'] ?? '#7F77DD';
    
    // معالجة رفع اللوجو الجديد
    $logo = $competition['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_logo = 'logo_' . time() . '.' . $ext;
        $target = "../uploads/" . $new_logo;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            if ($logo != 'default_logo.png' && file_exists("../uploads/" . $logo)) {
                unlink("../uploads/" . $logo);
            }
            $logo = $new_logo;
        }
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE competitions SET name = ?, description = ?, logo = ?, theme_color = ? WHERE id = ?");
        $stmt->execute([$name, $description, $logo, $theme_color, $comp_id]);
        $message = "تم تحديث بيانات المسابقة بنجاح";
        $competition['name'] = $name;
        $competition['description'] = $description;
        $competition['logo'] = $logo;
        $competition['theme_color'] = $theme_color;
    } catch (Exception $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// أرشفة المسابقة
if (isset($_POST['archive_competition'])) {
    try {
        $is_archived = $competition['is_archived'] ? 0 : 1;
        $archived_at = $is_archived ? date('Y-m-d H:i:s') : null;
        
        $stmt = $pdo->prepare("UPDATE competitions SET is_archived = ?, archived_at = ? WHERE id = ?");
        $stmt->execute([$is_archived, $archived_at, $comp_id]);
        
        if ($is_archived) {
            $message = "تم أرشفة المسابقة بنجاح. لن تظهر في القوائم الرئيسية.";
        } else {
            $message = "تم إلغاء أرشفة المسابقة بنجاح.";
        }
        
        $competition['is_archived'] = $is_archived;
        $competition['archived_at'] = $archived_at;
        
    } catch (Exception $e) {
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// حذف المسابقة نهائياً
if (isset($_POST['delete_competition'])) {
    if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'on') {
        $error = "يرجى تأكيد حذف المسابقة";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM competitions WHERE id = ?");
            $stmt->execute([$comp_id]);
            
            header("Location: dashboard.php?msg=" . urlencode("تم حذف المسابقة نهائياً"));
            exit;
            
        } catch (Exception $e) {
            $error = "حدث خطأ أثناء الحذف: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات المسابقة - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .settings-card {
            max-width: 800px;
            margin: 0 auto;
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .danger-zone {
            border: 1px solid rgba(216,90,48,0.3);
            background: rgba(216,90,48,0.05);
        }
        
        .danger-zone h3 {
            color: var(--coral-l);
            margin-bottom: 1rem;
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
        
        .form-group input, 
        .form-group textarea {
            width: 100%;
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 10px;
            padding: 10px 12px;
            color: white;
            font-family: 'Cairo', sans-serif;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .current-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 10px;
            background: #0f0f1a;
            border-radius: 10px;
        }
        
        .current-logo img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .color-input-group input[type="color"] {
            width: 60px;
            height: 50px;
            padding: 5px;
        }
        .color-input-group .color-text {
            width: 100px;
        }
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid #2d2d44;
        }
        
        .alert-success {
            background: rgba(29,158,117,0.15);
            border: 1px solid rgba(29,158,117,0.3);
            color: var(--teal-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: var(--coral-l);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .help-text {
            font-size: 11px;
            color: var(--text3);
            margin-top: 5px;
        }
        
        .archive-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 1rem;
        }
        
        .status-archived {
            background: rgba(216,90,48,0.15);
            color: var(--coral-l);
        }
        
        .status-active {
            background: rgba(29,158,117,0.15);
            color: var(--teal-l);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1rem 0;
        }
        
        .checkbox-group input {
            width: 20px;
            height: 20px;
        }
        
        hr {
            border-color: #2d2d44;
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .settings-card {
                padding: 1rem;
                margin: 0 1rem 1rem;
            }
            .form-actions {
                flex-direction: column;
            }
            .color-input-group {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">⚙️</div>
                إعدادات المسابقة
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
            <div class="hero-tag">⚙️ التخصيص</div>
            <h1><?php echo htmlspecialchars($competition['name']); ?></h1>
            <p>تغيير البيانات الأساسية، الألوان، أرشفة، أو حذف المسابقة</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- قسم البيانات الأساسية -->
        <div class="settings-card">
            <h3 style="margin-bottom: 1.5rem;">📝 البيانات الأساسية</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>🏆 اسم المسابقة</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($competition['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>🖼️ لوجو المسابقة</label>
                    <div class="current-logo">
                        <img src="../uploads/<?php echo $competition['logo']; ?>" alt="اللوجو الحالي">
                        <span class="help-text">اللوجو الحالي</span>
                    </div>
                    <input type="file" name="logo" accept="image/*">
                    <div class="help-text">اتركه فارغاً إذا لم ترد تغيير اللوجو</div>
                </div>

                <!-- ✅ قسم اللون الرئيسي -->
                <div class="form-group">
                    <label>🎨 اللون الرئيسي للمسابقة</label>
                    <div class="color-input-group">
                        <input type="color" name="theme_color" id="themeColorPicker" value="<?php echo $competition['theme_color'] ?? '#7F77DD'; ?>">
                        <input type="text" name="theme_color_text" id="themeColorText" class="color-text field-input" value="<?php echo $competition['theme_color'] ?? '#7F77DD'; ?>">
                        <div class="color-preview" id="colorPreview" style="background: <?php echo $competition['theme_color'] ?? '#7F77DD'; ?>;"></div>
                    </div>
                    <div class="help-text">اختر لوناً يميز مسابقتك (سيظهر في الأزرار والحدود)</div>
                </div>

                <div class="form-group">
                    <label>📝 وصف المسابقة</label>
                    <textarea name="description" placeholder="أدخل وصفاً مفصلاً للمسابقة..."><?php echo htmlspecialchars($competition['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="save_settings" class="btn btn-primary">💾 حفظ التغييرات</button>
                </div>
            </form>
        </div>

        <!-- قسم الأرشفة -->
        <div class="settings-card">
            <h3 style="margin-bottom: 1rem;">📦 أرشفة المسابقة</h3>
            <div class="archive-status <?php echo $competition['is_archived'] ? 'status-archived' : 'status-active'; ?>">
                <?php if ($competition['is_archived']): ?>
                    🗄️ هذه المسابقة مؤرشفة حالياً
                <?php else: ?>
                    ✅ هذه المسابقة نشطة
                <?php endif; ?>
            </div>
            <p style="color: var(--text2); margin-bottom: 1rem;">
                الأرشفة تخفي المسابقة من القوائم الرئيسية ولكن تحتفظ بكل البيانات. 
                يمكنك إلغاء الأرشفة في أي وقت.
            </p>
            <form method="POST">
                <button type="submit" name="archive_competition" class="btn btn-secondary">
                    <?php echo $competition['is_archived'] ? '📂 إلغاء الأرشفة' : '🗄️ أرشفة المسابقة'; ?>
                </button>
            </form>
        </div>

        <!-- قسم الحذف النهائي - منطقة خطر -->
        <div class="settings-card danger-zone">
            <h3>⚠️ حذف المسابقة نهائياً</h3>
            <p style="color: var(--coral-l); margin-bottom: 1rem;">
                تحذير: هذا الإجراء لا يمكن التراجع عنه. سيتم حذف المسابقة وجميع البيانات المرتبطة بها:
            </p>
            <ul style="color: var(--text2); margin-bottom: 1rem; padding-right: 1.5rem;">
                <li>✓ جميع الفرق المسجلة</li>
                <li>✓ جميع المتسابقين والحكام المسجلين</li>
                <li>✓ سجل الحضور والتعديلات</li>
                <li>✓ جميع الدرجات والإحصائيات</li>
            </ul>
            
            <form method="POST" onsubmit="return confirm('⚠️ هل أنت متأكد تماماً من حذف هذه المسابقة؟ هذا الإجراء لا يمكن التراجع عنه!');">
                <div class="checkbox-group">
                    <input type="checkbox" name="confirm_delete" id="confirm_delete" required>
                    <label for="confirm_delete" style="color: var(--coral-l);">
                        نعم، أنا متأكد من رغبتي في حذف المسابقة "<strong><?php echo htmlspecialchars($competition['name']); ?></strong>" نهائياً
                    </label>
                </div>
                <button type="submit" name="delete_competition" class="btn btn-danger" 
                        onclick="return confirm('⚠️ هل أنت متأكد تماماً؟ هذا الإجراء لا يمكن التراجع عنه!');">
                    🗑️ حذف المسابقة نهائياً
                </button>
            </form>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">إعدادات المسابقة</span></p>
    </footer>

    <script>
        // معاينة اللوجو قبل الرفع
        const logoInput = document.querySelector('input[name="logo"]');
        if (logoInput) {
            logoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const currentLogo = document.querySelector('.current-logo img');
                        if (currentLogo) {
                            currentLogo.src = event.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // مزامنة اللون بين color picker والحقل النصي
        const colorPicker = document.getElementById('themeColorPicker');
        const colorText = document.getElementById('themeColorText');
        const colorPreview = document.getElementById('colorPreview');
        
        if (colorPicker && colorText) {
            colorPicker.addEventListener('input', function() {
                colorText.value = this.value;
                colorPreview.style.background = this.value;
            });
            colorText.addEventListener('input', function() {
                colorPicker.value = this.value;
                colorPreview.style.background = this.value;
            });
        }
        
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>