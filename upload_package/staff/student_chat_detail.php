<?php
/**
 * スタッフ用 - 生徒チャット詳細
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

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

// メッセージを既読にする（生徒からの未読メッセージ）
if ($roomId) {
    $stmt = $pdo->prepare("
        UPDATE student_chat_messages
        SET is_read = 1
        WHERE room_id = ? AND sender_type = 'student' AND is_read = 0
    ");
    $stmt->execute([$roomId]);
}

// メッセージを取得（ルームが存在する場合のみ、削除されていないもののみ）
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
        WHERE scm.room_id = ? AND (scm.is_deleted = 0 OR scm.is_deleted IS NULL)
        ORDER BY scm.created_at ASC
    ");
    $stmt->execute([$roomId]);
    $messages = $stmt->fetchAll();
}

// ページ開始
$currentPage = 'student_chat_detail';
$pageTitle = htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') . 'とのチャット';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .chat-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 200px);
            min-height: 500px;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            background: var(--apple-bg-tertiary);
            overflow: hidden;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-lg);
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
            background: var(--primary-purple);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .message.sent .message-avatar {
            background: var(--apple-green);
        }

        .message-content {
            flex: 1;
        }

        .message-sender {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .message-bubble {
            background: var(--apple-gray-4);
            color: white;
            padding: var(--spacing-md) 15px;
            border-radius: var(--radius-lg);
            word-wrap: break-word;
            line-height: 1.5;
        }

        .message.sent .message-bubble {
            background: var(--primary-purple);
            color: white;
        }

        .message-time {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.sent .message-time {
            flex-direction: row-reverse;
        }

        .delete-message-btn {
            background: var(--apple-red);
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .delete-message-btn:hover {
            background: var(--apple-red);
        }

        .message-attachment {
            margin-top: 8px;
            padding: var(--spacing-md);
            background: var(--apple-gray-5);
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        .message-attachment a {
            color: var(--primary-purple);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .message.sent .message-attachment a {
            color: white;
        }

        .input-area {
            padding: var(--spacing-lg);
            background: var(--apple-bg-secondary);
            border-top: 1px solid #4a4a4c;
        }

        .input-form {
            display: flex;
            gap: 10px;
        }

        .input-form textarea {
            flex: 1;
            padding: var(--spacing-md);
            border: 1px solid #4a4a4c;
            border-radius: 25px;
            resize: none;
            font-family: inherit;
            font-size: var(--text-subhead);
            min-height: 50px;
            max-height: 120px;
            background: var(--apple-gray-4);
            color: white;
        }

        .input-form textarea:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .send-btn {
            padding: var(--spacing-md) 24px;
            background: var(--primary-purple);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
            white-space: nowrap;
        }

        .send-btn:hover {
            background: var(--primary-purple);
        }

        .send-btn:disabled {
            background: var(--apple-gray-4);
            cursor: not-allowed;
        }

        .attachment-preview {
            margin-top: 10px;
            padding: var(--spacing-md);
            background: var(--apple-gray-4);
            border-radius: var(--radius-sm);
            display: none;
            color: white;
        }

        .attachment-preview.show {
            display: block;
        }

        .file-input-btn {
            padding: var(--spacing-md);
            background: var(--apple-gray-4);
            border: 1px solid #4a4a4c;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-input-btn:hover {
            background: #4a4a4c;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: var(--spacing-lg);
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

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんとのチャット</h1>
        <p class="page-subtitle">生徒との直接メッセージ</p>
    </div>
    <div class="page-header-actions">
        <a href="student_chats.php" class="btn btn-secondary">← 一覧に戻る</a>
    </div>
</div>

<div class="chat-wrapper">
    <div class="chat-container">
        <div class="messages-area" id="messagesArea">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💬</div>
                    <p>まだメッセージがありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_type'] === 'staff' ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
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
                                <span><?php echo date('m/d H:i', strtotime($msg['created_at'])); ?></span>
                                <?php if ($msg['sender_type'] === 'staff' && $msg['sender_id'] == $currentUser['id']): ?>
                                    <button class="delete-message-btn" onclick="deleteMessage(<?php echo $msg['id']; ?>)">取消</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="input-area">
            <div class="attachment-preview" id="attachmentPreview">
                <span id="fileName"></span>
                <button type="button" onclick="clearAttachment()" style="margin-left: 10px; background: var(--apple-red); color: white; border: none; padding: 5px 10px; border-radius: var(--radius-sm); cursor: pointer;">削除</button>
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
</div><!-- /.chat-wrapper -->

    <script>
    const messagesArea = document.getElementById('messagesArea');
    const messageForm = document.getElementById('messageForm');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const fileInput = document.getElementById('fileInput');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const fileName = document.getElementById('fileName');
    const roomId = <?php echo $roomId ?? 0; ?>;

    // 最後に取得したメッセージID
    let lastMessageId = <?php echo !empty($messages) ? max(array_column($messages, 'id')) : 0; ?>;

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

    // メッセージをDOMに追加
    function addMessageToDOM(msg) {
        // 空のステート表示を削除
        const emptyState = messagesArea.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ' + (msg.sender_type === 'staff' ? 'sent' : 'received');
        messageDiv.dataset.messageId = msg.id;

        const avatarIcon = msg.sender_type === 'staff' ? '👨‍🏫' : '🎓';
        const isOwnMessage = msg.sender_type === 'staff' && msg.sender_id === <?php echo $currentUser['id']; ?>;

        let attachmentHTML = '';
        if (msg.attachment_path) {
            attachmentHTML = `
                <div class="message-attachment">
                    <a href="download_student_chat_attachment.php?id=${msg.id}" target="_blank">
                        📎 ${escapeHtml(msg.attachment_original_name || 'ファイル')}
                        (${(msg.attachment_size / 1024).toFixed(1)}KB)
                    </a>
                </div>
            `;
        }

        const messageDate = new Date(msg.created_at);
        const timeStr = String(messageDate.getMonth() + 1).padStart(2, '0') + '/' +
                       String(messageDate.getDate()).padStart(2, '0') + ' ' +
                       String(messageDate.getHours()).padStart(2, '0') + ':' +
                       String(messageDate.getMinutes()).padStart(2, '0');

        let deleteButtonHTML = '';
        if (isOwnMessage) {
            deleteButtonHTML = `<button class="delete-message-btn" onclick="deleteMessage(${msg.id})">取消</button>`;
        }

        messageDiv.innerHTML = `
            <div class="message-avatar">${avatarIcon}</div>
            <div class="message-content">
                <div class="message-sender">${escapeHtml(msg.sender_name || '不明')}</div>
                <div class="message-bubble">
                    ${escapeHtml(msg.message || '').replace(/\n/g, '<br>')}
                    ${attachmentHTML}
                </div>
                <div class="message-time">
                    <span>${timeStr}</span>
                    ${deleteButtonHTML}
                </div>
            </div>
        `;

        messagesArea.appendChild(messageDiv);
        scrollToBottom();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 新着メッセージを取得
    async function fetchNewMessages() {
        if (!roomId) {
            console.log('No roomId, skipping fetch');
            return;
        }

        try {
            console.log('Fetching new messages, roomId:', roomId, 'lastMessageId:', lastMessageId);
            const response = await fetch(`get_student_chat_messages.php?room_id=${roomId}&last_message_id=${lastMessageId}`);
            const result = await response.json();
            console.log('Fetch result:', result);

            if (result.success && result.messages.length > 0) {
                console.log('Adding', result.messages.length, 'messages to DOM');
                result.messages.forEach(msg => {
                    console.log('Adding message:', msg);
                    addMessageToDOM(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });
            } else {
                console.log('No new messages');
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    }

    messageForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        sendBtn.disabled = true;
        sendBtn.textContent = '送信中...';

        console.log('Sending message...');

        try {
            const response = await fetch('send_student_chat_message.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            console.log('Send result:', result);

            if (result.success) {
                console.log('Message sent successfully, ID:', result.message_id);
                messageInput.value = '';
                clearAttachment();
                // 新着メッセージを取得（送信したメッセージを含む）
                console.log('Fetching new messages after send...');
                await fetchNewMessages();
            } else {
                console.error('Send failed:', result.error);
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

    // メッセージ削除
    async function deleteMessage(messageId) {
        if (!confirm('このメッセージを削除しますか？')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('message_id', messageId);

            const response = await fetch('delete_student_chat_message.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // メッセージをDOMから削除
                const messageDiv = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageDiv) {
                    messageDiv.remove();
                }
            } else {
                alert('削除に失敗しました: ' + (result.error || '不明なエラー'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
        }
    }

    // 3秒ごとに新着メッセージをチェック
    if (roomId) {
        setInterval(fetchNewMessages, 3000);
    }
    </script>

<?php renderPageEnd(); ?>
