<?php
session_start();
require_once '../config/db.php';

// التأكد من أن المستخدم أدمن
if (!isset($_SESSION['user_id']) || !$_SESSION['is_super_admin']) {
    die('❌ غير مصرح به - فقط الأدمن يمكنه استخدام هذه الصفحة');
}

// ============================================
// بيانات المتسابقين (مع الباسوردات الأصلية)
// ============================================
$contestants = [
    // ['الاسم الكامل', 'اسم المستخدم', 'كلمة المرور', 'رقم الفريق', 'الدور']
    // الأدوار: contestant, team_leader, judge, spectator
    // رقم الفريق: 1=النسور, 2=الأبطال, 3=النجوم, 4=اللهب, 0=بدون فريق
    
    // ===== متفرجين (spectator) =====
    ['David Mikhail', 'david-mikhail-ananoub', '8xK2', 0, 'spectator'],
    ['Philopater Ayman', 'philopater-ayman-ananoub', 'R4mQ', 0, 'spectator'],
    ['Kevin Milad', 'kevin-milad-azmy', 'Ht3B', 0, 'spectator'],
    ['Philopater Ananoub', 'philopater-ananoub-gamil', '5YuE', 0, 'spectator'],
    ['Karas Ayman', 'karas-ayman-nassef', 'Jl2M', 0, 'spectator'],
    ['Fam Amir', 'fam-amir-sergius', 'Nw8F', 0, 'spectator'],
    ['Steven Atef', 'steven-atef-refaat', 'Kd3X', 0, 'spectator'],
    ['Justin Milad', 'justin-milad-ayad', 'Zg9Q', 0, 'spectator'],
    ['Karas Mouheb', 'karas-mouheb-mikhail', 'Lp5V', 0, 'spectator'],
    ['John Ehab', 'john-ehab-ezzat', 'Ms8Y', 0, 'spectator'],
    ['Boutros Romanos', 'boutros-romanos-anes', 'Bc4H', 0, 'spectator'],
    ['Samir Fahem', 'samir-fahem-samir', 'Fu7N', 0, 'spectator'],
    ['Abram Melad', 'abram-melad-mounir', 'Tx1G', 0, 'spectator'],
    ['Mina George', 'mina-george-adel', 'Ws4K', 0, 'spectator'],
    ['Tharwat Hany', 'tharwat-hany-tharwat', 'Yx3C', 0, 'spectator'],
    ['Rifaat Romanos', 'rifaat-romanos-refaat', 'Av8J', 0, 'spectator'],
    ['Kevin Michael', 'kevin-michael-shehata', 'Di5Z', 0, 'spectator'],
    ['Philopater Sherif', 'philopater-sherif-aryan', 'Br6Y', 0, 'spectator'],
    ['Mina Ashraf', 'mina-ashraf-habib', 'Ke4X', 0, 'spectator'],
    ['Kyrillos Michel', 'kyrillos-michel-saeed', 'Qt1G', 0, 'spectator'],
    ['Armia Gerges', 'armia-gerges-armia', 'Wn8L', 0, 'spectator'],
    ['Kyrillos Samuel', 'kyrillos-samuel-thabet', 'Rc9Y', 0, 'spectator'],
    ['Malak Mikhail', 'malak-mikhail-ata', 'Gu1S', 0, 'spectator'],
    ['Gordian Antonious', 'gordian-antonious-gabriel', 'Yo8D', 0, 'spectator'],
    ['Ramy Romanos', 'ramy-romanos-rasmy', 'Mx3F', 0, 'spectator'],
    ['Kyrillos Bishoy', 'kyrillos-bishoy-adel', 'Qf4H', 0, 'spectator'],
    ['Bola Binyamin', 'bola-binyamin-neroz', 'Nr7U', 0, 'spectator'],
    ['Mina Amged', 'mina-amged-bahgat', 'Xk5R', 0, 'spectator'],
    ['Ananoub Adel', 'ananoub-adel-alfons', 'Fb6Z', 0, 'spectator'],
    ['Mina Remon', 'mina-remon-gamal', 'Qu2T', 0, 'spectator'],
    ['Kyrillos Ishaq', 'kyrillos-ishaq-kamel', 'Hy7W', 0, 'spectator'],
    ['Antonious Diaa', 'antonious-diaa-zakher', 'Js4C', 0, 'spectator'],
    ['Kyrillos Atef', 'kyrillos-atef-zareef', 'We1V', 0, 'spectator'],
    ['Bemen Farid', 'bemen-farid-shawky', 'Am3X', 0, 'spectator'],
    ['Mina Magdy R', 'mina-magdy-ramzy', 'Re1H', 0, 'spectator'],
    ['Kyrillos Ibrahim', 'kyrillos-ibrahim-raghab', 'Jk7X', 0, 'spectator'],
    ['Bola Sami', 'bola-sami-zareef', 'Yp1C', 0, 'spectator'],
    ['Mina Michael', 'mina-michael-mankarious', 'Pa7R', 0, 'spectator'],
    ['Philopater Sami', 'philopater-sami-foad', 'Kx7V', 0, 'spectator'],
    ['Kyrillos Raafat', 'kyrillos-raafat-thabet', 'Wy8D', 0, 'spectator'],
    ['Rogier Robert', 'rogier-robert-zakaria', 'Mc3S', 0, 'spectator'],
    ['Thomas Nabil', 'thomas-nabil-labeeb', 'Tf2X', 0, 'spectator'],
    ['Karas Hany', 'karas-hany-abdo', 'Ym5K', 0, 'spectator'],
    ['Romanos Waeed', 'romanos-waeed-romany', 'Fd7B', 0, 'spectator'],
    ['Gerges Saed', 'gerges-saed-makram', 'Ht5R', 0, 'spectator'],
    ['Kyrillos Remon A', 'kyrillos-remon-adel', 'Wl3Y', 0, 'spectator'],
    ['Marco Zareef', 'marco-zareef-nassef', 'Po7J', 0, 'spectator'],
    ['Kyrillos Bishoy M', 'kyrillos-bishoy-mounir', 'Zu4C', 0, 'spectator'],
    
    // ===== فريق اللهب (رقم 4) =====
    ['Mina Atef', 'mina-atef', 'WpL9', 4, 'contestant'],
    ['John Ayman', 'john-ayman', 'Cv7R', 4, 'contestant'],
    ['Bavly Hany', 'bavly-hany', 'Sp9F', 4, 'contestant'],
    ['Philopater Kimi', 'philopater-kimi-sobhy', 'Ja3R', 4, 'contestant'],
    ['Ghofani Rafik', 'ghofani-rafik-magdy', 'Wa2Y', 4, 'contestant'],
    ['Shady Khalil', 'shady-khalil-saleeb', 'Ti8Z', 4, 'contestant'],
    ['Antonious George', 'antonious-george-fahem', 'Dv7U', 4, 'contestant'],
    ['Kyrillos Joseph G', 'kyrillos-joseph-sobhy', 'Wf8U', 4, 'contestant'],
    ['John Emad', 'john-emad', 'Qs9K', 4, 'contestant'],
    ['Shenouda Mina', 'shenouda-mina-shendy', 'Tv2W', 4, 'contestant'],
    ['Youssef Ayman', 'youssef-ayman-shehata', 'Bn1J', 4, 'contestant'],
    ['Steven Wael', 'steven-wael', 'Rc6U', 4, 'contestant'],
    ['Kyrillos Hosny', 'kyrillos-hosny-mounir', 'Hv9G', 4, 'contestant'],
    ['George Abdelmasih', 'george-abdelmasih-abdo', 'Jz4V', 4, 'contestant'],
    
    // ===== فريق النجوم (رقم 3) =====
    ['Mina Magdy', 'mina-magdy-adly', 'He2T', 3, 'contestant'],
    ['Noufer Hany', 'noufer-hany-rashdy', 'Hm2U', 3, 'contestant'],
    ['Kyrillos Naeem', 'kyrillos-naeem-fayez', 'Cd6V', 3, 'contestant'],
    ['Andrew Maged', 'andrew-maged-nabih', 'Jd6K', 3, 'contestant'],
    ['Kyrillos Youssef', 'kyrillos-youssef-samir', 'Lp8M', 3, 'contestant'],
    ['Robert Romel', 'robert-romel-tawadros', 'Vg3D', 3, 'contestant'],
    ['Mina Ashraf T', 'mina-ashraf-tawadros', 'Cd9G', 3, 'contestant'],
    ['Youssef Nader', 'youssef-nader-youssef', 'Fq2J', 3, 'contestant'],
    ['Mina Edward', 'mina-edward-khalaf', 'Bo9Y', 3, 'contestant'],
    ['Philopater Naguib', 'philopater-naguib-magdy', 'Xa5L', 3, 'contestant'],
    ['Mario Melad', 'mario-melad-zaki', 'Zn6H', 3, 'contestant'],
    ['David Sameeh', 'david-sameeh-morad', 'Xc3F', 3, 'contestant'],
    ['Mina Magdy S', 'mina-magdy-khalaf', 'Le9T', 3, 'contestant'],
    ['Mina Maged T', 'mina-maged-romany', 'Qa8F', 3, 'contestant'],
    ['Philopater George F', 'philopater-george-fared', 'Vx2U', 3, 'contestant'],
    
    // ===== فريق الأبطال (رقم 2) =====
    ['Fadi Mattar', 'fadi-mattar-nassef', 'Ra2Q', 2, 'contestant'],
    ['Bavly John', 'bavly-john-kamal', 'Pd9M', 2, 'contestant'],
    ['Kyrillos Maged', 'kyrillos-maged-melad', 'Bv4U', 2, 'contestant'],
    ['Kyrillos Remon', 'kyrillos-remon-maher', 'Ze7P', 2, 'contestant'],
    ['Gerges Sameh', 'gerges-sameh-fouad', 'Vc2M', 2, 'contestant'],
    ['Basilios Botros', 'basilios-botros-samir', 'Hg2N', 2, 'contestant'],
    ['Karl Ehab', 'karl-ehab-khalil', 'Bu9M', 2, 'contestant'],
    ['John Mouheb', 'john-mouheb-ayad', 'Sk2W', 2, 'contestant'],
    ['Andrew Hany', 'andrew-hany-wahba', 'Ge4T', 2, 'contestant'],
    ['Kyrillos botros M', 'kyrillos-botros-mounir', 'Zu4C', 2, 'contestant'],
    
    // ===== فريق النسور (رقم 1) =====
    ['Michael Daniel', 'michael-daniel-latef', 'Jn6W', 1, 'contestant'],
    ['Philopater George', 'philopater-george-obeid', 'Nw2H', 1, 'contestant'],
    ['Michael Remon', 'michael-remon-alkass', 'As9W', 1, 'contestant'],
    ['Mina Gad', 'mina-gad-badry', 'Tc1B', 1, 'contestant'],
    ['Kyrillos Joseph', 'kyrillos-joseph-gamal', 'Ru6S', 1, 'contestant'],
    ['Bishoy Ayman', 'bishoy-ayman-gad', 'Mn4Q', 1, 'contestant'],
    ['Philopater Atef', 'philopater-atef-rezk', 'Rt3G', 1, 'contestant'],
    ['Kyrillos Ghaly', 'kyrillos-ghaly-nashed', 'Md4B', 1, 'contestant'],
    ['Philopater Emad', 'philopater-emad-daniel', 'Qr6Y', 1, 'contestant'],
    ['Philopater Ayad', 'philopater-ayad-abdalla', 'Mc5N', 1, 'contestant'],
];

