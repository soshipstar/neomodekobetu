<?php
/**
 * スタッフ用 - 生徒チャット詳細
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    header('Location: student_chats.php');
    exit;
}

// 生徒情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, g.classroom_id
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, student_name
        FROM students
        WHERE id = ?
    ");
    $stmt->execute([$studentId]);
}

$student = $stmt->fetch();

if (!$student) {
    header('Location: student_chats.php');
    exit;
}

// チャットルームを取得（存在しない場合はnull）
$stmt = $pdo->prepare("
    SELECT id
    FROM student_chat_rooms
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$room = $stmt->fetch();
$roomId = $room ? $room['id'] : null;

// メッセージを取得（ルームが存在する場合のみ）
$messages = [];
if ($roomId) {
    $stmt = $pdo->prepare("
        SELECT
            scm.id,
            scm.sender_type,
            scm.sender_id,
            scm.message,
            scm.attachment_path,
            scm.attachment_original_name,
            scm.attachment_size,
            scm.created_at,
            CASE
                WHEN scm.sender_type = 'student' THEN s.student_name
                WHEN scm.sender_type = 'staff' THEN u.full_name
            END as sender_name
        FROM student_chat_messages scm
        LEFT JOIN students s ON scm.sender_type = 'student' AND scm.sender_id = s.id
        LEFT JOIN users u ON scm.sender_type = 'staff' AND scm.sender_id = u.id
        WHERE scm.room_id = ?
        ORDER BY scm.created_at ASC
    ");
    $stmt->execute([$roomId]);
    $messages = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>とのチャット - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 20px;
            color: #333;
        }

        .back-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            background: white;
            overflow: hidden;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            display: flex;
            gap: 10px;
            max-width: 70%;
        }

        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .message.sent .message-avatar {
            background: #28a745;
        }

        .message-content {
            flex: 1;
        }

        .message-sender {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .message-bubble {
            background: #f0f0f0;
            padding: 12px 15px;
            border-radius: 15px;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .message.sent .message-bubble {
            background: #667eea;
            color: white;
        }

        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }

        .message-attachment {
            margin-top: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            display: inline-block;
        }

        .message-attachment a {
            color: #667eea;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .message.sent .message-attachment a {
            color: white;
        }

        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #ddd;
        }

        .input-form {
            display: flex;
            gap: 10px;
        }

        .input-form textarea {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 25px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            min-height: 50px;
            max-height: 120px;
        }

        .input-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .send-btn:hover {
            background: #5568d3;
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .attachment-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }

        .attachment-preview.show {
            display: block;
        }

        .file-input-btn {
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-input-btn:hover {
            background: #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .message {
                max-width: 85%;
            }

            .input-form {
                flex-direction: column;
            }

            .send-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💬 <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんとのチャット</h1>
        <a href="student_chats.php" class="back-btn">← 一覧に戻る</a>
    </div>

    <div class="chat-container">
        <div class="messages-area" id="messagesArea">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💬</div>
                    <p>まだメッセージがありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_type'] === 'staff' ? 'sent' : 'received'; ?>">
                        <div class="message-avatar">
                            <?php echo $msg['sender_type'] === 'staff' ? '👨‍🏫' : '🎓'; ?>
                        </div>
                        <div class="message-content">
                            <div class="message-sender">
                                <?php echo htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="message-bubble">
                                <?php echo nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')); ?>

                                <?php if ($msg['attachment_path']): ?>
                                    <div class="message-attachment">
                                        <a href="download_student_chat_attachment.php?id=<?php echo $msg['id']; ?>" target="_blank">
                                            📎 <?php echo htmlspecialchars($msg['attachment_original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            (<?php echo number_format($msg['attachment_size'] / 1024, 1); ?>KB)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('m/d H:i', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="input-area">
            <div class="attachment-preview" id="attachmentPreview">
                <span id="fileName"></span>
                <button type="button" onclick="clearAttachment()" style="margin-left: 10px; background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">削除</button>
            </div>

            <form id="messageForm" class="input-form" enctype="multipart/form-data">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">

                <label for="fileInput" class="file-input-btn" title="ファイルを添付">
                    📎
                </label>
                <input type="file" id="fileInput" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display: none;">

                <textarea
                    name="message"
                    id="messageInput"
                    placeholder="メッセージを入力..."
                    required
                ></textarea>

                <button type="submit" class="send-btn" id="sendBtn">送信</button>
            </form>
        </div>
    </div>

    <script>
    const messagesArea = document.getElementById('messagesArea');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const fileInput = document.getElementById('fileInput');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const fileName = document.getElementById('fileName');

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    scrollToBottom();

    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            const maxSize = 3 * 1024 * 1024;

            if (file.size > maxSize) {
                alert('ファイルサイズは3MB以下にしてください');
                this.value = '';
                return;
            }

            fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + 'KB)';
            attachmentPreview.classList.add('show');
        }
    });

    function clearAttachment() {
        fileInput.value = '';
        attachmentPreview.classList.remove('show');
        fileName.textContent = '';
    }

    messageForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        sendBtn.disabled = true;
        sendBtn.textContent = '送信中...';

        try {
            const response = await fetch('send_student_chat_message.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                messageInput.value = '';
                clearAttachment();
                location.reload();
            } else {
                alert('送信に失敗しました: ' + (result.error || '不明なエラー'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
        } finally {
            sendBtn.disabled = false;
            sendBtn.textContent = '送信';
        }
    });

    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            messageForm.dispatchEvent(new Event('submit'));
        }
    });

    setInterval(function() {
        location.reload();
    }, 5000);
    </script>
</body>
</html>
