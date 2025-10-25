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
        ORDER BY last_message_at IS NULL, last_message_at DESC, s.student_name ASC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT
            s.id as student_id,
            s.student_name,
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
        ORDER BY last_message_at IS NULL, last_message_at DESC, s.student_name ASC
    ");
}

$rooms = $stmt->fetchAll();
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
        $totalRooms = count($rooms);
        $totalUnread = array_sum(array_column($rooms, 'unread_count'));
        $activeRooms = count(array_filter($rooms, function($r) { return $r['message_count'] > 0; }));
        ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalRooms; ?></div>
                <div class="stat-label">生徒数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $activeRooms; ?></div>
                <div class="stat-label">チャット有り</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUnread; ?></div>
                <div class="stat-label">未読メッセージ</div>
            </div>
        </div>

        <div class="room-list">
            <?php if (empty($rooms)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>生徒チャットルームがありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($rooms as $room): ?>
                    <a href="student_chat_detail.php?room_id=<?php echo $room['room_id']; ?>" class="room-item">
                        <div class="room-avatar">🎓</div>
                        <div class="room-info">
                            <div class="room-name">
                                <?php echo htmlspecialchars($room['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($room['unread_count'] > 0): ?>
                                    <span class="room-badge"><?php echo $room['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="room-meta">
                                <?php if ($room['last_message_at']): ?>
                                    最終メッセージ: <?php echo date('Y/m/d H:i', strtotime($room['last_message_at'])); ?>
                                <?php else: ?>
                                    メッセージなし
                                <?php endif; ?>
                                （<?php echo $room['message_count']; ?>件）
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
