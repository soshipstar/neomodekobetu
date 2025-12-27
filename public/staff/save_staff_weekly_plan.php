<?php
/**
 * スタッフ用 - 生徒週間計画表の保存処理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

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

// アクセス権限チェック（生徒のclassroom_idでフィルタ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        WHERE s.id = ? AND s.classroom_id = ?
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

// フィールドデータを収集
$weeklyGoal = trim($_POST['weekly_goal'] ?? '');
$sharedGoal = trim($_POST['shared_goal'] ?? '');
$mustDo = trim($_POST['must_do'] ?? '');
$shouldDo = trim($_POST['should_do'] ?? '');
$wantToDo = trim($_POST['want_to_do'] ?? '');

// 曜日ごとの計画データを収集
$planData = [];
$days = ['day_0', 'day_1', 'day_2', 'day_3', 'day_4', 'day_5', 'day_6'];
foreach ($days as $day) {
    $planData[$day] = trim($_POST[$day] ?? '');
}

$planDataJson = json_encode($planData, JSON_UNESCAPED_UNICODE);

// 提出物データを収集
$submissions = $_POST['submissions'] ?? [];

try {
    $pdo->beginTransaction();

    // 既存の計画があるかチェック
    $stmt = $pdo->prepare("
        SELECT id FROM weekly_plans
        WHERE student_id = ? AND week_start_date = ?
    ");
    $stmt->execute([$studentId, $weekStartDate]);
    $existingPlan = $stmt->fetch();

    if ($existingPlan) {
        // 更新
        $planId = $existingPlan['id'];
        $stmt = $pdo->prepare("
            UPDATE weekly_plans
            SET weekly_goal = ?,
                shared_goal = ?,
                must_do = ?,
                should_do = ?,
                want_to_do = ?,
                plan_data = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $weeklyGoal,
            $sharedGoal,
            $mustDo,
            $shouldDo,
            $wantToDo,
            $planDataJson,
            $planId
        ]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO weekly_plans (
                student_id,
                week_start_date,
                weekly_goal,
                shared_goal,
                must_do,
                should_do,
                want_to_do,
                plan_data,
                created_by_type,
                created_by_id,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'staff', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $studentId,
            $weekStartDate,
            $weeklyGoal,
            $sharedGoal,
            $mustDo,
            $shouldDo,
            $wantToDo,
            $planDataJson,
            $_SESSION['user_id']
        ]);
        $planId = $pdo->lastInsertId();
    }

    // 提出物の処理
    // 既存の提出物IDを取得
    $stmt = $pdo->prepare("SELECT id FROM weekly_plan_submissions WHERE weekly_plan_id = ?");
    $stmt->execute([$planId]);
    $existingSubmissionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $processedSubmissionIds = [];

    foreach ($submissions as $submission) {
        $item = trim($submission['item'] ?? '');
        $dueDate = $submission['due_date'] ?? '';
        $isCompleted = isset($submission['completed']) && $submission['completed'] == '1';
        $submissionId = $submission['id'] ?? null;

        if (empty($item) || empty($dueDate)) {
            continue;
        }

        if ($submissionId && in_array($submissionId, $existingSubmissionIds)) {
            // 既存の提出物を更新
            if ($isCompleted) {
                // 完了状態に変更
                $stmt = $pdo->prepare("
                    UPDATE weekly_plan_submissions
                    SET submission_item = ?,
                        due_date = ?,
                        is_completed = 1,
                        completed_at = COALESCE(completed_at, NOW()),
                        completed_by_type = COALESCE(completed_by_type, 'staff'),
                        completed_by_id = COALESCE(completed_by_id, ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item, $dueDate, $_SESSION['user_id'], $submissionId]);
            } else {
                // 未完了に戻す
                $stmt = $pdo->prepare("
                    UPDATE weekly_plan_submissions
                    SET submission_item = ?,
                        due_date = ?,
                        is_completed = 0,
                        completed_at = NULL,
                        completed_by_type = NULL,
                        completed_by_id = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$item, $dueDate, $submissionId]);
            }
            $processedSubmissionIds[] = $submissionId;
        } else {
            // 新しい提出物を追加
            $stmt = $pdo->prepare("
                INSERT INTO weekly_plan_submissions (
                    weekly_plan_id,
                    submission_item,
                    due_date,
                    is_completed,
                    completed_at,
                    completed_by_type,
                    completed_by_id,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if ($isCompleted) {
                $stmt->execute([
                    $planId,
                    $item,
                    $dueDate,
                    1,
                    date('Y-m-d H:i:s'),
                    'staff',
                    $_SESSION['user_id']
                ]);
            } else {
                $stmt->execute([
                    $planId,
                    $item,
                    $dueDate,
                    0,
                    null,
                    null,
                    null
                ]);
            }
        }
    }

    // 削除された提出物を処理
    $submissionIdsToDelete = array_diff($existingSubmissionIds, $processedSubmissionIds);
    if (!empty($submissionIdsToDelete)) {
        $placeholders = implode(',', array_fill(0, count($submissionIdsToDelete), '?'));
        $stmt = $pdo->prepare("DELETE FROM weekly_plan_submissions WHERE id IN ($placeholders)");
        $stmt->execute($submissionIdsToDelete);
    }

    $pdo->commit();

    header("Location: student_weekly_plan_detail.php?student_id=$studentId&date=$weekStartDate&success=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: student_weekly_plan_detail.php?student_id=$studentId&date=$weekStartDate&error=" . urlencode($e->getMessage()));
    exit;
}
