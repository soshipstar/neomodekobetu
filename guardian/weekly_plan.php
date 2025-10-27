<?php
/**
 * 保護者用 - 週間計画表
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 生徒一覧を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? ORDER BY student_name");
$stmt->execute([$currentUser['id']]);
$students = $stmt->fetchAll();

// デフォルトで最初の生徒を選択
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);
$targetDate = $_GET['date'] ?? date('Y-m-d');

if (!$selectedStudentId) {
    // 生徒がいない場合
    $student = null;
    $weeklyPlan = null;
    $planData = [];
    $comments = [];
} else {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE id = ? AND guardian_id = ?");
    $stmt->execute([$selectedStudentId, $currentUser['id']]);
    $student = $stmt->fetch();

    if (!$student) {
        header('Location: dashboard.php');
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
    $stmt->execute([$selectedStudentId, $weekStartDate]);
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
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>週間計画表 - 個別支援連絡帳システム</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .student-selector {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .student-selector select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
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
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .comment-form button:hover {
            background: #5568d3;
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
            <h1>📝 週間計画表</h1>
            <a href="dashboard.php" class="back-btn">← マイページ</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">コメントを投稿しました</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (count($students) > 1): ?>
            <div class="student-selector">
                <select onchange="location.href='?student_id=' + this.value">
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $selectedStudentId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($student): ?>
            <div class="week-nav">
                <h2><?php echo date('Y年m月d日', strtotime($weekStartDate)); ?>の週</h2>
                <div class="week-nav-buttons">
                    <a href="?student_id=<?php echo $selectedStudentId; ?>&date=<?php echo $prevWeek; ?>">← 前週</a>
                    <a href="?student_id=<?php echo $selectedStudentId; ?>&date=<?php echo date('Y-m-d'); ?>">今週</a>
                    <a href="?student_id=<?php echo $selectedStudentId; ?>&date=<?php echo $nextWeek; ?>">次週 →</a>
                </div>
            </div>

            <?php if (!$weeklyPlan): ?>
                <div class="plan-table-container">
                    <div class="no-plan">
                        <p>この週の計画はまだ作成されていません</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="plan-table-container">
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
                        <form method="POST" action="add_guardian_plan_comment.php">
                            <input type="hidden" name="weekly_plan_id" value="<?php echo $weeklyPlan['id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $selectedStudentId; ?>">
                            <input type="hidden" name="week_start_date" value="<?php echo $weekStartDate; ?>">
                            <textarea name="comment" placeholder="コメントを入力..." required></textarea>
                            <button type="submit">コメントを投稿</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="plan-table-container">
                <div class="no-plan">
                    <p>生徒が登録されていません</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
