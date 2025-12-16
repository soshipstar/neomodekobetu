<?php
/**
 * スタッフ用 - 生徒週間計画表一覧
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
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE g.classroom_id = ?
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

// 週オフセットを取得（0=今週、-1=先週、-2=2週前...）
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

// 今週の開始日を取得
$today = date('Y-m-d');
$dayOfWeek = date('w', strtotime($today));
$daysFromMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
$currentWeekStart = date('Y-m-d', strtotime("-$daysFromMonday days", strtotime($today)));

// オフセットを適用して表示する週を計算
$thisWeekStart = date('Y-m-d', strtotime("$weekOffset weeks", strtotime($currentWeekStart)));
$thisWeekEnd = date('Y-m-d', strtotime('+6 days', strtotime($thisWeekStart)));

// ナビゲーション用のオフセット
$prevWeekOffset = $weekOffset - 1;
$nextWeekOffset = $weekOffset + 1;
$isCurrentWeek = ($weekOffset === 0);

// 各生徒の今週の計画を取得
$plansByStudent = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT id, plan_data, updated_at
        FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$student['id'], $thisWeekStart]);
    $plan = $stmt->fetch();

    $plansByStudent[$student['id']] = $plan ?: null;
}

// ページ開始
$currentPage = 'student_weekly_plans';
renderPageStart('staff', $currentPage, '生徒週間計画表');
?>

<style>
        .week-nav {
            background: var(--apple-bg-primary);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .week-nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 10px 16px;
            background: var(--apple-gray-6);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .week-nav-btn:hover {
            background: var(--apple-gray-5);
        }

        .week-nav-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .week-info {
            text-align: center;
            flex: 1;
        }

        .week-info h2 {
            color: var(--primary-purple);
            font-size: 18px;
            margin: 0;
        }

        .week-info .week-range {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .week-info .week-label {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            background: var(--apple-blue);
            color: white;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
        }

        .week-info .week-label.past {
            background: var(--apple-gray);
        }

        .student-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .student-card {
            background: var(--apple-bg-primary);
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
            background: var(--primary-purple);
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

        .plan-status {
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
            background: var(--apple-bg-secondary);
            color: #721c24;
        }

        .plan-preview {
            font-size: var(--text-footnote);
            color: var(--text-secondary);
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--apple-bg-primary);
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
        <h1 class="page-title">生徒週間計画表</h1>
        <p class="page-subtitle">各生徒の週間計画を確認</p>
    </div>
</div>

        <div class="week-nav">
            <a href="?week=<?php echo $prevWeekOffset; ?>" class="week-nav-btn">
                ← 前の週
            </a>

            <div class="week-info">
                <h2><?php echo date('Y年n月', strtotime($thisWeekStart)); ?></h2>
                <div class="week-range">
                    <?php echo date('n/j', strtotime($thisWeekStart)); ?>（月）〜 <?php echo date('n/j', strtotime($thisWeekEnd)); ?>（日）
                </div>
                <?php if ($isCurrentWeek): ?>
                    <span class="week-label">今週</span>
                <?php elseif ($weekOffset < 0): ?>
                    <span class="week-label past"><?php echo abs($weekOffset); ?>週前</span>
                <?php else: ?>
                    <span class="week-label"><?php echo $weekOffset; ?>週後</span>
                <?php endif; ?>
            </div>

            <a href="?week=<?php echo $nextWeekOffset; ?>" class="week-nav-btn <?php echo $nextWeekOffset > 0 ? 'disabled' : ''; ?>">
                次の週 →
            </a>
        </div>

        <?php if (!$isCurrentWeek): ?>
        <div style="text-align: center; margin-bottom: 15px;">
            <a href="?week=0" class="week-nav-btn" style="background: var(--apple-blue); color: white;">
                📅 今週に戻る
            </a>
        </div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>生徒が登録されていません</p>
            </div>
        <?php else: ?>
            <div class="student-list">
                <?php foreach ($students as $student): ?>
                    <?php
                    $plan = $plansByStudent[$student['id']];
                    $hasPlan = $plan !== null;
                    ?>
                    <a href="student_weekly_plan_detail.php?student_id=<?php echo $student['id']; ?>&date=<?php echo $thisWeekStart; ?>" class="student-card">
                        <div class="student-card-header">
                            <div class="student-avatar">🎓</div>
                            <div class="student-name">
                                <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>

                        <div class="plan-status">
                            <?php if ($hasPlan): ?>
                                <span class="status-badge active">✓ 計画あり</span>
                                <div class="plan-preview">
                                    最終更新: <?php echo date('m/d H:i', strtotime($plan['updated_at'])); ?>
                                </div>
                            <?php else: ?>
                                <span class="status-badge inactive">計画なし</span>
                                <div class="plan-preview">
                                    この週の計画はまだ作成されていません
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php renderPageEnd(); ?>
