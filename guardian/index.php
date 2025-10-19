<?php
/**
 * 保護者用トップページ
 * 送信された活動記録を表示
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();
checkUserType('guardian');

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$stmt = $pdo->prepare("
    SELECT c.*, u.classroom_id as user_classroom_id FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// デバッグ用（後で削除）
error_log("Guardian ID: " . $guardianId);
error_log("Classroom data: " . print_r($classroom, true));

// デバッグ: 教室情報が取得できない場合、ユーザーの教室IDを確認
if (!$classroom) {
    $stmt = $pdo->prepare("SELECT classroom_id FROM users WHERE id = ?");
    $stmt->execute([$guardianId]);
    $userInfo = $stmt->fetch();
    error_log("User classroom_id: " . print_r($userInfo, true));

    // classroom_idがある場合は直接教室を取得
    if ($userInfo && $userInfo['classroom_id']) {
        $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
        $stmt->execute([$userInfo['classroom_id']]);
        $classroom = $stmt->fetch();
        error_log("Direct classroom fetch: " . print_r($classroom, true));
    }
}

// この保護者に紐づく生徒を取得
$stmt = $pdo->prepare("
    SELECT id, student_name, grade_level
    FROM students
    WHERE guardian_id = ? AND is_active = 1
    ORDER BY student_name
");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 各生徒の最新の連絡帳を取得
$notesData = [];
foreach ($students as $student) {
    $stmt = $pdo->prepare("
        SELECT
            inote.id,
            inote.integrated_content,
            inote.sent_at,
            dr.activity_name,
            dr.record_date
        FROM integrated_notes inote
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        WHERE inote.student_id = ? AND inote.is_sent = 1
        ORDER BY dr.record_date DESC, inote.sent_at DESC
        LIMIT 10
    ");
    $stmt->execute([$student['id']]);
    $notesData[$student['id']] = $stmt->fetchAll();
}

// 学年表示用のラベル
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
    <title>連絡帳 - 保護者ページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .student-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .student-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        .student-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-right: 10px;
        }
        .grade-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        .note-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .activity-name {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        .note-date {
            color: #666;
            font-size: 14px;
        }
        .note-content {
            color: #333;
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
        }
        .no-notes {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h2 {
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <?php if ($classroom && !empty($classroom['logo_path']) && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
                    <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ" style="height: 50px; width: auto;">
                <?php else: ?>
                    <div style="font-size: 40px;">📖</div>
                <?php endif; ?>
                <div>
                    <h1>連絡帳</h1>
                    <?php if ($classroom): ?>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            <?= htmlspecialchars($classroom['classroom_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; align-items: center;">
                <span class="user-info">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>さん（保護者）
                </span>
                <a href="../logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

        <?php if (empty($students)): ?>
            <div class="student-section">
                <div class="empty-state">
                    <h2>お子様の情報が登録されていません</h2>
                    <p>管理者にお問い合わせください。</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <div class="student-section">
                    <div class="student-header">
                        <span class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></span>
                        <span class="grade-badge"><?php echo getGradeLabel($student['grade_level']); ?></span>
                    </div>

                    <?php if (empty($notesData[$student['id']])): ?>
                        <div class="no-notes">
                            まだ連絡帳が送信されていません
                        </div>
                    <?php else: ?>
                        <?php foreach ($notesData[$student['id']] as $note): ?>
                            <div class="note-item">
                                <div class="note-header">
                                    <span class="activity-name"><?php echo htmlspecialchars($note['activity_name']); ?></span>
                                    <span class="note-date">
                                        <?php echo date('Y年n月j日', strtotime($note['record_date'])); ?>
                                        （送信: <?php echo date('H:i', strtotime($note['sent_at'])); ?>）
                                    </span>
                                </div>
                                <div class="note-content"><?php echo htmlspecialchars($note['integrated_content']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
