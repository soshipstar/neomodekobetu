<?php
/**
 * スタッフ用 - 生徒情報の保存・更新処理
 */

// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_helper.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';

// ログインチェック
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

// support_plan_start_type カラムの存在チェック
$hasSupportPlanStartType = false;
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM students LIKE 'support_plan_start_type'");
    $hasSupportPlanStartType = $checkCol->rowCount() > 0;
} catch (Exception $e) {
    $hasSupportPlanStartType = false;
}

try {
    switch ($action) {
        case 'create':
            // 新規生徒登録
            $studentName = trim($_POST['student_name']);
            $birthDate = $_POST['birth_date'] ?? null;
            $supportStartDate = $_POST['support_start_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;
            $gradeAdjustment = isset($_POST['grade_adjustment']) ? (int)$_POST['grade_adjustment'] : 0;
            $status = $_POST['status'] ?? 'active';
            $withdrawalDate = !empty($_POST['withdrawal_date']) ? $_POST['withdrawal_date'] : null;

            // 参加予定曜日
            $scheduledMonday = isset($_POST['scheduled_monday']) ? 1 : 0;
            $scheduledTuesday = isset($_POST['scheduled_tuesday']) ? 1 : 0;
            $scheduledWednesday = isset($_POST['scheduled_wednesday']) ? 1 : 0;
            $scheduledThursday = isset($_POST['scheduled_thursday']) ? 1 : 0;
            $scheduledFriday = isset($_POST['scheduled_friday']) ? 1 : 0;
            $scheduledSaturday = isset($_POST['scheduled_saturday']) ? 1 : 0;
            $scheduledSunday = isset($_POST['scheduled_sunday']) ? 1 : 0;
            $supportPlanStartType = $_POST['support_plan_start_type'] ?? 'current';

            if (empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
                throw new Exception('生徒名、生年月日、支援開始日は必須です。');
            }

            // 生年月日から学年を自動計算（学年調整を考慮）
            $gradeLevel = getGradeCategory(calculateGradeLevel($birthDate, null, $gradeAdjustment));

            // スタッフの教室IDを取得
            $classroomId = $_SESSION['classroom_id'] ?? null;

            // support_plan_start_type カラムの有無に応じてSQL文を構築
            if ($hasSupportPlanStartType) {
                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        student_name, birth_date, support_start_date, grade_level, grade_adjustment, guardian_id, status, withdrawal_date, classroom_id, is_active, created_at,
                        scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                        scheduled_friday, scheduled_saturday, scheduled_sunday, support_plan_start_type
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate, $classroomId,
                    $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                    $scheduledFriday, $scheduledSaturday, $scheduledSunday, $supportPlanStartType
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        student_name, birth_date, support_start_date, grade_level, grade_adjustment, guardian_id, status, withdrawal_date, classroom_id, is_active, created_at,
                        scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                        scheduled_friday, scheduled_saturday, scheduled_sunday
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate, $classroomId,
                    $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                    $scheduledFriday, $scheduledSaturday, $scheduledSunday
                ]);
            }

            $studentId = $pdo->lastInsertId();

            // かけはし期間の自動生成（「現在の期間から作成する」の場合のみ）
            if (!empty($supportStartDate) && $supportPlanStartType === 'current') {
                try {
                    $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate);
                    error_log("Generated " . count($generatedPeriods) . " kakehashi periods for student {$studentId}");
                } catch (Exception $e) {
                    // かけはし生成エラーはログに記録するが、生徒登録自体は成功させる
                    error_log("かけはし期間生成エラー: " . $e->getMessage());
                    $_SESSION['warning'] = 'かけはし期間の自動生成でエラーが発生しました: ' . $e->getMessage();
                }
            } elseif ($supportPlanStartType === 'next') {
                error_log("Student {$studentId} has support_plan_start_type='next'. Skipping initial kakehashi generation.");
            }

            header('Location: students.php?success=created');
            exit;

        case 'update':
            // 生徒情報更新
            $studentId = (int)$_POST['student_id'];
            $studentName = trim($_POST['student_name']);
            $birthDate = $_POST['birth_date'] ?? null;
            $supportStartDate = $_POST['support_start_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;
            $gradeAdjustment = isset($_POST['grade_adjustment']) ? (int)$_POST['grade_adjustment'] : 0;
            $status = $_POST['status'] ?? 'active';

            // 退所日は退所ステータスの時のみ設定、それ以外はNULLにする
            $withdrawalDate = ($status === 'withdrawn' && !empty($_POST['withdrawal_date'])) ? $_POST['withdrawal_date'] : null;

            // 参加予定曜日
            $scheduledMonday = isset($_POST['scheduled_monday']) ? 1 : 0;
            $scheduledTuesday = isset($_POST['scheduled_tuesday']) ? 1 : 0;
            $scheduledWednesday = isset($_POST['scheduled_wednesday']) ? 1 : 0;
            $scheduledThursday = isset($_POST['scheduled_thursday']) ? 1 : 0;
            $scheduledFriday = isset($_POST['scheduled_friday']) ? 1 : 0;
            $scheduledSaturday = isset($_POST['scheduled_saturday']) ? 1 : 0;
            $scheduledSunday = isset($_POST['scheduled_sunday']) ? 1 : 0;
            $supportPlanStartType = $_POST['support_plan_start_type'] ?? 'current';

            if (empty($studentId) || empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
                throw new Exception('必須項目が入力されていません。');
            }

            // 生年月日から学年を自動計算（学年調整を考慮）
            $gradeLevel = getGradeCategory(calculateGradeLevel($birthDate, null, $gradeAdjustment));

            // 生徒用ログイン情報
            $studentUsername = trim($_POST['student_username'] ?? '');
            $studentPassword = $_POST['student_password'] ?? '';

            // パスワードハッシュの準備
            $passwordHash = null;
            if (!empty($studentPassword)) {
                $passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);
            }

            // support_plan_start_type カラムのSQL部分
            $supportPlanSql = $hasSupportPlanStartType ? "support_plan_start_type = ?," : "";
            $supportPlanParams = $hasSupportPlanStartType ? [$supportPlanStartType] : [];

            // 共通の更新パラメータを準備
            $baseParams = [
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday
            ];

            // ユーザー名が空の場合はログイン情報をクリア
            if (empty($studentUsername)) {
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET student_name = ?,
                        birth_date = ?,
                        support_start_date = ?,
                        grade_level = ?,
                        grade_adjustment = ?,
                        guardian_id = ?,
                        status = ?,
                        withdrawal_date = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        {$supportPlanSql}
                        username = NULL,
                        password_hash = NULL,
                        password_plain = NULL
                    WHERE id = ?
                ");
                $stmt->execute(array_merge($baseParams, $supportPlanParams, [$studentId]));
            } elseif ($passwordHash) {
                // ユーザー名とパスワードの両方を更新（平文パスワードも保存）
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET student_name = ?,
                        birth_date = ?,
                        support_start_date = ?,
                        grade_level = ?,
                        grade_adjustment = ?,
                        guardian_id = ?,
                        status = ?,
                        withdrawal_date = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        {$supportPlanSql}
                        username = ?,
                        password_hash = ?,
                        password_plain = ?
                    WHERE id = ?
                ");
                $stmt->execute(array_merge($baseParams, $supportPlanParams, [$studentUsername, $passwordHash, $studentPassword, $studentId]));
            } else {
                // ユーザー名のみ更新（パスワードは変更しない）
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET student_name = ?,
                        birth_date = ?,
                        support_start_date = ?,
                        grade_level = ?,
                        grade_adjustment = ?,
                        guardian_id = ?,
                        status = ?,
                        withdrawal_date = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        {$supportPlanSql}
                        username = ?
                    WHERE id = ?
                ");
                $stmt->execute(array_merge($baseParams, $supportPlanParams, [$studentUsername, $studentId]));
            }

            header('Location: students.php?success=updated');
            exit;

        case 'delete':
            // 生徒削除
            $studentId = (int)$_POST['student_id'];

            if (empty($studentId)) {
                throw new Exception('生徒IDが指定されていません。');
            }

            // 外部キー制約により、関連する記録も自動的に削除される
            // (ON DELETE CASCADEが設定されているため)
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$studentId]);

            header('Location: students.php?success=deleted');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: students.php?error=' . urlencode($e->getMessage()));
    exit;
}
