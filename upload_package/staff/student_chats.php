<?php
/**
 * スタッフ用 - 生徒チャット一覧
 */

// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

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
            COALESCE(
                (SELECT COUNT(*)
                 FROM student_chat_messages scm
                 WHERE scm.room_id = scr.id), 0
            ) as message_count,
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
        ORDER BY s.grade_level, s.student_name ASC
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
            COALESCE(
                (SELECT COUNT(*)
                 FROM student_chat_messages scm
                 WHERE scm.room_id = scr.id), 0
            ) as message_count,
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
        ORDER BY s.grade_level, s.student_name ASC
    ");
}

$allStudents = $stmt->fetchAll();

// 学部別に分類
$elementary = []; // 小学生
$junior = [];     // 中学生
$senior = [];     // 高校生

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

// ページ開始
$currentPage = 'student_chats';
$pageTitle = '生徒チャット一覧';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .search-box {
            background: linear-gradient(135deg, #2c2c2e 0%, #3a3a3c 100%);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .search-filters {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            width: 100%;
            padding: var(--spacing-md) 15px;
            border: 1px solid #4a4a4c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            background: var(--apple-gray-4);
            color: var(--text-primary);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .search-box select {
            padding: var(--spacing-md) 15px;
            border: 1px solid #4a4a4c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            background: var(--apple-gray-4);
            color: var(--text-primary);
            cursor: pointer;
            min-width: 150px;
        }

        .search-box select:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        @media (max-width: 768px) {
            .search-filters {
                grid-template-columns: 1fr;
            }
        }

        .accordion {
            margin-bottom: 15px;
        }

        .accordion-header {
            background: linear-gradient(135deg, #2c2c2e 0%, #3a3a3c 100%);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            transition: background 0.2s;
            color: var(--text-primary);
        }

        .accordion-header:hover {
            background: linear-gradient(135deg, #3a3a3c 0%, #4a4a4c 100%);
        }

        .accordion-header.active {
            background: var(--primary-purple);
            color: var(--text-primary);
        }

        .accordion-title {
            font-size: var(--text-callout);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .accordion-count {
            font-size: var(--text-subhead);
            opacity: 0.8;
        }

        .accordion-icon {
            transition: transform 0.3s;
        }

        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            margin-top: 10px;
        }

        .accordion-content.active {
            max-height: 2000px;
        }

        .room-list {
            background: var(--apple-bg-tertiary);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .room-item {
            padding: var(--spacing-lg);
            border-bottom: 1px solid #4a4a4c;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-primary);
            transition: background 0.2s;
            position: relative;
        }

        .room-item:hover {
            background: var(--apple-gray-4);
        }

        .room-checkbox {
            margin-left: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .room-item.selected {
            background: #3a3a5c;
        }

        .room-item:last-child {
            border-bottom: none;
        }

        .room-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-purple);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-title-2);
            flex-shrink: 0;
            margin-right: 15px;
        }

        .room-info {
            flex: 1;
        }

        .room-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .room-meta {
            font-size: var(--text-footnote);
            color: var(--text-secondary);
        }

        .room-badge {
            background: #e74c3c;
            color: var(--text-primary);
            padding: 4px 10px;
            border-radius: var(--radius-md);
            font-size: var(--text-caption-1);
            font-weight: 600;
            margin-left: 10px;
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: var(--spacing-lg);
        }

        .stat-card {
            background: linear-gradient(135deg, #2c2c2e 0%, #3a3a3c 100%);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-purple);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
        }

        .broadcast-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #2c2c2e 0%, #3a3a3c 100%);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
            padding: 15px 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
        }

        .broadcast-bar.active {
            display: flex;
        }

        .broadcast-info {
            font-size: var(--text-subhead);
            color: var(--text-primary);
        }

        .broadcast-count {
            font-weight: 700;
            color: var(--primary-purple);
            font-size: 18px;
        }

        .broadcast-actions {
            display: flex;
            gap: 10px;
        }

        .btn-broadcast {
            padding: var(--spacing-md) 20px;
            background: var(--primary-purple);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .btn-broadcast:hover {
            background: var(--primary-purple);
        }

        .btn-cancel {
            padding: var(--spacing-md) 20px;
            background: var(--apple-gray);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
        }

        .btn-cancel:hover {
            background: var(--apple-gray);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #2c2c2e 0%, #3a3a3c 100%);
            border-radius: var(--radius-lg);
            padding: var(--spacing-2xl);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: var(--spacing-lg);
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid #4a4a4c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            resize: vertical;
            min-height: 100px;
            background: var(--apple-gray-4);
            color: var(--text-primary);
        }

        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .file-input-wrapper {
            display: inline-block;
            position: relative;
            cursor: pointer;
        }

        .file-input-label {
            padding: var(--spacing-md) 20px;
            background: var(--apple-gray-4);
            border: 2px dashed #4a4a4c;
            border-radius: var(--radius-sm);
            display: inline-block;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
            color: var(--text-primary);
        }

        .file-input-label:hover {
            background: #4a4a4c;
            border-color: var(--primary-purple);
        }

        .file-preview {
            margin-top: 10px;
            padding: var(--spacing-md);
            background: var(--apple-gray-4);
            border-radius: var(--radius-sm);
            display: none;
            align-items: center;
            gap: 10px;
        }

        .file-preview.show {
            display: flex;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .file-size {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
        }

        .remove-file-btn {
            padding: 5px 10px;
            background: #e74c3c;
            color: var(--text-primary);
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: var(--text-caption-1);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        .btn-send {
            padding: var(--spacing-md) 30px;
            background: var(--primary-purple);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .btn-send:hover {
            background: var(--primary-purple);
        }

        .btn-send:disabled {
            background: var(--apple-gray-4);
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .room-item {
                padding: 15px;
            }

            .room-avatar {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .room-name {
                font-size: var(--text-callout);
            }

            .broadcast-bar {
                flex-direction: column;
                gap: 10px;
            }

            .modal-content {
                padding: var(--spacing-lg);
            }
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">生徒チャット</h1>
        <p class="page-subtitle">生徒との直接メッセージ一覧</p>
    </div>
    <div class="page-header-actions">
        <a href="renrakucho_activities.php" class="btn btn-secondary">← 活動管理</a>
    </div>
</div>

        <?php
        $totalStudents = count($allStudents);
        $totalUnread = array_sum(array_column($allStudents, 'unread_count'));
        $activeChats = count(array_filter($allStudents, function($s) { return $s['message_count'] > 0; }));
        ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalStudents; ?></div>
                <div class="stat-label">生徒数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $activeChats; ?></div>
                <div class="stat-label">チャット有り</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUnread; ?></div>
                <div class="stat-label">未読メッセージ</div>
            </div>
        </div>

        <!-- 検索ボックス -->
        <div class="search-box">
            <div class="search-filters">
                <input type="text" id="searchInput" placeholder="🔍 生徒名で検索..." onkeyup="filterStudents()">
                <select id="gradeLevelFilter" onchange="filterStudents()">
                    <option value="">すべての学年</option>
                    <option value="elementary">小学生</option>
                    <option value="junior_high">中学生</option>
                    <option value="high_school">高校生</option>
                </select>
                <select id="statusFilter" onchange="filterStudents()">
                    <option value="">すべての状態</option>
                    <option value="active">在籍</option>
                    <option value="trial">体験</option>
                    <option value="short_term">短期利用</option>
                    <option value="withdrawn">退所</option>
                </select>
            </div>
        </div>

        <?php if (empty($allStudents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>生徒がいません</p>
            </div>
        <?php else: ?>
            <!-- 小学生 -->
            <?php if (!empty($elementary)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎒 小学生</span>
                        <span class="accordion-count">(<?php echo count($elementary); ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="room-list">
                        <?php foreach ($elementary as $student): ?>
                            <div class="room-item" data-student-id="<?php echo $student['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>" data-grade-level="elementary" data-is-active="<?php echo $student['is_active'] ?? 1; ?>" data-status="<?php echo htmlspecialchars($student['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>" onclick="handleStudentClick(event, <?php echo $student['student_id']; ?>)">
                                <div class="room-avatar">🎓</div>
                                <div class="room-info">
                                    <div class="room-name">
                                        <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($student['unread_count'] > 0): ?>
                                            <span class="room-badge"><?php echo $student['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="room-meta">
                                        <?php if ($student['last_message_at']): ?>
                                            最終メッセージ: <?php echo date('Y/m/d H:i', strtotime($student['last_message_at'])); ?>
                                        <?php else: ?>
                                            メッセージなし
                                        <?php endif; ?>
                                        （<?php echo $student['message_count']; ?>件）
                                    </div>
                                </div>
                                <input type="checkbox" class="room-checkbox" data-student-id="<?php echo $student['student_id']; ?>" onclick="event.stopPropagation(); toggleStudentSelection(<?php echo $student['student_id']; ?>)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 中学生 -->
            <?php if (!empty($junior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>📚 中学生</span>
                        <span class="accordion-count">(<?php echo count($junior); ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="room-list">
                        <?php foreach ($junior as $student): ?>
                            <div class="room-item" data-student-id="<?php echo $student['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>" data-grade-level="junior_high" data-is-active="<?php echo $student['is_active'] ?? 1; ?>" data-status="<?php echo htmlspecialchars($student['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>" onclick="handleStudentClick(event, <?php echo $student['student_id']; ?>)">
                                <div class="room-avatar">🎓</div>
                                <div class="room-info">
                                    <div class="room-name">
                                        <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($student['unread_count'] > 0): ?>
                                            <span class="room-badge"><?php echo $student['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="room-meta">
                                        <?php if ($student['last_message_at']): ?>
                                            最終メッセージ: <?php echo date('Y/m/d H:i', strtotime($student['last_message_at'])); ?>
                                        <?php else: ?>
                                            メッセージなし
                                        <?php endif; ?>
                                        （<?php echo $student['message_count']; ?>件）
                                    </div>
                                </div>
                                <input type="checkbox" class="room-checkbox" data-student-id="<?php echo $student['student_id']; ?>" onclick="event.stopPropagation(); toggleStudentSelection(<?php echo $student['student_id']; ?>)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 高校生 -->
            <?php if (!empty($senior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎓 高校生</span>
                        <span class="accordion-count">(<?php echo count($senior); ?>名)</span>
                    </div>
                    <span class="accordion-icon">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="room-list">
                        <?php foreach ($senior as $student): ?>
                            <div class="room-item" data-student-id="<?php echo $student['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>" data-grade-level="high_school" data-is-active="<?php echo $student['is_active'] ?? 1; ?>" data-status="<?php echo htmlspecialchars($student['status'] ?? 'active', ENT_QUOTES, 'UTF-8'); ?>" onclick="handleStudentClick(event, <?php echo $student['student_id']; ?>)">
                                <div class="room-avatar">🎓</div>
                                <div class="room-info">
                                    <div class="room-name">
                                        <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($student['unread_count'] > 0): ?>
                                            <span class="room-badge"><?php echo $student['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="room-meta">
                                        <?php if ($student['last_message_at']): ?>
                                            最終メッセージ: <?php echo date('Y/m/d H:i', strtotime($student['last_message_at'])); ?>
                                        <?php else: ?>
                                            メッセージなし
                                        <?php endif; ?>
                                        （<?php echo $student['message_count']; ?>件）
                                    </div>
                                </div>
                                <input type="checkbox" class="room-checkbox" data-student-id="<?php echo $student['student_id']; ?>" onclick="event.stopPropagation(); toggleStudentSelection(<?php echo $student['student_id']; ?>)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 一斉送信バー -->
    <div id="broadcastBar" class="broadcast-bar">
        <div class="broadcast-info">
            <span class="broadcast-count" id="selectedCount">0</span>名の生徒を選択中
        </div>
        <div class="broadcast-actions">
            <button class="btn-cancel" onclick="clearSelection()">選択解除</button>
            <button class="btn-broadcast" onclick="openBroadcastModal()">📤 一斉送信</button>
        </div>
    </div>

    <!-- 一斉送信モーダル -->
    <div id="broadcastModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">📤 一斉メッセージ送信</div>
            <form id="broadcastForm" onsubmit="sendBroadcast(event)">
                <div class="form-group">
                    <label>送信先: <span id="recipientsList"></span></label>
                </div>
                <div class="form-group">
                    <label for="broadcastMessage">メッセージ *</label>
                    <textarea id="broadcastMessage" name="message" required placeholder="送信するメッセージを入力してください..."></textarea>
                </div>
                <div class="form-group">
                    <label>ファイル添付（任意、最大3MB）</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="broadcastFileInput" name="attachment" style="display: none;" onchange="handleBroadcastFileSelect(event)" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <label for="broadcastFileInput" class="file-input-label">
                            📎 ファイルを選択
                        </label>
                    </div>
                    <div id="broadcastFilePreview" class="file-preview">
                        <div class="file-info">
                            <div class="file-name" id="broadcastFileName"></div>
                            <div class="file-size" id="broadcastFileSize"></div>
                        </div>
                        <button type="button" class="remove-file-btn" onclick="removeBroadcastFile()">削除</button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeBroadcastModal()">キャンセル</button>
                    <button type="submit" class="btn-send" id="sendBtn">送信する</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // アコーディオンの開閉
        function toggleAccordion(header) {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');

            // すべてのアコーディオンを閉じる（オプション：1つだけ開く場合）
            // document.querySelectorAll('.accordion-header').forEach(h => h.classList.remove('active'));
            // document.querySelectorAll('.accordion-content').forEach(c => c.classList.remove('active'));

            if (isActive) {
                header.classList.remove('active');
                content.classList.remove('active');
            } else {
                header.classList.add('active');
                content.classList.add('active');
            }
        }

        // 検索フィルター
        function filterStudents() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const gradeLevelFilter = document.getElementById('gradeLevelFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const allItems = document.querySelectorAll('.room-item');

            allItems.forEach(item => {
                const studentName = item.getAttribute('data-student-name').toLowerCase();
                const gradeLevel = item.getAttribute('data-grade-level');
                const status = item.getAttribute('data-status');

                // 各フィルター条件をチェック
                let matchName = !searchText || studentName.includes(searchText);
                let matchGrade = !gradeLevelFilter || gradeLevel === gradeLevelFilter;
                let matchStatus = !statusFilter || status === statusFilter;

                // すべての条件が一致する場合のみ表示
                if (matchName && matchGrade && matchStatus) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });

            // フィルターが適用されている場合は全アコーディオンを開く
            if (searchText.length > 0 || gradeLevelFilter || statusFilter) {
                document.querySelectorAll('.accordion-header').forEach(h => h.classList.add('active'));
                document.querySelectorAll('.accordion-content').forEach(c => c.classList.add('active'));
            }
        }

        // ページロード時にすべてのアコーディオンを開く
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.accordion-header').forEach(header => {
                header.classList.add('active');
            });
            document.querySelectorAll('.accordion-content').forEach(content => {
                content.classList.add('active');
            });
        });

        // 一斉送信機能
        let selectedStudents = new Set();
        let selectedFile = null;
        const MAX_FILE_SIZE = 3 * 1024 * 1024; // 3MB

        // 生徒選択のトグル
        function toggleStudentSelection(studentId) {
            const checkbox = document.querySelector(`.room-checkbox[data-student-id="${studentId}"]`);
            const roomItem = checkbox.closest('.room-item');

            if (selectedStudents.has(studentId)) {
                selectedStudents.delete(studentId);
                checkbox.checked = false;
                roomItem.classList.remove('selected');
            } else {
                selectedStudents.add(studentId);
                checkbox.checked = true;
                roomItem.classList.add('selected');
            }

            updateSelectionUI();
        }

        // 生徒アイテムクリック処理
        function handleStudentClick(event, studentId) {
            // チェックボックスがクリックされた場合は何もしない
            if (event.target.classList.contains('room-checkbox')) {
                return;
            }

            // 選択モード中でなければ詳細ページへ遷移
            if (selectedStudents.size === 0) {
                window.location.href = `student_chat_detail.php?student_id=${studentId}`;
            }
        }

        // 選択状態のUI更新
        function updateSelectionUI() {
            const count = selectedStudents.size;
            document.getElementById('selectedCount').textContent = count;

            const broadcastBar = document.getElementById('broadcastBar');
            if (count > 0) {
                broadcastBar.classList.add('active');
            } else {
                broadcastBar.classList.remove('active');
            }
        }

        // 選択解除
        function clearSelection() {
            selectedStudents.clear();
            document.querySelectorAll('.room-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.room-item').forEach(item => item.classList.remove('selected'));
            updateSelectionUI();
        }

        // 一斉送信モーダルを開く
        function openBroadcastModal() {
            if (selectedStudents.size === 0) {
                alert('送信先の生徒を選択してください');
                return;
            }

            // 送信先リストを作成
            const recipientNames = Array.from(selectedStudents).map(id => {
                const item = document.querySelector(`.room-item[data-student-id="${id}"]`);
                return item.getAttribute('data-student-name');
            });
            document.getElementById('recipientsList').textContent = recipientNames.join('、');

            document.getElementById('broadcastModal').classList.add('active');
        }

        // モーダルを閉じる
        function closeBroadcastModal() {
            document.getElementById('broadcastModal').classList.remove('active');
            document.getElementById('broadcastForm').reset();
            removeBroadcastFile();
        }

        // ファイル選択処理
        function handleBroadcastFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (file.size > MAX_FILE_SIZE) {
                alert('ファイルサイズは3MB以下にしてください。');
                event.target.value = '';
                return;
            }

            selectedFile = file;
            document.getElementById('broadcastFileName').textContent = file.name;
            document.getElementById('broadcastFileSize').textContent = formatFileSize(file.size);
            document.getElementById('broadcastFilePreview').classList.add('show');
        }

        // ファイル削除
        function removeBroadcastFile() {
            selectedFile = null;
            document.getElementById('broadcastFileInput').value = '';
            document.getElementById('broadcastFilePreview').classList.remove('show');
        }

        // ファイルサイズのフォーマット
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        // 一斉送信処理
        async function sendBroadcast(event) {
            event.preventDefault();

            const message = document.getElementById('broadcastMessage').value.trim();
            if (!message) {
                alert('メッセージを入力してください');
                return;
            }

            if (selectedStudents.size === 0) {
                alert('送信先の生徒を選択してください');
                return;
            }

            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.textContent = '送信中...';

            try {
                const formData = new FormData();
                formData.append('student_ids', Array.from(selectedStudents).join(','));
                formData.append('message', message);
                if (selectedFile) {
                    formData.append('attachment', selectedFile);
                }

                const response = await fetch('student_chat_broadcast.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(`${result.sent_count}名の生徒にメッセージを送信しました`);
                    closeBroadcastModal();
                    clearSelection();
                } else {
                    alert('送信に失敗しました: ' + (result.error || '不明なエラー'));
                }
            } catch (error) {
                console.error('送信エラー:', error);
                alert('送信中にエラーが発生しました');
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = '送信する';
            }
        }
    </script>

<?php renderPageEnd(); ?>
