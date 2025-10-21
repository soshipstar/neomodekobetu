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

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// POSTデータまたはGETパラメータから取得
$studentIds = $_POST['student_ids'] ?? [];
$activityName = $_POST['activity_name'] ?? '';
$recordDate = $_POST['record_date'] ?? date('Y-m-d');
$activityId = $_GET['activity_id'] ?? null;

// 既存の活動を編集する場合（同じ教室のスタッフが作成した活動も編集可能）
if ($activityId) {
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
    $existingRecord = $stmt->fetch();

    if (!$existingRecord) {
        $_SESSION['error'] = 'この活動にアクセスする権限がありません';
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

// 参加者情報を取得（自分の教室の生徒のみ、セキュリティ対策）
$placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.id IN ($placeholders) AND s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $params = array_merge($studentIds, [$classroomId]);
    $stmt->execute($params);
} else {
    $stmt = $pdo->prepare("
        SELECT id, student_name
        FROM students
        WHERE id IN ($placeholders) AND is_active = 1
        ORDER BY student_name
    ");
    $stmt->execute($studentIds);
}
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

// 追加可能な全生徒を取得（自分の教室の生徒から、すでに参加している生徒を除く）
$availableStudents = [];
if ($classroomId) {
    $currentStudentIds = array_column($students, 'id');
    if (!empty($currentStudentIds)) {
        $placeholders = str_repeat('?,', count($currentStudentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_name
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE s.is_active = 1 AND u.classroom_id = ? AND s.id NOT IN ($placeholders)
            ORDER BY s.student_name
        ");
        $params = array_merge([$classroomId], $currentStudentIds);
        $stmt->execute($params);
    } else {
        // 参加生徒がいない場合は全員表示
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_name
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            ORDER BY s.student_name
        ");
        $stmt->execute([$classroomId]);
    }
    $availableStudents = $stmt->fetchAll();
} else {
    $currentStudentIds = array_column($students, 'id');
    if (!empty($currentStudentIds)) {
        $placeholders = str_repeat('?,', count($currentStudentIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT id, student_name
            FROM students
            WHERE is_active = 1 AND id NOT IN ($placeholders)
            ORDER BY student_name
        ");
        $stmt->execute($currentStudentIds);
    } else {
        $stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
    }
    $availableStudents = $stmt->fetchAll();
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

        .btn-add-student {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0;
        }

        .btn-add-student:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .student-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .student-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-item:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .student-item.selected {
            border-color: #667eea;
            background: #e3f2fd;
        }

        .student-item-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .student-item-check {
            width: 24px;
            height: 24px;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .student-item.selected .student-item-check {
            background: #667eea;
            border-color: #667eea;
        }

        .student-item.selected .student-item-check::after {
            content: '✓';
            color: white;
            font-weight: bold;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .add-student-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .student-card.new {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .remove-student-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .remove-student-btn:hover {
            background: #c82333;
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
            <div style="margin-bottom: 10px;">
                <strong style="color: #667eea; font-size: 18px;">活動名:</strong>
                <span style="font-size: 18px; margin-left: 10px;"><?php echo htmlspecialchars($activityName, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if (isset($existingRecord) && $existingRecord): ?>
                <div style="font-size: 14px; color: #666;">
                    作成者: <?php echo htmlspecialchars($existingRecord['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($existingRecord['staff_id'] == $currentUser['id']): ?>
                        <span style="color: #667eea; font-weight: bold;">(自分)</span>
                    <?php else: ?>
                        <span style="color: #ff9800; font-weight: bold;">(他のスタッフ)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

            <!-- 参加生徒を追加セクション -->
            <?php if (!empty($availableStudents)): ?>
            <div class="add-student-section">
                <button type="button" class="btn-add-student" onclick="openAddStudentModal()">
                    ➕ 参加生徒を追加
                </button>
                <p class="info-text">追加可能な生徒: <?php echo count($availableStudents); ?>名</p>
            </div>
            <?php endif; ?>

            <!-- 送信ボタン -->
            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <?php echo $activityId ? '修正して保存' : '確定して保存'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- 生徒追加モーダル -->
    <div class="modal" id="addStudentModal">
        <div class="modal-content">
            <h3 class="modal-header">参加生徒を追加</h3>
            <div class="student-list" id="availableStudentList">
                <?php foreach ($availableStudents as $student): ?>
                <div class="student-item" data-student-id="<?php echo $student['id']; ?>" data-student-name="<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>" onclick="toggleStudentSelection(this)">
                    <div class="student-item-name"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="student-item-check"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeAddStudentModal()">キャンセル</button>
                <button type="button" class="btn btn-secondary" onclick="addSelectedStudents()">選択した生徒を追加</button>
            </div>
        </div>
    </div>

    <script>
        // 5領域の定義（JavaScriptでも使用）
        const domains = <?php echo json_encode($domains); ?>;

        // 選択された生徒を管理
        let selectedStudentsForAdd = new Set();

        // モーダルを開く
        function openAddStudentModal() {
            document.getElementById('addStudentModal').classList.add('active');
            selectedStudentsForAdd.clear();
            // 選択状態をリセット
            document.querySelectorAll('.student-item').forEach(item => {
                item.classList.remove('selected');
            });
        }

        // モーダルを閉じる
        function closeAddStudentModal() {
            document.getElementById('addStudentModal').classList.remove('active');
            selectedStudentsForAdd.clear();
        }

        // 生徒選択をトグル
        function toggleStudentSelection(element) {
            const studentId = element.dataset.studentId;

            if (element.classList.contains('selected')) {
                element.classList.remove('selected');
                selectedStudentsForAdd.delete(studentId);
            } else {
                element.classList.add('selected');
                selectedStudentsForAdd.add(studentId);
            }
        }

        // 選択した生徒をフォームに追加
        function addSelectedStudents() {
            if (selectedStudentsForAdd.size === 0) {
                alert('追加する生徒を選択してください');
                return;
            }

            const form = document.getElementById('renrakuchoForm');
            const submitSection = document.querySelector('.form-actions');

            selectedStudentsForAdd.forEach(studentId => {
                const studentItem = document.querySelector(`.student-item[data-student-id="${studentId}"]`);
                const studentName = studentItem.dataset.studentName;

                // 生徒カードを作成
                const studentCard = createStudentCard(studentId, studentName);

                // 送信ボタンの前に挿入
                submitSection.parentNode.insertBefore(studentCard, submitSection);

                // モーダルから該当の生徒を削除
                studentItem.remove();
            });

            // 追加可能な生徒数を更新
            updateAvailableStudentCount();

            closeAddStudentModal();

            // スクロールして追加された生徒が見えるようにする
            setTimeout(() => {
                const newCards = document.querySelectorAll('.student-card.new');
                if (newCards.length > 0) {
                    newCards[newCards.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }

        // 生徒カードを作成
        function createStudentCard(studentId, studentName) {
            const card = document.createElement('div');
            card.className = 'student-card new';
            card.dataset.studentId = studentId;

            const domainOptions = Object.entries(domains).map(([key, label]) =>
                `<option value="${key}">${escapeHtml(label)}</option>`
            ).join('');

            card.innerHTML = `
                <button type="button" class="remove-student-btn" onclick="removeStudentCard(${studentId})">✕ この生徒を削除</button>
                <h3>${escapeHtml(studentName)}</h3>

                <input type="hidden" name="students[${studentId}][id]" value="${studentId}">

                <!-- 本日の様子 -->
                <div class="domain-group" style="background: #e3f2fd; padding: 15px; border-radius: 5px; border-left: 4px solid #2196f3;">
                    <h4 style="color: #1976d2;">本日の様子</h4>
                    <textarea
                        name="students[${studentId}][daily_note]"
                        class="domain-textarea"
                        placeholder="本日の全体的な様子を自由に記入してください"
                        style="background: white;"
                    ></textarea>
                </div>

                <!-- 領域1 -->
                <div class="domain-group">
                    <h4>気になったこと 1つ目</h4>
                    <select name="students[${studentId}][domain1]" class="domain-select" required>
                        <option value="">領域を選択してください</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain1_content]"
                        class="domain-textarea"
                        placeholder="気になったことを記入してください"
                        required
                    ></textarea>
                </div>

                <!-- 領域2 -->
                <div class="domain-group">
                    <h4>気になったこと 2つ目</h4>
                    <select name="students[${studentId}][domain2]" class="domain-select" required>
                        <option value="">領域を選択してください</option>
                        ${domainOptions}
                    </select>
                    <textarea
                        name="students[${studentId}][domain2_content]"
                        class="domain-textarea"
                        placeholder="気になったことを記入してください"
                        required
                    ></textarea>
                </div>
            `;

            // アニメーションクラスを一定時間後に削除
            setTimeout(() => {
                card.classList.remove('new');
            }, 300);

            return card;
        }

        // 生徒カードを削除
        function removeStudentCard(studentId) {
            const card = document.querySelector(`.student-card[data-student-id="${studentId}"]`);
            if (!card) return;

            const studentName = card.querySelector('h3').textContent;

            if (confirm(`「${studentName}」の入力を削除しますか？`)) {
                // モーダルのリストに戻す
                const studentList = document.getElementById('availableStudentList');
                const studentItem = document.createElement('div');
                studentItem.className = 'student-item';
                studentItem.dataset.studentId = studentId;
                studentItem.dataset.studentName = studentName;
                studentItem.onclick = function() { toggleStudentSelection(this); };
                studentItem.innerHTML = `
                    <div class="student-item-name">${escapeHtml(studentName)}</div>
                    <div class="student-item-check"></div>
                `;

                studentList.appendChild(studentItem);

                // カードを削除
                card.remove();

                // 追加可能な生徒数を更新
                updateAvailableStudentCount();
            }
        }

        // 追加可能な生徒数を更新
        function updateAvailableStudentCount() {
            const addSection = document.querySelector('.add-student-section');
            if (!addSection) return;

            const count = document.querySelectorAll('#availableStudentList .student-item').length;
            const infoText = addSection.querySelector('.info-text');

            if (count === 0) {
                addSection.style.display = 'none';
            } else {
                addSection.style.display = 'block';
                infoText.textContent = `追加可能な生徒: ${count}名`;
            }
        }

        // HTMLエスケープ
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // モーダル外クリックで閉じる
        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddStudentModal();
            }
        });

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
