<?php
/**
 * 利用者一括登録 - 確認・編集ページ
 * CSVの内容を解析・表示し、編集可能なフォームで登録前の確認を行う
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

// CSVファイルのパスをセッションから取得
if (!isset($_SESSION['bulk_register_csv']) || !file_exists($_SESSION['bulk_register_csv'])) {
    header('Location: bulk_register.php');
    exit;
}

$csvPath = $_SESSION['bulk_register_csv'];

/**
 * 文字コードを検出してUTF-8に変換
 */
function convertToUtf8($content) {
    // BOMを除去
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    // 文字コードを検出
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ASCII'], true);

    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    return $content;
}

/**
 * CSVを解析して保護者・生徒データを取得
 */
function parseCsv($csvPath, $pdo, $classroomId) {
    $result = [
        'guardians' => [],
        'students' => [],
        'errors' => [],
        'warnings' => []
    ];

    // ファイル全体を読み込んでUTF-8に変換
    $content = file_get_contents($csvPath);
    if ($content === false) {
        $result['errors'][] = 'CSVファイルを開けませんでした。';
        return $result;
    }

    $content = convertToUtf8($content);

    // 一時ファイルに書き出してfgetcsvで読み込む
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_utf8_');
    file_put_contents($tempFile, $content);

    $handle = fopen($tempFile, 'r');
    if (!$handle) {
        unlink($tempFile);
        $result['errors'][] = 'CSVファイルを開けませんでした。';
        return $result;
    }

    $lineNumber = 0;
    $headers = null;
    $guardianMap = []; // 保護者氏名 => 一時ID
    $studentIndex = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;

        // 空行をスキップ
        if (empty(array_filter($row))) {
            continue;
        }

        // ヘッダー行
        if ($lineNumber === 1) {
            $headers = $row;
            continue;
        }

        // データ行（最低3列必要：保護者氏名、生徒氏名、生年月日）
        $guardianName = trim($row[0] ?? '');
        $studentName = trim($row[1] ?? '');
        $birthDate = trim($row[2] ?? '');
        $guardianEmail = trim($row[3] ?? '');
        $supportStartDate = trim($row[4] ?? '');
        $gradeAdjustment = isset($row[5]) && $row[5] !== '' ? (int)$row[5] : 0;
        $scheduledMon = (int)($row[6] ?? 0);
        $scheduledTue = (int)($row[7] ?? 0);
        $scheduledWed = (int)($row[8] ?? 0);
        $scheduledThu = (int)($row[9] ?? 0);
        $scheduledFri = (int)($row[10] ?? 0);
        $scheduledSat = (int)($row[11] ?? 0);

        // 必須項目チェック（保護者氏名、生徒氏名、生年月日のみ必須）
        $rowErrors = [];
        if (empty($guardianName)) {
            $rowErrors[] = '保護者氏名が空です';
        }
        if (empty($studentName)) {
            $rowErrors[] = '生徒氏名が空です';
        }
        if (empty($birthDate)) {
            $rowErrors[] = '生年月日が空です';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            $rowErrors[] = '生年月日の形式が不正です（YYYY-MM-DD）';
        }

        if (!empty($rowErrors)) {
            $result['errors'][] = "{$lineNumber}行目: " . implode('、', $rowErrors);
            continue;
        }

        // 支援開始日の形式チェック（入力がある場合のみ）
        if (!empty($supportStartDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $supportStartDate)) {
            $result['warnings'][] = "{$lineNumber}行目: 支援開始日の形式が不正です。登録時に設定してください。";
            $supportStartDate = '';
        }

        // 保護者を登録（同一氏名は同一保護者）
        if (!isset($guardianMap[$guardianName])) {
            $guardianId = 'G' . (count($guardianMap) + 1);
            $guardianMap[$guardianName] = $guardianId;
            $result['guardians'][$guardianId] = [
                'id' => $guardianId,
                'name' => $guardianName,
                'email' => $guardianEmail,
                'students' => []
            ];
        } else {
            $guardianId = $guardianMap[$guardianName];
            // メールアドレスが後から指定された場合は更新
            if (!empty($guardianEmail) && empty($result['guardians'][$guardianId]['email'])) {
                $result['guardians'][$guardianId]['email'] = $guardianEmail;
            }
        }

        // 学年を計算
        $gradeLevel = 'elementary';
        if (function_exists('calculateGradeLevel')) {
            $gradeLevel = calculateGradeLevel($birthDate, null, $gradeAdjustment);
        }

        // 生徒を追加
        $result['students'][] = [
            'index' => $studentIndex,
            'guardian_id' => $guardianId,
            'guardian_name' => $guardianName,
            'name' => $studentName,
            'birth_date' => $birthDate,
            'support_start_date' => $supportStartDate,
            'grade_adjustment' => $gradeAdjustment,
            'grade_level' => $gradeLevel,
            'scheduled_monday' => $scheduledMon,
            'scheduled_tuesday' => $scheduledTue,
            'scheduled_wednesday' => $scheduledWed,
            'scheduled_thursday' => $scheduledThu,
            'scheduled_friday' => $scheduledFri,
            'scheduled_saturday' => $scheduledSat,
            'line_number' => $lineNumber
        ];

        $result['guardians'][$guardianId]['students'][] = $studentName;
        $studentIndex++;
    }

    fclose($handle);

    // 一時ファイルを削除
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }

    // 既存ユーザーとの重複チェック
    foreach ($result['guardians'] as $id => $guardian) {
        if (!empty($guardian['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND classroom_id = ?");
            $stmt->execute([$guardian['email'], $classroomId]);
            if ($stmt->fetch()) {
                $result['warnings'][] = "保護者「{$guardian['name']}」のメールアドレスは既に登録されています。";
            }
        }
    }

    return $result;
}

$parseResult = parseCsv($csvPath, $pdo, $classroomId);
$hasErrors = !empty($parseResult['errors']);
$guardianCount = count($parseResult['guardians']);
$studentCount = count($parseResult['students']);

$role = 'staff';
$currentPage = 'bulk_register';

renderPageStart($role, $currentPage, '利用者一括登録 - 確認', [
    'classroom' => $classroom
]);
?>

<style>
.edit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.edit-table th, .edit-table td {
    border: 1px solid var(--border-primary);
    padding: 8px;
    vertical-align: middle;
}
.edit-table th {
    background: var(--apple-bg-tertiary);
    font-weight: 600;
    white-space: nowrap;
}
.edit-table input[type="text"],
.edit-table input[type="date"],
.edit-table input[type="email"],
.edit-table select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-sm);
    font-size: 13px;
}
.edit-table input[type="text"]:focus,
.edit-table input[type="date"]:focus,
.edit-table input[type="email"]:focus,
.edit-table select:focus {
    outline: none;
    border-color: var(--apple-blue);
    box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
}
.schedule-checkboxes {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.schedule-checkboxes label {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 4px 6px;
    background: var(--apple-bg-tertiary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: 12px;
}
.schedule-checkboxes input[type="checkbox"] {
    margin: 0;
}
.schedule-checkboxes label:has(input:checked) {
    background: var(--apple-blue);
    color: white;
}
.required-mark {
    color: var(--apple-red);
    margin-left: 2px;
}
.row-number {
    color: var(--text-tertiary);
    font-size: 11px;
}
.optional-field {
    background: var(--apple-bg-secondary);
}
</style>

<div class="page-header">
    <h1>利用者一括登録 - 確認・編集</h1>
    <p class="page-description">登録内容を確認し、必要に応じて編集してください。</p>
</div>

<?php if (!empty($parseResult['errors'])): ?>
    <div class="alert alert-danger">
        <strong>エラーがあります。修正してから再度アップロードしてください。</strong>
        <ul style="margin: var(--spacing-sm) 0 0 var(--spacing-lg);">
            <?php foreach ($parseResult['errors'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($parseResult['warnings'])): ?>
    <div class="alert alert-warning">
        <strong>警告</strong>
        <ul style="margin: var(--spacing-sm) 0 0 var(--spacing-lg);">
            <?php foreach ($parseResult['warnings'] as $warning): ?>
                <li><?= htmlspecialchars($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>登録サマリー</h2>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: var(--spacing-xl); flex-wrap: wrap;">
            <div style="text-align: center; padding: var(--spacing-lg); background: var(--apple-bg-tertiary); border-radius: var(--radius-md); min-width: 150px;">
                <div style="font-size: var(--text-title-1); font-weight: bold; color: var(--apple-blue);"><?= $guardianCount ?></div>
                <div style="color: var(--text-secondary);">保護者</div>
            </div>
            <div style="text-align: center; padding: var(--spacing-lg); background: var(--apple-bg-tertiary); border-radius: var(--radius-md); min-width: 150px;">
                <div style="font-size: var(--text-title-1); font-weight: bold; color: var(--apple-green);"><?= $studentCount ?></div>
                <div style="color: var(--text-secondary);">生徒</div>
            </div>
        </div>
        <p style="margin-top: var(--spacing-md); color: var(--text-secondary); font-size: var(--text-footnote);">
            <span class="required-mark">*</span> は必須項目です。その他の項目は後から登録することもできます。
        </p>
    </div>
</div>

<?php if (!$hasErrors && $guardianCount > 0): ?>
<form method="POST" action="bulk_register_execute.php" id="bulkRegisterForm">
    <?= csrfTokenField() ?>

    <div class="card" style="margin-top: var(--spacing-xl);">
        <div class="card-header">
            <h2>保護者情報</h2>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="edit-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>保護者氏名<span class="required-mark">*</span></th>
                        <th>メールアドレス</th>
                        <th>紐付け生徒</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $gIndex = 0; foreach ($parseResult['guardians'] as $gId => $guardian): ?>
                    <tr>
                        <td class="row-number"><?= $gIndex + 1 ?></td>
                        <td>
                            <input type="hidden" name="guardians[<?= $gIndex ?>][id]" value="<?= htmlspecialchars($gId) ?>">
                            <input type="text" name="guardians[<?= $gIndex ?>][name]" value="<?= htmlspecialchars($guardian['name']) ?>" required>
                        </td>
                        <td class="optional-field">
                            <input type="email" name="guardians[<?= $gIndex ?>][email]" value="<?= htmlspecialchars($guardian['email']) ?>" placeholder="任意">
                        </td>
                        <td style="color: var(--text-secondary);">
                            <?= htmlspecialchars(implode('、', $guardian['students'])) ?>
                        </td>
                    </tr>
                    <?php $gIndex++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: var(--spacing-xl);">
        <div class="card-header">
            <h2>生徒情報</h2>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="edit-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>生徒氏名<span class="required-mark">*</span></th>
                        <th>保護者</th>
                        <th>生年月日<span class="required-mark">*</span></th>
                        <th>学年調整<span class="required-mark">*</span></th>
                        <th>支援開始日</th>
                        <th>通所曜日</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parseResult['students'] as $idx => $student): ?>
                    <tr>
                        <td class="row-number"><?= $idx + 1 ?></td>
                        <td>
                            <input type="hidden" name="students[<?= $idx ?>][guardian_id]" value="<?= htmlspecialchars($student['guardian_id']) ?>">
                            <input type="text" name="students[<?= $idx ?>][name]" value="<?= htmlspecialchars($student['name']) ?>" required>
                        </td>
                        <td style="color: var(--text-secondary); white-space: nowrap;">
                            <?= htmlspecialchars($student['guardian_name']) ?>
                        </td>
                        <td>
                            <input type="date" name="students[<?= $idx ?>][birth_date]" value="<?= htmlspecialchars($student['birth_date']) ?>" required>
                        </td>
                        <td style="width: 100px;">
                            <select name="students[<?= $idx ?>][grade_adjustment]">
                                <option value="-2" <?= $student['grade_adjustment'] == -2 ? 'selected' : '' ?>>-2</option>
                                <option value="-1" <?= $student['grade_adjustment'] == -1 ? 'selected' : '' ?>>-1</option>
                                <option value="0" <?= $student['grade_adjustment'] == 0 ? 'selected' : '' ?>>0</option>
                                <option value="1" <?= $student['grade_adjustment'] == 1 ? 'selected' : '' ?>>+1</option>
                                <option value="2" <?= $student['grade_adjustment'] == 2 ? 'selected' : '' ?>>+2</option>
                            </select>
                        </td>
                        <td class="optional-field">
                            <input type="date" name="students[<?= $idx ?>][support_start_date]" value="<?= htmlspecialchars($student['support_start_date']) ?>">
                        </td>
                        <td>
                            <div class="schedule-checkboxes">
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_monday]" value="1" <?= $student['scheduled_monday'] ? 'checked' : '' ?>> 月</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_tuesday]" value="1" <?= $student['scheduled_tuesday'] ? 'checked' : '' ?>> 火</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_wednesday]" value="1" <?= $student['scheduled_wednesday'] ? 'checked' : '' ?>> 水</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_thursday]" value="1" <?= $student['scheduled_thursday'] ? 'checked' : '' ?>> 木</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_friday]" value="1" <?= $student['scheduled_friday'] ? 'checked' : '' ?>> 金</label>
                                <label><input type="checkbox" name="students[<?= $idx ?>][scheduled_saturday]" value="1" <?= $student['scheduled_saturday'] ? 'checked' : '' ?>> 土</label>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: var(--spacing-xl); background: var(--apple-yellow-light, #fffde7); border: 1px solid var(--apple-yellow, #ffc107);">
        <div class="card-body">
            <p style="margin: 0; text-align: center; font-size: var(--text-body);">
                <strong>この保護者、生徒を登録しますがよろしいですか？</strong>
            </p>
        </div>
    </div>

    <div class="form-actions" style="margin-top: var(--spacing-xl); display: flex; gap: var(--spacing-md); justify-content: center;">
        <a href="bulk_register.php" class="btn btn-secondary">キャンセル</a>
        <button type="submit" class="btn btn-primary">登録する</button>
    </div>
</form>
<?php else: ?>
<div class="form-actions" style="margin-top: var(--spacing-xl);">
    <a href="bulk_register.php" class="btn btn-secondary">戻る</a>
</div>
<?php endif; ?>

<?php renderPageEnd(); ?>
