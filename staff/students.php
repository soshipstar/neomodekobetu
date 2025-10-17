<?php
/**
 * スタッフ用 - 生徒管理ページ
 * 生徒の登録・編集
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType('staff');

$pdo = getDbConnection();

// 全生徒を取得
$stmt = $pdo->query("
    SELECT
        s.id,
        s.student_name,
        s.birth_date,
        s.kakehashi_initial_date,
        s.grade_level,
        s.guardian_id,
        s.is_active,
        s.created_at,
        s.scheduled_monday,
        s.scheduled_tuesday,
        s.scheduled_wednesday,
        s.scheduled_thursday,
        s.scheduled_friday,
        s.scheduled_saturday,
        s.scheduled_sunday,
        u.full_name as guardian_name
    FROM students s
    LEFT JOIN users u ON s.guardian_id = u.id
    ORDER BY s.is_active DESC, s.student_name
");
$students = $stmt->fetchAll();

// 保護者一覧を取得（生徒登録用）
$stmt = $pdo->query("
    SELECT id, full_name, username
    FROM users
    WHERE user_type = 'guardian' AND is_active = 1
    ORDER BY full_name
");
$guardians = $stmt->fetchAll();

// 学年表示用のラベル
function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
    ];
    return $labels[$gradeLevel] ?? '';
}

// 学年バッジの色
function getGradeBadgeColor($gradeLevel) {
    $colors = [
        'elementary' => '#28a745',
        'junior_high' => '#007bff',
        'high_school' => '#dc3545'
    ];
    return $colors[$gradeLevel] ?? '#6c757d';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒管理 - スタッフページ</title>
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
            max-width: 1200px;
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
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        table tr:hover {
            background: #f8f9fa;
        }
        .grade-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
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
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 生徒管理</h1>
            <div class="header-actions">
                <span class="user-info"><?php echo htmlspecialchars($_SESSION['full_name']); ?>（スタッフ）</span>
                <a href="renrakucho_activities.php" class="btn btn-secondary btn-sm">連絡帳に戻る</a>
                <a href="../logout.php" class="btn btn-danger btn-sm">ログアウト</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php
                switch ($_GET['success']) {
                    case 'created':
                        echo '生徒を登録しました。';
                        break;
                    case 'updated':
                        echo '生徒情報を更新しました。';
                        break;
                    default:
                        echo '処理が完了しました。';
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                ⚠ <?php echo htmlspecialchars($_SESSION['warning']); ?>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>

        <!-- 新規登録フォーム -->
        <div class="content-box">
            <h2 class="section-title">新規生徒登録</h2>
            <form action="students_save.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>生徒名 *</label>
                        <input type="text" name="student_name" required placeholder="例: 山田 太郎">
                    </div>
                    <div class="form-group">
                        <label>生年月日 *</label>
                        <input type="date" name="birth_date" required>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">※学年は生年月日から自動で計算されます</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>🌉 初回かけはし提出期限（任意）</label>
                    <input type="date" name="kakehashi_initial_date">
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※提出期限を設定すると、その翌日から6か月間のかけはし期間が自動で半年ごとに生成されます</div>
                </div>
                <div class="form-group">
                    <label>保護者（任意）</label>
                    <select name="guardian_id">
                        <option value="">保護者を選択（後で設定可能）</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?php echo $guardian['id']; ?>">
                                <?php echo htmlspecialchars($guardian['full_name']) . ' (' . htmlspecialchars($guardian['username']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>参加予定曜日</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_monday" value="1"> 月曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_tuesday" value="1"> 火曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_wednesday" value="1"> 水曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_thursday" value="1"> 木曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_friday" value="1"> 金曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_saturday" value="1"> 土曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_sunday" value="1"> 日曜日
                        </label>
                    </div>
                </div>
                <div style="text-align: right;">
                    <button type="submit" class="btn btn-success">登録する</button>
                </div>
            </form>
        </div>

        <!-- 生徒一覧 -->
        <div class="content-box">
            <h2 class="section-title">生徒一覧</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>生徒名</th>
                        <th>生年月日</th>
                        <th>年齢</th>
                        <th>学年</th>
                        <th>保護者</th>
                        <th>状態</th>
                        <th>登録日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
                                登録されている生徒がいません
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $age = $student['birth_date'] ? calculateAge($student['birth_date']) : '-';
                            $calculatedGrade = $student['birth_date'] ? calculateGradeLevel($student['birth_date']) : $student['grade_level'];
                            ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td><?php echo $student['birth_date'] ? date('Y/m/d', strtotime($student['birth_date'])) : '-'; ?></td>
                                <td><?php echo $age !== '-' ? $age . '歳' : '-'; ?></td>
                                <td>
                                    <span class="grade-badge" style="background-color: <?php echo getGradeBadgeColor($calculatedGrade); ?>">
                                        <?php echo getGradeLabel($calculatedGrade); ?>
                                    </span>
                                </td>
                                <td><?php echo $student['guardian_name'] ? htmlspecialchars($student['guardian_name']) : '-'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $student['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $student['is_active'] ? '有効' : '無効'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)" class="btn btn-primary btn-sm">編集</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 編集モーダル -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-header">生徒情報の編集</h3>
            <form action="students_save.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" id="edit_student_id">
                <div class="form-group">
                    <label>生徒名 *</label>
                    <input type="text" name="student_name" id="edit_student_name" required>
                </div>
                <div class="form-group">
                    <label>生年月日 *</label>
                    <input type="date" name="birth_date" id="edit_birth_date" required>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※学年は生年月日から自動で計算されます</div>
                </div>
                <div class="form-group">
                    <label>🌉 初回かけはし提出期限（任意）</label>
                    <input type="date" name="kakehashi_initial_date" id="edit_kakehashi_initial_date">
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※変更すると期間が再生成されます（既存の入力データは保持されます）</div>
                </div>
                <div class="form-group">
                    <label>保護者（任意）</label>
                    <select name="guardian_id" id="edit_guardian_id">
                        <option value="">保護者なし</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?php echo $guardian['id']; ?>">
                                <?php echo htmlspecialchars($guardian['full_name']) . ' (' . htmlspecialchars($guardian['username']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>参加予定曜日</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_monday" id="edit_scheduled_monday" value="1"> 月曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_tuesday" id="edit_scheduled_tuesday" value="1"> 火曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_wednesday" id="edit_scheduled_wednesday" value="1"> 水曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_thursday" id="edit_scheduled_thursday" value="1"> 木曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_friday" id="edit_scheduled_friday" value="1"> 金曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_saturday" id="edit_scheduled_saturday" value="1"> 土曜日
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px; font-weight: normal;">
                            <input type="checkbox" name="scheduled_sunday" id="edit_scheduled_sunday" value="1"> 日曜日
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">キャンセル</button>
                    <button type="submit" class="btn btn-primary">更新する</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editStudent(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_student_name').value = student.student_name;
            document.getElementById('edit_birth_date').value = student.birth_date || '';
            document.getElementById('edit_kakehashi_initial_date').value = student.kakehashi_initial_date || '';
            document.getElementById('edit_guardian_id').value = student.guardian_id || '';

            // 曜日チェックボックスの設定
            document.getElementById('edit_scheduled_monday').checked = student.scheduled_monday == 1;
            document.getElementById('edit_scheduled_tuesday').checked = student.scheduled_tuesday == 1;
            document.getElementById('edit_scheduled_wednesday').checked = student.scheduled_wednesday == 1;
            document.getElementById('edit_scheduled_thursday').checked = student.scheduled_thursday == 1;
            document.getElementById('edit_scheduled_friday').checked = student.scheduled_friday == 1;
            document.getElementById('edit_scheduled_saturday').checked = student.scheduled_saturday == 1;
            document.getElementById('edit_scheduled_sunday').checked = student.scheduled_sunday == 1;

            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // モーダル外クリックで閉じる
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
