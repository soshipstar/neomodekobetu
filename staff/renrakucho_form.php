<?php
/**
 * 連絡帳入力フォームページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// POSTデータまたはGETパラメータから取得
$studentIds = $_POST['student_ids'] ?? [];
$activityName = $_POST['activity_name'] ?? '';
$recordDate = $_POST['record_date'] ?? date('Y-m-d');
$activityId = $_GET['activity_id'] ?? null;

// 既存の活動を編集する場合
if ($activityId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date
        FROM daily_records dr
        WHERE dr.id = ? AND dr.staff_id = ?
    ");
    $stmt->execute([$activityId, $currentUser['id']]);
    $existingRecord = $stmt->fetch();

    if (!$existingRecord) {
        header('Location: renrakucho_activities.php');
        exit;
    }

    $activityName = $existingRecord['activity_name'];

    // 既存の参加者を取得
    $stmt = $pdo->prepare("
        SELECT DISTINCT student_id FROM student_records WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $studentIds = array_column($stmt->fetchAll(), 'student_id');
}

if (empty($studentIds)) {
    header('Location: renrakucho.php');
    exit;
}

// 参加者情報を取得
$placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT id, student_name
    FROM students
    WHERE id IN ($placeholders) AND is_active = 1
    ORDER BY student_name
");
$stmt->execute($studentIds);
$students = $stmt->fetchAll();

// 既存の学生記録を取得
$existingStudentRecords = [];

if ($activityId) {
    $stmt = $pdo->prepare("
        SELECT student_id, daily_note, domain1, domain1_content, domain2, domain2_content
        FROM student_records
        WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);
    $records = $stmt->fetchAll();

    foreach ($records as $record) {
        $existingStudentRecords[$record['student_id']] = $record;
    }
}

// 5領域の定義
$domains = [
    'health_life' => '健康・生活',
    'motor_sensory' => '運動・感覚',
    'cognitive_behavior' => '認知・行動',
    'language_communication' => '言語・コミュニケーション',
    'social_relations' => '人間関係・社会性'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>連絡帳入力フォーム - 個別支援連絡帳システム</title>
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

        .common-activity-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #ffc107;
        }

        .common-activity-section h2 {
            color: #856404;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .common-activity-section textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .student-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .student-card h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .domain-selection {
            margin-bottom: 20px;
        }

        .domain-selection h4 {
            color: #555;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .domain-group {
            margin-bottom: 25px;
        }

        .domain-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 10px;
            background: white;
        }

        .domain-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-secondary {
            background: #007bff;
            color: white;
        }

        .info-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>連絡帳入力フォーム</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動一覧へ</a>
        </div>

        <div style="background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);">
            <strong style="color: #667eea; font-size: 18px;">活動名:</strong>
            <span style="font-size: 18px; margin-left: 10px;"><?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <form method="POST" action="renrakucho_save.php" id="renrakuchoForm">
            <input type="hidden" name="activity_name" value="<?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="record_date" value="<?php echo htmlspecialchars($recordDate, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($activityId): ?>
                <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">
            <?php endif; ?>

            <!-- 共通活動入力欄 -->
            <div class="common-activity-section">
                <h2>本日の活動（共通）</h2>
                <p class="info-text">全ての参加者に反映される共通の活動内容を記入してください</p>
                <textarea
                    name="common_activity"
                    id="commonActivity"
                    placeholder="例: 公園で散歩、音楽活動、制作活動など"
                ><?php echo htmlspecialchars($existingRecord['common_activity'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <!-- 個別の生徒記録 -->
            <?php foreach ($students as $student): ?>
                <?php
                $studentId = $student['id'];
                $existingData = $existingStudentRecords[$studentId] ?? null;
                ?>
                <div class="student-card">
                    <h3><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?></h3>

                    <input type="hidden" name="students[<?php echo $studentId; ?>][id]" value="<?php echo $studentId; ?>">

                    <!-- 本日の様子 -->
                    <div class="domain-group" style="background: #e3f2fd; padding: 15px; border-radius: 5px; border-left: 4px solid #2196f3;">
                        <h4 style="color: #1976d2;">本日の様子</h4>
                        <textarea
                            name="students[<?php echo $studentId; ?>][daily_note]"
                            class="domain-textarea"
                            placeholder="本日の全体的な様子を自由に記入してください"
                            style="background: white;"
                        ><?php echo htmlspecialchars($existingData['daily_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 領域1 -->
                    <div class="domain-group">
                        <h4>気になったこと 1つ目</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain1]"
                            class="domain-select"
                            required
                        >
                            <option value="">領域を選択してください</option>
                            <?php foreach ($domains as $key => $label): ?>
                                <option
                                    value="<?php echo $key; ?>"
                                    <?php echo ($existingData && $existingData['domain1'] === $key) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <textarea
                            name="students[<?php echo $studentId; ?>][domain1_content]"
                            class="domain-textarea"
                            placeholder="気になったことを記入してください"
                            required
                        ><?php echo htmlspecialchars($existingData['domain1_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 領域2 -->
                    <div class="domain-group">
                        <h4>気になったこと 2つ目</h4>
                        <select
                            name="students[<?php echo $studentId; ?>][domain2]"
                            class="domain-select"
                            required
                        >
                            <option value="">領域を選択してください</option>
                            <?php foreach ($domains as $key => $label): ?>
                                <option
                                    value="<?php echo $key; ?>"
                                    <?php echo ($existingData && $existingData['domain2'] === $key) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <textarea
                            name="students[<?php echo $studentId; ?>][domain2_content]"
                            class="domain-textarea"
                            placeholder="気になったことを記入してください"
                            required
                        ><?php echo htmlspecialchars($existingData['domain2_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- 送信ボタン -->
            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <?php echo $activityId ? '修正して保存' : '確定して保存'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        // フォーム送信前のバリデーション
        document.getElementById('renrakuchoForm').addEventListener('submit', function(e) {
            const commonActivity = document.getElementById('commonActivity').value.trim();

            if (commonActivity === '') {
                alert('本日の活動（共通）を入力してください');
                e.preventDefault();
                return false;
            }

            // 各生徒の領域が重複していないかチェック
            const studentCards = document.querySelectorAll('.student-card');
            let hasError = false;

            studentCards.forEach(card => {
                const selects = card.querySelectorAll('.domain-select');
                const domain1 = selects[0].value;
                const domain2 = selects[1].value;

                if (domain1 === domain2 && domain1 !== '') {
                    alert('同じ領域を2回選択することはできません');
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            return true;
        });
    </script>
</body>
</html>
