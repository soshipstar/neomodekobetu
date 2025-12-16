<?php
/**
 * スタッフ用 - 生徒チャット一覧（保護者チャットと統一デザイン）
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/student_helper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;
$staffId = $_SESSION['user_id'];

// 生徒一覧を取得（チャットルームの有無に関わらず、教室でフィルタリング）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT
            s.id as student_id,
            s.student_name,
            s.grade_level,
            s.is_active,
            s.status,
            scr.id as room_id,
            (SELECT MAX(created_at)
             FROM student_chat_messages scm
             WHERE scm.room_id = scr.id) as last_message_at,
            COALESCE(
                (SELECT COUNT(*)
                 FROM student_chat_messages scm
                 WHERE scm.room_id = scr.id
                   AND scm.sender_type = 'student'
                   AND scm.is_read = 0), 0
            ) as unread_count
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        LEFT JOIN student_chat_rooms scr ON s.id = scr.student_id
        WHERE g.classroom_id = ?
        ORDER BY CASE WHEN scr.id IS NULL THEN 1 ELSE 0 END,
                 (SELECT MAX(created_at) FROM student_chat_messages WHERE room_id = scr.id) DESC,
                 s.grade_level, s.student_name ASC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT
            s.id as student_id,
            s.student_name,
            s.grade_level,
            s.is_active,
            s.status,
            scr.id as room_id,
            (SELECT MAX(created_at)
             FROM student_chat_messages scm
             WHERE scm.room_id = scr.id) as last_message_at,
            COALESCE(
                (SELECT COUNT(*)
                 FROM student_chat_messages scm
                 WHERE scm.room_id = scr.id
                   AND scm.sender_type = 'student'
                   AND scm.is_read = 0), 0
            ) as unread_count
        FROM students s
        LEFT JOIN student_chat_rooms scr ON s.id = scr.student_id
        ORDER BY CASE WHEN scr.id IS NULL THEN 1 ELSE 0 END,
                 (SELECT MAX(created_at) FROM student_chat_messages WHERE room_id = scr.id) DESC,
                 s.grade_level, s.student_name ASC
    ");
}

$allStudents = $stmt->fetchAll();

// 学部別に分類
$elementary = [];
$junior = [];
$senior = [];

foreach ($allStudents as $student) {
    $grade = $student['grade_level'];
    if ($grade === 'elementary') {
        $elementary[] = $student;
    } elseif ($grade === 'junior_high') {
        $junior[] = $student;
    } elseif ($grade === 'high_school') {
        $senior[] = $student;
    }
}

// 選択された生徒ID
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedStudent = null;
$selectedRoomId = null;

if ($selectedStudentId) {
    foreach ($allStudents as $student) {
        if ($student['student_id'] == $selectedStudentId) {
            $selectedStudent = $student;
            $selectedRoomId = $student['room_id'];
            break;
        }
    }
} elseif (!empty($allStudents)) {
    $selectedStudent = $allStudents[0];
    $selectedStudentId = $selectedStudent['student_id'];
    $selectedRoomId = $selectedStudent['room_id'];
}

// ルームが存在しない場合は作成
if ($selectedStudent && !$selectedRoomId) {
    $stmt = $pdo->prepare("INSERT INTO student_chat_rooms (student_id) VALUES (?)");
    $stmt->execute([$selectedStudentId]);
    $selectedRoomId = $pdo->lastInsertId();
}

// メッセージを既読にする（生徒からの未読メッセージ）
if ($selectedRoomId) {
    $stmt = $pdo->prepare("
        UPDATE student_chat_messages
        SET is_read = 1
        WHERE room_id = ? AND sender_type = 'student' AND is_read = 0
    ");
    $stmt->execute([$selectedRoomId]);
}

// ページ開始
$currentPage = 'student_chats';
renderPageStart('staff', $currentPage, '生徒チャット', [
    'additionalCss' => ['/assets/css/chat.css'],
    'noContainer' => true
]);
?>

<style>
/* スタッフ生徒チャット固有のスタイル - 保護者チャットと統一 */
.staff-chat-layout {
    display: flex;
    height: calc(100vh - 60px);
    background: var(--apple-bg-primary);
}

