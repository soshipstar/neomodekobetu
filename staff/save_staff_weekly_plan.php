<?php
/**
 * スタッフ用 - 生徒週間計画表の保存処理
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_POST['student_id'] ?? null;
$weekStartDate = $_POST['week_start_date'] ?? null;

if (!$studentId || !$weekStartDate) {
    header('Location: student_weekly_plans.php?error=' . urlencode('必須項目が不足しています'));
    exit;
}

// アクセス権限チェック
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        INNER JOIN users g ON s.guardian_id = g.id
        WHERE s.id = ? AND g.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
}

if (!$stmt->fetch()) {
    header('Location: student_weekly_plans.php?error=' . urlencode('アクセス権限がありません'));
    exit;
}

// 計画データを収集
$planData = [];
$days = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
foreach ($days as $day) {
    $planData[$day] = trim($_POST[$day] ?? '');
}

$planDataJson = json_encode($planData, JSON_UNESCAPED_UNICODE);

try {
    // 既存の計画があるかチェック
    $stmt = $pdo->prepare("
        SELECT id FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$studentId, $weekStartDate]);
    $existingPlan = $stmt->fetch();

    if ($existingPlan) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE weekly_plans
            SET plan_data = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$planDataJson, $existingPlan['id']]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO weekly_plans (
                student_id,
                week_start_date,
                plan_data,
                created_by_type,
                created_by_id,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, 'staff', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $studentId,
            $weekStartDate,
            $planDataJson,
            $_SESSION['user_id']
        ]);
    }

    header("Location: student_weekly_plan_detail.php?student_id=$studentId&date=$weekStartDate&success=1");
    exit;

} catch (Exception $e) {
    header("Location: student_weekly_plan_detail.php?student_id=$studentId&date=$weekStartDate&error=" . urlencode($e->getMessage()));
    exit;
}
