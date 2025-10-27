<?php
/**
 * 生徒用提出物管理画面
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 保護者用チャットルームを取得
$stmt = $pdo->prepare("
    SELECT id FROM chat_rooms WHERE student_id = ?
");
$stmt->execute([$studentId]);
$chatRoom = $stmt->fetch();

$submissions = [];
if ($chatRoom) {
    // 提出物を取得
    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.title,
            sr.description,
            sr.due_date,
            sr.is_completed,
            sr.completed_at,
            sr.completion_notes,
            sr.attachment_path,
            sr.attachment_original_name,
            sr.attachment_size,
            sr.created_at
        FROM submission_requests sr
        WHERE sr.room_id = ?
        ORDER BY
            CASE WHEN sr.is_completed = 0 THEN 0 ELSE 1 END,
            sr.due_date ASC
    ");
    $stmt->execute([$chatRoom['id']]);
    $submissions = $stmt->fetchAll();
}

// 未提出と提出済みに分ける
$pending = array_filter($submissions, function($s) { return !$s['is_completed']; });
$completed = array_filter($submissions, function($s) { return $s['is_completed']; });
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提出物管理 - 個別支援連絡帳システム</title>
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
            max-width: 1000px;
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

        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .submission-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .submission-card.urgent {
            border-left-color: #e74c3c;
            background: #fff5f5;
        }

        .submission-card.overdue {
            border-left-color: #95a5a6;
            background: #f5f5f5;
        }

        .submission-card.completed {
            border-left-color: #28a745;
            background: #f0f8f0;
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .submission-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }

        .submission-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            margin-left: 10px;
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
            background: #667eea;
            color: white;
        }

        .submission-badge.completed {
            background: #28a745;
            color: white;
        }

        .submission-due {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .submission-description {
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .submission-attachment {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }

        .submission-attachment a {
            color: #667eea;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-attachment a:hover {
            text-decoration: underline;
        }

        .completion-note {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
            border-left: 3px solid #28a745;
        }

        .completion-note-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .completion-note-text {
            color: #333;
            line-height: 1.5;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .summary-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 14px;
            color: #666;
        }

        .summary-card.urgent .summary-number {
            color: #e74c3c;
        }

        .summary-card.completed .summary-number {
            color: #28a745;
        }

        @media (max-width: 768px) {
            .submission-header {
                flex-direction: column;
            }

            .submission-badge {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 提出物管理</h1>
            <a href="dashboard.php" class="back-btn">← マイページ</a>
        </div>

        <?php
        $today = date('Y-m-d');
        $urgentCount = 0;
        $pendingCount = count($pending);
        $completedCount = count($completed);

        foreach ($pending as $sub) {
            $daysLeft = (strtotime($sub['due_date']) - strtotime($today)) / 86400;
            if ($daysLeft <= 3) $urgentCount++;
        }
        ?>

        <div class="summary">
            <div class="summary-card urgent">
                <div class="summary-number"><?php echo $urgentCount; ?></div>
                <div class="summary-label">⚠️ 期限間近</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $pendingCount; ?></div>
                <div class="summary-label">📝 未提出</div>
            </div>
            <div class="summary-card completed">
                <div class="summary-number"><?php echo $completedCount; ?></div>
                <div class="summary-label">✅ 提出済み</div>
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
                            <span class="submission-badge <?php echo $badgeClass; ?>">
                                <?php echo $badgeText; ?>
                            </span>
                        </div>

                        <div class="submission-due">
                            📅 提出期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                            <?php if ($daysLeft >= 0): ?>
                                （あと<?php echo ceil($daysLeft); ?>日）
                            <?php endif; ?>
                        </div>

                        <div class="submission-description">
                            <?php echo nl2br(htmlspecialchars($sub['description'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>

                        <?php if ($sub['attachment_path']): ?>
                            <div class="submission-attachment">
                                <a href="../staff/download_submission_attachment.php?id=<?php echo $sub['id']; ?>" target="_blank">
                                    📎 <?php echo htmlspecialchars($sub['attachment_original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo number_format($sub['attachment_size'] / 1024, 1); ?>KB)
                                </a>
                            </div>
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
                            <span class="submission-badge completed">提出済み</span>
                        </div>

                        <div class="submission-due">
                            📅 提出期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                        </div>

                        <div class="submission-description">
                            <?php echo nl2br(htmlspecialchars($sub['description'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>

                        <?php if ($sub['attachment_path']): ?>
                            <div class="submission-attachment">
                                <a href="../staff/download_submission_attachment.php?id=<?php echo $sub['id']; ?>" target="_blank">
                                    📎 <?php echo htmlspecialchars($sub['attachment_original_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo number_format($sub['attachment_size'] / 1024, 1); ?>KB)
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($sub['completion_notes']): ?>
                            <div class="completion-note">
                                <div class="completion-note-label">スタッフからのコメント:</div>
                                <div class="completion-note-text">
                                    <?php echo nl2br(htmlspecialchars($sub['completion_notes'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
