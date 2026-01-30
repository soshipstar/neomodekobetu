<?php
/**
 * スタッフ用 - 生徒面談記録一覧
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 生徒一覧を取得（生徒のclassroom_idでフィルタ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        WHERE s.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT id, student_name
        FROM students
        ORDER BY student_name
    ");
}

$students = $stmt->fetchAll();

// 各生徒の最新の面談記録を取得
$interviewsByStudent = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT id, interview_date, interview_content, updated_at
        FROM student_interviews
        WHERE student_id = ?
        ORDER BY interview_date DESC
        LIMIT 1
    ");
    $stmt->execute([$student['id']]);
    $interview = $stmt->fetch();

    // 面談記録の総数を取得
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM student_interviews
        WHERE student_id = ?
    ");
    $countStmt->execute([$student['id']]);
    $countResult = $countStmt->fetch();

    $interviewsByStudent[$student['id']] = [
        'latest' => $interview ?: null,
        'count' => $countResult['count']
    ];
}

// ページ開始
$currentPage = 'student_interviews';
renderPageStart('staff', $currentPage, '生徒面談記録');
?>

<style>
    .student-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .student-card {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform var(--duration-fast) var(--ease-out), box-shadow 0.2s;
        cursor: pointer;
        text-decoration: none;
        color: var(--text-primary);
        display: block;
    }

    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .student-card-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .student-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--md-teal);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--text-title-2);
        margin-right: 15px;
    }

    .student-name {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .interview-status {
        margin-top: 10px;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: var(--radius-lg);
        font-size: var(--text-footnote);
        font-weight: 600;
    }

    .status-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.inactive {
        background: var(--md-bg-secondary);
        color: #666;
    }

    .interview-preview {
        font-size: var(--text-footnote);
        color: var(--text-secondary);
        margin-top: 10px;
    }

    .interview-count {
        font-size: var(--text-caption-1);
        color: var(--md-teal);
        margin-top: 8px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-secondary);
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .empty-state-icon {
        font-size: 64px;
        margin-bottom: var(--spacing-lg);
    }

    @media (max-width: 768px) {
        .student-list {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">生徒面談記録</h1>
        <p class="page-subtitle">各生徒の面談記録を管理</p>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <span class="material-symbols-outlined" style="font-size: 64px;">group_off</span>
        </div>
        <p>生徒が登録されていません</p>
    </div>
<?php else: ?>
    <div class="student-list">
        <?php foreach ($students as $student): ?>
            <?php
            $data = $interviewsByStudent[$student['id']];
            $latestInterview = $data['latest'];
            $interviewCount = $data['count'];
            $hasInterview = $latestInterview !== null;
            ?>
            <a href="student_interview_detail.php?student_id=<?php echo $student['id']; ?>" class="student-card">
                <div class="student-card-header">
                    <div class="student-avatar">
                        <span class="material-symbols-outlined" style="font-size: 24px;">person</span>
                    </div>
                    <div class="student-name">
                        <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>

                <div class="interview-status">
                    <?php if ($hasInterview): ?>
                        <span class="status-badge active">
                            <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">check_circle</span>
                            記録あり
                        </span>
                        <div class="interview-preview">
                            最新面談日: <?php echo date('Y/m/d', strtotime($latestInterview['interview_date'])); ?>
                        </div>
                        <div class="interview-count">
                            <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">folder</span>
                            <?php echo $interviewCount; ?>件の記録
                        </div>
                    <?php else: ?>
                        <span class="status-badge inactive">記録なし</span>
                        <div class="interview-preview">
                            まだ面談記録がありません
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderPageEnd(); ?>
