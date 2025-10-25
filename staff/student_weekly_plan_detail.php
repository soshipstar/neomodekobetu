<?php
/**
 * スタッフ用 - 生徒週間計画表詳細
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;
$targetDate = $_GET['date'] ?? date('Y-m-d');

if (!$studentId) {
    header('Location: student_weekly_plans.php');
    exit;
}

// 生徒情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
}

$student = $stmt->fetch();

if (!$student) {
    header('Location: student_weekly_plans.php');
    exit;
}

// 週の開始日を計算
$timestamp = strtotime($targetDate);
$dayOfWeek = date('w', $timestamp);
$daysFromMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
$weekStartDate = date('Y-m-d', strtotime("-$daysFromMonday days", $timestamp));

// 週間計画を取得
$stmt = $pdo->prepare("
    SELECT id, plan_data, created_at, updated_at
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date = ?
");
$stmt->execute([$studentId, $weekStartDate]);
$weeklyPlan = $stmt->fetch();

$planData = $weeklyPlan ? json_decode($weeklyPlan['plan_data'], true) : [];

// コメントを取得
$comments = [];
if ($weeklyPlan) {
    $stmt = $pdo->prepare("
        SELECT
            wpc.id,
            wpc.commenter_type,
            wpc.comment,
            wpc.created_at,
            CASE
                WHEN wpc.commenter_type = 'staff' THEN u.full_name
                WHEN wpc.commenter_type = 'guardian' THEN u2.full_name
                WHEN wpc.commenter_type = 'student' THEN s.student_name
            END as commenter_name
        FROM weekly_plan_comments wpc
        LEFT JOIN users u ON wpc.commenter_type = 'staff' AND wpc.commenter_id = u.id
        LEFT JOIN users u2 ON wpc.commenter_type = 'guardian' AND wpc.commenter_id = u2.id
        LEFT JOIN students s ON wpc.commenter_type = 'student' AND wpc.commenter_id = s.id
        WHERE wpc.weekly_plan_id = ?
        ORDER BY wpc.created_at ASC
    ");
    $stmt->execute([$weeklyPlan['id']]);
    $comments = $stmt->fetchAll();
}

// 前週・次週の日付
$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($weekStartDate)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($weekStartDate)));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>の週間計画表 - 個別支援連絡帳システム</title>
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

        .week-nav {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .week-nav h2 {
            color: #333;
            font-size: 18px;
        }

        .week-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .week-nav-buttons a {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .week-nav-buttons a:hover {
            background: #5568d3;
        }

        .plan-table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .plan-table {
            width: 100%;
            border-collapse: collapse;
        }

        .plan-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: 600;
            color: #333;
        }

        .plan-table td {
            padding: 12px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .day-header {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
        }

        .plan-content {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .empty-plan {
            color: #999;
            font-style: italic;
        }

        .comments-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .comments-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .comment {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .comment.staff {
            border-left-color: #28a745;
        }

        .comment.student {
            border-left-color: #667eea;
        }

        .comment.guardian {
            border-left-color: #ffc107;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .comment-author {
            font-weight: 600;
            color: #667eea;
        }

        .comment-date {
            font-size: 12px;
            color: #999;
        }

        .comment-body {
            color: #333;
            line-height: 1.6;
        }

        .comment-form {
            margin-top: 20px;
        }

        .comment-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
        }

        .comment-form button {
            margin-top: 10px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .comment-form button:hover {
            background: #218838;
        }

        .message {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .no-plan {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .plan-table {
                font-size: 13px;
            }

            .plan-table th,
            .plan-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんの週間計画表</h1>
            <a href="student_weekly_plans.php" class="back-btn">← 一覧に戻る</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <?php if ($_GET['success'] == '1'): ?>
                    週間計画表を保存しました
                <?php else: ?>
                    コメントを投稿しました
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="week-nav">
            <h2><?php echo date('Y年m月d日', strtotime($weekStartDate)); ?>の週</h2>
            <div class="week-nav-buttons">
                <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $prevWeek; ?>">← 前週</a>
                <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo date('Y-m-d'); ?>">今週</a>
                <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $nextWeek; ?>">次週 →</a>
            </div>
        </div>

        <?php
        // 編集モードかどうか
        $isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';
        ?>

        <?php if (!$weeklyPlan && !$isEditMode): ?>
            <div class="plan-table-container">
                <div class="no-plan">
                    <p>この週の計画はまだ作成されていません</p>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>&edit=1" class="btn-edit" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">計画を作成する</a>
                </div>
            </div>
        <?php elseif ($isEditMode): ?>
            <!-- 編集モード -->
            <form method="POST" action="save_staff_weekly_plan.php">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">

                <div class="plan-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3>📝 週間計画を編集</h3>
                        <div>
                            <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>" style="padding: 8px 16px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">キャンセル</a>
                            <button type="submit" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">保存する</button>
                        </div>
                    </div>

                    <table class="plan-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">曜日</th>
                                <th>計画・目標</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                            foreach ($days as $index => $day):
                                $dayKey = "day_$index";
                                $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                                $content = $planData[$dayKey] ?? '';
                            ?>
                                <tr>
                                    <td>
                                        <div class="day-header"><?php echo $day; ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo $date; ?></div>
                                    </td>
                                    <td>
                                        <textarea name="<?php echo $dayKey; ?>" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <!-- 表示モード -->
            <div class="plan-table-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>📝 週間計画</h3>
                    <a href="?student_id=<?php echo $studentId; ?>&date=<?php echo $targetDate; ?>&edit=1" style="padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;">編集する</a>
                </div>
                <table class="plan-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">曜日</th>
                            <th>計画・目標</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days = ['月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日', '日曜日'];
                        foreach ($days as $index => $day):
                            $dayKey = "day_$index";
                            $date = date('m/d', strtotime("+$index days", strtotime($weekStartDate)));
                            $content = $planData[$dayKey] ?? '';
                        ?>
                            <tr>
                                <td>
                                    <div class="day-header"><?php echo $day; ?></div>
                                    <div style="font-size: 12px; color: #666;"><?php echo $date; ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($content)): ?>
                                        <div class="plan-content"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php else: ?>
                                        <div class="empty-plan">計画なし</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="comments-section">
                <h3>💬 コメント</h3>

                <?php if (empty($comments)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">まだコメントはありません</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment <?php echo $comment['commenter_type']; ?>">
                            <div class="comment-header">
                                <span class="comment-author">
                                    <?php
                                    $icon = $comment['commenter_type'] === 'staff' ? '👨‍🏫' :
                                            ($comment['commenter_type'] === 'guardian' ? '👪' : '🎓');
                                    echo $icon . ' ' . htmlspecialchars($comment['commenter_name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                </span>
                                <span class="comment-date"><?php echo date('Y/m/d H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-body">
                                <?php echo nl2br(htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="comment-form">
                    <form method="POST" action="add_staff_plan_comment.php">
                        <input type="hidden" name="weekly_plan_id" value="<?php echo $weeklyPlan['id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                        <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">
                        <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                        <button type="submit">コメントを投稿</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
