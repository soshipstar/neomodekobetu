<?php
/**
 * スタッフ用 - 生徒別提出物一覧
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 生徒一覧を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, g.full_name as guardian_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE g.classroom_id = ?
        ORDER BY s.student_name ASC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, g.full_name as guardian_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        ORDER BY s.student_name ASC
    ");
}
$students = $stmt->fetchAll();

// 各生徒の提出物統計を取得
$submissionStats = [];
foreach ($students as $student) {
    $studentId = $student['id'];

    // 週間計画表の提出物
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
               SUM(CASE WHEN is_completed = 0 AND due_date >= CURDATE() AND DATEDIFF(due_date, CURDATE()) <= 3 THEN 1 ELSE 0 END) as urgent
        FROM weekly_plan_submissions wps
        INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
        WHERE wp.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $wpStats = $stmt->fetch();

    // 保護者チャット経由の提出物
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
               SUM(CASE WHEN is_completed = 0 AND due_date >= CURDATE() AND DATEDIFF(due_date, CURDATE()) <= 3 THEN 1 ELSE 0 END) as urgent
        FROM submission_requests sr
        INNER JOIN chat_rooms cr ON sr.room_id = cr.id
        WHERE cr.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $chatStats = $stmt->fetch();

    // 生徒自身が登録した提出物
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
               SUM(CASE WHEN is_completed = 0 AND due_date >= CURDATE() AND DATEDIFF(due_date, CURDATE()) <= 3 THEN 1 ELSE 0 END) as urgent
        FROM student_submissions
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $studentStats = $stmt->fetch();

    // 統計を集計
    $submissionStats[$studentId] = [
        'total' => ($wpStats['total'] ?? 0) + ($chatStats['total'] ?? 0) + ($studentStats['total'] ?? 0),
        'pending' => ($wpStats['pending'] ?? 0) + ($chatStats['pending'] ?? 0) + ($studentStats['pending'] ?? 0),
        'overdue' => ($wpStats['overdue'] ?? 0) + ($chatStats['overdue'] ?? 0) + ($studentStats['overdue'] ?? 0),
        'urgent' => ($wpStats['urgent'] ?? 0) + ($chatStats['urgent'] ?? 0) + ($studentStats['urgent'] ?? 0)
    ];
}

// フィルタ
$filter = $_GET['filter'] ?? 'all';
$filteredStudents = $students;

if ($filter === 'pending') {
    $filteredStudents = array_filter($students, function($s) use ($submissionStats) {
        return $submissionStats[$s['id']]['pending'] > 0;
    });
} elseif ($filter === 'overdue') {
    $filteredStudents = array_filter($students, function($s) use ($submissionStats) {
        return $submissionStats[$s['id']]['overdue'] > 0;
    });
} elseif ($filter === 'urgent') {
    $filteredStudents = array_filter($students, function($s) use ($submissionStats) {
        return $submissionStats[$s['id']]['urgent'] > 0;
    });
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒別提出物一覧 - 個別支援連絡帳システム</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 24px;
            color: #333;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .filter-tabs {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            text-decoration: none;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-tab.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .student-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .student-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .student-card.has-overdue {
            border-left: 4px solid #dc3545;
        }

        .student-card.has-urgent {
            border-left: 4px solid #ffc107;
        }

        .student-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .guardian-name {
            font-size: 13px;
            color: #999;
            margin-bottom: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .stat-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-item.overdue .stat-number {
            color: #dc3545;
        }

        .stat-item.urgent .stat-number {
            color: #ffc107;
        }

        .stat-item.pending .stat-number {
            color: #007bff;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state-text {
            font-size: 18px;
            color: #666;
        }

        @media (max-width: 768px) {
            .student-grid {
                grid-template-columns: 1fr;
            }

            .filter-tabs {
                flex-direction: column;
            }

            .filter-tab {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 生徒別提出物一覧</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 戻る</a>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📊 すべて (<?php echo count($students); ?>)
            </a>
            <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                📝 未提出あり
            </a>
            <a href="?filter=overdue" class="filter-tab <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                🔴 期限切れあり
            </a>
            <a href="?filter=urgent" class="filter-tab <?php echo $filter === 'urgent' ? 'active' : ''; ?>">
                ⚠️ 期限間近あり
            </a>
        </div>

        <?php if (empty($filteredStudents)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <p class="empty-state-text">
                    <?php if ($filter === 'pending'): ?>
                        未提出の提出物がある生徒はいません
                    <?php elseif ($filter === 'overdue'): ?>
                        期限切れの提出物がある生徒はいません
                    <?php elseif ($filter === 'urgent'): ?>
                        期限間近の提出物がある生徒はいません
                    <?php else: ?>
                        生徒が登録されていません
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="student-grid">
                <?php foreach ($filteredStudents as $student):
                    $stats = $submissionStats[$student['id']];
                    $cardClass = '';
                    if ($stats['overdue'] > 0) {
                        $cardClass = 'has-overdue';
                    } elseif ($stats['urgent'] > 0) {
                        $cardClass = 'has-urgent';
                    }
                ?>
                    <a href="student_submission_detail.php?student_id=<?php echo $student['id']; ?>" class="student-card <?php echo $cardClass; ?>">
                        <div class="student-name">
                            <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="guardian-name">
                            保護者: <?php echo htmlspecialchars($student['guardian_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="stats">
                            <div class="stat-item overdue">
                                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                                <div class="stat-label">期限切れ</div>
                            </div>
                            <div class="stat-item urgent">
                                <div class="stat-number"><?php echo $stats['urgent']; ?></div>
                                <div class="stat-label">期限間近</div>
                            </div>
                            <div class="stat-item pending">
                                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">未提出</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total']; ?></div>
                                <div class="stat-label">合計</div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
