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
        s.guardian_id,
        s.is_active,
        s.status,
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
    {$whereClause}
    {$orderBy}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// 保護者一覧を取得（生徒登録用、教室フィルタリング）
if ($classroomId) {
    // 通常管理者：自分の教室の保護者のみ
    $stmt = $pdo->prepare("
        SELECT id, full_name, username
        FROM users
        WHERE user_type = 'guardian' AND is_active = 1 AND classroom_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$classroomId]);
} else {
    // マスター管理者またはスタッフ：全保護者
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
                    case 'deleted':
                        echo '生徒データを削除しました。';
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
                    <label>支援開始日 *</label>
                    <input type="date" name="support_start_date" required>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※かけはしの提出期限が自動で設定されます</div>
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
                    <label>在籍状況 *</label>
                    <select name="status" id="status" required onchange="toggleWithdrawalDate()">
                        <option value="active">在籍</option>
                        <option value="trial">体験</option>
                        <option value="short_term">短期利用</option>
                        <option value="withdrawn">退所</option>
                    </select>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※在籍以外の生徒には保護者への連絡は表示されません</div>
                </div>
                <div class="form-group" id="withdrawal_date_group" style="display: none;">
                    <label>退所日</label>
                    <input type="date" name="withdrawal_date" id="withdrawal_date">
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※退所日以降のかけはし・計画書・モニタリング表は作成されません</div>
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

        <!-- 検索・絞り込みフォーム -->
        <div class="content-box">
            <h2 class="section-title">🔍 検索・絞り込み</h2>
            <form method="GET" action="">
                <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label>生徒名</label>
                        <input type="text" name="search_name" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="部分一致で検索">
                    </div>
                    <div class="form-group">
                        <label>学年</label>
                        <select name="search_grade">
                            <option value="">すべて</option>
                            <option value="elementary" <?php echo $searchGrade === 'elementary' ? 'selected' : ''; ?>>小学部</option>
                            <option value="junior_high" <?php echo $searchGrade === 'junior_high' ? 'selected' : ''; ?>>中学部</option>
                            <option value="high_school" <?php echo $searchGrade === 'high_school' ? 'selected' : ''; ?>>高等部</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>保護者</label>
                        <input type="text" name="search_guardian" value="<?php echo htmlspecialchars($searchGuardian); ?>" placeholder="名前またはIDで部分一致検索">
                    </div>
                    <div class="form-group">
                        <label>状態</label>
                        <select name="search_status">
                            <option value="">すべて</option>
                            <option value="1" <?php echo $searchStatus === '1' ? 'selected' : ''; ?>>有効</option>
                            <option value="0" <?php echo $searchStatus === '0' ? 'selected' : ''; ?>>無効</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>並び替え</label>
                        <select name="sort_by">
                            <option value="status_name" <?php echo $sortBy === 'status_name' ? 'selected' : ''; ?>>状態→名前</option>
                            <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>名前</option>
                            <option value="age" <?php echo $sortBy === 'age' ? 'selected' : ''; ?>>年齢</option>
                            <option value="grade" <?php echo $sortBy === 'grade' ? 'selected' : ''; ?>>学年</option>
                            <option value="guardian" <?php echo $sortBy === 'guardian' ? 'selected' : ''; ?>>保護者</option>
                            <option value="created" <?php echo $sortBy === 'created' ? 'selected' : ''; ?>>登録日</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">検索</button>
                    <a href="students.php" class="btn btn-secondary">クリア</a>
                </div>
            </form>
        </div>

        <!-- 生徒一覧 -->
        <div class="content-box">
            <h2 class="section-title">生徒一覧（<?php echo count($students); ?>名）</h2>
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
                                    <?php
                                    $statusLabels = [
                                        'active' => '在籍',
                                        'trial' => '体験',
                                        'short_term' => '短期利用',
                                        'withdrawn' => '退所'
                                    ];
                                    $statusClass = '';
                                    switch ($student['status']) {
                                        case 'active':
                                            $statusClass = 'status-active';
                                            break;
                                        case 'trial':
                                            $statusClass = 'status-badge';
                                            $statusClass .= '" style="background: #fff3cd; color: #856404';
                                            break;
                                        case 'short_term':
                                            $statusClass = 'status-badge';
                                            $statusClass .= '" style="background: #cfe2ff; color: #084298';
                                            break;
                                        case 'withdrawn':
                                            $statusClass = 'status-inactive';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$student['status']] ?? '不明'; ?>
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
                    <label>支援開始日 *</label>
                    <input type="date" name="support_start_date" id="edit_support_start_date" required>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※変更するとかけはし期限に影響する可能性があります</div>
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
                    <label>在籍状況 *</label>
                    <select name="status" id="edit_status" required onchange="toggleEditWithdrawalDate()">
                        <option value="active">在籍</option>
                        <option value="trial">体験</option>
                        <option value="short_term">短期利用</option>
                        <option value="withdrawn">退所</option>
                    </select>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※在籍以外の生徒には保護者への連絡は表示されません</div>
                </div>
                <div class="form-group" id="edit_withdrawal_date_group">
                    <label>退所日</label>
                    <input type="date" name="withdrawal_date" id="edit_withdrawal_date">
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">※退所日以降のかけはし・計画書・モニタリング表は作成されません</div>
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
                    <button type="button" onclick="deleteStudent()" class="btn btn-danger" style="margin-right: auto;">削除</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">キャンセル</button>
                    <button type="submit" class="btn btn-primary">更新する</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 退所日入力欄の表示/非表示（新規登録）
        function toggleWithdrawalDate() {
            const status = document.getElementById('status').value;
            const withdrawalDateGroup = document.getElementById('withdrawal_date_group');
            if (status === 'withdrawn') {
                withdrawalDateGroup.style.display = 'block';
            } else {
                withdrawalDateGroup.style.display = 'none';
                document.getElementById('withdrawal_date').value = '';
            }
        }

        // 退所日入力欄の表示/非表示（編集）
        function toggleEditWithdrawalDate() {
            const status = document.getElementById('edit_status').value;
            const withdrawalDateGroup = document.getElementById('edit_withdrawal_date_group');
            if (status === 'withdrawn') {
                withdrawalDateGroup.style.display = 'block';
            } else {
                withdrawalDateGroup.style.display = 'none';
                document.getElementById('edit_withdrawal_date').value = '';
            }
        }

        function editStudent(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_student_name').value = student.student_name;
            document.getElementById('edit_birth_date').value = student.birth_date || '';
            document.getElementById('edit_support_start_date').value = student.support_start_date || '';
            document.getElementById('edit_guardian_id').value = student.guardian_id || '';
            document.getElementById('edit_status').value = student.status || 'active';
            document.getElementById('edit_withdrawal_date').value = student.withdrawal_date || '';

            // 曜日チェックボックスの設定
            document.getElementById('edit_scheduled_monday').checked = student.scheduled_monday == 1;
            document.getElementById('edit_scheduled_tuesday').checked = student.scheduled_tuesday == 1;
            document.getElementById('edit_scheduled_wednesday').checked = student.scheduled_wednesday == 1;
            document.getElementById('edit_scheduled_thursday').checked = student.scheduled_thursday == 1;
            document.getElementById('edit_scheduled_friday').checked = student.scheduled_friday == 1;
            document.getElementById('edit_scheduled_saturday').checked = student.scheduled_saturday == 1;
            document.getElementById('edit_scheduled_sunday').checked = student.scheduled_sunday == 1;

            // 退所日欄の表示/非表示
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

        // モーダル外クリックで閉じる
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
