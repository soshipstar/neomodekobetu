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

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 部門フィルター
$departmentFilter = $_GET['department'] ?? '';

// 自分の教室の生徒を取得（チャットルームがなくても表示）
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
    LEFT JOIN classrooms cl ON u.classroom_id = cl.id
    LEFT JOIN chat_rooms cr ON s.id = cr.student_id AND s.guardian_id = cr.guardian_id
    WHERE s.is_active = 1
";

$params = [];

// 教室フィルタリング
if ($classroomId) {
    $sql .= " AND u.classroom_id = ?";
    $params[] = $classroomId;
}

if ($departmentFilter) {
    // grade_levelの値に変換
    $gradeMapping = [
        '小学生' => 'elementary',
        '中学生' => 'junior_high',
        '高校生' => 'high_school'
    ];
    if (isset($gradeMapping[$departmentFilter])) {
        $sql .= " AND s.grade_level = ?";
        $params[] = $gradeMapping[$departmentFilter];
    }
}

// 会話順（最新メッセージ順）にソート - NULLは最後に表示
$sql .= " ORDER BY CASE WHEN cr.last_message_at IS NULL THEN 1 ELSE 0 END, cr.last_message_at DESC, s.student_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

// 選択された生徒IDまたはルームID
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedRoomId = $_GET['room_id'] ?? null;

// 選択された生徒の情報
$selectedStudent = null;
$selectedRoom = null;

