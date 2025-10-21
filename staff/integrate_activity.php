<?php
/**
 * 活動内容統合ページ（ChatGPT統合）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/chatgpt.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = $_GET['activity_id'] ?? null;

if (!$activityId) {
    $_SESSION['error'] = '活動IDが指定されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動情報を取得（同じ教室のスタッフが作成した活動も統合可能）
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

// 生徒ごとの記録を取得
$stmt = $pdo->prepare("
    SELECT sr.student_id, s.student_name, sr.daily_note,
           sr.domain1, sr.domain1_content, sr.domain2, sr.domain2_content
    FROM student_records sr
    JOIN students s ON sr.student_id = s.id
    WHERE sr.daily_record_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$activityId]);
$studentRecords = $stmt->fetchAll();

// 既存の統合記録を取得（あれば）
$stmt = $pdo->prepare("
    SELECT student_id, integrated_content, is_sent
    FROM integrated_notes
    WHERE daily_record_id = ?
");
$stmt->execute([$activityId]);
$existingIntegrations = [];
foreach ($stmt->fetchAll() as $row) {
    $existingIntegrations[$row['student_id']] = $row;
}

// ChatGPTで統合文章を生成
$integratedNotes = [];
foreach ($studentRecords as $record) {
    $studentId = $record['student_id'];

    // 既に統合済みの場合はそれを使用
    if (isset($existingIntegrations[$studentId])) {
        $integratedNotes[$studentId] = [
            'student_name' => $record['student_name'],
            'content' => $existingIntegrations[$studentId]['integrated_content'],
            'is_sent' => $existingIntegrations[$studentId]['is_sent']
        ];
        continue;
    }

    // ChatGPTで統合
    $domains = [];
    if (!empty($record['domain1']) && !empty($record['domain1_content'])) {
        $domains[] = [
            'category' => $record['domain1'],
            'content' => $record['domain1_content']
        ];
    }
    if (!empty($record['domain2']) && !empty($record['domain2_content'])) {
        $domains[] = [
            'category' => $record['domain2'],
            'content' => $record['domain2_content']
        ];
    }

    $integratedContent = generateIntegratedNote(
        $activity['activity_name'],
        $activity['common_activity'],
        $record['daily_note'] ?? '',
        $domains
    );

    if ($integratedContent === false) {
        $integratedContent = "統合に失敗しました。手動で編集してください。\n\n" .
            "【活動内容】\n" . $activity['common_activity'] . "\n\n" .
            "【本日の様子】\n" . ($record['daily_note'] ?? '') . "\n\n" .
            "【気になったこと】\n" . implode("\n", array_map(function($d) {
                return getDomainLabel($d['category']) . ': ' . $d['content'];
            }, $domains));
    }

    $integratedNotes[$studentId] = [
        'student_name' => $record['student_name'],
        'content' => $integratedContent,
        'is_sent' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>活動内容の統合 - 個別支援連絡帳システム</title>
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

        .activity-info {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .activity-info h2 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .student-note {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .student-note h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .student-note textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 200px;
            line-height: 1.8;
        }

        .sent-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #28a745;
            color: white;
            border-radius: 15px;
            font-size: 12px;
            margin-left: 10px;
        }

        .submit-btn {
            width: 100%;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-btn:hover {
            background: #218838;
        }

        .info-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>活動内容の統合</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動一覧へ</a>
        </div>

        <div class="activity-info">
            <h2><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                作成者: <?php echo htmlspecialchars($activity['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($activity['staff_id'] == $currentUser['id']): ?>
                    <span style="color: #667eea; font-weight: bold;">(自分)</span>
                <?php endif; ?>
            </p>
            <p><?php echo nl2br(htmlspecialchars($activity['common_activity'], ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <p class="info-text" style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
            ChatGPTが生成した文章を確認・編集できます。編集後、「活動内容を送信」ボタンで保護者に配信されます。
        </p>

        <form method="POST" action="send_to_guardians.php">
            <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">

            <?php foreach ($integratedNotes as $studentId => $note): ?>
                <div class="student-note">
                    <h3>
                        <?php echo htmlspecialchars($note['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($note['is_sent']): ?>
                            <span class="sent-badge">送信済み</span>
                        <?php endif; ?>
                    </h3>
                    <textarea
                        name="notes[<?php echo $studentId; ?>]"
                        <?php echo $note['is_sent'] ? 'readonly' : ''; ?>
                    ><?php echo htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="submit-btn">活動内容を送信</button>
        </form>
    </div>
</body>
</html>
