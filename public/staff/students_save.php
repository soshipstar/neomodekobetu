<?php
/**
 * スタッフ用 - 生徒情報の保存・更新処理
 */

// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/student_helper.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';

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

            // 待機児童のフィールド
            $desiredStartDate = !empty($_POST['desired_start_date']) ? $_POST['desired_start_date'] : null;
            $desiredWeeklyCount = !empty($_POST['desired_weekly_count']) ? (int)$_POST['desired_weekly_count'] : null;
            $desiredMonday = isset($_POST['desired_monday']) ? 1 : 0;
            $desiredTuesday = isset($_POST['desired_tuesday']) ? 1 : 0;
            $desiredWednesday = isset($_POST['desired_wednesday']) ? 1 : 0;
            $desiredThursday = isset($_POST['desired_thursday']) ? 1 : 0;
            $desiredFriday = isset($_POST['desired_friday']) ? 1 : 0;
            $desiredSaturday = isset($_POST['desired_saturday']) ? 1 : 0;
            $desiredSunday = isset($_POST['desired_sunday']) ? 1 : 0;
            $waitingNotes = trim($_POST['waiting_notes'] ?? '');
            $supportPlanStartType = $_POST['support_plan_start_type'] ?? 'current';

            // 待機児童の場合は支援開始日は必須ではない
            if ($status === 'waiting') {
                if (empty($studentName) || empty($birthDate)) {
                    throw new Exception('生徒名と生年月日は必須です。');
                }
                // 待機の場合、支援開始日がなければ仮の値を設定（入所時に更新）
                if (empty($supportStartDate)) {
                    $supportStartDate = $desiredStartDate ?? date('Y-m-d');
                }
            } else {
                if (empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
                    throw new Exception('生徒名、生年月日、支援開始日は必須です。');
                }
            }

            // 生年月日から学年を自動計算（学年調整を考慮）
            $gradeLevel = calculateGradeLevel($birthDate, null, $gradeAdjustment);

            // スタッフの教室IDを取得
            $classroomId = $_SESSION['classroom_id'] ?? null;

            // 待機児童はis_active=0にする
            $isActive = ($status === 'waiting' || $status === 'withdrawn') ? 0 : 1;

            // support_plan_start_type カラムの有無に応じてSQL文を構築
            if ($hasSupportPlanStartType) {
                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        student_name, birth_date, support_start_date, grade_level, grade_adjustment, guardian_id, status, withdrawal_date, classroom_id, is_active, created_at,
                        scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                        scheduled_friday, scheduled_saturday, scheduled_sunday,
                        desired_start_date, desired_weekly_count,
                        desired_monday, desired_tuesday, desired_wednesday, desired_thursday,
                        desired_friday, desired_saturday, desired_sunday, waiting_notes, support_plan_start_type
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate, $classroomId, $isActive,
                    $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                    $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                    $desiredStartDate, $desiredWeeklyCount,
                    $desiredMonday, $desiredTuesday, $desiredWednesday, $desiredThursday,
                    $desiredFriday, $desiredSaturday, $desiredSunday, $waitingNotes ?: null, $supportPlanStartType
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO students (
                        student_name, birth_date, support_start_date, grade_level, grade_adjustment, guardian_id, status, withdrawal_date, classroom_id, is_active, created_at,
                        scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                        scheduled_friday, scheduled_saturday, scheduled_sunday,
                        desired_start_date, desired_weekly_count,
                        desired_monday, desired_tuesday, desired_wednesday, desired_thursday,
                        desired_friday, desired_saturday, desired_sunday, waiting_notes
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate, $classroomId, $isActive,
                    $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                    $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                    $desiredStartDate, $desiredWeeklyCount,
                    $desiredMonday, $desiredTuesday, $desiredWednesday, $desiredThursday,
                    $desiredFriday, $desiredSaturday, $desiredSunday, $waitingNotes ?: null
                ]);
            }

            $studentId = $pdo->lastInsertId();

            // かけはし期間の自動生成（待機児童以外、かつ「現在の期間から作成する」の場合のみ）
            // support_plan_start_type が 'next' の場合は次回の期間までスキップ
            if ($status !== 'waiting' && !empty($supportStartDate) && $supportPlanStartType === 'current') {
                try {
                    $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate);
                    error_log("Generated " . count($generatedPeriods) . " kakehashi periods for student {$studentId}");
                } catch (Exception $e) {
                    // かけはし生成エラーはログに記録するが、生徒登録自体は成功させる
                    error_log("かけはし期間生成エラー: " . $e->getMessage());
                    $_SESSION['warning'] = 'かけはし期間の自動生成でエラーが発生しました: ' . $e->getMessage();
                }
            } elseif ($status !== 'waiting' && $supportPlanStartType === 'next') {
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

            // 待機児童のフィールド
            $desiredStartDate = !empty($_POST['desired_start_date']) ? $_POST['desired_start_date'] : null;
            $desiredWeeklyCount = !empty($_POST['desired_weekly_count']) ? (int)$_POST['desired_weekly_count'] : null;
            $desiredMonday = isset($_POST['desired_monday']) ? 1 : 0;
            $desiredTuesday = isset($_POST['desired_tuesday']) ? 1 : 0;
            $desiredWednesday = isset($_POST['desired_wednesday']) ? 1 : 0;
            $desiredThursday = isset($_POST['desired_thursday']) ? 1 : 0;
            $desiredFriday = isset($_POST['desired_friday']) ? 1 : 0;
            $desiredSaturday = isset($_POST['desired_saturday']) ? 1 : 0;
            $desiredSunday = isset($_POST['desired_sunday']) ? 1 : 0;
            $waitingNotes = trim($_POST['waiting_notes'] ?? '');
            $supportPlanStartType = $_POST['support_plan_start_type'] ?? 'current';

            // 待機児童の場合は支援開始日は必須ではない
            if ($status === 'waiting') {
                if (empty($studentId) || empty($studentName) || empty($birthDate)) {
                    throw new Exception('生徒名と生年月日は必須です。');
                }
                // 待機の場合、支援開始日がなければ仮の値を設定
                if (empty($supportStartDate)) {
                    $supportStartDate = $desiredStartDate ?? date('Y-m-d');
                }
            } elseif (empty($studentId) || empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
                throw new Exception('必須項目が入力されていません。');
            }

            // is_activeフラグの設定
            $isActive = ($status === 'waiting' || $status === 'withdrawn') ? 0 : 1;

            // 生年月日から学年を自動計算（学年調整を考慮）
            $gradeLevel = calculateGradeLevel($birthDate, null, $gradeAdjustment);

            // 生徒用ログイン情報
            $studentUsername = trim($_POST['student_username'] ?? '');
            $studentPassword = $_POST['student_password'] ?? '';

            // パスワードハッシュの準備
            $passwordHash = null;
            if (!empty($studentPassword)) {
                $passwordHash = password_hash($studentPassword, PASSWORD_DEFAULT);
            }

            // 共通の更新パラメータを準備
            $baseParams = [
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $gradeAdjustment, $guardianId, $status, $withdrawalDate, $isActive,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                $desiredStartDate, $desiredWeeklyCount,
                $desiredMonday, $desiredTuesday, $desiredWednesday, $desiredThursday,
                $desiredFriday, $desiredSaturday, $desiredSunday, $waitingNotes ?: null
            ];

            // support_plan_start_type カラムのSQL部分
            $supportPlanSql = $hasSupportPlanStartType ? "support_plan_start_type = ?," : "";
            $supportPlanParams = $hasSupportPlanStartType ? [$supportPlanStartType] : [];

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
                        is_active = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        desired_start_date = ?,
                        desired_weekly_count = ?,
                        desired_monday = ?,
                        desired_tuesday = ?,
                        desired_wednesday = ?,
                        desired_thursday = ?,
                        desired_friday = ?,
                        desired_saturday = ?,
                        desired_sunday = ?,
                        waiting_notes = ?,
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
                        is_active = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        desired_start_date = ?,
                        desired_weekly_count = ?,
                        desired_monday = ?,
                        desired_tuesday = ?,
                        desired_wednesday = ?,
                        desired_thursday = ?,
                        desired_friday = ?,
                        desired_saturday = ?,
                        desired_sunday = ?,
                        waiting_notes = ?,
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
                        is_active = ?,
                        scheduled_monday = ?,
                        scheduled_tuesday = ?,
                        scheduled_wednesday = ?,
                        scheduled_thursday = ?,
                        scheduled_friday = ?,
                        scheduled_saturday = ?,
                        scheduled_sunday = ?,
                        desired_start_date = ?,
                        desired_weekly_count = ?,
                        desired_monday = ?,
                        desired_tuesday = ?,
                        desired_wednesday = ?,
                        desired_thursday = ?,
                        desired_friday = ?,
                        desired_saturday = ?,
                        desired_sunday = ?,
                        waiting_notes = ?,
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

            // 教室IDを取得
            $classroomId = $_SESSION['classroom_id'] ?? null;

            // 外部キー制約により、関連する記録も自動的に削除される
            // (ON DELETE CASCADEが設定されているため)
            // 自分の教室の生徒のみ削除可能
            if ($classroomId) {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND classroom_id = ?");
                $result = $stmt->execute([$studentId, $classroomId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $result = $stmt->execute([$studentId]);
            }

            // 削除結果を確認
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Student delete failed: " . print_r($errorInfo, true));
                throw new Exception('削除に失敗しました: ' . $errorInfo[2]);
            }

            $rowCount = $stmt->rowCount();
            error_log("Student delete: ID={$studentId}, classroomId={$classroomId}, rowCount={$rowCount}");

            if ($rowCount === 0) {
                throw new Exception('削除対象の生徒が見つかりませんでした。または削除権限がありません。');
            }

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
