<?php
/**
 * 保護者用 チャットページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
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
$classroomId = $classroom['id'] ?? null;

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

// 選択された生徒の名前と参加予定日を取得
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

        // 既存の欠席連絡を取得（今日から30日間）
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+30 days'));
        $stmt = $pdo->prepare("
            SELECT absence_date FROM absence_notifications
            WHERE student_id = ? AND absence_date BETWEEN ? AND ?
        ");
        $stmt->execute([$selectedStudentId, $startDate, $endDate]);
        $existingAbsences = array_column($stmt->fetchAll(), 'absence_date');

        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $dayOfWeek = (int)date('w', strtotime($date));
            $columnName = $dayMapping[$dayOfWeek];

            // 通常の参加予定日かつ欠席連絡がまだない日のみ表示
            if ($schedule[$columnName] == 1 && !in_array($date, $existingAbsences)) {
                $scheduledDays[] = [
                    'date' => $date,
                    'display' => date('n月j日', strtotime($date)) . '(' . ['日', '月', '火', '水', '木', '金', '土'][$dayOfWeek] . ')'
                ];
            }
        }
    }

    // 未来のイベント一覧を取得
    $upcomingEvents = [];
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT id, event_name, event_date, event_description
            FROM events
            WHERE event_date >= CURDATE()
                AND classroom_id = ?
            ORDER BY event_date ASC
            LIMIT 30
        ");
        $stmt->execute([$classroomId]);
        $upcomingEvents = $stmt->fetchAll();
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
/* チャットページ用追加スタイル */
.chat-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
}

.chat-page-header h1 {
    font-size: var(--text-title-3);
    color: var(--text-primary);
}

.makeup-section {
    margin-top: var(--spacing-lg);
    padding: var(--spacing-lg);
    background: rgba(36, 161, 72, 0.15);
    border: 2px solid var(--md-green);
    border-radius: 0;
}

.makeup-section-inner {
    background: var(--md-bg-primary);
    padding: var(--spacing-md);
    border-radius: 0;
    margin-bottom: var(--spacing-md);
}

.makeup-info-box {
    background: var(--md-bg-primary);
    padding: var(--spacing-md);
    border-radius: 0;
    border-left: 3px solid var(--md-blue);
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    line-height: 1.5;
}

.no-students-message {
    text-align: center;
    padding: var(--spacing-2xl);
    color: var(--text-secondary);
}


/* 面談申込フォーム */
.special-form-title.meeting {
    color: var(--md-purple);
}

.special-form-select.meeting:focus {
    border-color: var(--md-purple);
}

.special-form-submit.meeting {
    background: var(--md-purple);
    color: white;
}

input[type="datetime-local"].special-form-select {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid var(--md-gray-5);
    border-radius: 0;
    font-size: var(--text-subhead);
    background: var(--md-bg-primary);
    color: var(--text-primary);
}

input[type="datetime-local"].special-form-select.meeting:focus {
    border-color: var(--md-purple);
    border-radius: 0;
    outline: none;
}
</style>

