<?php
/**
 * 保護者用 チャットページ
 * ミニマム版
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /minimum/index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// 保護者に紐づく生徒を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? AND is_active = 1 ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// チャットルームを取得または作成
$roomId = null;
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE student_id = ? AND guardian_id = ?");
    $stmt->execute([$selectedStudentId, $guardianId]);
    $room = $stmt->fetch();

    if ($room) {
        $roomId = $room['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO chat_rooms (student_id, guardian_id) VALUES (?, ?)");
        $stmt->execute([$selectedStudentId, $guardianId]);
        $roomId = $pdo->lastInsertId();
    }

    // メッセージを既読にする
    $stmt = $pdo->prepare("
        UPDATE chat_messages
        SET is_read = 1
        WHERE room_id = ? AND sender_type != 'guardian' AND is_read = 0
    ");
    $stmt->execute([$roomId]);
}

// 選択された生徒の名前を取得
$selectedStudentName = '';
if ($selectedStudentId) {
    foreach ($students as $student) {
        if ($student['id'] == $selectedStudentId) {
            $selectedStudentName = $student['student_name'];
            break;
        }
    }
}

// ページ開始
$currentPage = 'chat';
renderPageStart('guardian', $currentPage, 'チャット', [
    'additionalCss' => ['/assets/css/chat.css'],
    'classroom' => $classroom
]);
?>

<style>
.chat-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 400px;
    background: var(--md-bg-secondary);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.chat-student-selector {
    padding: 16px;
    background: var(--md-bg-primary);
    border-bottom: 1px solid var(--md-border);
}

.chat-student-selector select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid var(--md-border);
    font-size: 14px;
}

.messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.message {
    max-width: 75%;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.5;
}

.message.sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #4CAF50, #66BB6A);
    color: white;
    border-bottom-right-radius: 4px;
}

.message.received {
    align-self: flex-start;
    background: var(--md-bg-primary);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.message-time {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 4px;
}

.message-sender {
    font-size: 12px;
    color: var(--md-blue);
    margin-bottom: 4px;
    font-weight: 500;
}

.chat-input-area {
    padding: 16px;
    background: var(--md-bg-primary);
    border-top: 1px solid var(--md-border);
}

.chat-input-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
}

.chat-input-form textarea {
    flex: 1;
    padding: 12px;
    border-radius: 20px;
    border: 1px solid var(--md-border);
    resize: none;
    font-size: 14px;
    max-height: 100px;
}

.chat-input-form button {
    padding: 12px 24px;
    background: linear-gradient(135deg, #4CAF50, #66BB6A);
    color: white;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    cursor: pointer;
}

.chat-input-form button:hover {
    transform: translateY(-1px);
}

.chat-empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.chat-empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.no-students-message {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
}
</style>

<?php if (!empty($students)): ?>
<div class="chat-wrapper">
    <!-- 生徒セレクター -->
    <div class="chat-student-selector">
        <select onchange="location.href='chat.php?student_id=' + this.value">
            <?php foreach ($students as $student): ?>
                <option value="<?= $student['id'] ?>" <?= $selectedStudentId == $student['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($student['student_name']) ?>さんについてのチャット
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- メッセージエリア -->
    <div class="messages-area" id="messagesArea">
        <div class="chat-empty-state">
            <div class="chat-empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span></div>
            <h3>メッセージを読み込み中...</h3>
        </div>
    </div>

    <!-- 入力エリア -->
    <div class="chat-input-area">
        <form class="chat-input-form" id="messageForm" onsubmit="return sendMessage(event)">
            <textarea
                id="messageInput"
                placeholder="メッセージを入力..."
                rows="1"
                onkeydown="handleKeyDown(event)"
            ></textarea>
            <button type="submit">送信</button>
        </form>
    </div>
</div>

<script>
const roomId = <?= $roomId ?? 'null' ?>;
const guardianId = <?= $guardianId ?>;
const csrfToken = '<?= generateCsrfToken() ?>';
let lastMessageId = 0;

// メッセージを読み込む
async function loadMessages() {
    if (!roomId) return;

    try {
        const response = await fetch(`chat_api.php?action=get_messages&room_id=${roomId}&last_id=${lastMessageId}`);
        const data = await response.json();

        if (data.success && data.messages.length > 0) {
            const messagesArea = document.getElementById('messagesArea');
            const wasAtBottom = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 50;

            // 初回読み込み時はエリアをクリア
            if (lastMessageId === 0) {
                messagesArea.innerHTML = '';
            }

            data.messages.forEach(msg => {
                const div = document.createElement('div');
                const isOwn = msg.sender_type === 'guardian';
                div.className = `message ${isOwn ? 'sent' : 'received'}`;

                let html = '';
                // スタッフからのメッセージには送信者名を表示
                if (!isOwn) {
                    html += `<div class="message-sender">${escapeHtml(msg.sender_name || 'スタッフ')}</div>`;
                }
                html += `<div class="message-content">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>`;
                html += `<div class="message-time">${formatTime(msg.created_at)}</div>`;
                div.innerHTML = html;

                messagesArea.appendChild(div);
                lastMessageId = Math.max(lastMessageId, msg.id);
            });

            if (wasAtBottom || lastMessageId === 0) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        } else if (lastMessageId === 0) {
            document.getElementById('messagesArea').innerHTML = `
                <div class="chat-empty-state">
                    <div class="chat-empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span></div>
                    <h3>メッセージはまだありません</h3>
                    <p>スタッフにメッセージを送信してみましょう</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

// メッセージを送信
async function sendMessage(event) {
    event.preventDefault();

    const input = document.getElementById('messageInput');
    const message = input.value.trim();

    if (!message || !roomId) return false;

    try {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('room_id', roomId);
        formData.append('message', message);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('chat_api.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            loadMessages();
        } else {
            alert(data.error || 'メッセージの送信に失敗しました');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('メッセージの送信に失敗しました');
    }

    return false;
}

// エンターキーで送信
function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(event);
    }
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 日時フォーマット
function formatTime(dateTimeStr) {
    const date = new Date(dateTimeStr);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const msgDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const time = date.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });

    if (msgDate.getTime() === today.getTime()) {
        return time;
    } else {
        return date.toLocaleDateString('ja-JP', { month: 'numeric', day: 'numeric' }) + ' ' + time;
    }
}

// 初期読み込みとポーリング
loadMessages();
setInterval(loadMessages, 3000);
</script>

<?php else: ?>
<div class="no-students-message">
    <p>お子様が登録されていません。</p>
    <p>管理者にお問い合わせください。</p>
</div>
<?php endif; ?>

<?php renderPageEnd(); ?>
