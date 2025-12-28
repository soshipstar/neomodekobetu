<?php
/**
 * 利用者一括登録 - 登録処理
 * フォームから送信されたデータを元に保護者・生徒をデータベースに登録
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bulk_register.php');
    exit;
}

requireCsrfToken();

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// フォームデータを取得
$guardiansData = $_POST['guardians'] ?? [];
$studentsData = $_POST['students'] ?? [];

if (empty($guardiansData) || empty($studentsData)) {
    $_SESSION['bulk_register_error'] = '登録データがありません。';
    header('Location: bulk_register.php');
    exit;
}

/**
 * ランダムなパスワードを生成（8文字の英数字）
 */
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

try {
    $pdo->beginTransaction();

    $registeredGuardians = [];
    $registeredStudents = [];
    $guardianIdMap = []; // 一時ID (G1, G2...) => 実際のDB ID

    // 既存のguardian_XXX形式のユーザー名の最大番号を取得
    $stmt = $pdo->prepare("SELECT username FROM users WHERE username LIKE 'guardian_%' ORDER BY username DESC LIMIT 1");
    $stmt->execute();
    $lastUsername = $stmt->fetchColumn();
    $nextNumber = 1;
    if ($lastUsername && preg_match('/guardian_(\d+)/', $lastUsername, $matches)) {
        $nextNumber = (int)$matches[1] + 1;
    }

    // 保護者を登録
    foreach ($guardiansData as $guardian) {
        $tempId = $guardian['id'] ?? '';
        $name = trim($guardian['name'] ?? '');
        $email = trim($guardian['email'] ?? '');

        if (empty($name)) {
            continue;
        }

        $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        $nextNumber++;

        // ユーザー名の重複確認
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        while ($stmt->fetchColumn() > 0) {
            $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
            $stmt->execute([$username]);
        }

        $password = generateRandomPassword();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, password_plain, full_name, email, user_type, classroom_id, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 'guardian', ?, 1, NOW())
        ");
        $stmt->execute([
            $username,
            $hashedPassword,
            $password,
            $name,
            $email ?: null,
            $classroomId
        ]);

        $guardianDbId = $pdo->lastInsertId();
        $guardianIdMap[$tempId] = $guardianDbId;

        $registeredGuardians[] = [
            'id' => $guardianDbId,
            'temp_id' => $tempId,
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'students' => []
        ];
    }

    // 生徒を登録
    foreach ($studentsData as $student) {
        $guardianTempId = $student['guardian_id'] ?? '';
        $guardianDbId = $guardianIdMap[$guardianTempId] ?? null;

        $name = trim($student['name'] ?? '');
        $birthDate = trim($student['birth_date'] ?? '');
        $gradeAdjustment = (int)($student['grade_adjustment'] ?? 0);
        $supportStartDate = trim($student['support_start_date'] ?? '');

        // 通所曜日
        $scheduledMon = isset($student['scheduled_monday']) ? 1 : 0;
        $scheduledTue = isset($student['scheduled_tuesday']) ? 1 : 0;
        $scheduledWed = isset($student['scheduled_wednesday']) ? 1 : 0;
        $scheduledThu = isset($student['scheduled_thursday']) ? 1 : 0;
        $scheduledFri = isset($student['scheduled_friday']) ? 1 : 0;
        $scheduledSat = isset($student['scheduled_saturday']) ? 1 : 0;

        if (empty($name) || empty($birthDate)) {
            continue;
        }

        // 学年を計算
        $gradeLevel = 'elementary';
        if (function_exists('calculateGradeLevel')) {
            $gradeLevel = calculateGradeLevel($birthDate, null, $gradeAdjustment);
        }

        // 支援開始日が空の場合はNULL
        $supportStartDateValue = !empty($supportStartDate) ? $supportStartDate : null;

        $stmt = $pdo->prepare("
            INSERT INTO students (
                student_name, birth_date, support_start_date, grade_level, grade_adjustment,
                guardian_id, classroom_id, is_active, status, created_at,
                scheduled_monday, scheduled_tuesday, scheduled_wednesday,
                scheduled_thursday, scheduled_friday, scheduled_saturday, scheduled_sunday
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW(), ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $name,
            $birthDate,
            $supportStartDateValue,
            $gradeLevel,
            $gradeAdjustment,
            $guardianDbId,
            $classroomId,
            $scheduledMon,
            $scheduledTue,
            $scheduledWed,
            $scheduledThu,
            $scheduledFri,
            $scheduledSat
        ]);

        $studentDbId = $pdo->lastInsertId();

        // 保護者の生徒リストに追加
        foreach ($registeredGuardians as &$g) {
            if ($g['temp_id'] === $guardianTempId) {
                $g['students'][] = $name;
                break;
            }
        }
        unset($g);

        $registeredStudents[] = [
            'id' => $studentDbId,
            'name' => $name,
            'guardian_name' => $registeredGuardians[array_search($guardianTempId, array_column($registeredGuardians, 'temp_id'))]['name'] ?? '',
            'birth_date' => $birthDate,
            'support_start_date' => $supportStartDate ?: '未設定'
        ];
    }

    $pdo->commit();

    // 登録結果をセッションに保存
    $_SESSION['bulk_register_result'] = [
        'guardians' => $registeredGuardians,
        'students' => $registeredStudents,
        'registered_at' => date('Y-m-d H:i:s')
    ];

    // 一時ファイルを削除
    if (isset($_SESSION['bulk_register_csv']) && file_exists($_SESSION['bulk_register_csv'])) {
        unlink($_SESSION['bulk_register_csv']);
    }
    unset($_SESSION['bulk_register_csv']);
    unset($_SESSION['bulk_register_data']);

    // PDF画面へリダイレクト
    header('Location: bulk_register_pdf.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Bulk register error: " . $e->getMessage());

    $_SESSION['bulk_register_error'] = '登録処理中にエラーが発生しました: ' . $e->getMessage();
    header('Location: bulk_register.php');
    exit;
}
