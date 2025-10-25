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
                   AND scm.created_at > COALESCE(
                       (SELECT MAX(created_at)
                        FROM student_chat_messages
                        WHERE room_id = scr.id AND sender_type = 'staff'),
                       '1970-01-01'
                   )), 0
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
                   AND scm.created_at > COALESCE(
                       (SELECT MAX(created_at)
                        FROM student_chat_messages
                        WHERE room_id = scr.id AND sender_type = 'staff'),
                       '1970-01-01'
                   )), 0
            ) as unread_count
        FROM students s
        LEFT JOIN student_chat_rooms scr ON s.id = scr.student_id
        ORDER BY s.grade_level, s.student_name ASC
    ");
}

$allStudents = $stmt->fetchAll();

// 学部別に分類
$elementary = []; // 小学部
$junior = [];     // 中等部
$senior = [];     // 高等部

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒チャット一覧 - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: white;
            font-size: 24px;
        }

        .search-box {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .search-filters {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }

        .search-box select:focus {
            outline: none;
            border-color: #667eea;
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
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: background 0.2s;
        }

        .accordion-header:hover {
            background: #f8f9fa;
        }

        .accordion-header.active {
            background: #667eea;
            color: white;
        }

        .accordion-title {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .accordion-count {
            font-size: 14px;
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .room-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
            transition: background 0.2s;
            position: relative;
        }

        .room-item:hover {
            background: #f8f9fa;
        }

        .room-checkbox {
            margin-left: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .room-item.selected {
            background: #e3f2fd;
        }

        .room-item:last-child {
            border-bottom: none;
        }

        .room-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
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
            font-size: 13px;
            color: #999;
        }

        .room-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        .broadcast-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
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
            font-size: 14px;
            color: #333;
        }

        .broadcast-count {
            font-weight: 700;
            color: #667eea;
            font-size: 18px;
        }

        .broadcast-actions {
            display: flex;
            gap: 10px;
        }

        .btn-broadcast {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-broadcast:hover {
            background: #5568d3;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-cancel:hover {
            background: #5a6268;
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
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .file-input-wrapper {
            display: inline-block;
            position: relative;
            cursor: pointer;
        }

        .file-input-label {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 5px;
            display: inline-block;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-input-label:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
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
            color: #333;
        }

        .file-size {
            font-size: 12px;
            color: #999;
        }

        .remove-file-btn {
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
            transition: all 0.3s;
        }

        .dropdown-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 200px;
            margin-top: 5px;
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #f8f9fa;
        }

        .dropdown-menu a .menu-icon {
            margin-right: 8px;
        }

        .user-info {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-send {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-send:hover {
            background: #5568d3;
        }

        .btn-send:disabled {
            background: #ccc;
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
                font-size: 16px;
            }

            .broadcast-bar {
                flex-direction: column;
                gap: 10px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 生徒チャット</h1>
            <div class="user-info" id="userInfo">
                <!-- 保護者ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👨‍👩‍👧 保護者
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="chat.php">
                            <span class="menu-icon">💬</span>保護者チャット
                        </a>
                        <a href="submission_management.php">
                            <span class="menu-icon">📮</span>提出期限管理
                        </a>
                    </div>
                </div>

                <!-- 生徒ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🎓 生徒
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="student_chats.php">
                            <span class="menu-icon">💬</span>生徒チャット
                        </a>
                        <a href="student_weekly_plans.php">
                            <span class="menu-icon">📝</span>週間計画表
                        </a>
                    </div>
                </div>

                <!-- かけはし管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        🌉 かけはし管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="kakehashi_staff.php">
                            <span class="menu-icon">✏️</span>スタッフかけはし入力
                        </a>
                        <a href="kakehashi_guardian_view.php">
                            <span class="menu-icon">📋</span>保護者かけはし確認
                        </a>
                        <a href="kobetsu_plan.php">
                            <span class="menu-icon">📄</span>個別支援計画書作成
                        </a>
                        <a href="kobetsu_monitoring.php">
                            <span class="menu-icon">📊</span>モニタリング表作成
                        </a>
                        <a href="newsletter_create.php">
                            <span class="menu-icon">📰</span>施設通信を作成
                        </a>
                    </div>
                </div>

                <!-- マスタ管理ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        ⚙️ マスタ管理
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="students.php">
                            <span class="menu-icon">👥</span>生徒管理
                        </a>
                        <a href="guardians.php">
                            <span class="menu-icon">👨‍👩‍👧</span>保護者管理
                        </a>
                        <a href="holidays.php">
                            <span class="menu-icon">🗓️</span>休日管理
                        </a>
                        <a href="events.php">
                            <span class="menu-icon">🎉</span>イベント管理
                        </a>
                    </div>
                </div>

                <a href="renrakucho_activities.php" class="logout-btn">← 活動管理</a>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
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
                    <option value="elementary">小学部</option>
                    <option value="junior_high">中等部</option>
                    <option value="high_school">高等部</option>
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
            <!-- 小学部 -->
            <?php if (!empty($elementary)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎒 小学部</span>
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

            <!-- 中等部 -->
            <?php if (!empty($junior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>📚 中等部</span>
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

            <!-- 高等部 -->
            <?php if (!empty($senior)): ?>
            <div class="accordion">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title">
                        <span>🎓 高等部</span>
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

        // ドロップダウンメニューのトグル
        function toggleDropdown(event, button) {
            event.stopPropagation();
            const dropdown = button.closest('.dropdown');
            const isOpen = dropdown.classList.contains('open');

            // 他のドロップダウンを閉じる
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });

            // このドロップダウンをトグル
            if (!isOpen) {
                dropdown.classList.add('open');
            }
        }

        // ドロップダウン外をクリックしたら閉じる
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
            });
        });

        // ドロップダウン内のクリックで伝播を止める
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