@media (min-width: 769px) {
    .staff-chat-layout {
        height: 100vh;
    }
}

.student-sidebar {
    width: 300px;
    background: var(--apple-bg-tertiary);
    border-right: 1px solid var(--apple-gray-5);
    overflow-y: auto;
    flex-shrink: 0;
}

.student-sidebar-header {
    padding: var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-bottom: 1px solid var(--apple-gray-5);
}

.broadcast-btn {
    width: 100%;
    padding: var(--spacing-md);
    background: var(--apple-green);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    font-weight: 600;
    cursor: pointer;
    margin-bottom: var(--spacing-md);
}

.broadcast-btn:hover {
    opacity: 0.9;
}

.search-input {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    background: var(--apple-bg-primary);
    color: var(--text-primary);
}

.accordion-header {
    padding: var(--spacing-md);
    background: var(--apple-gray-4);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--apple-gray-5);
    transition: background var(--duration-fast);
}

.accordion-header:hover {
    background: var(--apple-gray-3);
}

.accordion-header.active {
    background: var(--apple-blue);
    color: white;
}

.accordion-title {
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.accordion-count {
    font-size: var(--text-caption-1);
    opacity: 0.8;
}

.accordion-icon {
    transition: transform var(--duration-normal);
}

.accordion-header.active .accordion-icon {
    transform: rotate(180deg);
}

.accordion-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height var(--duration-normal) ease-out;
}

.accordion-content.active {
    max-height: 2000px;
}

.student-item {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--apple-gray-5);
    cursor: pointer;
    background: var(--apple-bg-primary);
    transition: background var(--duration-fast);
}

.student-item:hover {
    background: var(--apple-bg-secondary);
}

.student-item.active {
    background: rgba(0, 122, 255, 0.2);
    border-left: 4px solid var(--apple-blue);
}

.student-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.student-item-name {
    font-weight: 600;
    color: var(--text-primary);
}

.unread-badge {
    background: var(--apple-red);
    color: white;
    padding: 2px 8px;
    border-radius: var(--radius-md);
    font-size: 11px;
    font-weight: 600;
}

.student-status-label {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    margin-top: 4px;
}

.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

.chat-main .chat-wrapper {
    flex: 1;
    height: auto;
    max-height: none;
    display: flex;
    flex-direction: column;
}

.chat-main .messages-area {
    flex: 1;
    overflow-y: auto;
}

.chat-header-bar {
    padding: var(--spacing-md) var(--spacing-lg);
    background: var(--apple-bg-tertiary);
    border-bottom: 1px solid var(--apple-gray-5);
}

.chat-title {
    font-size: var(--text-headline);
    font-weight: 600;
    color: var(--text-primary);
}

.chat-subtitle {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    margin-top: 2px;
}

.delete-message-btn {
    background: var(--apple-red);
    color: white;
    border: none;
    padding: 3px 8px;
    border-radius: var(--radius-xs);
    font-size: 10px;
    cursor: pointer;
}

/* モーダル */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--apple-bg-tertiary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-lg);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
}

.modal-title {
    font-size: var(--text-title-3);
    font-weight: 600;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-footer {
    display: flex;
    gap: var(--spacing-sm);
    justify-content: flex-end;
    margin-top: var(--spacing-lg);
}

.student-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    padding: var(--spacing-md);
    background: var(--apple-bg-secondary);
}

.student-list label {
    display: block;
    padding: var(--spacing-sm);
    cursor: pointer;
    border-bottom: 1px solid var(--apple-gray-5);
}

.student-list label:last-child {
    border-bottom: none;
}

/* レスポンシブ */
@media (max-width: 768px) {
    .staff-chat-layout {
        flex-direction: column;
        height: auto;
    }

    .student-sidebar {
        width: 100%;
        max-height: 40vh;
    }

    .chat-main {
        min-height: 50vh;
    }
}
</style>