<?php if (!empty($students)): ?>
<div class="chat-wrapper role-guardian">
    <!-- モバイル用ヘッダー -->
    <div class="chat-mobile-header">
        <a href="dashboard.php" class="chat-back-btn">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div class="chat-mobile-title">チャット</div>
    </div>

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

    <!-- 欠席連絡フォーム -->
    <div class="special-form-area" id="absenceFormArea">
        <div class="special-form-header">
            <div class="special-form-title absence"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event_busy</span> 欠席連絡</div>
            <button type="button" class="special-form-close" onclick="closeAbsenceForm()">閉じる</button>
        </div>
        <select class="special-form-select absence" id="absenceDate">
            <option value="">欠席する日を選択してください</option>
            <?php foreach ($scheduledDays as $day): ?>
                <option value="<?= $day['date'] ?>"><?= htmlspecialchars($day['display']) ?></option>
            <?php endforeach; ?>
        </select>
        <textarea class="special-form-textarea" id="absenceReason" placeholder="欠席理由（任意）&#10;例：体調不良のため"></textarea>

        <!-- 振替日選択 -->
        <div class="makeup-section">
            <div class="makeup-section-header">
                <span class="makeup-section-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">sync</span></span>
                <div>
                    <div class="makeup-section-title">
                        振替日を選択してください <span class="makeup-required">*</span>
                    </div>
                    <div class="makeup-section-subtitle">欠席した分の授業を振替できます</div>
                </div>
            </div>

            <div class="makeup-section-inner">
                <label style="display: block; margin-bottom: 8px; font-weight: 600;">振替希望日</label>
                <select class="special-form-select absence" id="makeupOption" onchange="handleMakeupOptionChange()" style="margin-bottom: 10px;">
                    <option value="">選択してください</option>
                    <option value="decide_later"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 後日決める（イベント等で振替予定）</option>
                    <option value="choose_date">📆 今すぐ日にちを決める</option>
                </select>

                <div id="makeupDateSection" style="display: none; margin-top: var(--spacing-md);">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;">振替する日を選択</label>
                    <select class="special-form-select absence" id="makeupDate">
                        <option value="">日付を選択してください</option>
                        <?php
                        for ($i = 0; $i <= 60; $i++) {
                            $date = date('Y-m-d', strtotime("+$i days"));
                            $display = date('n月j日（', strtotime($date)) . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($date))] . '）';
                            echo "<option value=\"{$date}\">{$display}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="makeup-info-box">
                <div style="font-weight: 600; margin-bottom: 5px;"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> 振替のメリット</div>
                <div style="padding-left: 20px;">
                    • 欠席分の授業を無駄にしません<br>
                    • お子様の学習ペースを保てます<br>
                    • スタッフが承認後、すぐに予定に追加されます
                </div>
            </div>

            <div class="makeup-warning" id="makeupWarning"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span> 振替日の選択は必須です</div>
        </div>

        <button type="button" class="special-form-submit absence" onclick="sendAbsenceNotification()" id="sendAbsenceBtn">
            欠席連絡を送信
        </button>
    </div>

    <!-- イベント参加フォーム -->
    <div class="special-form-area" id="eventFormArea">
        <div class="special-form-header">
            <div class="special-form-title event"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">celebration</span> イベント参加申込</div>
            <button type="button" class="special-form-close" onclick="closeEventForm()">閉じる</button>
        </div>
        <select class="special-form-select event" id="eventSelect" onchange="showEventDetails()">
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
        <div class="event-details-box" id="eventDetails"></div>
        <textarea class="special-form-textarea" id="eventNotes" placeholder="備考（任意）&#10;例：アレルギー情報、送迎について等"></textarea>
        <button type="button" class="special-form-submit event" onclick="sendEventRegistration()" id="sendEventBtn">
            イベントに参加する
        </button>
    </div>

    <!-- 面談申込フォーム -->
    <div class="special-form-area" id="meetingFormArea">
        <div class="special-form-header">
            <div class="special-form-title meeting"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">calendar_month</span> 面談の申し込み</div>
            <button type="button" class="special-form-close" onclick="closeMeetingForm()">閉じる</button>
        </div>
        <div style="margin-bottom: var(--spacing-md);">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">面談の目的</label>
            <select class="special-form-select meeting" id="meetingPurpose">
                <option value="">選択してください</option>
                <option value="個別支援計画">個別支援計画について</option>
                <option value="モニタリング">モニタリングについて</option>
                <option value="進路相談">進路相談</option>
                <option value="学習相談">学習相談</option>
                <option value="生活相談">生活・行動について</option>
                <option value="その他">その他</option>
            </select>
        </div>
        <textarea class="special-form-textarea" id="meetingDetail" placeholder="面談で相談したい内容（任意）&#10;例：最近の様子について相談したい"></textarea>

        <div style="margin-bottom: var(--spacing-md);">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">希望日時（第1希望）<span style="color: var(--md-red);">*</span></label>
            <input type="datetime-local" class="special-form-select meeting" id="meetingDate1" required>
        </div>
        <div style="margin-bottom: var(--spacing-md);">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">希望日時（第2希望）</label>
            <input type="datetime-local" class="special-form-select meeting" id="meetingDate2">
        </div>
        <div style="margin-bottom: var(--spacing-md);">
            <label style="display: block; margin-bottom: 8px; font-weight: 600;">希望日時（第3希望）</label>
            <input type="datetime-local" class="special-form-select meeting" id="meetingDate3">
        </div>

        <button type="button" class="special-form-submit meeting" onclick="sendMeetingRequest()" id="sendMeetingBtn">
            面談を申し込む
        </button>
    </div>

    <!-- 入力エリア -->
    <div class="chat-input-area">
        <div class="message-type-selector">
            <select onchange="selectMessageType(this.value)">
                <option value="normal">💬 通常メッセージ</option>
                <option value="absence">📅 欠席連絡</option>
                <option value="event">🎉 イベント参加申込</option>
                <option value="meeting">📆 面談の申し込み</option>
            </select>
        </div>

        <div class="file-preview" id="filePreview">
            <div class="file-preview-info"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span> <span id="fileName"></span> (<span id="fileSize"></span>)</div>
            <button type="button" class="file-preview-remove" onclick="removeFile()">削除</button>
        </div>

        <form class="chat-input-form" onsubmit="sendMessage(event)" id="chatForm">
            <label for="fileInput" class="file-attach-btn" title="ファイルを添付"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span></label>
            <input type="file" id="fileInput" class="file-attach-input" onchange="handleFileSelect(event)" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            <textarea id="messageInput" class="chat-textarea" placeholder="メッセージを入力..." onkeydown="handleKeyDown(event)"></textarea>
            <button type="submit" class="chat-send-btn" id="sendBtn"><span class="material-symbols-outlined">send</span></button>
        </form>
    </div>
