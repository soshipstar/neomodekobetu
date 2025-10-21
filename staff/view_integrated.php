<?php
/**
 * 統合内容閲覧ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

if (!$activityId) {
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動情報を取得（同じ教室のスタッフが作成した活動も閲覧可能）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
               u.full_name as staff_name
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$activityId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
               u.full_name as staff_name
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ?
    ");
    $stmt->execute([$activityId]);
}
$activity = $stmt->fetch();

if (!$activity) {
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 送信済みの統合内容のみを取得
$stmt = $pdo->prepare("
    SELECT
        inote.id,
        inote.integrated_content,
        inote.is_sent,
        inote.sent_at,
        inote.created_at,
        s.student_name,
        s.grade_level
    FROM integrated_notes inote
    INNER JOIN students s ON inote.student_id = s.id
    WHERE inote.daily_record_id = ? AND inote.is_sent = 1
    ORDER BY s.student_name
");
$stmt->execute([$activityId]);
$integratedNotes = $stmt->fetchAll();

function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
    ];
    return $labels[$gradeLevel] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送信済み内容の閲覧 - <?php echo htmlspecialchars($activity['activity_name']); ?></title>
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .activity-info {
            color: #666;
            font-size: 14px;
        }

        .back-btn {
            display: inline-block;
            margin-top: 15px;
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

        .note-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .grade-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            color: white;
            background: #667eea;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-sent {
            background: #d4edda;
            color: #155724;
        }

        .status-not-sent {
            background: #fff3cd;
            color: #856404;
        }

        .note-content {
            color: #333;
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
            margin-bottom: 15px;
        }

        .note-meta {
            color: #999;
            font-size: 13px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .empty-message {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-message h2 {
            margin-bottom: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 送信済み内容の閲覧</h1>
            <div class="activity-info">
                <strong>活動名:</strong> <?php echo htmlspecialchars($activity['activity_name']); ?><br>
                <strong>記録日:</strong> <?php echo date('Y年n月j日', strtotime($activity['record_date'])); ?><br>
                <strong>作成者:</strong> <?php echo htmlspecialchars($activity['staff_name']); ?>
                <?php if ($activity['staff_id'] == $currentUser['id']): ?>
                    <span style="color: #667eea; font-weight: bold;">(自分)</span>
                <?php endif; ?>
            </div>
            <a href="renrakucho_activities.php?date=<?php echo $activity['record_date']; ?>" class="back-btn">← 活動一覧に戻る</a>
        </div>

        <?php if (empty($integratedNotes)): ?>
            <div class="empty-message">
                <h2>送信済みの内容がありません</h2>
                <p>「統合内容を編集」から統合内容を編集し、保護者に送信してください。</p>
            </div>
        <?php else: ?>
            <?php foreach ($integratedNotes as $note): ?>
                <div class="note-card">
                    <div class="student-header">
                        <div class="student-info">
                            <span class="student-name"><?php echo htmlspecialchars($note['student_name']); ?></span>
                            <span class="grade-badge"><?php echo getGradeLabel($note['grade_level']); ?></span>
                        </div>
                        <div>
                            <span class="status-badge status-sent">送信済み</span>
                        </div>
                    </div>

                    <div class="note-content">
                        <?php echo htmlspecialchars($note['integrated_content']); ?>
                    </div>

                    <div class="note-meta">
                        統合日時: <?php echo date('Y年n月j日 H:i', strtotime($note['created_at'])); ?>
                        <?php if ($note['is_sent']): ?>
                            | 送信日時: <?php echo date('Y年n月j日 H:i', strtotime($note['sent_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