<div class="staff-chat-layout">
    <!-- 生徒サイドバー -->
    <div class="student-sidebar">
        <div class="student-sidebar-header">
            <button class="broadcast-btn" onclick="openBroadcastModal()">📢 一斉送信</button>
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 生徒名で検索..." onkeyup="filterStudents()">
        </div>

        <?php if (empty($allStudents)): ?>
            <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                生徒がいません
            </div>
        <?php else: ?>
            <!-- 小学生 -->
            <?php if (!empty($elementary)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎒 小学生</span>
                        <span class="accordion-count">(<?= count($elementary) ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <?php foreach ($elementary as $student): ?>
                        <div class="student-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                             data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                             data-student-id="<?= $student['student_id'] ?>"
                             onclick="location.href='student_chats.php?student_id=<?= $student['student_id'] ?>'">
                            <div class="student-item-header">
                                <div class="student-item-name"><?= htmlspecialchars($student['student_name']) ?>さん</div>
                                <?php if ($student['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $student['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="student-status-label">
                                <?php if ($student['last_message_at']): ?>
                                    最終: <?= date('m/d H:i', strtotime($student['last_message_at'])) ?>
                                <?php else: ?>
                                    メッセージなし
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 中学生 -->
            <?php if (!empty($junior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>📚 中学生</span>
                        <span class="accordion-count">(<?= count($junior) ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <?php foreach ($junior as $student): ?>
                        <div class="student-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                             data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                             data-student-id="<?= $student['student_id'] ?>"
                             onclick="location.href='student_chats.php?student_id=<?= $student['student_id'] ?>'">
                            <div class="student-item-header">
                                <div class="student-item-name"><?= htmlspecialchars($student['student_name']) ?>さん</div>
                                <?php if ($student['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $student['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="student-status-label">
                                <?php if ($student['last_message_at']): ?>
                                    最終: <?= date('m/d H:i', strtotime($student['last_message_at'])) ?>
                                <?php else: ?>
                                    メッセージなし
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 高校生 -->
            <?php if (!empty($senior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎓 高校生</span>
                        <span class="accordion-count">(<?= count($senior) ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <?php foreach ($senior as $student): ?>
                        <div class="student-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                             data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                             data-student-id="<?= $student['student_id'] ?>"
                             onclick="location.href='student_chats.php?student_id=<?= $student['student_id'] ?>'">
                            <div class="student-item-header">
                                <div class="student-item-name"><?= htmlspecialchars($student['student_name']) ?>さん</div>
                                <?php if ($student['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $student['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="student-status-label">
                                <?php if ($student['last_message_at']): ?>
                                    最終: <?= date('m/d H:i', strtotime($student['last_message_at'])) ?>
                                <?php else: ?>
                                    メッセージなし
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- チャットエリア -->
    <div class="chat-main">
        <?php if ($selectedStudent): ?>
            <div class="chat-header-bar">
                <div class="chat-title"><?= htmlspecialchars($selectedStudent['student_name']) ?>さん</div>
                <div class="chat-subtitle">生徒との直接メッセージ</div>
            </div>

            <div class="chat-wrapper role-staff" style="border-radius: 0; box-shadow: none;">
                <div class="messages-area" id="messagesArea"></div>

                <div class="chat-input-area">
                    <div class="file-preview" id="filePreview">
                        <div class="file-preview-info">📎 <span id="fileName"></span> (<span id="fileSize"></span>)</div>
                        <button type="button" class="file-preview-remove" onclick="removeFile()">削除</button>
                    </div>

                    <form class="chat-input-form" onsubmit="sendMessage(event)" id="chatForm">
                        <label for="fileInput" class="file-attach-btn" title="ファイルを添付">📎</label>
                        <input type="file" id="fileInput" class="file-attach-input" onchange="handleFileSelect(event)" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <textarea id="messageInput" class="chat-textarea" placeholder="メッセージを入力..." onkeydown="handleKeyDown(event)"></textarea>
                        <button type="submit" class="chat-send-btn" id="sendBtn">➤</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="chat-empty-state">
                <div class="chat-empty-state-icon">💬</div>
                <h3>チャットを選択してください</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 一斉送信モーダル -->
<div id="broadcastModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">📢 一斉送信</div>
            <button class="modal-close" onclick="closeBroadcastModal()">×</button>
        </div>

        <div class="form-group">
            <label class="form-label">メッセージ</label>
            <textarea id="broadcastMessage" class="form-control" rows="4" placeholder="送信するメッセージを入力してください"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">ファイル添付（任意）</label>
            <input type="file" id="broadcastFile" class="form-control" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
            <small style="color: var(--text-secondary);">※ 1つのファイルを全員に共有します（最大10MB）</small>
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                <label class="form-label" style="margin-bottom: 0;">送信先を選択</label>
                <div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="selectAllStudents(true)">全選択</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllStudents(false)">全解除</button>
                </div>
            </div>
            <div class="student-list">
                <?php foreach ($allStudents as $student): ?>
                    <label>
                        <input type="checkbox" class="student-checkbox" value="<?= $student['student_id'] ?>">
                        <strong><?= htmlspecialchars($student['student_name']) ?>さん</strong>
                        <span style="color: var(--text-secondary); font-size: var(--text-footnote);">
                            (<?php
                                $gradeLabel = match($student['grade_level']) {
                                    'elementary' => '小学生',
                                    'junior_high' => '中学生',
                                    'high_school' => '高校生',
                                    default => ''
                                };
                                echo $gradeLabel;
                            ?>)
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeBroadcastModal()">キャンセル</button>
            <button type="button" class="btn btn-success" onclick="sendBroadcast()">送信</button>
        </div>
    </div>
</div>

<?php
$roomIdJs = $selectedRoomId ?? 0;
$studentIdJs = $selectedStudentId ?? 0;

$inlineJs = <<<JS
const roomId = {$roomIdJs};
const studentId = {$studentIdJs};
let isLoading = false;
let lastMessageId = 0;
let selectedFile = null;
const MAX_FILE_SIZE = 3 * 1024 * 1024;

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > MAX_FILE_SIZE) {
        alert('ファイルサイズは3MB以下にしてください。');
        event.target.value = '';
        return;
    }

    selectedFile = file;
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('filePreview').classList.add('show');
}

function removeFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.remove('show');
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function loadMessages() {
    if (!roomId) return;

    fetch('get_student_chat_messages.php?room_id=' + roomId + '&last_message_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const messagesArea = document.getElementById('messagesArea');
                const shouldScroll = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;

                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });

                if (shouldScroll) scrollToBottom();
            }
        })
        .catch(error => console.error('メッセージの読み込みエラー:', error));
}

function appendMessage(msg) {
    const messagesArea = document.getElementById('messagesArea');
    const isOwn = msg.sender_type === 'staff';

    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + (isOwn ? 'sent' : 'received');
    messageDiv.dataset.messageId = msg.id;

    let html = '<div class="message-content">';
    if (!isOwn) {
        html += '<div class="message-sender">' + escapeHtml(msg.sender_name || '生徒') + '</div>';
    }

    html += '<div class="message-bubble">';
    if (msg.message) {
        html += escapeHtml(msg.message).replace(/\\n/g, '<br>');
    }
    if (msg.attachment_path) {
        html += '<div class="message-attachment"><a href="download_student_chat_attachment.php?id=' + msg.id + '" target="_blank">📎 ' + escapeHtml(msg.attachment_original_name || 'ファイル') + '</a></div>';
    }
    html += '</div>';
    html += '<div class="message-time">';
    html += formatDateTime(msg.created_at);
    if (isOwn) {
        html += ' <button class="delete-message-btn" onclick="deleteMessage(' + msg.id + ')">取消</button>';
    }
    html += '</div></div>';

    messageDiv.innerHTML = html;
    messagesArea.appendChild(messageDiv);
}

function sendMessage(event) {
    event.preventDefault();

    const input = document.getElementById('messageInput');
    const message = input.value.trim();

    if (!message && !selectedFile) {
        alert('メッセージまたはファイルを入力してください。');
        return;
    }

    if (isLoading) return;

    isLoading = true;
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;

    const formData = new FormData();
    formData.append('student_id', studentId);
    formData.append('message', message);
    if (selectedFile) {
        formData.append('attachment', selectedFile);
    }

    fetch('send_student_chat_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            removeFile();
            loadMessages();
        } else {
            alert('送信エラー: ' + data.error);
        }
    })
    .catch(error => alert('送信エラー: ' + error))
    .finally(() => {
        isLoading = false;
        sendBtn.disabled = false;
        input.focus();
    });
}

function handleKeyDown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage(event);
    }
}

function scrollToBottom() {
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function formatDateTime(dateTimeStr) {
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

async function deleteMessage(messageId) {
    if (!confirm('このメッセージを削除しますか？')) return;

    const formData = new FormData();
    formData.append('message_id', messageId);

    try {
        const response = await fetch('delete_student_chat_message.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            const messageDiv = document.querySelector('[data-message-id="' + messageId + '"]');
            if (messageDiv) messageDiv.remove();
        } else {
            alert('削除に失敗しました: ' + (result.error || '不明なエラー'));
        }
    } catch (error) {
        alert('通信エラーが発生しました');
    }
}

// アコーディオン
function toggleAccordion(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('active');
    content.classList.toggle('active');
}

// 検索
function filterStudents() {
    const searchText = document.getElementById('searchInput').value.toLowerCase();
    const allItems = document.querySelectorAll('.student-item');

    allItems.forEach(item => {
        const studentName = item.getAttribute('data-student-name').toLowerCase();

        if (studentName.includes(searchText)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });

    if (searchText.length > 0) {
        document.querySelectorAll('.accordion-header').forEach(h => h.classList.add('active'));
        document.querySelectorAll('.accordion-content').forEach(c => c.classList.add('active'));
    }
}

// 一斉送信
function openBroadcastModal() {
    document.getElementById('broadcastModal').classList.add('active');
}

function closeBroadcastModal() {
    document.getElementById('broadcastModal').classList.remove('active');
    document.getElementById('broadcastMessage').value = '';
    document.getElementById('broadcastFile').value = '';
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
}

function selectAllStudents(checked) {
    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = checked);
}

function sendBroadcast() {
    const message = document.getElementById('broadcastMessage').value.trim();
    const fileInput = document.getElementById('broadcastFile');
    const file = fileInput.files[0];
    const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked'))
        .map(cb => cb.value);

    if (!message && !file) {
        alert('メッセージまたはファイルを入力してください');
        return;
    }

    if (selectedStudents.length === 0) {
        alert('送信先を選択してください');
        return;
    }

    // ファイルサイズチェック（10MB）
    if (file && file.size > 10 * 1024 * 1024) {
        alert('ファイルサイズは10MB以下にしてください');
        return;
    }

    if (!confirm(selectedStudents.length + '名の生徒にメッセージを送信しますか？')) return;

    const formData = new FormData();
    formData.append('message', message);
    formData.append('student_ids', selectedStudents.join(','));
    if (file) {
        formData.append('attachment', file);
    }

    fetch('student_chat_broadcast.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('メッセージを送信しました');
            closeBroadcastModal();
            location.reload();
        } else {
            alert('送信に失敗しました: ' + data.error);
        }
    })
    .catch(error => alert('送信に失敗しました'));
}

// モーダル外クリック
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// サイドバーのスクロール位置を保存・復元
const sidebar = document.querySelector('.student-sidebar');
const SCROLL_KEY = 'student_chat_sidebar_scroll';

// ページ遷移前にスクロール位置を保存
document.querySelectorAll('.student-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (sidebar) {
            sessionStorage.setItem(SCROLL_KEY, sidebar.scrollTop);
        }
    });
});

// ページ読み込み時にスクロール位置を復元
document.addEventListener('DOMContentLoaded', function() {
    const savedScroll = sessionStorage.getItem(SCROLL_KEY);
    if (savedScroll && sidebar) {
        sidebar.scrollTop = parseInt(savedScroll, 10);
    }
});

// 初期化
if (roomId) {
    loadMessages();
    scrollToBottom();
    setInterval(loadMessages, 5000);
}
JS;

renderPageEnd(['inlineJs' => $inlineJs, 'noContainer' => true]);
?>