// ============================================
// جلب معرف المسابقة الحالية
// ============================================
$comp_id = $_GET['comp_id'] ?? $_SESSION['comp_id'] ?? null;
if (!$comp_id) {
    $stmt = $pdo->query("SELECT id FROM competitions ORDER BY id DESC LIMIT 1");
    $comp_id = $stmt->fetchColumn();
    if (!$comp_id) {
        die('❌ لا توجد مسابقة. الرجاء إنشاء مسابقة أولاً.');
    }
}

// جلب اسم المسابقة
$stmt = $pdo->prepare("SELECT name FROM competitions WHERE id = ?");
$stmt->execute([$comp_id]);
$comp_name = $stmt->fetchColumn();

echo "<h1>📥 إضافة المتسابقين للمسابقة</h1>";
echo "<p><strong>المسابقة:</strong> " . htmlspecialchars($comp_name) . " (ID: $comp_id)</p>";
echo "<hr>";

$added = 0;
$skipped = 0;
$errors = [];

// جلب الفرق
$teams = $pdo->prepare("SELECT id, name FROM teams WHERE competition_id = ?");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();

$team_ids = [];
foreach ($teams_list as $t) {
    if (strpos($t['name'], 'نسور') !== false) $team_ids[1] = $t['id'];
    elseif (strpos($t['name'], 'أبطال') !== false) $team_ids[2] = $t['id'];
    elseif (strpos($t['name'], 'نجوم') !== false) $team_ids[3] = $t['id'];
    elseif (strpos($t['name'], 'لهب') !== false) $team_ids[4] = $t['id'];
}

