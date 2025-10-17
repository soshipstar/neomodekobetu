<?php
/**
 * スタッフ用 - 生徒情報の保存・更新処理
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_helper.php';

// ログインチェック
requireLogin();
checkUserType('staff');

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // 新規生徒登録
            $studentName = trim($_POST['student_name']);
            $birthDate = $_POST['birth_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;

            // 参加予定曜日
            $scheduledMonday = isset($_POST['scheduled_monday']) ? 1 : 0;
            $scheduledTuesday = isset($_POST['scheduled_tuesday']) ? 1 : 0;
            $scheduledWednesday = isset($_POST['scheduled_wednesday']) ? 1 : 0;
            $scheduledThursday = isset($_POST['scheduled_thursday']) ? 1 : 0;
            $scheduledFriday = isset($_POST['scheduled_friday']) ? 1 : 0;
            $scheduledSaturday = isset($_POST['scheduled_saturday']) ? 1 : 0;
            $scheduledSunday = isset($_POST['scheduled_sunday']) ? 1 : 0;

            if (empty($studentName) || empty($birthDate)) {
                throw new Exception('生徒名と生年月日は必須です。');
            }

            // 生年月日から学年を自動計算
            $gradeLevel = calculateGradeLevel($birthDate);

            $stmt = $pdo->prepare("
                INSERT INTO students (
                    student_name, birth_date, grade_level, guardian_id, is_active, created_at,
                    scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                    scheduled_friday, scheduled_saturday, scheduled_sunday
                )
                VALUES (?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $studentName, $birthDate, $gradeLevel, $guardianId,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday
            ]);

            header('Location: students.php?success=created');
            exit;

        case 'update':
            // 生徒情報更新
            $studentId = (int)$_POST['student_id'];
            $studentName = trim($_POST['student_name']);
            $birthDate = $_POST['birth_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;

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
                    grade_level = ?,
                    guardian_id = ?,
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
                $studentName, $birthDate, $gradeLevel, $guardianId,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                $studentId
            ]);

            header('Location: students.php?success=updated');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: students.php?error=' . urlencode($e->getMessage()));
    exit;
}
