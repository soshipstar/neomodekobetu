<?php
/**
 * スタッフ用 - 生徒週間計画表一覧
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

// 今週の開始日を取得
$today = date('Y-m-d');
$dayOfWeek = date('w', strtotime($today));
$daysFromMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
$thisWeekStart = date('Y-m-d', strtotime("-$daysFromMonday days", strtotime($today)));

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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒週間計画表 - 個別支援連絡帳システム</title>
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
            max-width: 1200px;
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

        .week-info {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .week-info h2 {
            color: #667eea;
            font-size: 18px;
        }

        .student-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .student-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: #333;
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
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }

        .student-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .plan-status {
            margin-top: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .plan-preview {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .student-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 生徒週間計画表</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動管理</a>
        </div>

        <div class="week-info">
            <h2><?php echo date('Y年m月d日', strtotime($thisWeekStart)); ?>の週</h2>
        </div>

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
    </div>
</body>
</html>
