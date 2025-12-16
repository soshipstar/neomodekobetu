<?php
/**
 * 生徒用チャット画面
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 生徒用チャットルームを取得または作成
$stmt = $pdo->prepare("SELECT id FROM student_chat_rooms WHERE student_id = ?");
$stmt->execute([$studentId]);
$room = $stmt->fetch();

if (!$room) {
    $stmt = $pdo->prepare("INSERT INTO student_chat_rooms (student_id) VALUES (?)");
    $stmt->execute([$studentId]);
    $roomId = $pdo->lastInsertId();
} else {
    $roomId = $room['id'];
}

// メッセージを既読にする
$stmt = $pdo->prepare("
    UPDATE student_chat_messages
    SET is_read = 1
    WHERE room_id = ? AND sender_type = 'staff' AND (is_read = 0 OR is_read IS NULL)
");
$stmt->execute([$roomId]);

// チャットメッセージを取得（削除されていないもののみ）
$stmt = $pdo->prepare("
    SELECT
        scm.id,
        scm.sender_type,
        scm.sender_id,
        scm.message_type,
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

// ページ開始
$_SESSION['user_type'] = 'student';
$_SESSION['full_name'] = $student['student_name'];
$currentPage = 'chat';
renderPageStart('student', $currentPage, 'チャット', ['additionalCss' => ['/assets/css/chat.css']]);
?>

<style>
/* チャット用の特別なレイアウト調整 */
.chat-page-wrapper {
    height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
}

@media (max-width: 768px) {
    .chat-page-wrapper {
        height: calc(100vh - 60px);
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header" style="flex-shrink: 0;">
    <div class="page-header-content">
        <h1 class="page-title">スタッフとのチャット</h1>
        <p class="page-subtitle">質問や相談があればメッセージを送ってください</p>
    </div>
</div>

<!-- チャットコンテナ -->
<div class="chat-wrapper" style="flex: 1; margin-top: var(--spacing-md);">
    <div class="messages-area" id="messagesArea">
        <?php if (empty($messages)): ?>
            <div class="chat-empty-state">
                <div class="chat-empty-state-icon">💬</div>
                <h3>まだメッセージがありません</h3>
                <p>スタッフにメッセージを送ってみましょう</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['sender_type'] === 'student' ? 'sent' : 'received' ?>">
                    <div class="message-avatar">
                        <?= $msg['sender_type'] === 'student' ? '👤' : '👨‍🏫' ?>
                    </div>
                    <div class="message-content">
                        <div class="message-sender">
                            <?= htmlspecialchars($msg['sender_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="message-bubble">
                            <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>

                            <?php if ($msg['attachment_path']): ?>
                                <div class="message-attachment">
                                    <a href="download_attachment.php?id=<?= $msg['id'] ?>" target="_blank">
                                        📎 <?= htmlspecialchars($msg['attachment_original_name'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= number_format($msg['attachment_size'] / 1024, 1) ?>KB)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="message-time">
                            <?= date('m/d H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chat-input-area">
        <div class="file-preview" id="filePreview">
            <div class="file-preview-info">
                📎 <span id="fileName"></span> (<span id="fileSize"></span>)
            </div>
            <button type="button" class="file-preview-remove" onclick="clearAttachment()">削除</button>
        </div>

        <form id="messageForm" class="chat-input-form" enctype="multipart/form-data">
            <input type="hidden" name="room_id" value="<?= $roomId ?>">

            <label for="fileInput" class="file-attach-btn" title="ファイルを添付">
                📎
            </label>
            <input type="file" id="fileInput" name="attachment" class="file-attach-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">

            <textarea
                name="message"
                id="messageInput"
                class="chat-textarea"
                placeholder="メッセージを入力..."
            ></textarea>

            <button type="submit" class="chat-send-btn" id="sendBtn">➤</button>
        </form>
    </div>
</div>

<?php
$lastMessageId = $messages ? max(array_column($messages, 'id')) : 0;
$inlineJs = <<<JS
const messagesArea = document.getElementById('messagesArea');
const messageForm = document.getElementById('messageForm');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');

let lastMessageId = {$lastMessageId};
const MAX_FILE_SIZE = 3 * 1024 * 1024;

function scrollToBottom() {
    messagesArea.scrollTop = messagesArea.scrollHeight;
}
scrollToBottom();

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        const file = this.files[0];
        if (file.size > MAX_FILE_SIZE) {
            alert('ファイルサイズは3MB以下にしてください');
            this.value = '';
            return;
        }
        fileName.textContent = file.name;
        fileSize.textContent = (file.size / 1024).toFixed(1) + 'KB';
        filePreview.classList.add('show');
    }
});

function clearAttachment() {
    fileInput.value = '';
    filePreview.classList.remove('show');
    fileName.textContent = '';
}

function addMessageToDOM(msg) {
    const emptyState = messagesArea.querySelector('.chat-empty-state');
    if (emptyState) emptyState.remove();

    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + (msg.sender_type === 'student' ? 'sent' : 'received');

    const avatarIcon = msg.sender_type === 'student' ? '👤' : '👨‍🏫';

    let attachmentHTML = '';
    if (msg.attachment_path) {
        attachmentHTML = '<div class="message-attachment"><a href="download_attachment.php?id=' + msg.id + '" target="_blank">📎 ' + escapeHtml(msg.attachment_original_name || 'ファイル') + ' (' + (msg.attachment_size / 1024).toFixed(1) + 'KB)</a></div>';
    }

    const messageDate = new Date(msg.created_at);
    const timeStr = String(messageDate.getMonth() + 1).padStart(2, '0') + '/' +
                   String(messageDate.getDate()).padStart(2, '0') + ' ' +
                   String(messageDate.getHours()).padStart(2, '0') + ':' +
                   String(messageDate.getMinutes()).padStart(2, '0');

    messageDiv.innerHTML = '<div class="message-avatar">' + avatarIcon + '</div>' +
        '<div class="message-content">' +
        '<div class="message-sender">' + escapeHtml(msg.sender_name || '不明') + '</div>' +
        '<div class="message-bubble">' + escapeHtml(msg.message || '').replace(/\\n/g, '<br>') + attachmentHTML + '</div>' +
        '<div class="message-time">' + timeStr + '</div></div>';

    messagesArea.appendChild(messageDiv);
    scrollToBottom();
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function fetchNewMessages() {
    try {
        const response = await fetch('get_messages.php?last_message_id=' + lastMessageId);
        const result = await response.json();

        if (result.success && result.messages.length > 0) {
            result.messages.forEach(msg => {
                addMessageToDOM(msg);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });
        }
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
}

messageForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const message = messageInput.value.trim();
    if (!message && fileInput.files.length === 0) {
        alert('メッセージを入力してください');
        return;
    }

    const formData = new FormData(this);
    sendBtn.disabled = true;

    try {
        const response = await fetch('send_message.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            messageInput.value = '';
            clearAttachment();
            await fetchNewMessages();
        } else {
            alert('送信に失敗しました: ' + (result.error || '不明なエラー'));
        }
    } catch (error) {
        alert('通信エラーが発生しました');
    } finally {
        sendBtn.disabled = false;
    }
});

messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        messageForm.dispatchEvent(new Event('submit'));
    }
});

setInterval(fetchNewMessages, 3000);
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