if ($selectedStudentId) {
    foreach ($allStudents as $student) {
        if ($student['student_id'] == $selectedStudentId) {
            $selectedStudent = $student;
            $selectedRoomId = $student['room_id'];
            break;
        }
    }
} elseif (!$selectedStudentId && !empty($allStudents)) {
    // 何も選択されていない場合は最初の生徒を選択
    $selectedStudent = $allStudents[0];
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
    <link rel="stylesheet" href="/assets/css/apple-design.css">
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
            background: #000000;
            min-height: 100vh;
            padding: var(--spacing-md);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #1c1c1e;
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 40px);
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: var(--spacing-lg) 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: var(--spacing-sm);
            background: rgba(255,255,255,0.2);
            border-radius: var(--radius-sm);
            transition: all var(--duration-normal) var(--ease-out);
        }

        .hamburger:hover {
            background: rgba(255,255,255,0.3);
        }

        .hamburger span {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .header h1 {
            font-size: var(--text-title-2);
            font-weight: 600;
            color: white;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: var(--spacing-sm) 16px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.2);
            transition: all var(--duration-normal) var(--ease-out);
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
            border-right: 1px solid #2c2c2e;
            background: #2c2c2e;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .room-item {
            padding: 15px 20px;
            border-bottom: 1px solid #3a3a3c;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .room-item:hover {
            background: #3a3a3c;
        }

        .room-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
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
            color: #f5f5f7;
        }

        .unread-badge {
            background: #ff3b30;
            color: white;
            padding: 2px 8px;
            border-radius: var(--radius-md);
            font-size: 11px;
            font-weight: 600;
        }

        .guardian-name {
            font-size: var(--text-footnote);
            color: #98989d;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 15px 20px;
            background: #2c2c2e;
            border-bottom: 1px solid #3a3a3c;
        }

        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #f5f5f7;
        }

        .chat-subtitle {
            font-size: var(--text-footnote);
            color: #98989d;
            margin-top: 2px;
        }

        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-lg);
            background: #1c1c1e;
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
            padding: var(--spacing-md) 16px;
            border-radius: 18px;
            word-wrap: break-word;
            line-height: 1.5;
        }

        .message.received .message-bubble {
            background: #2c2c2e;
            color: #f5f5f7;
            border-bottom-left-radius: 4px;
        }

        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-info {
            font-size: 11px;
            color: #98989d;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.sent .message-info {
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

        .sender-name {
            font-weight: 600;
            font-size: var(--text-caption-1);
            margin-bottom: 4px;
            color: #bf5af2;
        }

        .input-area {
            padding: var(--spacing-lg);
            background: #2c2c2e;
            border-top: 1px solid #3a3a3c;
        }

        .input-form {
            display: flex;
            gap: 10px;
        }

        .input-form textarea {
            flex: 1;
            padding: var(--spacing-md);
            border: 2px solid #3a3a3c;
            border-radius: var(--radius-md);
            font-size: 15px;
            resize: none;
            font-family: inherit;
            min-height: 50px;
            max-height: 150px;
            background: #1c1c1e;
            color: #f5f5f7;
        }

        .input-form textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .send-btn {
            padding: var(--spacing-md) 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
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
            color: #98989d;
        }

        .filter-section {
            padding: 15px;
            background: #1c1c1e;
            border-bottom: 2px solid #3a3a3c;
        }

        .department-filter {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid #3a3a3c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            background: #2c2c2e;
            color: #f5f5f7;
            cursor: pointer;
        }

        .search-input {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid #3a3a3c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            background: #1c1c1e;
            color: #f5f5f7;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-input::placeholder {
            color: #636366;
        }

        .accordion {
            margin-bottom: var(--spacing-md);
        }

        .accordion-header {
            padding: var(--spacing-md) 15px;
            background: #3a3a3c;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
            border-bottom: 1px solid #48484a;
        }

        .accordion-header:hover {
            background: #48484a;
        }

        .accordion-header.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .accordion-title {
            font-size: var(--text-subhead);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #f5f5f7;
        }

        .accordion-header.active .accordion-title {
            color: white;
        }

        .accordion-count {
            font-size: var(--text-caption-1);
            opacity: 0.8;
        }

        .accordion-icon {
            transition: transform 0.3s;
            font-size: var(--text-caption-1);
        }

        .accordion-header.active .accordion-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .accordion-content.active {
            max-height: 2000px;
        }

        .department-badge {
            display: inline-block;
            padding: 2px 8px;
            background: var(--primary-purple);
            color: white;
            border-radius: var(--radius-md);
            font-size: 10px;
            margin-left: 5px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-label {
            padding: var(--spacing-md) 20px;
            background: #1c1c1e;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
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
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md);
            background: #3a3a3c;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            color: #f5f5f7;
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
            background: var(--apple-red);
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: var(--text-caption-1);
        }

        /* デスクトップ用レイアウト（デフォルト） */
        @media (min-width: 769px) {
            .hamburger {
                display: none !important;
            }

            .user-info {
                display: flex !important;
            }

            .rooms-sidebar {
                position: static !important;
                transform: none !important;
            }

            .sidebar-overlay {
                display: none !important;
            }
        }

        /* レスポンシブデザイン */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .container {
                border-radius: 0;
                height: 100vh;
            }

            .header {
                padding: 15px 20px;
            }

            .header h1 {
                font-size: 18px;
            }

            .hamburger {
                display: flex;
            }

            .user-info {
                display: none !important;
            }

            .rooms-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100%;
                z-index: 999;
                transform: translateX(-100%);
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }

            .rooms-sidebar.active {
                transform: translateX(0);
            }

            .chat-container {
                width: 100%;
            }

            .chat-header {
                padding: var(--spacing-md) 15px;
            }

            .chat-title {
                font-size: var(--text-callout);
            }

            .messages-area {
                padding: 15px;
            }

            .message-bubble {
                max-width: 75%;
                font-size: var(--text-subhead);
            }

            .input-area {
                padding: 15px;
            }

            .input-form textarea {
                font-size: var(--text-subhead);
            }

            .send-btn {
                padding: var(--spacing-md) 20px;
                font-size: var(--text-subhead);
            }

            .file-input-label {
                padding: var(--spacing-md) 15px;
                font-size: var(--text-subhead);
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: var(--text-callout);
            }

            .rooms-sidebar {
                width: 240px;
            }

            .input-form {
                flex-direction: column;
            }

            .send-btn {
                width: 100%;
            }

            .message-bubble {
                max-width: 85%;
            }
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: var(--spacing-sm) 12px;
            background: rgba(255,255,255,0.2);
            border-radius: var(--radius-sm);
            color: inherit;
            text-decoration: none;
            font-size: var(--text-footnote);
            transition: all var(--duration-fast) var(--ease-out);
        }

        .message.received .attachment-link {
            background: rgba(102, 126, 234, 0.2);
            color: #bf5af2;
        }

        .attachment-link:hover {
            opacity: 0.8;
        }

        /* 提出期限設定モーダル */
        .submission-modal {
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

        .submission-modal.active {
            display: flex;
        }

        .submission-modal-content {
            background: #2c2c2e;
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
        }

        .submission-modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: var(--spacing-lg);
            color: #f5f5f7;
        }

        .submission-form-group {
            margin-bottom: var(--spacing-lg);
        }

        .submission-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #f5f5f7;
        }

        .submission-form-group input,
        .submission-form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid #3a3a3c;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            background: #1c1c1e;
            color: #f5f5f7;
        }

        .submission-form-group input:focus,
        .submission-form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .submission-form-group input::placeholder,
        .submission-form-group textarea::placeholder {
            color: #636366;
        }

        .submission-form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .submission-modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        .btn-submission {
            padding: var(--spacing-md) 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .btn-submission-cancel {
            background: var(--apple-gray);
            color: white;
        }

        .btn-submission-cancel:hover {
            background: var(--apple-gray);
        }

        .btn-submission-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-submission-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .submission-btn {
            padding: var(--spacing-md) 20px;
            background: #ff9800;
            color: white;
            border: 2px solid #ff9800;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .submission-btn:hover {
            background: #f57c00;
            border-color: #f57c00;
        }

        /* ドロップダウンメニュー */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: var(--spacing-sm) 16px;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            font-family: inherit;
            transition: all var(--duration-normal) var(--ease-out);
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
            background: #2c2c2e;
            border-radius: var(--radius-sm);
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            min-width: 200px;
            margin-top: 5px;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid #3a3a3c;
        }

        .dropdown.open .dropdown-menu {
            display: block;
        }

        .dropdown-menu a {
            display: block;
            padding: var(--spacing-md) 20px;
            color: #f5f5f7;
            text-decoration: none;
            transition: background 0.2s;
            border-bottom: 1px solid #3a3a3c;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background: #3a3a3c;
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
            padding: var(--spacing-sm) 16px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.2);
            transition: all var(--duration-normal) var(--ease-out);
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
    <!-- サイドバーオーバーレイ -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <h1>💬 保護者チャット</h1>
            </div>
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

                <!-- ユーザー設定ドロップダウン -->
                <div class="dropdown">
                    <button class="dropdown-toggle" onclick="toggleDropdown(event, this)">
                        👤 アカウント
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php">
                            <span class="menu-icon">👤</span>プロフィール編集
                        </a>
                        <a href="/logout.php">
                            <span class="menu-icon">🚪</span>ログアウト
                        </a>
                    </div>
                </div>

                <a href="renrakucho_activities.php" class="logout-btn">← 活動管理</a>
            </div>
        </div>

        <div class="main-content">
            <!-- 生徒一覧サイドバー -->
            <div class="rooms-sidebar" id="roomsSidebar">
                <!-- 一斉送信ボタン -->
                <div class="filter-section">
                    <button onclick="openBroadcastModal()" style="width: 100%; padding: var(--spacing-md); background: #30d158; color: white; border: none; border-radius: var(--radius-sm); font-size: var(--text-subhead); font-weight: 600; cursor: pointer; margin-bottom: var(--spacing-md);">
                        📢 一斉送信
                    </button>
                </div>

                <!-- 検索ボックス -->
                <div class="filter-section">
                    <input type="text" id="searchInput" class="search-input" placeholder="🔍 生徒名・保護者名で検索..." onkeyup="filterStudents()">
                </div>

                <!-- 生徒一覧（学部別アコーディオン） -->
                <?php if (empty($allStudents)): ?>
                    <div class="empty-state">
                        <p>生徒がいません</p>
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
                                <div class="room-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                                     data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                                     data-guardian-name="<?= htmlspecialchars($student['guardian_name'] ?? '') ?>"
                                     onclick="location.href='chat.php?student_id=<?= $student['student_id'] ?>'">
                                    <div class="room-item-header">
                                        <div class="student-name">
                                            <?= htmlspecialchars($student['student_name']) ?>さん
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
                                <div class="room-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                                     data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                                     data-guardian-name="<?= htmlspecialchars($student['guardian_name'] ?? '') ?>"
                                     onclick="location.href='chat.php?student_id=<?= $student['student_id'] ?>'">
                                    <div class="room-item-header">
                                        <div class="student-name">
                                            <?= htmlspecialchars($student['student_name']) ?>さん
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
                                <div class="room-item <?= $selectedStudentId == $student['student_id'] ? 'active' : '' ?>"
                                     data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                                     data-guardian-name="<?= htmlspecialchars($student['guardian_name'] ?? '') ?>"
                                     onclick="location.href='chat.php?student_id=<?= $student['student_id'] ?>'">
                                    <div class="room-item-header">
                                        <div class="student-name">
                                            <?= htmlspecialchars($student['student_name']) ?>さん
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
                        </div>
                    </div>
                    <?php endif; ?>
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
                            <button type="button" class="submission-btn" onclick="openSubmissionModal()">
                                📋 提出期限を設定
                            </button>
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

    <!-- 提出期限設定モーダル -->
    <div id="submissionModal" class="submission-modal">
        <div class="submission-modal-content">
            <h3 class="submission-modal-header">📋 提出期限の設定</h3>
            <form id="submissionForm" onsubmit="submitSubmissionRequest(event)">
                <div class="submission-form-group">
                    <label>提出物タイトル *</label>
                    <input type="text" id="submissionTitle" required placeholder="例: 学校の健康診断結果">
                </div>
                <div class="submission-form-group">
                    <label>詳細説明</label>
                    <textarea id="submissionDescription" placeholder="提出物の詳細を入力してください"></textarea>
                </div>
                <div class="submission-form-group">
                    <label>提出期限 *</label>
                    <input type="date" id="submissionDueDate" required>
                </div>
                <div class="submission-form-group">
                    <label>参考資料の添付（任意）</label>
                    <input type="file" id="submissionAttachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                    <div style="font-size: var(--text-caption-1); color: #98989d; margin-top: 5px;">
                        最大3MBまで（画像・PDF・Word・Excel・テキスト）
                    </div>
                    <div id="submissionFilePreview" style="display: none; margin-top: 10px; padding: var(--spacing-md); background: #3a3a3c; border-radius: var(--radius-sm); font-size: var(--text-footnote); color: #f5f5f7;">
                        📎 <span id="submissionFileName"></span> (<span id="submissionFileSize"></span>)
                        <button type="button" onclick="removeSubmissionFile()" style="margin-left: 10px; padding: 2px 8px; background: #ff3b30; color: white; border: none; border-radius: 3px; cursor: pointer;">削除</button>
                    </div>
                </div>
                <div class="submission-modal-footer">
                    <button type="button" class="btn-submission btn-submission-cancel" onclick="closeSubmissionModal()">キャンセル</button>
                    <button type="submit" class="btn-submission btn-submission-submit">設定して送信</button>
                </div>
            </form>
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
            messageDiv.dataset.messageId = msg.id;

            let html = '<div>';
            if (!isOwn) {
                html += `<div class="sender-name">${escapeHtml(msg.sender_name || '保護者')}</div>`;
            }

            // 欠席連絡・イベント参加メッセージの場合は特別なスタイル
            const isEvent = msg.message_type === 'event_registration';
            if (isAbsence) {
                html += `<div class="message-bubble" style="background: #ffe6e6; border-left: 4px solid #ff6b35; color: #333; font-weight: 500; white-space: normal; word-wrap: break-word;">`;
            } else if (isEvent) {
                html += `<div class="message-bubble" style="background: #e6f2ff; border-left: 4px solid #2563eb; color: #333; font-weight: 500; white-space: normal; word-wrap: break-word;">`;
            } else {
                html += `<div class="message-bubble">`;
            }

            if (msg.message) {
                // 欠席連絡・イベント参加も改行を<br>に変換
                html += escapeHtml(msg.message).replace(/\n/g, '<br>');
            }
            if (msg.attachment_path) {
                html += `<a href="download_attachment.php?id=${msg.id}" class="attachment-link" target="_blank">`;
                html += `📎 ${escapeHtml(msg.attachment_original_name || 'ファイル')}`;
                html += `</a>`;
            }
            html += `</div>`;
            html += `<div class="message-info">`;
            html += `<span>${formatDateTime(msg.created_at)}</span>`;
            // 自分が送信したメッセージには削除ボタンを表示
            if (isOwn) {
                html += `<button class="delete-message-btn" onclick="deleteMessage(${msg.id})">取消</button>`;
            }
            html += `</div>`;
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

        // メッセージ削除
        async function deleteMessage(messageId) {
            if (!confirm('このメッセージを削除しますか？')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_message');
                formData.append('message_id', messageId);

                const response = await fetch('chat_api.php', {
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
                    alert('削除に失敗しました: ' + (result.error || result.message || '不明なエラー'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
            }
        }

        // リアルタイム更新用の関数（新しいAPI使用）
        function checkNewMessages() {
            if (!roomId) return;

            fetch(`chat_realtime.php?room_id=${roomId}&last_message_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.new_messages && data.new_messages.length > 0) {
                        const messagesArea = document.getElementById('messagesArea');
                        const shouldScroll = messagesArea.scrollHeight - messagesArea.scrollTop <= messagesArea.clientHeight + 100;

                        data.new_messages.forEach(msg => {
                            appendMessage(msg);
                            lastMessageId = Math.max(lastMessageId, msg.id);
                        });

                        if (shouldScroll) {
                            scrollToBottom();
                        }
                    }

                    // 未読数をバッジ更新（オプション）
                    if (data.unread_count !== undefined) {
                        // ここで未読バッジを更新可能
                    }
                })
                .catch(error => console.error('リアルタイム更新エラー:', error));
        }

        // 初期読み込み
        if (roomId) {
            loadMessages();
            scrollToBottom();

            // 5秒ごとに新しいメッセージをチェック（リアルタイム更新）
            setInterval(checkNewMessages, 5000);
        }

        // アコーディオンの初期化（すべて開く）
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.classList.add('active');
        });
        document.querySelectorAll('.accordion-content').forEach(content => {
            content.classList.add('active');
        });

        // ハンバーガーメニューの開閉
        const hamburger = document.getElementById('hamburger');
        const sidebar = document.getElementById('roomsSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            hamburger.classList.toggle('active');
        }

        hamburger.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // 生徒を選択したらサイドバーを閉じる（モバイルのみ）
        const roomItems = document.querySelectorAll('.room-item');
        roomItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                        hamburger.classList.remove('active');
                    }, 100);
                }
            });
        });
        // 提出期限モーダルを開く
        function openSubmissionModal() {
            if (!roomId) {
                alert('チャットルームを選択してください');
                return;
            }

            // デフォルトの期限を明日に設定
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateString = tomorrow.toISOString().split('T')[0];
            document.getElementById('submissionDueDate').value = dateString;

            document.getElementById('submissionModal').classList.add('active');
        }

        // 提出期限モーダルを閉じる
        function closeSubmissionModal() {
            document.getElementById('submissionModal').classList.remove('active');
            document.getElementById('submissionForm').reset();
        }

        // 提出期限用ファイル選択時のプレビュー
        document.getElementById('submissionAttachment').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 3 * 1024 * 1024; // 3MB
                if (file.size > maxSize) {
                    alert('ファイルサイズは3MB以下にしてください');
                    e.target.value = '';
                    return;
                }

                document.getElementById('submissionFileName').textContent = file.name;
                document.getElementById('submissionFileSize').textContent = formatFileSize(file.size);
                document.getElementById('submissionFilePreview').style.display = 'block';
            }
        });

        // 提出期限用ファイル削除
        function removeSubmissionFile() {
            document.getElementById('submissionAttachment').value = '';
            document.getElementById('submissionFilePreview').style.display = 'none';
        }

        // 提出期限リクエストを送信
        async function submitSubmissionRequest(event) {
            event.preventDefault();

            const title = document.getElementById('submissionTitle').value;
            const description = document.getElementById('submissionDescription').value;
            const dueDate = document.getElementById('submissionDueDate').value;
            const fileInput = document.getElementById('submissionAttachment');
            const file = fileInput.files[0];

            if (!title || !dueDate) {
                alert('タイトルと提出期限を入力してください');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'create_submission');
                formData.append('room_id', roomId);
                formData.append('title', title);
                formData.append('description', description);
                formData.append('due_date', dueDate);

                if (file) {
                    formData.append('attachment', file);
                }

                const response = await fetch('chat_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('提出期限を設定しました');
                    closeSubmissionModal();
                    loadMessages(); // メッセージを再読み込み
                } else {
                    alert('エラー: ' + (result.error || '提出期限の設定に失敗しました'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('エラーが発生しました');
            }
        }

        // モーダル外クリックで閉じる
        document.getElementById('submissionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSubmissionModal();
            }
        });

        // アコーディオンの開閉
        function toggleAccordion(header) {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');

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
            const allItems = document.querySelectorAll('.room-item');

            allItems.forEach(item => {
                const studentName = item.getAttribute('data-student-name').toLowerCase();
                const guardianName = item.getAttribute('data-guardian-name').toLowerCase();

                if (studentName.includes(searchText) || guardianName.includes(searchText)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });

            // 検索中は全アコーディオンを開く
            if (searchText.length > 0) {
                document.querySelectorAll('.accordion-header').forEach(h => h.classList.add('active'));
                document.querySelectorAll('.accordion-content').forEach(c => c.classList.add('active'));
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

        // 一斉送信モーダル関連
        function openBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'block';
        }

        function closeBroadcastModal() {
            document.getElementById('broadcastModal').style.display = 'none';
            document.getElementById('broadcastMessage').value = '';
            // すべてのチェックボックスを解除
            document.querySelectorAll('.guardian-checkbox').forEach(cb => cb.checked = false);
        }

        function selectAllGuardians(checked) {
            document.querySelectorAll('.guardian-checkbox').forEach(cb => {
                cb.checked = checked;
            });
        }

        function sendBroadcast() {
            const message = document.getElementById('broadcastMessage').value.trim();
            const selectedGuardians = Array.from(document.querySelectorAll('.guardian-checkbox:checked'))
                .map(cb => cb.value);

            if (!message) {
                alert('メッセージを入力してください');
                return;
            }

            if (selectedGuardians.length === 0) {
                alert('送信先を選択してください');
                return;
            }

            if (!confirm(`${selectedGuardians.length}名の保護者にメッセージを送信しますか？`)) {
                return;
            }

            // 一斉送信
            fetch('broadcast_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    guardian_ids: selectedGuardians
                })
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
            .catch(error => {
                console.error('Error:', error);
                alert('送信に失敗しました');
            });
        }
    </script>

    <!-- 一斉送信モーダル -->
    <div id="broadcastModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7);">
        <div style="background-color: #2c2c2e; margin: 5% auto; padding: var(--spacing-2xl); width: 80%; max-width: 800px; border-radius: var(--radius-md); max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <h2 style="margin: 0; color: #f5f5f7;">📢 一斉送信</h2>
                <button onclick="closeBroadcastModal()" style="background: none; border: none; font-size: var(--text-title-2); cursor: pointer; color: #f5f5f7;">×</button>
            </div>

            <div style="margin-bottom: var(--spacing-lg);">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f5f5f7;">メッセージ</label>
                <textarea id="broadcastMessage" rows="5" style="width: 100%; padding: var(--spacing-md); border: 2px solid #3a3a3c; border-radius: var(--radius-sm); font-size: var(--text-subhead); font-family: inherit; resize: vertical; background: #1c1c1e; color: #f5f5f7;" placeholder="送信するメッセージを入力してください"></textarea>
            </div>

            <div style="margin-bottom: var(--spacing-lg);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                    <label style="font-weight: 600; color: #f5f5f7;">送信先を選択</label>
                    <div>
                        <button onclick="selectAllGuardians(true)" style="padding: 6px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: var(--radius-sm); font-size: var(--text-footnote); cursor: pointer; margin-right: 5px;">全選択</button>
                        <button onclick="selectAllGuardians(false)" style="padding: 6px 12px; background: #636366; color: white; border: none; border-radius: var(--radius-sm); font-size: var(--text-footnote); cursor: pointer;">全解除</button>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #3a3a3c; border-radius: var(--radius-sm); padding: var(--spacing-md); background: #1c1c1e;">
                    <?php
                    // 保護者の重複を除去
                    $uniqueGuardians = [];
                    foreach ($allStudents as $student) {
                        if ($student['guardian_id'] && !isset($uniqueGuardians[$student['guardian_id']])) {
                            $uniqueGuardians[$student['guardian_id']] = [
                                'guardian_id' => $student['guardian_id'],
                                'guardian_name' => $student['guardian_name'],
                                'student_names' => []
                            ];
                        }
                        if ($student['guardian_id']) {
                            $uniqueGuardians[$student['guardian_id']]['student_names'][] = $student['student_name'];
                        }
                    }
                    ?>
                    <?php foreach ($uniqueGuardians as $guardian): ?>
                        <label style="display: block; padding: var(--spacing-md); cursor: pointer; border-bottom: 1px solid #3a3a3c;">
                            <input type="checkbox" class="guardian-checkbox" value="<?= $guardian['guardian_id'] ?>" style="margin-right: 10px;">
                            <span style="font-weight: 600; color: #f5f5f7;"><?= htmlspecialchars($guardian['guardian_name'] ?? '名前未登録') ?></span>
                            <span style="color: #98989d; font-size: var(--text-footnote); margin-left: 10px;">
                                (<?= implode('、', array_map('htmlspecialchars', $guardian['student_names'])) ?>さんの保護者)
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeBroadcastModal()" style="padding: var(--spacing-md) 24px; background: #636366; color: white; border: none; border-radius: var(--radius-sm); font-size: var(--text-subhead); cursor: pointer;">
                    キャンセル
                </button>
                <button onclick="sendBroadcast()" style="padding: var(--spacing-md) 24px; background: #30d158; color: white; border: none; border-radius: var(--radius-sm); font-size: var(--text-subhead); cursor: pointer;">
                    送信
                </button>
            </div>
        </div>
    </div>
</body>
</html>
