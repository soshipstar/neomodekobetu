<?php
/**
 * 週間計画表の保存（生徒用）
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: weekly_plan.php');
    exit;
}

$weekStartDate = $_POST['week_start_date'] ?? null;

if (!$weekStartDate) {
    header('Location: weekly_plan.php?error=' . urlencode('日付が指定されていません'));
    exit;
}

// 曜日ごとの計画データを収集
$planData = [];
$days = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
foreach ($days as $day) {
    $planData[$day] = trim($_POST[$day] ?? '');
}

try {
    // JSONエンコード
    $planDataJson = json_encode($planData, JSON_UNESCAPED_UNICODE);

    // 既存の計画があるかチェック
    $stmt = $pdo->prepare("
        SELECT id FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$studentId, $weekStartDate]);
    $existing = $stmt->fetch();

    if ($existing) {
        // 更新（生徒は各曜日の計画のみ更新）
        $stmt = $pdo->prepare("
            UPDATE weekly_plans
            SET plan_data = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$planDataJson, $existing['id']]);
    } else {
        // 新規作成（通常は発生しない - スタッフが先に作成）
        $stmt = $pdo->prepare("
            INSERT INTO weekly_plans (student_id, week_start_date, plan_data, created_by_type, created_by_id)
            VALUES (?, ?, ?, 'student', ?)
        ");
        $stmt->execute([$studentId, $weekStartDate, $planDataJson, $studentId]);
    }

    header('Location: weekly_plan.php?date=' . urlencode($weekStartDate) . '&success=1');
    exit;

} catch (Exception $e) {
    error_log("Weekly plan save error: " . $e->getMessage());
    header('Location: weekly_plan.php?date=' . urlencode($weekStartDate) . '&error=' . urlencode('保存中にエラーが発生しました'));
    exit;
}
