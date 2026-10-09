<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) { header('Location: dashboard.php'); exit; }

$message = '';

// Handle Single User Addition to this competition
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $team_id = $_POST['team_id'];
    $role = $_POST['role'] ?? 'contestant';
    
    try {
        $pdo->beginTransaction();
        // Create user in global users if not exists
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $full_name]);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $u_id = $stmt->fetchColumn();
        
        // Link to competition
        $stmt = $pdo->prepare("INSERT INTO competition_users (competition_id, user_id, team_id, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$comp_id, $u_id, $team_id ?: null, $role]);
        
        $pdo->commit();
        $message = "تم إضافة المستخدم للمسابقة";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "خطأ: " . $e->getMessage();
    }
}

// Handle CSV Upload for this competition
if (isset($_POST['upload_csv'])) {
    if ($_FILES['csv_file']['name']) {
        $filename = $_FILES['csv_file']['tmp_name'];
        $file = fopen($filename, "r");
        while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
            $full_name = $data[0];
            $username = $data[1];
            $password = password_hash($data[2], PASSWORD_DEFAULT);
            $team_id = $data[3]; // Should be team ID in this competition
            
            try {
                $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name) VALUES (?, ?, ?)")->execute([$username, $password, $full_name]);
                $u_id = $pdo->query("SELECT id FROM users WHERE username = '$username'")->fetchColumn();
                $pdo->prepare("INSERT IGNORE INTO competition_users (competition_id, user_id, team_id, role) VALUES (?, ?, ?, 'contestant')")->execute([$comp_id, $u_id, $team_id]);
            } catch (Exception $e) {}
        }
        fclose($file);
        $message = "تم معالجة الملف";
    }
}

$users = $pdo->prepare("
    SELECT u.*, cu.role, t.name as team_name 
    FROM users u 
    JOIN competition_users cu ON u.id = cu.user_id 
    LEFT JOIN teams t ON cu.team_id = t.id 
    WHERE cu.competition_id = ?
");
$users->execute([$comp_id]);
$users_list = $users->fetchAll();

$teams = $pdo->prepare("SELECT * FROM teams WHERE competition_id = ?");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة المتسابقين - المسابقة</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav>
        <div>
            <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
            <a href="manage_competition.php?id=<?php echo $comp_id; ?>">العودة للإدارة</a>
            <a href="users.php?comp_id=<?php echo $comp_id; ?>">المتسابقين</a>
            <a href="teams.php?comp_id=<?php echo $comp_id; ?>">الفرق</a>
            <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">الحضور</a>
        </div>
        <div><a href="../logout.php">خروج</a></div>
    </nav>
    <div class="container">
        <h2>إدارة المتسابقين في المسابقة</h2>
        <?php if ($message): ?> <div class="message"><?php echo $message; ?></div> <?php endif; ?>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1; background: #eee; padding: 15px; border-radius: 8px;">
                <h3>إضافة يدوي</h3>
                <form method="POST">
                    <div class="form-group"><input type="text" name="full_name" placeholder="الاسم الكامل" required></div>
                    <div class="form-group"><input type="text" name="username" placeholder="اسم المستخدم" required></div>
                    <div class="form-group"><input type="password" name="password" placeholder="كلمة المرور" required></div>
                    <div class="form-group">
                        <select name="team_id">
                            <option value="">بدون فريق (أو اختر فريق)</option>
                            <?php foreach ($teams_list as $team): ?>
                                <option value="<?php echo $team['id']; ?>"><?php echo $team['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <select name="role">
                            <option value="contestant">متسابق</option>
                            <option value="judge">حكم</option>
                            <option value="spectator">متفرج</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-success">إضافة للمسابقة</button>
                </form>
            </div>
            <div style="flex: 1; background: #eee; padding: 15px; border-radius: 8px;">
                <h3>رفع من شيت (CSV)</h3>
                <p><small>التنسيق: الاسم، اسم المستخدم، الباسورد، رقم الفريق</small></p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group"><input type="file" name="csv_file" accept=".csv" required></div>
                    <button type="submit" name="upload_csv" class="btn btn-primary">رفع الملف</button>
                </form>
            </div>
        </div>

        <h3>قائمة المشاركين</h3>
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>اسم المستخدم</th>
                    <th>الفريق</th>
                    <th>الدور</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $user): ?>
                <tr>
                    <td><?php echo $user['full_name']; ?></td>
                    <td><?php echo $user['username']; ?></td>
                    <td><?php echo $user['team_name'] ?: '-'; ?></td>
                    <td><?php echo $user['role']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
