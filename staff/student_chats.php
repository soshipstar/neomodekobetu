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
$elementary = []; // 小学部 (1-6年)
$junior = [];     // 中等部 (7-9年)
$senior = [];     // 高等部 (10-12年)

foreach ($allStudents as $student) {
    $grade = $student['grade_level'];
    if ($grade >= 1 && $grade <= 6) {
        $elementary[] = $student;
    } elseif ($grade >= 7 && $grade <= 9) {
        $junior[] = $student;
    } elseif ($grade >= 10 && $grade <= 12) {
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .back-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .search-box {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
        }

        .room-item:hover {
            background: #f8f9fa;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 生徒チャット一覧</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動管理</a>
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
            <input type="text" id="searchInput" placeholder="🔍 生徒名で検索..." onkeyup="filterStudents()">
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
                            <a href="student_chat_detail.php?student_id=<?php echo $student['student_id']; ?>" class="room-item" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                            </a>
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
                            <a href="student_chat_detail.php?student_id=<?php echo $student['student_id']; ?>" class="room-item" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                            </a>
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
                            <a href="student_chat_detail.php?student_id=<?php echo $student['student_id']; ?>" class="room-item" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>">
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
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
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
            const allItems = document.querySelectorAll('.room-item');

            allItems.forEach(item => {
                const studentName = item.getAttribute('data-student-name').toLowerCase();
                if (studentName.includes(searchText)) {
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
    </script>
</body>
</html>
