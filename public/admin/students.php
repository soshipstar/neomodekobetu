<?php
/**
 * 管理者用 - 生徒管理ページ
 * 生徒の登録・編集
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/student_helper.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();

// 管理者の場合、教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 検索・並び替えパラメータ
$searchName = $_GET['search_name'] ?? '';
$searchGrade = $_GET['search_grade'] ?? '';
$searchGuardian = $_GET['search_guardian'] ?? '';
$searchStatus = $_GET['search_status'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'status_name';

// WHERE句の構築
$where = [];
$params = [];

if ($classroomId && !isMasterAdmin()) {
    $where[] = "s.classroom_id = ?";
    $params[] = $classroomId;
}

if (!empty($searchName)) {
    $where[] = "s.student_name LIKE ?";
    $params[] = "%{$searchName}%";
}

if (!empty($searchGrade)) {
    $where[] = "s.grade_level = ?";
    $params[] = $searchGrade;
}

if (!empty($searchGuardian)) {
    $where[] = "(u.full_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%{$searchGuardian}%";
    $params[] = "%{$searchGuardian}%";
}

if ($searchStatus !== '') {
    $where[] = "s.is_active = ?";
    $params[] = (int)$searchStatus;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ORDER BY句の構築
$orderBy = "ORDER BY s.is_active DESC, s.student_name";
switch ($sortBy) {
    case 'name':
        $orderBy = "ORDER BY s.student_name";
        break;
    case 'age':
        $orderBy = "ORDER BY s.birth_date DESC";
        break;
    case 'grade':
        $orderBy = "ORDER BY s.grade_level, s.student_name";
        break;
    case 'guardian':
        $orderBy = "ORDER BY u.full_name, s.student_name";
        break;
    case 'status':
        $orderBy = "ORDER BY s.is_active DESC, s.student_name";
        break;
    case 'created':
        $orderBy = "ORDER BY s.created_at DESC";
        break;
    case 'status_name':
    default:
        $orderBy = "ORDER BY s.is_active DESC, s.student_name";
        break;
}

// 生徒を取得
$sql = "
    SELECT
        s.id,
        s.student_name,
        s.birth_date,
        s.support_start_date,
        s.grade_level,
        s.grade_adjustment,
        s.guardian_id,
        s.is_active,
        s.status,
        s.username,
        s.password_plain,
        s.created_at,
        s.withdrawal_date,
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
    {$whereClause}
    {$orderBy}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// 保護者一覧を取得（生徒登録用、教室フィルタリング）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, username
        FROM users
        WHERE user_type = 'guardian' AND is_active = 1 AND classroom_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT id, full_name, username
        FROM users
        WHERE user_type = 'guardian' AND is_active = 1
        ORDER BY full_name
    ");
}
$guardians = $stmt->fetchAll();

// 学年表示用のラベル
function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生'
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

// ステータスラベル
$statusLabels = [
    'active' => '在籍',
    'trial' => '体験',
    'short_term' => '短期利用',
    'withdrawn' => '退所'
];

// ページ開始
$currentPage = 'students';
renderPageStart('admin', $currentPage, '生徒管理');
?>

<style>
.grade-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-md);
    font-size: var(--text-caption-1);
    color: white;
    font-weight: bold;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">生徒管理</h1>
        <p class="page-subtitle">生徒の登録・編集</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch ($_GET['success']) {
            case 'created': echo '生徒を登録しました。'; break;
            case 'updated': echo '生徒情報を更新しました。'; break;
            case 'deleted': echo '生徒データを削除しました。'; break;
            default: echo '処理が完了しました。';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">新規生徒登録</h2>
        <form action="students_save.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">生徒名 *</label>
                    <input type="text" name="student_name" class="form-control" required placeholder="例: 山田 太郎">
                </div>
                <div class="form-group">
                    <label class="form-label">生年月日 *</label>
                    <input type="date" name="birth_date" class="form-control" required>
                    <small style="color: var(--text-secondary);">学年は生年月日から自動計算されます</small>
                </div>
                <div class="form-group">
                    <label class="form-label">学年調整</label>
                    <select name="grade_adjustment" class="form-control">
                        <option value="0" selected>調整なし (0)</option>
                        <option value="1">1学年上 (+1)</option>
                        <option value="2">2学年上 (+2)</option>
                        <option value="-1">1学年下 (-1)</option>
                        <option value="-2">2学年下 (-2)</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">支援開始日 *</label>
                    <input type="date" name="support_start_date" class="form-control" required>
                    <small style="color: var(--text-secondary);">かけはしの提出期限が自動で設定されます</small>
                </div>
                <div class="form-group">
                    <label class="form-label">保護者（任意）</label>
                    <select name="guardian_id" class="form-control">
                        <option value="">保護者を選択（後で設定可能）</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?= $guardian['id'] ?>"><?= htmlspecialchars($guardian['full_name']) ?> (<?= htmlspecialchars($guardian['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">在籍状況 *</label>
                    <select name="status" id="status" class="form-control" required onchange="toggleWithdrawalDate()">
                        <option value="active">在籍</option>
                        <option value="trial">体験</option>
                        <option value="short_term">短期利用</option>
                        <option value="withdrawn">退所</option>
                    </select>
                </div>
                <div class="form-group" id="withdrawal_date_group" style="display: none;">
                    <label class="form-label">退所日</label>
                    <input type="date" name="withdrawal_date" id="withdrawal_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">参加予定曜日</label>
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
</div>

<!-- 検索・絞り込みフォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">検索・絞り込み</h2>
        <form method="GET" action="">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label class="form-label">生徒名</label>
                    <input type="text" name="search_name" class="form-control" value="<?= htmlspecialchars($searchName) ?>" placeholder="部分一致で検索">
                </div>
                <div class="form-group">
                    <label class="form-label">学年</label>
                    <select name="search_grade" class="form-control">
                        <option value="">すべて</option>
                        <option value="elementary" <?= $searchGrade === 'elementary' ? 'selected' : '' ?>>小学生</option>
                        <option value="junior_high" <?= $searchGrade === 'junior_high' ? 'selected' : '' ?>>中学生</option>
                        <option value="high_school" <?= $searchGrade === 'high_school' ? 'selected' : '' ?>>高校生</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">保護者</label>
                    <input type="text" name="search_guardian" class="form-control" value="<?= htmlspecialchars($searchGuardian) ?>" placeholder="名前またはIDで検索">
                </div>
                <div class="form-group">
                    <label class="form-label">状態</label>
                    <select name="search_status" class="form-control">
                        <option value="">すべて</option>
                        <option value="1" <?= $searchStatus === '1' ? 'selected' : '' ?>>有効</option>
                        <option value="0" <?= $searchStatus === '0' ? 'selected' : '' ?>>無効</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">並び替え</label>
                    <select name="sort_by" class="form-control">
                        <option value="status_name" <?= $sortBy === 'status_name' ? 'selected' : '' ?>>状態→名前</option>
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>名前</option>
                        <option value="age" <?= $sortBy === 'age' ? 'selected' : '' ?>>年齢</option>
                        <option value="grade" <?= $sortBy === 'grade' ? 'selected' : '' ?>>学年</option>
                        <option value="guardian" <?= $sortBy === 'guardian' ? 'selected' : '' ?>>保護者</option>
                        <option value="created" <?= $sortBy === 'created' ? 'selected' : '' ?>>登録日</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end;">
                <a href="students.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- 生徒一覧 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-purple);">生徒一覧（<?= count($students) ?>名）</h2>
        <table class="table">
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
                        <td colspan="9" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            登録されている生徒がいません
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $age = $student['birth_date'] ? calculateAge($student['birth_date']) : '-';
                        $calculatedGrade = $student['birth_date'] ? calculateGradeLevel($student['birth_date'], null, $student['grade_adjustment'] ?? 0) : $student['grade_level'];
                        ?>
                        <tr>
                            <td><?= $student['id'] ?></td>
                            <td><?= htmlspecialchars($student['student_name']) ?></td>
                            <td><?= $student['birth_date'] ? date('Y/m/d', strtotime($student['birth_date'])) : '-' ?></td>
                            <td><?= $age !== '-' ? $age . '歳' : '-' ?></td>
                            <td>
                                <span class="grade-badge" style="background-color: <?= getGradeBadgeColor($calculatedGrade) ?>">
                                    <?= getGradeLabel($calculatedGrade) ?>
                                </span>
                            </td>
                            <td><?= $student['guardian_name'] ? htmlspecialchars($student['guardian_name']) : '-' ?></td>
                            <td>
                                <?php
                                $statusClass = '';
                                switch ($student['status']) {
                                    case 'active': $statusClass = 'badge-success'; break;
                                    case 'trial': $statusClass = 'badge-warning'; break;
                                    case 'short_term': $statusClass = 'badge-info'; break;
                                    case 'withdrawn': $statusClass = 'badge-danger'; break;
                                }
                                ?>
                                <span class="badge <?= $statusClass ?>">
                                    <?= $statusLabels[$student['status']] ?? '不明' ?>
                                </span>
                            </td>
                            <td><?= date('Y/m/d', strtotime($student['created_at'])) ?></td>
                            <td>
                                <button onclick="editStudent(<?= htmlspecialchars(json_encode($student)) ?>)" class="btn btn-primary btn-sm">編集</button>
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
    <div class="modal-content" style="max-width: 700px;">
        <h3 style="margin-bottom: var(--spacing-lg);">生徒情報の編集</h3>
        <form action="students_save.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="student_id" id="edit_student_id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">生徒名 *</label>
                    <input type="text" name="student_name" id="edit_student_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">生年月日 *</label>
                    <input type="date" name="birth_date" id="edit_birth_date" class="form-control" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">学年調整</label>
                    <select name="grade_adjustment" id="edit_grade_adjustment" class="form-control">
                        <option value="0">調整なし (0)</option>
                        <option value="1">1学年上 (+1)</option>
                        <option value="2">2学年上 (+2)</option>
                        <option value="-1">1学年下 (-1)</option>
                        <option value="-2">2学年下 (-2)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">支援開始日 *</label>
                    <input type="date" name="support_start_date" id="edit_support_start_date" class="form-control" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">保護者（任意）</label>
                    <select name="guardian_id" id="edit_guardian_id" class="form-control">
                        <option value="">保護者なし</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?= $guardian['id'] ?>"><?= htmlspecialchars($guardian['full_name']) ?> (<?= htmlspecialchars($guardian['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">在籍状況 *</label>
                    <select name="status" id="edit_status" class="form-control" required onchange="toggleEditWithdrawalDate()">
                        <option value="active">在籍</option>
                        <option value="trial">体験</option>
                        <option value="short_term">短期利用</option>
                        <option value="withdrawn">退所</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="edit_withdrawal_date_group">
                <label class="form-label">退所日</label>
                <input type="date" name="withdrawal_date" id="edit_withdrawal_date" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">参加予定曜日</label>
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
            <div class="form-group">
                <label class="form-label">生徒用ログイン設定</label>
                <div style="background: var(--apple-gray-6); padding: 15px; border-radius: var(--radius-sm); border: 1px solid var(--apple-gray-5);">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-size: var(--text-subhead);">ユーザー名（半角英数字）</label>
                        <input type="text" name="student_username" id="edit_student_username" class="form-control" placeholder="例: tanaka_taro" pattern="[a-zA-Z0-9_]+">
                        <small style="color: var(--text-secondary);">空欄の場合、ログイン不可</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-label" style="font-size: var(--text-subhead);">現在のパスワード</label>
                        <div style="padding: var(--spacing-md); background: var(--apple-bg-primary); border: 1px solid var(--apple-gray-5); border-radius: var(--radius-sm); font-family: monospace;">
                            <span id="edit_student_password_display">-</span>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: var(--text-subhead);">新しいパスワード</label>
                        <input type="password" name="student_password" id="edit_student_password" class="form-control" placeholder="変更する場合のみ入力">
                    </div>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: var(--spacing-lg);">
                <button type="button" onclick="deleteStudent()" class="btn btn-danger">削除</button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">キャンセル</button>
                    <button type="submit" class="btn btn-primary">更新する</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleWithdrawalDate() {
        const status = document.getElementById('status').value;
        const group = document.getElementById('withdrawal_date_group');
        if (status === 'withdrawn') {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
            document.getElementById('withdrawal_date').value = '';
        }
    }

    function toggleEditWithdrawalDate() {
        const status = document.getElementById('edit_status').value;
        const group = document.getElementById('edit_withdrawal_date_group');
        if (status === 'withdrawn') {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
            document.getElementById('edit_withdrawal_date').value = '';
        }
    }

    function editStudent(student) {
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_student_name').value = student.student_name;
        document.getElementById('edit_birth_date').value = student.birth_date || '';
        document.getElementById('edit_support_start_date').value = student.support_start_date || '';
        document.getElementById('edit_guardian_id').value = student.guardian_id || '';
        document.getElementById('edit_grade_adjustment').value = student.grade_adjustment || '0';
        document.getElementById('edit_status').value = student.status || 'active';
        document.getElementById('edit_withdrawal_date').value = student.withdrawal_date || '';

        // 曜日チェックボックス
        document.getElementById('edit_scheduled_monday').checked = student.scheduled_monday == 1;
        document.getElementById('edit_scheduled_tuesday').checked = student.scheduled_tuesday == 1;
        document.getElementById('edit_scheduled_wednesday').checked = student.scheduled_wednesday == 1;
        document.getElementById('edit_scheduled_thursday').checked = student.scheduled_thursday == 1;
        document.getElementById('edit_scheduled_friday').checked = student.scheduled_friday == 1;
        document.getElementById('edit_scheduled_saturday').checked = student.scheduled_saturday == 1;
        document.getElementById('edit_scheduled_sunday').checked = student.scheduled_sunday == 1;

        // 生徒用ログイン情報
        document.getElementById('edit_student_username').value = student.username || '';
        document.getElementById('edit_student_password_display').textContent = student.password_plain || '未設定';
        document.getElementById('edit_student_password').value = '';

        toggleEditWithdrawalDate();
        document.getElementById('editModal').classList.add('active');
    }

    function deleteStudent() {
        const studentId = document.getElementById('edit_student_id').value;
        const studentName = document.getElementById('edit_student_name').value;

        if (confirm(`本当に「${studentName}」のデータを削除しますか？\n\nこの操作は取り消せません。関連する連絡帳、かけはし、個別支援計画書などのすべてのデータが削除されます。`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'students_save.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'student_id';
            idInput.value = studentId;

            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>

<?php renderPageEnd(); ?>
