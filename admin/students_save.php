<?php
/**
 * スタッフ用 - 生徒情報の保存・更新処理
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_helper.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';

// ログインチェック
requireLogin();
checkUserType(['admin', 'staff']);

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

// ログインユーザーの教室IDを取得
$userClassroomId = $_SESSION['classroom_id'] ?? null;

try {
    switch ($action) {
        case 'create':
            // 新規生徒登録
            $studentName = trim($_POST['student_name']);
            $birthDate = $_POST['birth_date'] ?? null;
            $supportStartDate = $_POST['support_start_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;
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

            if (empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
                throw new Exception('生徒名、生年月日、支援開始日は必須です。');
            }

            // 生年月日から学年を自動計算
            $gradeLevel = calculateGradeLevel($birthDate);

            // 通常管理者の場合、教室IDを自動設定
            $classroomIdToInsert = $userClassroomId ?? 1; // デフォルトは教室ID=1

            $stmt = $pdo->prepare("
                INSERT INTO students (
                    student_name, birth_date, support_start_date, grade_level, guardian_id, is_active, status, withdrawal_date, classroom_id, created_at,
                    scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                    scheduled_friday, scheduled_saturday, scheduled_sunday
                )
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $guardianId, $status, $withdrawalDate, $classroomIdToInsert,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday
            ]);

            $newStudentId = $pdo->lastInsertId();

            // かけはし期間を自動生成
            try {
                $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $newStudentId, $supportStartDate);
                error_log("Generated " . count($generatedPeriods) . " kakehashi periods for student {$newStudentId}");
            } catch (Exception $e) {
                error_log("Error generating kakehashi periods: " . $e->getMessage());
                // かけはし生成エラーでも生徒登録は成功させる
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

            if (empty($studentId) || empty($studentName) || empty($birthDate)) {
                throw new Exception('必須項目が入力されていません。');
            }

            // 生年月日から学年を自動計算
            $gradeLevel = calculateGradeLevel($birthDate);

            $stmt = $pdo->prepare("
                UPDATE students
                SET student_name = ?,
                    birth_date = ?,
                    support_start_date = ?,
                    grade_level = ?,
                    guardian_id = ?,
                    status = ?,
                    withdrawal_date = ?,
                    scheduled_monday = ?,
                    scheduled_tuesday = ?,
                    scheduled_wednesday = ?,
                    scheduled_thursday = ?,
                    scheduled_friday = ?,
                    scheduled_saturday = ?,
                    scheduled_sunday = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $guardianId, $status, $withdrawalDate,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                $studentId
            ]);

            header('Location: students.php?success=updated');
            exit;

        case 'delete':
            // 生徒データ削除
            $studentId = (int)$_POST['student_id'];

            if (empty($studentId)) {
                throw new Exception('生徒IDが指定されていません。');
            }

            // トランザクション開始
            $pdo->beginTransaction();

            try {
                // 関連データを削除
                // 1. 連絡帳データ
                $stmt = $pdo->prepare("DELETE FROM daily_records WHERE student_id = ?");
                $stmt->execute([$studentId]);

                // 2. かけはしデータ（保護者）
                $stmt = $pdo->prepare("DELETE FROM kakehashi_guardian WHERE student_id = ?");
                $stmt->execute([$studentId]);

                // 3. かけはしデータ（スタッフ）
                $stmt = $pdo->prepare("DELETE FROM kakehashi_staff WHERE student_id = ?");
                $stmt->execute([$studentId]);

                // 4. 個別支援計画書の明細
                $stmt = $pdo->prepare("
                    DELETE ispd FROM individual_support_plan_details ispd
                    INNER JOIN individual_support_plans isp ON ispd.plan_id = isp.id
                    WHERE isp.student_id = ?
                ");
                $stmt->execute([$studentId]);

                // 5. モニタリング記録の明細
                $stmt = $pdo->prepare("
                    DELETE md FROM monitoring_details md
                    INNER JOIN monitoring_records mr ON md.monitoring_id = mr.id
                    WHERE mr.student_id = ?
                ");
                $stmt->execute([$studentId]);

                // 6. モニタリング記録
                $stmt = $pdo->prepare("DELETE FROM monitoring_records WHERE student_id = ?");
                $stmt->execute([$studentId]);

                // 7. 個別支援計画書
                $stmt = $pdo->prepare("DELETE FROM individual_support_plans WHERE student_id = ?");
                $stmt->execute([$studentId]);

                // 8. 生徒本体を削除
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$studentId]);

                // コミット
                $pdo->commit();

                header('Location: students.php?success=deleted');
                exit;
            } catch (Exception $e) {
                // ロールバック
                $pdo->rollBack();
                throw $e;
            }

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: students.php?error=' . urlencode($e->getMessage()));
    exit;
}