</div>
<?php else: ?>
    <div class="no-students-message">
        <h3>お子様の情報が登録されていません</h3>
        <p>スタッフまでお問い合わせください。</p>
    </div>
<?php endif; ?>

<?php
$roomIdJs = $roomId ? $roomId : 'null';
$studentIdJs = $selectedStudentId ? $selectedStudentId : 'null';
$studentNameJs = json_encode($selectedStudentName);
$inlineJs = <<<JS
const roomId = {$roomIdJs};
const studentId = {$studentIdJs};
const studentName = {$studentNameJs};
let isLoading = false;
let lastMessageId = 0;
let selectedFile = null;
const MAX_FILE_SIZE = 3 * 1024 * 1024;

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

// メッセージを読み込む
function loadMessages(forceScroll = false) {
    if (!roomId) return;

    fetch('chat_api.php?action=get_messages&room_id=' + roomId + '&last_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            const messagesArea = document.getElementById('messagesArea');

            // 初回ロード時は空状態を削除
            const emptyState = messagesArea.querySelector('.chat-empty-state');
            if (emptyState) emptyState.remove();

            if (data.success && data.messages.length > 0) {
                const shouldScroll = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;

                data.messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });

                if (shouldScroll || forceScroll) scrollToBottom();
            } else if (data.success && data.messages.length === 0 && lastMessageId === 0) {
                // メッセージが0件の場合
                messagesArea.innerHTML = '<div class="chat-empty-state"><div class="chat-empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span></div><h3>まだメッセージがありません</h3><p>下の入力欄からメッセージを送信してください</p></div>';
            } else if (!data.success) {
                console.error('API error:', data.message);
                messagesArea.innerHTML = '<div class="chat-empty-state"><div class="chat-empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span></div><h3>エラーが発生しました</h3><p>' + (data.message || '再読み込みしてください') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('メッセージの読み込みエラー:', error);
            const messagesArea = document.getElementById('messagesArea');
            messagesArea.innerHTML = '<div class="chat-empty-state"><div class="chat-empty-state-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">warning</span></div><h3>接続エラー</h3><p>ページを再読み込みしてください</p></div>';
        });
}

