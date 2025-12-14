<?php
/**
 * 提出物の完了チェックトグル（生徒用）
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';

requireStudentLogin();

header('Content-Type: application/json');

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$submissionId = $_POST['submission_id'] ?? null;
$isCompleted = isset($_POST['is_completed']) && $_POST['is_completed'] == '1';

if (!$submissionId) {
    echo json_encode(['success' => false, 'error' => '提出物IDが指定されていません']);
    exit;
}

try {
    // 提出物が生徒のものかチェック
    $stmt = $pdo->prepare("
        SELECT wps.id, wps.weekly_plan_id
        FROM weekly_plan_submissions wps
        INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
        WHERE wps.id = ? AND wp.student_id = ?
    ");
    $stmt->execute([$submissionId, $studentId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
        exit;
    }

    // 提出物の完了状態を更新
    if ($isCompleted) {
        $stmt = $pdo->prepare("
            UPDATE weekly_plan_submissions
            SET is_completed = 1,
                completed_at = NOW(),
                completed_by_type = 'student',
                completed_by_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$studentId, $submissionId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE weekly_plan_submissions
            SET is_completed = 0,
                completed_at = NULL,
                completed_by_type = NULL,
                completed_by_id = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$submissionId]);
    }

    echo json_encode(['success' => true, 'message' => '更新しました']);

} catch (Exception $e) {
    error_log("Toggle submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '更新中にエラーが発生しました']);
}
