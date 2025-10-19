<?php
/**
 * スタッフ用 チャットページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// 部門フィルター
$departmentFilter = $_GET['department'] ?? '';

// 全生徒を取得（チャットルームがなくても表示）
$sql = "
    SELECT
        s.id as student_id,
        s.student_name,
        s.grade_level,
        s.guardian_id,
        u.full_name as guardian_name,
        cl.classroom_name,
        cr.id as room_id,
        cr.last_message_at,
        (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND sender_type = 'guardian' AND is_read = 0) as unread_count
    FROM students s
    LEFT JOIN users u ON s.guardian_id = u.id
    LEFT JOIN classrooms cl ON s.classroom_id = cl.id
    LEFT JOIN chat_rooms cr ON s.id = cr.student_id AND s.guardian_id = cr.guardian_id
    WHERE s.is_active = 1
";

$params = [];
if ($departmentFilter) {
    // grade_levelの値に変換
    $gradeMapping = [
        '小学部' => 'elementary',
        '中等部' => 'junior_high',
        '高等部' => 'high_school'
    ];
    if (isset($gradeMapping[$departmentFilter])) {
        $sql .= " AND s.grade_level = ?";
        $params[] = $gradeMapping[$departmentFilter];
    }
}

$sql .= " ORDER BY CASE WHEN cr.last_message_at IS NULL THEN 1 ELSE 0 END, cr.last_message_at DESC, s.student_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// 選択された生徒IDまたはルームID
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedRoomId = $_GET['room_id'] ?? null;

// 選択された生徒の情報
$selectedStudent = null;
$selectedRoom = null;

if ($selectedStudentId) {
    foreach ($students as $student) {
        if ($student['student_id'] == $selectedStudentId) {
            $selectedStudent = $student;
            $selectedRoomId = $student['room_id'];
            break;
        }
    }
} elseif (!$selectedStudentId && !empty($students)) {
    // 何も選択されていない場合は最初の生徒を選択
    $selectedStudent = $students[0];
    $selectedStudentId = $selectedStudent['student_id'];
    $selectedRoomId = $selectedStudent['room_id'];
}

// ルームが存在しない場合は作成
if ($selectedStudent && !$selectedRoomId && $selectedStudent['guardian_id']) {
    $stmt = $pdo->prepare("INSERT INTO chat_rooms (student_id, guardian_id) VALUES (?, ?)");
    $stmt->execute([$selectedStudentId, $selectedStudent['guardian_id']]);
    $selectedRoomId = $pdo->lastInsertId();
}

// メッセージを既読にする
if ($selectedRoomId) {
    $stmt = $pdo->prepare("
        UPDATE chat_messages
        SET is_read = 1
        WHERE room_id = ? AND sender_type = 'guardian' AND is_read = 0
    ");
    $stmt->execute([$selectedRoomId]);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チャット - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }

        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .rooms-sidebar {
            width: 320px;
            border-right: 1px solid #e0e0e0;
            background: #f8f9fa;
            overflow-y: auto;
        }

        .room-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s;
        }

        .room-item:hover {
            background: #e8eaf6;
        }

        .room-item.active {
            background: linear-gradient(135deg, #f0f4ff 0%, #faf0ff 100%);
            border-left: 4px solid #667eea;
        }

        .room-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .student-name {
            font-weight: 600;
            font-size: 15px;
            color: #333;
        }

        .unread-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .guardian-name {
            font-size: 13px;
            color: #666;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .chat-subtitle {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        .message.sent {
            flex-direction: row-reverse;
        }

        .message-bubble {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .message.received .message-bubble {
            background: white;
            color: #333;
            border-bottom-left-radius: 4px;
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-info {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        .message.sent .message-info {
            text-align: right;
        }

        .sender-name {
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 4px;
            color: #667eea;
        }

        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .input-form {
            display: flex;
            gap: 10px;
        }

        .input-form textarea {
            flex: 1;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 15px;
            resize: none;
            font-family: inherit;
            min-height: 50px;
            max-height: 150px;
        }

        .input-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .filter-section {
            padding: 15px;
            background: white;
            border-bottom: 2px solid #e0e0e0;
        }

        .department-filter {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .department-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #667eea;
            color: white;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 5px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-label {
            padding: 12px 20px;
            background: #f8f9fa;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .file-input-label:hover {
            background: #667eea;
            color: white;
        }

        .file-input {
            display: none;
        }

        .file-preview {
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }

        .file-preview.show {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remove-file {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            color: inherit;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }

        .message.received .attachment-link {
            background: #e8eaf6;
        }

        .attachment-link:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 チャット</h1>
            <div class="nav-links">
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="main-content">
            <!-- 生徒一覧サイドバー -->
            <div class="rooms-sidebar">
                <!-- 検索フィルター -->
                <div class="filter-section">
                    <select onchange="location.href='chat.php?department=' + this.value" class="department-filter">
                        <option value="">全て</option>
                        <option value="小学部" <?= $departmentFilter === '小学部' ? 'selected' : '' ?>>小学部</option>
                        <option value="中等部" <?= $departmentFilter === '中等部' ? 'selected' : '' ?>>中等部</option>
                        <option value="高等部" <?= $departmentFilter === '高等部' ? 'selected' : '' ?>>高等部</option>
                    </select>
                </div>

                <!-- 生徒一覧 -->
                <?php if (!empty($students)): ?>
                    <?php
                    // grade_levelを日本語に変換
                    $gradeLabels = [
                        'elementary' => '小学部',
                        'junior_high' => '中等部',
                        'high_school' => '高等部'
                    ];
                    ?>
                    <?php foreach ($students as $student): ?>
                        <div class="room-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                             onclick="location.href='chat.php?student_id=<?= $student['student_id'] ?><?= $departmentFilter ? '&department=' . urlencode($departmentFilter) : '' ?>'">
                            <div class="room-item-header">
                                <div class="student-name">
                                    <?= htmlspecialchars($student['student_name']) ?>さん
                                    <?php if (isset($student['grade_level'])): ?>
                                        <span class="department-badge"><?= htmlspecialchars($gradeLabels[$student['grade_level']] ?? '') ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($student['unread_count']) && $student['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $student['unread_count'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="guardian-name">
                                保護者: <?= $student['guardian_name'] ? htmlspecialchars($student['guardian_name']) : '未登録' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>生徒がいません</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- チャットエリア -->
            <?php if ($selectedStudent): ?>
                <div class="chat-container">
                    <div class="chat-header">
                        <div class="chat-title"><?= htmlspecialchars($selectedStudent['student_name']) ?>さん</div>
                        <div class="chat-subtitle">保護者: <?= $selectedStudent['guardian_name'] ? htmlspecialchars($selectedStudent['guardian_name']) : '未登録' ?></div>
                    </div>

                    <div class="messages-area" id="messagesArea">
                        <!-- メッセージはJavaScriptで読み込まれます -->
                    </div>

                    <div class="input-area">
                        <div class="file-preview" id="filePreview">
                            <div class="file-info">
                                📎 <span id="fileName"></span> (<span id="fileSize"></span>)
                            </div>
                            <button type="button" class="remove-file" onclick="removeFile()">削除</button>
                        </div>
                        <form class="input-form" onsubmit="sendMessage(event)" id="chatForm">
                            <div class="file-input-wrapper">
                                <label for="fileInput" class="file-input-label">
                                    📎 ファイル
                                </label>
                                <input
                                    type="file"
                                    id="fileInput"
                                    class="file-input"
                                    onchange="handleFileSelect(event)"
                                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                >
                            </div>
                            <textarea
                                id="messageInput"
                                placeholder="メッセージを入力..."
                                rows="2"
                                onkeydown="handleKeyDown(event)"
                            ></textarea>
                            <button type="submit" class="send-btn" id="sendBtn">送信</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="chat-container">
                    <div class="empty-state">
                        <h3>チャットを選択してください</h3>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const roomId = <?= $selectedRoomId ?? 'null' ?>;
        let isLoading = false;
        let lastMessageId = 0;
        let selectedFile = null;
        const MAX_FILE_SIZE = 3 * 1024 * 1024; // 3MB

        // ファイル選択
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

        // ファイル削除
        function removeFile() {
            selectedFile = null;
            document.getElementById('fileInput').value = '';
            document.getElementById('filePreview').classList.remove('show');
        }

        // ファイルサイズのフォーマット
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        // メッセージを読み込む
        function loadMessages() {
            if (!roomId) return;

            fetch(`chat_api.php?action=get_messages&room_id=${roomId}&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const messagesArea = document.getElementById('messagesArea');
                        const shouldScroll = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;

                        data.messages.forEach(msg => {
                            appendMessage(msg);
                            lastMessageId = Math.max(lastMessageId, msg.id);
                        });

                        if (shouldScroll) {
                            scrollToBottom();
                        }
                    }
                })
                .catch(error => console.error('メッセージの読み込みエラー:', error));
        }

        // メッセージを表示に追加
        function appendMessage(msg) {
            const messagesArea = document.getElementById('messagesArea');
            const isOwn = msg.sender_type === 'staff' || msg.sender_type === 'admin';
            const isAbsence = msg.message_type === 'absence_notification';

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwn ? 'sent' : 'received'}`;

            let html = '<div>';
            if (!isOwn) {
                html += `<div class="sender-name">${escapeHtml(msg.sender_name || '保護者')}</div>`;
            }

            // 欠席連絡・イベント参加メッセージの場合は特別なスタイル
            const isEvent = msg.message_type === 'event_registration';
            if (isAbsence) {
                html += `<div class="message-bubble" style="background: #ffe6e6; border-left: 4px solid #ff6b35; color: #333; font-weight: 500; white-space: nowrap; max-width: none; width: auto;">`;
            } else if (isEvent) {
                html += `<div class="message-bubble" style="background: #e6f2ff; border-left: 4px solid #2563eb; color: #333; font-weight: 500; white-space: nowrap; max-width: none; width: auto;">`;
            } else {
                html += `<div class="message-bubble">`;
            }

            if (msg.message) {
                // 欠席連絡・イベント参加は改行をスペースに置き換え、通常メッセージは<br>に変換
                if (isAbsence || isEvent) {
                    html += escapeHtml(msg.message).replace(/\n/g, ' ');
                } else {
                    html += escapeHtml(msg.message).replace(/\n/g, '<br>');
                }
            }
            if (msg.attachment_path) {
                html += `<a href="download_attachment.php?id=${msg.id}" class="attachment-link" target="_blank">`;
                html += `📎 ${escapeHtml(msg.attachment_original_name || 'ファイル')}`;
                html += `</a>`;
            }
            html += `</div>`;
            html += `<div class="message-info">${formatDateTime(msg.created_at)}</div>`;
            html += '</div>';

            messageDiv.innerHTML = html;
            messagesArea.appendChild(messageDiv);
        }

        // メッセージを送信
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
            sendBtn.textContent = '送信中...';

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('room_id', roomId);
            formData.append('message', message);
            if (selectedFile) {
                formData.append('attachment', selectedFile);
            }

            fetch('chat_api.php', {
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
                    alert('送信エラー: ' + data.message);
                }
            })
            .catch(error => {
                alert('送信エラー: ' + error);
            })
            .finally(() => {
                isLoading = false;
                sendBtn.disabled = false;
                sendBtn.textContent = '送信';
                input.focus();
            });
        }

        // Enterキーで送信（Shift+Enterで改行）
        function handleKeyDown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage(event);
            }
        }

        // 最下部にスクロール
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // 日時フォーマット
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

        // 初期読み込み
        if (roomId) {
            loadMessages();
            scrollToBottom();

            // 3秒ごとに新しいメッセージをチェック
            setInterval(loadMessages, 3000);
        }
    </script>
</body>
</html>
