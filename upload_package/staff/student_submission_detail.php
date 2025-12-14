<?php
/**
 * スタッフ用 - 生徒の提出物詳細
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    header('Location: student_submissions.php');
    exit;
}

// 生徒情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, g.full_name as guardian_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, g.full_name as guardian_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
}

$student = $stmt->fetch();

if (!$student) {
    header('Location: student_submissions.php');
    exit;
}

// すべての提出物を統合
$allSubmissions = [];

// 1. 週間計画表の提出物
$stmt = $pdo->prepare("
    SELECT
        wps.id,
        wps.submission_item as title,
        '' as description,
        wps.due_date,
        wps.is_completed,
        wps.completed_at,
        'weekly_plan' as source,
        wp.week_start_date
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.title,
        sr.description,
        sr.due_date,
        sr.is_completed,
        sr.completed_at,
        'guardian_chat' as source,
        sr.attachment_path,
        sr.attachment_original_name,
        sr.attachment_size
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        description,
        due_date,
        is_completed,
        completed_at,
        'student' as source
    FROM student_submissions
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 日付でソート（未完了を先に、期限日順）
usort($allSubmissions, function($a, $b) {
    if ($a['is_completed'] != $b['is_completed']) {
        return $a['is_completed'] - $b['is_completed'];
    }
    return strcmp($a['due_date'], $b['due_date']);
});

// 未提出と提出済みに分ける
$pending = array_filter($allSubmissions, function($s) { return !$s['is_completed']; });
$completed = array_filter($allSubmissions, function($s) { return $s['is_completed']; });

$sourceLabels = [
    'weekly_plan' => '週間計画表',
    'guardian_chat' => '保護者チャット',
    'student' => '生徒が登録'
];

// ページ開始
$currentPage = 'student_submission_detail';
$pageTitle = htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') . 'の提出物';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .section {
            background: var(--apple-bg-primary);
            border-radius: var(--radius-md);
            padding: 25px;
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section h2 {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: var(--spacing-lg);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-purple);
        }

        .submission-card {
            background: var(--apple-gray-6);
            border-radius: var(--radius-sm);
            padding: var(--spacing-lg);
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-purple);
        }

        .submission-card.urgent {
            border-left-color: #e74c3c;
            background: var(--apple-bg-secondary);
        }

        .submission-card.overdue {
            border-left-color: #95a5a6;
            background: var(--apple-gray-6);
        }

        .submission-card.completed {
            border-left-color: var(--apple-green);
            background: var(--apple-bg-secondary);
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }

        .submission-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .submission-badges {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-left: 10px;
        }

        .submission-badge {
            padding: 4px 12px;
            border-radius: var(--radius-md);
            font-size: var(--text-caption-1);
            font-weight: 600;
            white-space: nowrap;
        }

        .submission-badge.urgent {
            background: #e74c3c;
            color: white;
        }

        .submission-badge.overdue {
            background: #95a5a6;
            color: white;
        }

        .submission-badge.normal {
            background: var(--primary-purple);
            color: white;
        }

        .submission-badge.completed {
            background: var(--apple-green);
            color: white;
        }

        .submission-badge.source {
            background: var(--apple-bg-secondary);
            color: var(--text-secondary);
        }

        .submission-due {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .submission-description {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: var(--spacing-md);
        }

        .submission-link {
            font-size: var(--text-footnote);
            color: var(--primary-purple);
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }

        .submission-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl) 20px;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: var(--spacing-lg);
        }

        .summary-card {
            background: var(--apple-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .summary-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-purple);
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
        }

        .summary-card.urgent .summary-number {
            color: #e74c3c;
        }

        .summary-card.overdue .summary-number {
            color: #95a5a6;
        }

        .summary-card.completed .summary-number {
            color: var(--apple-green);
        }

        @media (max-width: 768px) {
            .submission-header {
                flex-direction: column;
            }

            .submission-badges {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんの提出物</h1>
        <p class="page-subtitle">保護者: <?php echo htmlspecialchars($student['guardian_name'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>

        <?php
        $today = date('Y-m-d');
        $urgentCount = 0;
        $overdueCount = 0;
        $pendingCount = count($pending);
        $completedCount = count($completed);

        foreach ($pending as $sub) {
            $daysLeft = (strtotime($sub['due_date']) - strtotime($today)) / 86400;
            if ($daysLeft < 0) {
                $overdueCount++;
            } elseif ($daysLeft <= 3) {
                $urgentCount++;
            }
        }
        ?>

        <div class="summary">
            <div class="summary-card overdue">
                <div class="summary-number"><?php echo $overdueCount; ?></div>
                <div class="summary-label">期限切れ</div>
            </div>
            <div class="summary-card urgent">
                <div class="summary-number"><?php echo $urgentCount; ?></div>
                <div class="summary-label">期限間近</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $pendingCount; ?></div>
                <div class="summary-label">未提出</div>
            </div>
            <div class="summary-card completed">
                <div class="summary-number"><?php echo $completedCount; ?></div>
                <div class="summary-label">提出済み</div>
            </div>
        </div>

        <div class="section">
            <h2>📝 未提出の提出物</h2>

            <?php if (empty($pending)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🎉</div>
                    <p>未提出の提出物はありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $sub):
                    $dueDate = strtotime($sub['due_date']);
                    $today = strtotime(date('Y-m-d'));
                    $daysLeft = ($dueDate - $today) / 86400;

                    $cardClass = '';
                    $badgeClass = '';
                    $badgeText = '';

                    if ($daysLeft < 0) {
                        $cardClass = 'overdue';
                        $badgeClass = 'overdue';
                        $badgeText = '期限切れ';
                    } elseif ($daysLeft <= 3) {
                        $cardClass = 'urgent';
                        $badgeClass = 'urgent';
                        $badgeText = '期限間近';
                    } else {
                        $badgeClass = 'normal';
                        $badgeText = '未提出';
                    }
                ?>
                    <div class="submission-card <?php echo $cardClass; ?>">
                        <div class="submission-header">
                            <div class="submission-title">
                                <?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="submission-badges">
                                <span class="submission-badge <?php echo $badgeClass; ?>">
                                    <?php echo $badgeText; ?>
                                </span>
                                <span class="submission-badge source">
                                    <?php echo $sourceLabels[$sub['source']]; ?>
                                </span>
                            </div>
                        </div>

                        <div class="submission-due">
                            📅 提出期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                            <?php if ($daysLeft >= 0): ?>
                                （あと<?php echo ceil($daysLeft); ?>日）
                            <?php else: ?>
                                （<?php echo abs(floor($daysLeft)); ?>日超過）
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($sub['description'])): ?>
                            <div class="submission-description">
                                <?php echo nl2br(htmlspecialchars($sub['description'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($sub['source'] === 'weekly_plan'): ?>
                            <a href="student_weekly_plan_detail.php?student_id=<?php echo $studentId; ?>&date=<?php echo $sub['week_start_date']; ?>" class="submission-link">
                                → 週間計画表で確認
                            </a>
                        <?php elseif ($sub['source'] === 'guardian_chat'): ?>
                            <a href="chat.php?room_id=<?php echo $sub['room_id'] ?? ''; ?>" class="submission-link">
                                → チャットで確認
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($completed)): ?>
            <div class="section">
                <h2>✅ 提出済みの提出物</h2>

                <?php foreach ($completed as $sub): ?>
                    <div class="submission-card completed">
                        <div class="submission-header">
                            <div class="submission-title">
                                <?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="submission-badges">
                                <span class="submission-badge completed">提出済み</span>
                                <span class="submission-badge source">
                                    <?php echo $sourceLabels[$sub['source']]; ?>
                                </span>
                            </div>
                        </div>

                        <div class="submission-due">
                            📅 提出期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                        </div>

                        <?php if (!empty($sub['description'])): ?>
                            <div class="submission-description">
                                <?php echo nl2br(htmlspecialchars($sub['description'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($sub['source'] === 'weekly_plan'): ?>
                            <a href="student_weekly_plan_detail.php?student_id=<?php echo $studentId; ?>&date=<?php echo $sub['week_start_date']; ?>" class="submission-link">
                                → 週間計画表で確認
                            </a>
                        <?php elseif ($sub['source'] === 'guardian_chat'): ?>
                            <a href="chat.php?room_id=<?php echo $sub['room_id'] ?? ''; ?>" class="submission-link">
                                → チャットで確認
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php renderPageEnd(); ?>
