<?php
/**
 * 連絡帳入力ページ（スタッフ用）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 学年フィルター取得
$gradeFilter = $_GET['grade'] ?? 'all';

// 日付を取得（URLパラメータから、または本日）
$today = $_GET['date'] ?? date('Y-m-d');

// 本日の曜日を取得
$todayDayOfWeek = date('w', strtotime($today));
$dayColumns = [
    0 => 'scheduled_sunday',
    1 => 'scheduled_monday',
    2 => 'scheduled_tuesday',
    3 => 'scheduled_wednesday',
    4 => 'scheduled_thursday',
    5 => 'scheduled_friday',
    6 => 'scheduled_saturday'
];
$todayColumn = $dayColumns[$todayDayOfWeek];

// 本日が休日かチェック
$stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
$stmt->execute([$today]);
$isTodayHoliday = $stmt->fetchColumn() > 0;

// 本日の予定参加者IDを取得
$scheduledStudentIds = [];
if (!$isTodayHoliday) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM students
        WHERE is_active = 1 AND $todayColumn = 1
    ");
    $stmt->execute();
    $scheduledStudentIds = array_column($stmt->fetchAll(), 'id');
}

// 生徒を取得（学年フィルターと本日の予定参加者フィルター対応）
$sql = "
    SELECT id, student_name, grade_level
    FROM students
    WHERE is_active = 1
";

if ($gradeFilter === 'scheduled') {
    // 本日の予定参加者フィルター
    if (empty($scheduledStudentIds)) {
        $allStudents = [];
    } else {
        $placeholders = str_repeat('?,', count($scheduledStudentIds) - 1) . '?';
        $sql .= " AND id IN ($placeholders)";
        $sql .= " ORDER BY grade_level, student_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($scheduledStudentIds);
        $allStudents = $stmt->fetchAll();
    }
} else {
    if ($gradeFilter !== 'all') {
        $sql .= " AND grade_level = :grade_level";
    }

    $sql .= " ORDER BY grade_level, student_name";

    $stmt = $pdo->prepare($sql);

    if ($gradeFilter !== 'all') {
        $stmt->execute(['grade_level' => $gradeFilter]);
    } else {
        $stmt->execute();
    }

    $allStudents = $stmt->fetchAll();
}

// 既存の本日の記録があるかチェック
$stmt = $pdo->prepare("
    SELECT dr.id, dr.common_activity, dr.record_date
    FROM daily_records dr
    WHERE dr.record_date = ? AND dr.staff_id = ?
");
$stmt->execute([$today, $currentUser['id']]);
$existingRecord = $stmt->fetch();

// 既存の記録がある場合、参加者を取得
$existingParticipants = [];
if ($existingRecord) {
    $stmt = $pdo->prepare("
        SELECT sr.*, s.student_name
        FROM student_records sr
        JOIN students s ON sr.student_id = s.id
        WHERE sr.daily_record_id = ?
    ");
    $stmt->execute([$existingRecord['id']]);
    $existingParticipants = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>連絡帳入力 - 個別支援連絡帳システム</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .date-info {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 18px;
            color: #333;
        }

        .selection-area {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .selection-area h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .student-selection {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .student-checkbox {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .student-checkbox:hover {
            background: #e9ecef;
        }

        .student-checkbox input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .add-participants-btn {
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .add-participants-btn:hover {
            background: #218838;
        }

        .form-area {
            display: none;
        }

        .form-area.active {
            display: block;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .grade-filter {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .grade-filter label {
            font-weight: 600;
            color: #333;
        }

        .grade-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }

        .grade-btn:hover {
            background: #f0f0ff;
        }

        .grade-btn.active {
            background: #667eea;
            color: white;
        }

        .grade-btn[style*="border-color: #28a745"]:hover {
            background: #d4edda;
        }

        .grade-btn[style*="border-color: #28a745"].active {
            background: #28a745;
            color: white;
        }

        .student-grade-badge {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }

        .badge-elementary {
            background: #ffeaa7;
            color: #d63031;
        }

        .badge-junior-high {
            background: #74b9ff;
            color: #0984e3;
        }

        .badge-high-school {
            background: #a29bfe;
            color: #6c5ce7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>連絡帳入力 - 新規活動追加</h1>
            <div class="user-info">
                <a href="kakehashi_staff.php" style="padding: 8px 16px; background: #764ba2; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; margin-right: 10px;">🌉 スタッフかけはし</a>
                <a href="kakehashi_guardian_view.php" style="padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; margin-right: 10px;">📋 保護者かけはし確認</a>
                <a href="renrakucho_activities.php" style="padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; margin-right: 10px;">活動一覧</a>
                <span><?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?>さん</span>
                <a href="/logout.php" class="logout-btn">ログアウト</a>
            </div>
        </div>

        <div class="date-info">
            記録日: <?php echo date('Y年m月d日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($today))] . '）', strtotime($today)); ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php
                echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($isTodayHoliday): ?>
            <div class="error-message">
                本日は休日です。
            </div>
        <?php endif; ?>

        <?php if ($existingRecord): ?>
            <div class="success-message">
                本日の記録が既に存在します。修正する場合は下記のフォームから編集してください。
            </div>
        <?php endif; ?>

        <!-- 学年フィルター -->
        <div class="grade-filter">
            <label>フィルター:</label>
            <a href="?date=<?php echo urlencode($today); ?>&grade=all" class="grade-btn <?php echo $gradeFilter === 'all' ? 'active' : ''; ?>">すべて</a>
            <a href="?date=<?php echo urlencode($today); ?>&grade=scheduled" class="grade-btn <?php echo $gradeFilter === 'scheduled' ? 'active' : ''; ?>" style="border-color: #28a745; color: #28a745;">本日の予定参加者<?php if (!$isTodayHoliday && !empty($scheduledStudentIds)): ?> (<?php echo count($scheduledStudentIds); ?>名)<?php endif; ?></a>
            <a href="?date=<?php echo urlencode($today); ?>&grade=elementary" class="grade-btn <?php echo $gradeFilter === 'elementary' ? 'active' : ''; ?>">小学生</a>
            <a href="?date=<?php echo urlencode($today); ?>&grade=junior_high" class="grade-btn <?php echo $gradeFilter === 'junior_high' ? 'active' : ''; ?>">中学生</a>
            <a href="?date=<?php echo urlencode($today); ?>&grade=high_school" class="grade-btn <?php echo $gradeFilter === 'high_school' ? 'active' : ''; ?>">高校生</a>
        </div>

        <div class="selection-area">
            <h2>新しい活動の追加</h2>
            <div style="margin-bottom: 20px;">
                <label for="activityName" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">活動名</label>
                <input
                    type="text"
                    id="activityName"
                    placeholder="例: 午前の活動、外出活動、制作活動など"
                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
                    required
                >
            </div>

            <h2 style="margin-top: 20px;">参加者選択</h2>
            <div class="student-selection">
                <?php
                $gradeLabelMap = [
                    'elementary' => ['小', 'badge-elementary'],
                    'junior_high' => ['中', 'badge-junior-high'],
                    'high_school' => ['高', 'badge-high-school']
                ];

                foreach ($allStudents as $student):
                    $gradeInfo = $gradeLabelMap[$student['grade_level']] ?? ['?', ''];
                ?>
                    <label class="student-checkbox">
                        <input
                            type="checkbox"
                            name="students[]"
                            value="<?php echo $student['id']; ?>"
                            data-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo isset($existingParticipants[$student['id']]) ? 'checked' : ''; ?>
                        >
                        <?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="student-grade-badge <?php echo $gradeInfo[1]; ?>"><?php echo $gradeInfo[0]; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="button" class="add-participants-btn" id="addParticipantsBtn">参加者を追加</button>
        </div>

        <div class="form-area" id="formArea">
            <!-- フォームはJavaScriptで動的に生成 -->
        </div>
    </div>

    <script>
        const addParticipantsBtn = document.getElementById('addParticipantsBtn');
        const formArea = document.getElementById('formArea');
        const existingRecord = <?php echo json_encode($existingRecord); ?>;
        const existingParticipants = <?php echo json_encode($existingParticipants); ?>;

        addParticipantsBtn.addEventListener('click', function() {
            const activityName = document.getElementById('activityName').value.trim();
            const checkedBoxes = document.querySelectorAll('input[name="students[]"]:checked');

            if (activityName === '') {
                alert('活動名を入力してください');
                return;
            }

            if (checkedBoxes.length === 0) {
                alert('参加者を選択してください');
                return;
            }

            // 次のページ（フォーム入力）へ遷移
            const studentIds = Array.from(checkedBoxes).map(cb => cb.value);

            // フォーム入力ページへデータを渡して遷移
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'renrakucho_form.php';

            // 活動名を追加
            const activityInput = document.createElement('input');
            activityInput.type = 'hidden';
            activityInput.name = 'activity_name';
            activityInput.value = activityName;
            form.appendChild(activityInput);

            // 日付を追加
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'record_date';
            dateInput.value = '<?php echo $today; ?>';
            form.appendChild(dateInput);

            // 参加者IDを追加
            studentIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'student_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>
