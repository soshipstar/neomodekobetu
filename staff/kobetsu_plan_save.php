<?php
/**
 * 個別支援計画書保存処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kobetsu_plan.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

$studentId = $_POST['student_id'] ?? null;
$planId = $_POST['plan_id'] ?? null;
$action = $_POST['action'] ?? 'save';
$isDraft = isset($_POST['save_draft']) ? 1 : 0; // 下書き保存ボタンが押された場合は1

// 基本情報
$studentName = $_POST['student_name'] ?? '';
$createdDate = $_POST['created_date'] ?? '';
$lifeIntention = $_POST['life_intention'] ?? '';
$overallPolicy = $_POST['overall_policy'] ?? '';
$longTermGoalDate = $_POST['long_term_goal_date'] ?? null;
$longTermGoalText = $_POST['long_term_goal_text'] ?? '';
$shortTermGoalDate = $_POST['short_term_goal_date'] ?? null;
$shortTermGoalText = $_POST['short_term_goal_text'] ?? '';
$managerName = $_POST['manager_name'] ?? '';
$consentDate = $_POST['consent_date'] ?? null;
$guardianSignature = $_POST['guardian_signature'] ?? '';

// 明細データ
$details = $_POST['details'] ?? [];

// バリデーション
if (!$studentId || !$studentName || !$createdDate) {
    $_SESSION['error'] = '必須項目を入力してください。';
    header("Location: kobetsu_plan.php?student_id=$studentId" . ($planId ? "&plan_id=$planId" : ''));
    exit;
}

// 退所日チェック：退所済みの生徒の場合、退所日以降の計画書は作成できない
$stmt = $pdo->prepare("SELECT withdrawal_date FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
if ($student && $student['withdrawal_date']) {
    $withdrawalDate = new DateTime($student['withdrawal_date']);
    $planCreatedDate = new DateTime($createdDate);
    if ($planCreatedDate >= $withdrawalDate) {
        $_SESSION['error'] = "退所日（{$student['withdrawal_date']}）以降の個別支援計画書は作成できません。";
        header("Location: kobetsu_plan.php?student_id=$studentId" . ($planId ? "&plan_id=$planId" : ''));
        exit;
    }
}

try {
    $pdo->beginTransaction();

    if ($planId) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE individual_support_plans SET
                student_name = ?,
                created_date = ?,
                life_intention = ?,
                overall_policy = ?,
                long_term_goal_date = ?,
                long_term_goal_text = ?,
                short_term_goal_date = ?,
                short_term_goal_text = ?,
                manager_name = ?,
                consent_date = ?,
                guardian_signature = ?,
                is_draft = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $studentName,
            $createdDate,
            $lifeIntention,
            $overallPolicy,
            $longTermGoalDate ?: null,
            $longTermGoalText,
            $shortTermGoalDate ?: null,
            $shortTermGoalText,
            $managerName,
            $consentDate ?: null,
            $guardianSignature,
            $isDraft,
            $planId
        ]);

        // 既存の明細を削除
        $stmt = $pdo->prepare("DELETE FROM individual_support_plan_details WHERE plan_id = ?");
        $stmt->execute([$planId]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO individual_support_plans (
                student_id,
                student_name,
                created_date,
                life_intention,
                overall_policy,
                long_term_goal_date,
                long_term_goal_text,
                short_term_goal_date,
                short_term_goal_text,
                manager_name,
                consent_date,
                guardian_signature,
                is_draft,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $studentId,
            $studentName,
            $createdDate,
            $lifeIntention,
            $overallPolicy,
            $longTermGoalDate ?: null,
            $longTermGoalText,
            $shortTermGoalDate ?: null,
            $shortTermGoalText,
            $managerName,
            $consentDate ?: null,
            $guardianSignature,
            $isDraft,
            $staffId
        ]);

        $planId = $pdo->lastInsertId();
    }

    // 明細を保存
    $stmt = $pdo->prepare("
        INSERT INTO individual_support_plan_details (
            plan_id,
            row_order,
            category,
            sub_category,
            support_goal,
            support_content,
            achievement_date,
            staff_organization,
            notes,
            priority
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($details as $index => $detail) {
        // 空行はスキップ
        if (empty($detail['category']) && empty($detail['sub_category']) && empty($detail['support_goal'])) {
            continue;
        }

        $stmt->execute([
            $planId,
            $index,
            $detail['category'] ?? '',
            $detail['sub_category'] ?? '',
            $detail['support_goal'] ?? '',
            $detail['support_content'] ?? '',
            $detail['achievement_date'] ?: null,
            $detail['staff_organization'] ?? '',
            $detail['notes'] ?? '',
            $detail['priority'] ?: null
        ]);
    }

    $pdo->commit();

    if ($isDraft) {
        $_SESSION['success'] = '個別支援計画書を下書き保存しました。（保護者には非公開）';
    } else {
        $_SESSION['success'] = '個別支援計画書を提出しました。（保護者にも公開）';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header("Location: kobetsu_plan.php?student_id=$studentId&plan_id=$planId");
exit;