function appendMessage(msg) {
    const messagesArea = document.getElementById('messagesArea');
    const isOwn = msg.sender_type === 'guardian';
    const isAbsence = msg.message_type === 'absence_notification';
    const isEvent = msg.message_type === 'event_registration';
    const isMeetingRequest = msg.message_type === 'meeting_request';
    const isMeetingCounter = msg.message_type === 'meeting_counter';
    const isMeetingConfirmed = msg.message_type === 'meeting_confirmed';

    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + (isOwn ? 'sent' : 'received');

    let bubbleClass = 'message-bubble';
    if (isAbsence) bubbleClass += ' absence';
    if (isEvent) bubbleClass += ' event';
    if (isMeetingRequest || isMeetingCounter) bubbleClass += ' meeting';

    let html = '<div class="message-content">';
    if (!isOwn) {
        // スタッフからのメッセージにスタッフ名を表示
        html += '<div class="message-sender staff-sender">' + escapeHtml(msg.sender_name || 'スタッフ') + '</div>';
    }

    html += '<div class="' + bubbleClass + '">';
    if (msg.message) {
        html += escapeHtml(msg.message).replace(/\\n/g, '<br>');
    }
    // 面談予約リンクを追加
    if ((isMeetingRequest || isMeetingCounter) && msg.meeting_request_id) {
        html += '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(120, 82, 160, 0.3);">';
        html += '<a href="meeting_response.php?request_id=' + msg.meeting_request_id + '" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; background: var(--md-purple); color: white; text-decoration: none; border-radius: 0; font-weight: 600;">';
        html += '<span class="material-symbols-outlined" style="font-size: 18px;">calendar_month</span> 日程を回答する';
        html += '</a></div>';
    }
    if (msg.attachment_path) {
        html += '<div class="message-attachment"><a href="download_attachment.php?id=' + msg.id + '" target="_blank"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">attach_file</span> ' + escapeHtml(msg.attachment_original_name || 'ファイル') + '</a></div>';
    }
    html += '</div>';
    html += '<div class="message-time">' + formatDateTime(msg.created_at) + '</div>';
    html += '</div>';

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
    // 少し遅延させてDOM更新後にスクロール
    requestAnimationFrame(() => {
        setTimeout(() => {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }, 50);
    });
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

// メッセージタイプ選択
function selectMessageType(type) {
    document.getElementById('absenceFormArea').classList.remove('show');
    document.getElementById('eventFormArea').classList.remove('show');
    document.getElementById('meetingFormArea').classList.remove('show');
    document.getElementById('chatForm').style.display = 'flex';

    if (type === 'absence') {
        document.getElementById('absenceFormArea').classList.add('show');
        document.getElementById('chatForm').style.display = 'none';
    } else if (type === 'event') {
        document.getElementById('eventFormArea').classList.add('show');
        document.getElementById('chatForm').style.display = 'none';
    } else if (type === 'meeting') {
        document.getElementById('meetingFormArea').classList.add('show');
        document.getElementById('chatForm').style.display = 'none';
    }
}

function closeAbsenceForm() {
    document.querySelector('.message-type-selector select').value = 'normal';
    selectMessageType('normal');
}

function closeEventForm() {
    document.querySelector('.message-type-selector select').value = 'normal';
    selectMessageType('normal');
}

function closeMeetingForm() {
    document.querySelector('.message-type-selector select').value = 'normal';
    selectMessageType('normal');
}

function sendMeetingRequest() {
    // 二重送信防止
    if (isLoading) return;

    const purpose = document.getElementById('meetingPurpose').value;
    const detail = document.getElementById('meetingDetail').value;
    const date1 = document.getElementById('meetingDate1').value;
    const date2 = document.getElementById('meetingDate2').value;
    const date3 = document.getElementById('meetingDate3').value;

    if (!purpose) {
        alert('面談の目的を選択してください。');
        return;
    }
    if (!date1) {
        alert('第1希望の日時を入力してください。');
        return;
    }

    isLoading = true;
    const btn = document.getElementById('sendMeetingBtn');
    btn.disabled = true;
    btn.textContent = '送信中...';

    const formData = new FormData();
    formData.append('action', 'meeting_request');
    formData.append('room_id', roomId);
    formData.append('student_id', studentId);
    formData.append('purpose', purpose);
    formData.append('detail', detail);
    formData.append('date1', date1);
    formData.append('date2', date2);
    formData.append('date3', date3);

    fetch('chat_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // フォームをリセット
            document.getElementById('meetingPurpose').value = '';
            document.getElementById('meetingDetail').value = '';
            document.getElementById('meetingDate1').value = '';
            document.getElementById('meetingDate2').value = '';
            document.getElementById('meetingDate3').value = '';
            closeMeetingForm();
            loadMessages();
            alert('面談の申し込みを送信しました。スタッフからの回答をお待ちください。');
        } else {
            alert('エラー: ' + (data.message || '送信に失敗しました'));
        }
    })
    .catch(error => {
        alert('送信エラー: ' + error);
    })
    .finally(() => {
        isLoading = false;
        btn.disabled = false;
        btn.textContent = '面談を申し込む';
    });
}

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

    let html = '<strong>' + eventName + '</strong><br>';
    html += '日時: ' + dateStr + '(' + dayOfWeek + ')<br>';
    if (eventDesc) {
        html += '<div style="margin-top: 8px; color: var(--text-secondary);">' + escapeHtml(eventDesc) + '</div>';
    }

    detailsDiv.innerHTML = html;
    detailsDiv.classList.add('show');
}

