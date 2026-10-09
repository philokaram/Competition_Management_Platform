<?php
session_start();
require_once '../config/db.php';

$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) { header('Location: dashboard.php'); exit; }

if (isset($_POST['update_team_score'])) {
    $team_id = $_POST['team_id'];
    $points = (int)$_POST['points'];
    $stmt = $pdo->prepare("UPDATE teams SET bonus_score = bonus_score + ? WHERE id = ? AND competition_id = ?");
    $stmt->execute([$points, $team_id, $comp_id]);
}

$teams = $pdo->prepare("
    SELECT t.id, t.name, t.bonus_score,
    (t.bonus_score + IFNULL((SELECT SUM(mass_score + choir_score + interaction_score) FROM competition_users WHERE team_id = t.id), 0)) as total_score 
    FROM teams t
    WHERE t.competition_id = ?
");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الفرق والدرجات</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav>
        <button class="mobile-menu-btn" onclick="document.querySelector('nav').classList.toggle('show')">☰</button>
        <div>
            <a href="manage_competition.php?id=<?php echo $comp_id; ?>">العودة للإدارة</a>
            <a href="users.php?comp_id=<?php echo $comp_id; ?>">المتسابقين</a>
            <a href="teams.php?comp_id=<?php echo $comp_id; ?>">الفرق</a>
            <a href="attendance.php?comp_id=<?php echo $comp_id; ?>">الحضور</a>
        </div>
        <div><a href="../logout.php">خروج</a></div>
    </nav>
    <div class="container">
        <h2>درجات الفرق في المسابقة</h2>
        <table>
            <thead>
                <tr>
                    <th>اسم الفريق</th>
                    <th>بونص الفريق</th>
                    <th>إجمالي الدرجات (فريق + أفراد)</th>
                    <th>تعديل بونص الفريق</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams_list as $team): ?>
                <tr>
                    <td><?php echo $team['name']; ?></td>
                    <td><?php echo $team['bonus_score']; ?></td>
                    <td><strong><?php echo $team['total_score']; ?></strong></td>
                    <td>
                        <form method="POST" style="display: flex; gap: 5px; justify-content: center;">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <input type="number" name="points" placeholder="+/-" required style="width: 80px;">
                            <button type="submit" name="update_team_score" class="btn btn-primary">تحديث</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
