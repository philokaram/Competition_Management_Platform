<?php
session_start();
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$comp_id = $_SESSION['comp_id'] ?? null;

if (!$user_id || !$comp_id) {
    header('Location: ../select_competition.php');
    exit;
}

// جلب فريق المستخدم (إذا كان موجوداً)
$stmt = $pdo->prepare("
    SELECT team_id, role FROM competition_users 
    WHERE user_id = ? AND competition_id = ?
");
$stmt->execute([$user_id, $comp_id]);
$user_data = $stmt->fetch();
$user_team_id = $user_data['team_id'] ?? null;
$user_role = $user_data['role'] ?? 'contestant';

// جلب الرسائل الخاصة بفريق المستخدم + العامة
$messages = $pdo->prepare("
    SELECT cm.*, u.full_name as sender_name
    FROM chat_messages cm
    JOIN users u ON cm.sender_id = u.id
    WHERE cm.competition_id = ? AND (cm.team_id IS NULL OR cm.team_id = ?)
    ORDER BY cm.created_at ASC
");
$messages->execute([$comp_id, $user_team_id]);
$messages_list = $messages->fetchAll();

// تحديث حالة القراءة للرسائل الجديدة
$stmt = $pdo->prepare("
    UPDATE chat_messages SET is_read = 1 
    WHERE competition_id = ? AND (team_id IS NULL OR team_id = ?) 
    AND is_read = 0 AND id NOT IN (
        SELECT message_id FROM chat_read_receipts WHERE user_id = ?
    )
");
$stmt->execute([$comp_id, $user_team_id, $user_id]);

// تسجيل قراءة الرسائل
foreach ($messages_list as $msg) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO chat_read_receipts (message_id, user_id) VALUES (?, ?)
    ");
    $stmt->execute([$msg['id'], $user_id]);
}

// معالجة إرسال رسالة جديدة (للقادة فقط يمكنهم الرد؟ حسب الصلاحيات)
$can_reply = ($user_role == 'team_leader'); // قائد الفريق فقط يمكنه الرد
$message_sent = '';

if ($can_reply && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (competition_id, sender_id, team_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$comp_id, $user_id, $user_team_id, $message]);
        $message_sent = "تم إرسال الرسالة بنجاح";
        header("Location: chat.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحادثات - منصة المسابقات</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theme.php?comp_id=<?php echo $comp_id; ?>">
    <style>
        .chat-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 70vh;
        }
        .chat-header {
            padding: 1rem;
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
            text-align: center;
        }
        .messages-list {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .message-bubble {
            max-width: 75%;
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
            background: var(--bg2);
        }
        .chat-input-area textarea {
            flex: 1;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 10px 15px;
            color: var(--text);
            font-family: var(--font-ar);
            resize: none;
            font-size: 14px;
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
        }
        .empty-chat {
            text-align: center;
            padding: 3rem;
            color: var(--text3);
        }
        .alert-success {
            background: rgba(29,158,117,0.15);
            border: 1px solid rgba(29,158,117,0.3);
            color: var(--teal-l);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .message-bubble {
                max-width: 90%;
            }
            .chat-container {
                height: 80vh;
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
                <a href="../select_competition.php">تغيير المسابقة</a>
                <a href="profile.php">بروفايلي</a>
                <a href="ranking.php">ترتيب المتسابقين</a>
                <a href="team_ranking.php">ترتيب الفرق</a>
                <a href="score_report.php">📊 تقرير درجاتي</a>
                <a href="chat.php">💬 المحادثات</a>
                <a href="../logout.php">خروج</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <div class="hero" style="padding: 2rem 2rem 1rem;">
            <div class="hero-tag">💬 تواصل مباشر</div>
            <h1>المحادثات</h1>
            <p>رسائل من الحكم وإدارة المسابقة</p>
        </div>

        <?php if ($message_sent): ?>
            <div class="alert-success"><?php echo $message_sent; ?></div>
        <?php endif; ?>

        <div class="chat-container">
            <div class="chat-header">
                💬 محادثة <?php echo $user_team_id ? 'فريقي' : 'العامة'; ?>
                <small style="display: block; font-size: 11px; color: var(--text3);">
                    <?php if ($user_team_id): ?>
                        (الرسائل العامة والخاصة بفريقك فقط)
                    <?php else: ?>
                        (أنت متفرج - تشاهد الرسائل العامة فقط)
                    <?php endif; ?>
                </small>
            </div>
            
            <div class="messages-list" id="messagesList">
                <?php if (empty($messages_list)): ?>
                    <div class="empty-chat">
                        💬 لا توجد رسائل بعد<br>
                        <small>سيتم عرض رسائل الحكم هنا</small>
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
            
            <?php if ($can_reply): ?>
            <form method="POST" class="chat-input-area">
                <textarea name="message" placeholder="اكتب ردك هنا..." rows="2" required></textarea>
                <button type="submit" name="send_message" class="send-btn">📤</button>
            </form>
            <?php else: ?>
            <div class="chat-input-area" style="justify-content: center;">
                <p style="color: var(--text3); font-size: 12px;">🔒 يمكنك قراءة الرسائل فقط. للرد، تواصل مع قائد فريقك</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <p>منصة إدارة المسابقات · <span style="color:var(--purple-l)">المحادثات</span></p>
    </footer>

    <script>
        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
        
        let lastMessageCount = <?php echo count($messages_list); ?>;
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMessagesList = doc.getElementById('messagesList');
                    if (newMessagesList) {
                        if (messagesList.innerHTML !== newMessagesList.innerHTML) {
                            messagesList.innerHTML = newMessagesList.innerHTML;
                            messagesList.scrollTop = messagesList.scrollHeight;
                        }
                    }
                })
                .catch(err => console.log('Auto-refresh error:', err));
        }, 10000);
        
        document.querySelector('.mobile-menu-btn')?.addEventListener('click', function() {
            document.querySelector('nav').classList.toggle('show');
        });
    </script>
</body>
</html>