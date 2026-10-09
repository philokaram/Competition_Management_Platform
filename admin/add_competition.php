<?php
session_start();
if (!isset($_SESSION['user_id']) || !$_SESSION['is_super_admin']) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/db.php';

$message = '';
$error = '';

if (isset($_POST['add_competition'])) {
    $name = $_POST['comp_name'];
    $description = $_POST['description'];
    $logo = 'default_logo.png';
    
    if (isset($_FILES['logo']) && $_FILES['logo']['name']) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo = 'logo_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], "../uploads/" . $logo);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO competitions (name, description, logo) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $logo]);
        $comp_id = $pdo->lastInsertId();
        
        $judge_names = $_POST['judge_name'] ?? [];
        $judge_usernames = $_POST['judge_username'] ?? [];
        $judge_passwords = $_POST['judge_password'] ?? [];
        
        for ($i = 0; $i < count($judge_names); $i++) {
            if (!empty($judge_names[$i]) && !empty($judge_usernames[$i]) && !empty($judge_passwords[$i])) {
                $j_name = $judge_names[$i];
                $j_user = $judge_usernames[$i];
                $j_pass = password_hash($judge_passwords[$i], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name) VALUES (?, ?, ?)");
                $stmt->execute([$j_user, $j_pass, $j_name]);
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$j_user]);
                $u_id = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("INSERT INTO competition_users (competition_id, user_id, role) VALUES (?, ?, 'judge')");
                $stmt->execute([$comp_id, $u_id]);
            }
        }
        
        $pdo->commit();
        header("Location: dashboard.php?msg=" . urlencode("تم إضافة المسابقة بنجاح"));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "خطأ: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مسابقة جديدة - منصة المسابقات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        /* Override styles for better visibility */
        body {
            background: #0a0a15;
        }
        
        .form-card {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 20px;
            padding: 2rem;
            margin-top: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .field-group {
            margin-bottom: 1.5rem;
        }
        
        .field-label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            font-size: 14px;
            color: #c4c4e0;
        }
        
        .input-wrap {
            position: relative;
        }
        
        .field-input {
            width: 100%;
            background: #0f0f1a;
            border: 1px solid #3a3a55;
            border-radius: 12px;
            padding: 12px 16px;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            color: #ffffff;
            outline: none;
            transition: all 0.2s;
        }
        
        .field-input:focus {
            border-color: #7F77DD;
            box-shadow: 0 0 0 3px rgba(127,119,221,0.2);
        }
        
        .field-input::placeholder {
            color: #6a6a8b;
        }
        
        textarea.field-input {
            resize: vertical;
            min-height: 100px;
        }
        
        input.field-input, 
        textarea.field-input, 
        select.field-input {
            color: white;
            background: #0f0f1a;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .full-width {
            grid-column: span 2;
        }
        
        .judge-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 12px;
            margin-bottom: 12px;
            align-items: center;
        }
        
        .judge-row .field-input {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #2d2d44;
        }
        
        hr {
            border-color: #2d2d44;
            margin: 0.5rem 0 1rem;
        }
        
        h3 {
            color: #a09aed;
            margin: 0.5rem 0;
            font-size: 18px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'Cairo', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7F77DD, #534AB7);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127,119,221,0.4);
        }
        
        .btn-secondary {
            background: transparent;
            color: #a09aed;
            border: 1px solid #3a3a55;
        }
        
        .btn-secondary:hover {
            background: rgba(127,119,221,0.1);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #D85A30, #4A1B0C);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
        }
        
        .alert-success {
            background: rgba(29,158,117,0.15);
            border: 1px solid rgba(29,158,117,0.3);
            color: #2dcea0;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: rgba(216,90,48,0.15);
            border: 1px solid rgba(216,90,48,0.3);
            color: #e97a55;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        small {
            color: #6a6a8b;
            font-size: 11px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
            .judge-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
    
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">➕</div>
                إضافة مسابقة جديدة
            </a>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <nav>
                <a href="dashboard.php">← العودة للمسابقات</a>
                <a href="../logout.php">تسجيل الخروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-glow"></div>
            <div class="hero-tag">✨ إنشاء جديد</div>
            <h1>إضافة مسابقة جديدة</h1>
            <p>قم بإدخال بيانات المسابقة وإضافة الحكام</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" class="form-grid">
                <div class="field-group full-width">
                    <label class="field-label">🏆 اسم المسابقة</label>
                    <div class="input-wrap">
                        <input type="text" name="comp_name" class="field-input" placeholder="مثال: مسابقة الكنيسة 2025" required>
                    </div>
                </div>
                
                <div class="field-group full-width">
                    <label class="field-label">📝 وصف المسابقة</label>
                    <div class="input-wrap">
                        <textarea name="description" class="field-input" rows="4" placeholder="أدخل وصفاً مفصلاً للمسابقة..."></textarea>
                    </div>
                </div>
                
                <div class="field-group">
                    <label class="field-label">🖼️ لوجو المسابقة</label>
                    <div class="input-wrap">
                        <input type="file" name="logo" class="field-input" accept="image/*">
                    </div>
                    <small>يُفضل استخدام صورة بحجم 200×200 بكسل</small>
                </div>
                
                <div class="field-group full-width">
                    <hr>
                    <h3>👨‍⚖️ إضافة الحكام</h3>
                    <p style="color: #8a8aaa; font-size: 13px; margin-bottom: 1rem;">يمكنك إضافة حكم أو أكثر للمسابقة</p>
                    <div id="judges-container">
                        <div class="judge-row">
                            <input type="text" name="judge_name[]" class="field-input" placeholder="اسم الحكم">
                            <input type="text" name="judge_username[]" class="field-input" placeholder="اسم المستخدم">
                            <input type="password" name="judge_password[]" class="field-input" placeholder="كلمة المرور">
                            <button type="button" class="btn btn-danger btn-sm remove-judge" style="display: none;">🗑 حذف</button>
                        </div>
                    </div>
                    <button type="button" id="add-judge" class="btn btn-secondary btn-sm" style="margin-top: 0.75rem;">+ إضافة حكم آخر</button>
                </div>
                
                <div class="form-actions full-width">
                    <button type="submit" name="add_competition" class="btn btn-primary">✨ إنشاء المسابقة</button>
                    <a href="dashboard.php" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:#a09aed">إضافة مسابقة جديدة</span></p>
    </footer>

    <script>
        const container = document.getElementById('judges-container');
        
        document.getElementById('add-judge').addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'judge-row';
            newRow.innerHTML = `
                <input type="text" name="judge_name[]" class="field-input" placeholder="اسم الحكم">
                <input type="text" name="judge_username[]" class="field-input" placeholder="اسم المستخدم">
                <input type="password" name="judge_password[]" class="field-input" placeholder="كلمة المرور">
                <button type="button" class="btn btn-danger btn-sm remove-judge">🗑 حذف</button>
            `;
            container.appendChild(newRow);
            
            document.querySelectorAll('.remove-judge').forEach(btn => {
                btn.style.display = 'block';
            });
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-judge')) {
                e.target.closest('.judge-row').remove();
            }
        });
    </script>
</body>
</html>