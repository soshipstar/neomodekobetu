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
            $supportStartDate = $_POST['support_start_date'] ?? null;
            $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;

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

            $stmt = $pdo->prepare("
                INSERT INTO students (
                    student_name, birth_date, support_start_date, grade_level, guardian_id, is_active, created_at,
                    scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                    scheduled_friday, scheduled_saturday, scheduled_sunday
                )
                VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $guardianId,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday
            ]);

            $studentId = $pdo->lastInsertId();

            // かけはし期間の自動生成
            if (!empty($supportStartDate)) {
                try {
                    $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate);
                    error_log("Generated " . count($generatedPeriods) . " kakehashi periods for student {$studentId}");
                } catch (Exception $e) {
                    // かけはし生成エラーはログに記録するが、生徒登録自体は成功させる
                    error_log("かけはし期間生成エラー: " . $e->getMessage());
                    $_SESSION['warning'] = 'かけはし期間の自動生成でエラーが発生しました: ' . $e->getMessage();
                }
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

            // 参加予定曜日
            $scheduledMonday = isset($_POST['scheduled_monday']) ? 1 : 0;
            $scheduledTuesday = isset($_POST['scheduled_tuesday']) ? 1 : 0;
            $scheduledWednesday = isset($_POST['scheduled_wednesday']) ? 1 : 0;
            $scheduledThursday = isset($_POST['scheduled_thursday']) ? 1 : 0;
            $scheduledFriday = isset($_POST['scheduled_friday']) ? 1 : 0;
            $scheduledSaturday = isset($_POST['scheduled_saturday']) ? 1 : 0;
            $scheduledSunday = isset($_POST['scheduled_sunday']) ? 1 : 0;

            if (empty($studentId) || empty($studentName) || empty($birthDate) || empty($supportStartDate)) {
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
                $studentName, $birthDate, $supportStartDate, $gradeLevel, $guardianId,
                $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
                $scheduledFriday, $scheduledSaturday, $scheduledSunday,
                $studentId
            ]);

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
