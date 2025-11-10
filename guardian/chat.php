<?php
/**
 * 保護者用 チャットページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 保護者の教室IDを取得
$stmt = $pdo->prepare("SELECT classroom_id FROM users WHERE id = ?");
$stmt->execute([$guardianId]);
$classroomId = $stmt->fetchColumn();

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
        // ルームが存在しない場合は作成
        $stmt = $pdo->prepare("INSERT INTO chat_rooms (student_id, guardian_id) VALUES (?, ?)");
        $stmt->execute([$selectedStudentId, $guardianId]);
        $roomId = $pdo->lastInsertId();
    }

    // メッセージを既読にする（保護者が受信したメッセージ）
    $stmt = $pdo->prepare("
        UPDATE chat_messages
        SET is_read = 1
        WHERE room_id = ? AND sender_type != 'guardian' AND is_read = 0
    ");
    $stmt->execute([$roomId]);
}

// 選択された生徒の名前
$selectedStudentName = '';
$scheduledDays = [];
if ($selectedStudentId) {
    foreach ($students as $student) {
        if ($student['id'] == $selectedStudentId) {
            $selectedStudentName = $student['student_name'];
            break;
        }
    }

    // 選択された生徒の参加予定日を取得
    $stmt = $pdo->prepare("
        SELECT
            scheduled_monday, scheduled_tuesday, scheduled_wednesday,
            scheduled_thursday, scheduled_friday, scheduled_saturday, scheduled_sunday
        FROM students WHERE id = ?
    ");
    $stmt->execute([$selectedStudentId]);
    $schedule = $stmt->fetch();

    // 今日から30日先までの参加予定日を取得
    if ($schedule) {
        $dayMapping = [
            0 => 'scheduled_sunday',
            1 => 'scheduled_monday',
            2 => 'scheduled_tuesday',
            3 => 'scheduled_wednesday',
            4 => 'scheduled_thursday',
            5 => 'scheduled_friday',
            6 => 'scheduled_saturday'
        ];

        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $dayOfWeek = (int)date('w', strtotime($date));
            $columnName = $dayMapping[$dayOfWeek];

            if ($schedule[$columnName] == 1) {
                $scheduledDays[] = [
                    'date' => $date,
                    'display' => date('n月j日', strtotime($date)) . '(' . ['日', '月', '火', '水', '木', '金', '土'][$dayOfWeek] . ')'
                ];
            }
        }
    }

    // 未来のイベント一覧を取得（生徒の所属する教室のイベントのみ）
    $upcomingEvents = [];
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT id, event_name, event_date, event_description
            FROM events
            WHERE event_date >= CURDATE()
                AND classroom_id = ?
                AND classroom_id IS NOT NULL
            ORDER BY event_date ASC
            LIMIT 30
        ");
        $stmt->execute([$classroomId]);
        $upcomingEvents = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チャット - 保護者ページ</title>
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
            max-width: 1000px;
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

        .student-selector {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .student-selector select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 15px;
            background: white;
            cursor: pointer;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
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

        .absence-form-area {
            padding: 15px 20px;
            background: #fff8f0;
            border-top: 1px solid #e0e0e0;
            display: none;
        }

        .absence-form-area.show {
            display: block;
        }

        .absence-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .absence-form-title {
            font-weight: 600;
            color: #ff6b35;
            font-size: 16px;
        }

        .close-absence-form {
            background: #ff6b35;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .absence-date-select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ff6b35;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .absence-reason {
            width: 100%;
            padding: 10px;
            border: 2px solid #ff6b35;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
            margin-bottom: 10px;
            font-family: inherit;
        }

        .send-absence-btn {
            width: 100%;
            padding: 12px;
            background: #ff6b35;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .send-absence-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* イベント参加フォーム */
        .event-form-area {
            padding: 15px 20px;
            background: #f0f8ff;
            border-top: 1px solid #e0e0e0;
            display: none;
        }

        .event-form-area.show {
            display: block;
        }

        .event-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .event-form-title {
            font-weight: 600;
            color: #2563eb;
            font-size: 16px;
        }

        .close-event-form {
            background: #2563eb;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }

        .event-select {
            width: 100%;
            padding: 10px;
            border: 2px solid #2563eb;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .event-details {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: none;
            font-size: 13px;
        }

        .event-details.show {
            display: block;
        }

        .event-notes {
            width: 100%;
            padding: 10px;
            border: 2px solid #2563eb;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
            margin-bottom: 10px;
            font-family: inherit;
        }

        .send-event-btn {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .send-event-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .message-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .message-type-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e8ed;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .message-type-btn:hover {
            border-color: #667eea;
        }

        .message-type-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .message-type-btn.absence.active {
            background: #ff6b35;
            border-color: #ff6b35;
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

        .date-divider {
            text-align: center;
            margin: 20px 0;
            color: #999;
            font-size: 12px;
            font-weight: 600;
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
            margin-top: 10px;
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
                <a href="dashboard.php">← ダッシュボード</a>
            </div>
        </div>

        <?php if (!empty($students)): ?>
            <div class="student-selector">
                <select onchange="location.href='chat.php?student_id=' + this.value">
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['id'] ?>" <?= $selectedStudentId == $student['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['student_name']) ?>さんについてのチャット
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="chat-container">
                <div class="messages-area" id="messagesArea">
                    <!-- メッセージはJavaScriptで読み込まれます -->
                </div>

                <!-- 欠席連絡フォーム -->
                <div class="absence-form-area" id="absenceFormArea">
                    <div class="absence-form-header">
                        <div class="absence-form-title">🚫 欠席連絡</div>
                        <button type="button" class="close-absence-form" onclick="closeAbsenceForm()">閉じる</button>
                    </div>
                    <select class="absence-date-select" id="absenceDate">
                        <option value="">欠席する日を選択してください</option>
                        <?php foreach ($scheduledDays as $day): ?>
                            <option value="<?= $day['date'] ?>"><?= htmlspecialchars($day['display']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea
                        class="absence-reason"
                        id="absenceReason"
                        placeholder="欠席理由（任意）&#10;例：体調不良のため"
                    ></textarea>

                    <!-- 振替依頼 -->
                    <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;">
                        <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 10px;">
                            <input type="checkbox" id="requestMakeup" onchange="toggleMakeupDate()" style="margin-right: 8px; width: 18px; height: 18px;">
                            <span style="font-weight: 600; color: #333;">振替を希望する</span>
                        </label>
                        <div id="makeupDateSection" style="display: none;">
                            <label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">振替希望日を選択してください</label>
                            <select class="absence-date-select" id="makeupDate">
                                <option value="">選択してください</option>
                                <?php
                                // 今日から30日後までの日付を生成
                                for ($i = 0; $i <= 30; $i++) {
                                    $date = date('Y-m-d', strtotime("+$i days"));
                                    $display = date('n月j日（', strtotime($date)) . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($date))] . '）';
                                    echo "<option value=\"{$date}\">{$display}</option>";
                                }
                                ?>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">※スタッフの承認後、選択した日の出席予定者に追加されます</p>
                        </div>
                    </div>

                    <button type="button" class="send-absence-btn" onclick="sendAbsenceNotification()" id="sendAbsenceBtn">
                        欠席連絡を送信
                    </button>
                </div>

                <!-- イベント参加フォーム -->
                <div class="event-form-area" id="eventFormArea">
                    <div class="event-form-header">
                        <div class="event-form-title">🎉 イベント参加申込</div>
                        <button type="button" class="close-event-form" onclick="closeEventForm()">閉じる</button>
                    </div>
                    <select class="event-select" id="eventSelect" onchange="showEventDetails()">
                        <option value="">参加するイベントを選択してください</option>
                        <?php foreach ($upcomingEvents as $event): ?>
                            <option value="<?= $event['id'] ?>"
                                    data-name="<?= htmlspecialchars($event['event_name']) ?>"
                                    data-date="<?= $event['event_date'] ?>"
                                    data-desc="<?= htmlspecialchars($event['event_description'] ?? '') ?>">
                                <?= date('n月j日', strtotime($event['event_date'])) ?>
                                - <?= htmlspecialchars($event['event_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="event-details" id="eventDetails"></div>
                    <textarea
                        class="event-notes"
                        id="eventNotes"
                        placeholder="備考（任意）&#10;例：アレルギー情報、送迎について等"
                    ></textarea>
                    <button type="button" class="send-event-btn" onclick="sendEventRegistration()" id="sendEventBtn">
                        イベントに参加する
                    </button>
                </div>

                <div class="input-area">
                    <!-- メッセージタイプセレクター（プルダウン） -->
                    <div class="message-type-selector">
                        <select onchange="selectMessageType(this.value)" style="width: 100%; padding: 10px; border: 2px solid #667eea; border-radius: 8px; font-size: 14px;">
                            <option value="normal">💬 通常メッセージ</option>
                            <option value="absence">🚫 欠席連絡</option>
                            <option value="event">🎉 イベント参加申込</option>
                        </select>
                    </div>

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
            <div class="empty-state">
                <h3>お子様の情報が登録されていません</h3>
                <p>スタッフまでお問い合わせください。</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const roomId = <?= $roomId ?? 'null' ?>;
        const studentName = <?= json_encode($selectedStudentName) ?>;
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
            const isOwn = msg.sender_type === 'guardian';
            const isAbsence = msg.message_type === 'absence_notification';

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwn ? 'sent' : 'received'}`;

            let html = '<div>';
            if (!isOwn) {
                html += `<div class="sender-name">${escapeHtml(msg.sender_name || 'スタッフ')}</div>`;
            }

            // 欠席連絡メッセージの場合は特別なスタイル
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

        // メッセージタイプ選択
        function selectMessageType(type) {
            document.getElementById('absenceFormArea').classList.remove('show');
            document.getElementById('eventFormArea').classList.remove('show');
            document.getElementById('chatForm').style.display = 'flex';

            if (type === 'absence') {
                document.getElementById('absenceFormArea').classList.add('show');
                document.getElementById('chatForm').style.display = 'none';
            } else if (type === 'event') {
                document.getElementById('eventFormArea').classList.add('show');
                document.getElementById('chatForm').style.display = 'none';
            }
        }

        // 欠席連絡フォームを閉じる
        function closeAbsenceForm() {
            document.querySelector('.message-type-selector select').value = 'normal';
            selectMessageType('normal');
        }

        // イベントフォームを閉じる
        function closeEventForm() {
            document.querySelector('.message-type-selector select').value = 'normal';
            selectMessageType('normal');
        }

        // イベント詳細を表示
        function showEventDetails() {
            const select = document.getElementById('eventSelect');
            const option = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('eventDetails');

            if (!option.value) {
                detailsDiv.classList.remove('show');
                return;
            }

            const eventName = option.dataset.name;
            const eventDate = option.dataset.date;
            const eventDesc = option.dataset.desc;

            const dateObj = new Date(eventDate);
            const dateStr = (dateObj.getMonth() + 1) + '月' + dateObj.getDate() + '日';
            const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][dateObj.getDay()];

            let html = `<strong>${eventName}</strong><br>`;
            html += `日時: ${dateStr}(${dayOfWeek})<br>`;
            if (eventDesc) {
                html += `<div style="margin-top: 8px; color: #666;">${escapeHtml(eventDesc)}</div>`;
            }

            detailsDiv.innerHTML = html;
            detailsDiv.classList.add('show');
        }

        // 振替依頼の表示/非表示を切り替え
        function toggleMakeupDate() {
            const checkbox = document.getElementById('requestMakeup');
            const dateSelector = document.getElementById('makeupDateSelector');
            if (checkbox.checked) {
                dateSelector.style.display = 'block';
            } else {
                dateSelector.style.display = 'none';
                document.getElementById('makeupDate').value = '';
            }
        }

        // 欠席連絡を送信
        function sendAbsenceNotification() {
            const absenceDate = document.getElementById('absenceDate').value;
            const reason = document.getElementById('absenceReason').value.trim();
            const requestMakeup = document.getElementById('requestMakeup').checked;
            const makeupDate = document.getElementById('makeupDate').value;

            if (!absenceDate) {
                alert('欠席する日を選択してください。');
                return;
            }

            if (requestMakeup && !makeupDate) {
                alert('振替希望日を選択してください。');
                return;
            }

            if (isLoading) return;

            isLoading = true;
            const sendBtn = document.getElementById('sendAbsenceBtn');
            sendBtn.disabled = true;
            sendBtn.textContent = '送信中...';

            const formData = new FormData();
            formData.append('action', 'send_absence_notification');
            formData.append('room_id', roomId);
            formData.append('student_id', <?= $selectedStudentId ?? 'null' ?>);
            formData.append('absence_date', absenceDate);
            formData.append('reason', reason);
            formData.append('request_makeup', requestMakeup ? '1' : '0');
            if (requestMakeup && makeupDate) {
                formData.append('makeup_date', makeupDate);
            }

            fetch('chat_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('absenceDate').value = '';
                    document.getElementById('absenceReason').value = '';
                    document.getElementById('requestMakeup').checked = false;
                    document.getElementById('makeupDate').value = '';
                    document.getElementById('makeupDateSelector').style.display = 'none';
                    selectMessageType('normal');
                    // loadMessages()は3秒ごとのポーリングで自動実行されるため不要
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
                sendBtn.textContent = '欠席連絡を送信';
            });
        }

        // イベント参加申込を送信
        function sendEventRegistration() {
            const eventId = document.getElementById('eventSelect').value;
            const notes = document.getElementById('eventNotes').value.trim();

            if (!eventId) {
                alert('イベントを選択してください。');
                return;
            }

            if (isLoading) return;

            isLoading = true;
            const sendBtn = document.getElementById('sendEventBtn');
            sendBtn.disabled = true;
            sendBtn.textContent = '送信中...';

            const formData = new FormData();
            formData.append('action', 'send_event_registration');
            formData.append('room_id', roomId);
            formData.append('student_id', <?= $selectedStudentId ?? 'null' ?>);
            formData.append('event_id', eventId);
            formData.append('notes', notes);

            fetch('chat_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('eventSelect').value = '';
                    document.getElementById('eventNotes').value = '';
                    document.getElementById('eventDetails').classList.remove('show');
                    document.querySelector('.message-type-selector select').value = 'normal';
                    selectMessageType('normal');
                    // loadMessages()は3秒ごとのポーリングで自動実行されるため不要
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
                sendBtn.textContent = 'イベントに参加する';
            });
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