function handleMakeupOptionChange() {
    const makeupOption = document.getElementById('makeupOption').value;
    const makeupDateSection = document.getElementById('makeupDateSection');
    const makeupWarning = document.getElementById('makeupWarning');

    makeupWarning.classList.remove('show');

    if (makeupOption === 'choose_date') {
        makeupDateSection.style.display = 'block';
    } else {
        makeupDateSection.style.display = 'none';
        document.getElementById('makeupDate').value = '';
    }
}

function sendAbsenceNotification() {
    const absenceDate = document.getElementById('absenceDate').value;
    const reason = document.getElementById('absenceReason').value.trim();
    const makeupOption = document.getElementById('makeupOption').value;
    const makeupDate = document.getElementById('makeupDate').value;
    const makeupWarning = document.getElementById('makeupWarning');

    if (!absenceDate) {
        alert('欠席する日を選択してください。');
        return;
    }

    if (!makeupOption) {
        makeupWarning.classList.add('show');
        document.getElementById('makeupOption').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (makeupOption === 'choose_date' && !makeupDate) {
        alert('振替する日を選択してください。');
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
    formData.append('student_id', studentId);
    formData.append('absence_date', absenceDate);
    formData.append('reason', reason);
    formData.append('makeup_option', makeupOption);

    if (makeupOption === 'decide_later') {
        formData.append('request_makeup', '1');
        formData.append('makeup_date', '');
    } else if (makeupOption === 'choose_date' && makeupDate) {
        formData.append('request_makeup', '1');
        formData.append('makeup_date', makeupDate);
    } else {
        formData.append('request_makeup', '0');
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
            document.getElementById('makeupOption').value = '';
            document.getElementById('makeupDate').value = '';
            document.getElementById('makeupDateSection').style.display = 'none';
            makeupWarning.classList.remove('show');
            selectMessageType('normal');
            alert('欠席連絡を送信しました。振替の承認をお待ちください。');
        } else {
            alert('送信エラー: ' + data.message);
        }
    })
    .catch(error => alert('送信エラー: ' + error))
    .finally(() => {
        isLoading = false;
        sendBtn.disabled = false;
        sendBtn.textContent = '欠席連絡を送信';
    });
}

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
    formData.append('student_id', studentId);
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
        } else {
            alert('送信エラー: ' + data.message);
        }
    })
    .catch(error => alert('送信エラー: ' + error))
    .finally(() => {
        isLoading = false;
        sendBtn.disabled = false;
        sendBtn.textContent = 'イベントに参加する';
    });
}

// リアルタイム更新
function checkNewMessages() {
    if (!roomId) return;

    fetch('chat_realtime.php?room_id=' + roomId + '&last_message_id=' + lastMessageId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_messages && data.new_messages.length > 0) {
                const messagesArea = document.getElementById('messagesArea');
                const shouldScroll = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;

                data.new_messages.forEach(msg => {
                    appendMessage(msg);
                    lastMessageId = Math.max(lastMessageId, msg.id);
                });

                if (shouldScroll) scrollToBottom();
            }
        })
        .catch(error => console.error('リアルタイム更新エラー:', error));
}

// 初期読み込み
if (roomId) {
    loadMessages(true); // 初回ロードは最下部にスクロール
    setInterval(checkNewMessages, 5000);
}

// モバイル用：bodyにクラスを追加（:has()セレクタのフォールバック）
document.body.classList.add('has-chat');
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
