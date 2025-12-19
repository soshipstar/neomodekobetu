<?php
/**
 * 待機児童管理 - 保存処理
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';

// 管理者またはスタッフのみアクセス可能
requireUserType(['admin', 'staff']);

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';
$userType = $_SESSION['user_type'];
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    header('Location: waiting_list.php?error=' . urlencode('教室IDが設定されていません'));
    exit;
}

try {
    switch ($action) {
        case 'admit':
            // 待機児童を入所させる
            $studentId = (int)$_POST['student_id'];

            if (empty($studentId)) {
                throw new Exception('生徒IDが指定されていません。');
            }

            // 生徒情報を取得
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND classroom_id = ? AND status = 'waiting'");
            $stmt->execute([$studentId, $classroomId]);
            $student = $stmt->fetch();

            if (!$student) {
                throw new Exception('待機児童が見つかりません。');
            }

            // 希望曜日を利用曜日にコピーし、ステータスを在籍に変更
            $supportStartDate = $student['desired_start_date'] ?: date('Y-m-d');

            $stmt = $pdo->prepare("
                UPDATE students
                SET status = 'active',
                    is_active = 1,
                    support_start_date = ?,
                    scheduled_monday = desired_monday,
                    scheduled_tuesday = desired_tuesday,
                    scheduled_wednesday = desired_wednesday,
                    scheduled_thursday = desired_thursday,
                    scheduled_friday = desired_friday,
                    scheduled_saturday = desired_saturday,
                    scheduled_sunday = desired_sunday,
                    desired_start_date = NULL,
                    desired_weekly_count = NULL,
                    desired_monday = 0,
                    desired_tuesday = 0,
                    desired_wednesday = 0,
                    desired_thursday = 0,
                    desired_friday = 0,
                    desired_saturday = 0,
                    desired_sunday = 0,
                    waiting_notes = NULL
                WHERE id = ?
            ");
            $stmt->execute([$supportStartDate, $studentId]);

            // かけはし期間の自動生成
            try {
                $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $studentId, $supportStartDate);
                error_log("Generated " . count($generatedPeriods) . " kakehashi periods for student {$studentId} on admission");
            } catch (Exception $e) {
                error_log("かけはし期間生成エラー（入所時）: " . $e->getMessage());
            }

            header('Location: waiting_list.php?success=admitted');
            exit;

        case 'update_capacity':
            // 営業日・定員設定の更新（管理者・スタッフ共に可能）
            $capacities = $_POST['capacity'] ?? [];
            $isOpenFlags = $_POST['is_open'] ?? [];

            for ($day = 0; $day <= 6; $day++) {
                $maxCapacity = isset($capacities[$day]) ? (int)$capacities[$day] : 10;
                $isOpen = isset($isOpenFlags[$day]) ? 1 : 0;

                // UPSERT処理
                $stmt = $pdo->prepare("
                    INSERT INTO classroom_capacity (classroom_id, day_of_week, max_capacity, is_open)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        max_capacity = VALUES(max_capacity),
                        is_open = VALUES(is_open),
                        updated_at = NOW()
                ");
                $stmt->execute([$classroomId, $day, $maxCapacity, $isOpen]);
            }

            header('Location: waiting_list.php?success=capacity_updated');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    header('Location: waiting_list.php?error=' . urlencode($e->getMessage()));
    exit;
}