echo "<div style='background:var(--bg2); padding:10px; border-radius:10px; margin-bottom:15px;'>";
echo "<strong>🔍 الفرق الموجودة:</strong><br>";
foreach ($team_ids as $num => $id) {
    $names = ['', 'النسور', 'الأبطال', 'النجوم', 'اللهب'];
    echo "  رقم $num → " . $names[$num] . " (ID: $id)<br>";
}
echo "</div>";

// ============================================
// إضافة المتسابقين
// ============================================
foreach ($contestants as $data) {
    $full_name = $data[0];
    $username = $data[1];
    $password = $data[2];
    $team_number = $data[3];
    $role = $data[4];
    
    $team_id = $team_ids[$team_number] ?? null;
    $team_name = $team_number ? ['', 'النسور', 'الأبطال', 'النجوم', 'اللهب'][$team_number] : 'بدون فريق';
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $u_id = $existing['id'];
            $skipped++;
            echo "⏭️ <strong>$full_name</strong> ($username) موجود بالفعل (تخطي)<br>";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed, $full_name]);
            $u_id = $pdo->lastInsertId();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO competition_users (competition_id, user_id, team_id, role, mass_score, choir_score, prayer_score, interaction_score) 
            VALUES (?, ?, ?, ?, 0, 0, 0, 0)
            ON DUPLICATE KEY UPDATE team_id = VALUES(team_id), role = VALUES(role)
        ");
        $stmt->execute([$comp_id, $u_id, $team_id, $role]);
        $added++;
        
        $role_icon = ['contestant' => '🏃', 'team_leader' => '👑', 'spectator' => '👁️', 'judge' => '⚖️'];
        echo "✅ <strong>$full_name</strong> ($username) → $team_name " . ($role_icon[$role] ?? '') . " ($role)<br>";
        
    } catch (Exception $e) {
        $errors[] = "❌ خطأ في $username: " . $e->getMessage();
        echo "❌ <strong>$full_name</strong> ($username) فشل: " . $e->getMessage() . "<br>";
    }
}

// ============================================
// عرض النتيجة النهائية
// ============================================
echo "<hr>";
echo "<h2>📊 النتيجة النهائية</h2>";
echo "<ul>";
echo "<li style='color:green;'>✅ تم إضافة: <strong>$added</strong> مستخدم</li>";
echo "<li style='color:orange;'>⏭️ تم تخطي: <strong>$skipped</strong> مستخدم (موجود بالفعل)</li>";
if (!empty($errors)) {
    echo "<li style='color:red;'>❌ أخطاء: " . count($errors) . "</li>";
    foreach ($errors as $err) {
        echo "<li style='color:red;'>$err</li>";
    }
}
echo "</ul>";

echo "<br><a href='competition_home.php?id=$comp_id' class='btn btn-primary' style='display:inline-block; padding:10px 20px; background:var(--purple); color:white; border-radius:10px; text-decoration:none;'>← العودة للمسابقة</a>";
?>