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

// جلب الفرق
$teams = $pdo->prepare("SELECT id, name FROM teams WHERE competition_id = ? ORDER BY name");
$teams->execute([$comp_id]);
$teams_list = $teams->fetchAll();

// معالجة إرسال رسالة جديدة
$message_sent = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $team_id = $_POST['team_id'] == 'all' ? null : (int)$_POST['team_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (competition_id, sender_id, team_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$comp_id, $user_id, $team_id, $message]);
        $message_sent = "تم إرسال الرسالة بنجاح";
    }
}

// جلب المحادثات (المجموعات حسب الفريق)
$conversations = $pdo->prepare("
    SELECT 
        t.id as team_id,
        t.name as team_name,
        COUNT(CASE WHEN cm.team_id = t.id AND cm.is_read = 0 THEN 1 END) as unread_count,
        MAX(cm.created_at) as last_message_time,
        (SELECT message FROM chat_messages WHERE competition_id = ? AND (team_id = t.id OR team_id IS NULL) ORDER BY created_at DESC LIMIT 1) as last_message
    FROM teams t
    LEFT JOIN chat_messages cm ON (cm.team_id = t.id OR cm.team_id IS NULL) AND cm.competition_id = ?
    WHERE t.competition_id = ?
    GROUP BY t.id
    ORDER BY last_message_time DESC
");
$conversations->execute([$comp_id, $comp_id, $comp_id]);
$conversations_list = $conversations->fetchAll();

// جلب المحادثة العامة (كل الفرق)
$general_conversation = $pdo->prepare("
    SELECT 
        NULL as team_id,
        'الجميع' as team_name,
        COUNT(CASE WHEN team_id IS NULL AND is_read = 0 THEN 1 END) as unread_count,
        MAX(created_at) as last_message_time,
        (SELECT message FROM chat_messages WHERE competition_id = ? AND team_id IS NULL ORDER BY created_at DESC LIMIT 1) as last_message
    FROM chat_messages
    WHERE competition_id = ? AND team_id IS NULL
");
$general_conversation->execute([$comp_id, $comp_id]);
$general_data = $general_conversation->fetch();

// المحادثة المختارة
$selected_team_id = $_GET['team'] ?? 'all';
$selected_team_name = $selected_team_id == 'all' ? 'الجميع' : '';

// جلب الرسائل
if ($selected_team_id == 'all') {
    $messages = $pdo->prepare("
        SELECT cm.*, u.full_name as sender_name
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.competition_id = ? AND cm.team_id IS NULL
        ORDER BY cm.created_at ASC
    ");
    $messages->execute([$comp_id]);
} else {
    $messages = $pdo->prepare("
        SELECT cm.*, u.full_name as sender_name
        FROM chat_messages cm
        JOIN users u ON cm.sender_id = u.id
        WHERE cm.competition_id = ? AND cm.team_id = ?
        ORDER BY cm.created_at ASC
    ");
    $messages->execute([$comp_id, $selected_team_id]);
    
    // جلب اسم الفريق
    $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt->execute([$selected_team_id]);
    $selected_team_name = $stmt->fetchColumn();
}
$messages_list = $messages->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحادثات - <?php echo htmlspecialchars($competition['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .chat-container {
            display: flex;
            gap: 1rem;
            background: var(--card);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
            min-height: 500px;
        }
        .conversations-sidebar {
            width: 300px;
            border-left: 1px solid var(--border);
            background: var(--bg2);
        }
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .conversation-item:hover {
            background: rgba(127,119,221,0.1);
        }
        .conversation-item.active {
            background: rgba(127,119,221,0.15);
            border-right: 3px solid var(--purple);
        }
        .conversation-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .conversation-info p {
            font-size: 11px;
            color: var(--text3);
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .unread-badge {
            background: var(--coral);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 20px;
            min-width: 20px;
            text-align: center;
        }
        .chat-messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--card);
        }
        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: bold;
        }
        .messages-list {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            max-height: 400px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            position: relative;
        }
        .message-sent {
            background: var(--purple);
            color: white;
            align-self: flex-end;
            border-bottom-left-radius: 4px;
        }
        .message-received {
            background: var(--bg2);
            color: var(--text);
            align-self: flex-start;
            border-bottom-right-radius: 4px;
        }
        .message-info {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.7;
            display: flex;
            gap: 8px;
        }
        .chat-input-area {
            padding: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 0.5rem;
        }
        .chat-input-area textarea {
            flex: 1;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 10px 15px;
            color: var(--text);
            font-family: var(--font-ar);
            resize: none;
            font-size: 14px;
        }
        .chat-input-area textarea:focus {
            outline: none;
            border-color: var(--purple);
        }
        .send-btn {
            background: var(--purple);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .send-btn:hover {
            transform: scale(1.05);
        }
        .empty-chat {
            text-align: center;
            padding: 3rem;
            color: var(--text3);
        }
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }
            .conversations-sidebar {
                width: 100%;
                display: flex;
                overflow-x: auto;
                border-left: none;
                border-bottom: 1px solid var(--border);
            }
            .conversation-item {
                min-width: 150px;
            }
            .message-bubble {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="#" class="logo">
                <div class="logo-icon">💬</div>
                المحادثات
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
            <div class="hero-tag">💬 تواصل مباشر</div>
            <h1>المحادثات</h1>
            <p>إرسال رسائل للفرق أو لجميع المشاركين</p>
        </div>

        <?php if ($message_sent): ?>
            <div class="alert alert-success"><?php echo $message_sent; ?></div>
        <?php endif; ?>

        <div class="chat-container">
            <!-- قائمة المحادثات -->
            <div class="conversations-sidebar">
                <!-- المحادثة العامة -->
                <a href="?comp_id=<?php echo $comp_id; ?>&team=all" class="conversation-item <?php echo $selected_team_id == 'all' ? 'active' : ''; ?>" style="text-decoration: none;">
                    <div class="conversation-info">
                        <h4>📢 الجميع (كل الفرق)</h4>
                        <p><?php echo htmlspecialchars(substr($general_data['last_message'] ?? '', 0, 40)); ?></p>
                    </div>
                    <?php if ($general_data['unread_count'] > 0): ?>
                        <span class="unread-badge"><?php echo $general_data['unread_count']; ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- محادثات الفرق -->
                <?php foreach ($teams_list as $team): ?>
                <a href="?comp_id=<?php echo $comp_id; ?>&team=<?php echo $team['id']; ?>" class="conversation-item <?php echo $selected_team_id == $team['id'] ? 'active' : ''; ?>" style="text-decoration: none;">
                    <div class="conversation-info">
                        <h4>🏷️ <?php echo htmlspecialchars($team['name']); ?></h4>
                        <p><?php 
                            $conv = array_filter($conversations_list, fn($c) => $c['team_id'] == $team['id']);
                            $conv = reset($conv);
                            echo htmlspecialchars(substr($conv['last_message'] ?? '', 0, 40));
                        ?></p>
                    </div>
                    <?php 
                        $unread = array_filter($conversations_list, fn($c) => $c['team_id'] == $team['id']);
                        $unread = reset($unread);
                        if ($unread && $unread['unread_count'] > 0): 
                    ?>
                        <span class="unread-badge"><?php echo $unread['unread_count']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- منطقة عرض الرسائل -->
            <div class="chat-messages-area">
                <div class="chat-header">
                    💬 المحادثة مع: 
                    <strong><?php echo $selected_team_id == 'all' ? 'الجميع' : htmlspecialchars($selected_team_name); ?></strong>
                </div>
                
                <div class="messages-list" id="messagesList">
                    <?php if (empty($messages_list)): ?>
                        <div class="empty-chat">
                            💬 لا توجد رسائل بعد<br>
                            <small>كن أول من يرسل رسالة!</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages_list as $msg): ?>
                            <div class="message-bubble <?php echo $msg['sender_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                <div class="message-info">
                                    <span><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                    <span>•</span>
                                    <span><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- نموذج إرسال رسالة -->
                <form method="POST" class="chat-input-area">
                    <input type="hidden" name="team_id" value="<?php echo $selected_team_id; ?>">
                    <textarea name="message" placeholder="اكتب رسالتك هنا..." rows="2" required></textarea>
                    <button type="submit" name="send_message" class="send-btn">📤</button>
                </form>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">نظام المحادثات</span></p>
    </footer>

    <script>
        // Auto-scroll to bottom of messages
        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
        
        // Auto-refresh messages every 10 seconds
        let lastMessageCount = <?php echo count($messages_list); ?>;
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMessagesList = doc.getElementById('messagesList');
                    if (newMessagesList) {
                        const oldMessagesHtml = messagesList.innerHTML;
                        const newMessagesHtml = newMessagesList.innerHTML;
                        if (oldMessagesHtml !== newMessagesHtml) {
                            messagesList.innerHTML = newMessagesHtml;
                            messagesList.scrollTop = messagesList.scrollHeight;
                        }
                    }
                })
                .catch(err => console.log('Auto-refresh error:', err));
        }, 10000);
        
        // Mobile menu toggle
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>