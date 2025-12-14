<?php
/**
 * スタッフ用 - 生徒別提出物一覧
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

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

// ページ開始
$currentPage = 'student_submissions';
renderPageStart('staff', $currentPage, '生徒別提出物一覧');
?>

<style>
        .filter-tabs {
            background: var(--apple-bg-primary);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: var(--spacing-md) 20px;
            border: 2px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: var(--text-subhead);
            transition: all var(--duration-fast) var(--ease-out);
        }

        .filter-tab:hover {
            border-color: var(--primary-purple);
            color: var(--primary-purple);
        }

        .filter-tab.active {
            background: var(--primary-purple);
            border-color: var(--primary-purple);
            color: white;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .student-card {
            background: var(--apple-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform var(--duration-fast) var(--ease-out), box-shadow 0.2s;
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
            border-left: 4px solid var(--apple-red);
        }

        .student-card.has-urgent {
            border-left: 4px solid var(--apple-orange);
        }

        .student-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .guardian-name {
            font-size: var(--text-footnote);
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .stat-item {
            padding: var(--spacing-md);
            background: var(--apple-gray-6);
            border-radius: var(--radius-sm);
            text-align: center;
        }

        .stat-number {
            font-size: var(--text-title-2);
            font-weight: 700;
            color: var(--primary-purple);
        }

        .stat-item.overdue .stat-number {
            color: var(--apple-red);
        }

        .stat-item.urgent .stat-number {
            color: var(--apple-orange);
        }

        .stat-item.pending .stat-number {
            color: var(--apple-blue);
        }

        .stat-label {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--apple-bg-primary);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: var(--spacing-lg);
        }

        .empty-state-text {
            font-size: 18px;
            color: var(--text-secondary);
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

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">生徒別提出物一覧</h1>
        <p class="page-subtitle">各生徒の提出物状況を確認</p>
    </div>
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

<?php renderPageEnd(); ?>
