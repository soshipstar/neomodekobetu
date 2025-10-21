<?php
/**
 * モニタリング表保存処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kobetsu_monitoring.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

$planId = $_POST['plan_id'] ?? null;
$studentId = $_POST['student_id'] ?? null;
$monitoringId = $_POST['monitoring_id'] ?? null;
$monitoringDate = $_POST['monitoring_date'] ?? null;
$overallComment = $_POST['overall_comment'] ?? '';
$details = $_POST['details'] ?? [];
$isDraft = isset($_POST['save_draft']) ? 1 : 0; // 下書き保存ボタンが押された場合は1

// バリデーション
if (!$planId || !$studentId || !$monitoringDate) {
    $_SESSION['error'] = '必須項目を入力してください。';
    header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId" . ($monitoringId ? "&monitoring_id=$monitoringId" : ''));
    exit;
}

// 計画書から生徒名を取得
$stmt = $pdo->prepare("SELECT student_name FROM individual_support_plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '個別支援計画書が見つかりません。';
    header("Location: kobetsu_monitoring.php");
    exit;
}

// 退所日チェック：退所済みの生徒の場合、退所日以降のモニタリングは作成できない
$stmt = $pdo->prepare("SELECT withdrawal_date FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
if ($student && $student['withdrawal_date']) {
    $withdrawalDate = new DateTime($student['withdrawal_date']);
    $recordDate = new DateTime($monitoringDate);
    if ($recordDate >= $withdrawalDate) {
        $_SESSION['error'] = "退所日（{$student['withdrawal_date']}）以降のモニタリング表は作成できません。";
        header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId" . ($monitoringId ? "&monitoring_id=$monitoringId" : ''));
        exit;
    }
}

try {
    $pdo->beginTransaction();

    if ($monitoringId) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE monitoring_records SET
                monitoring_date = ?,
                overall_comment = ?,
                is_draft = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $monitoringDate,
            $overallComment,
            $isDraft,
            $monitoringId
        ]);

        // 既存の明細を削除
        $stmt = $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$monitoringId]);
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO monitoring_records (
                plan_id,
                student_id,
                student_name,
                monitoring_date,
                overall_comment,
                is_draft,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $planId,
            $studentId,
            $plan['student_name'],
            $monitoringDate,
            $overallComment,
            $isDraft,
            $staffId
        ]);

        $monitoringId = $pdo->lastInsertId();
    }

    // 明細を保存
    $stmt = $pdo->prepare("
        INSERT INTO monitoring_details (
            monitoring_id,
            plan_detail_id,
            achievement_status,
            monitoring_comment
        ) VALUES (?, ?, ?, ?)
    ");

    foreach ($details as $detailData) {
        // 達成状況もコメントも空の場合はスキップ
        if (empty($detailData['achievement_status']) && empty($detailData['monitoring_comment'])) {
            continue;
        }

        $stmt->execute([
            $monitoringId,
            $detailData['plan_detail_id'],
            $detailData['achievement_status'] ?? '',
            $detailData['monitoring_comment'] ?? ''
        ]);
    }

    $pdo->commit();

    if ($isDraft) {
        $_SESSION['success'] = 'モニタリング表を下書き保存しました。（保護者には非公開）';
    } else {
        $_SESSION['success'] = 'モニタリング表を提出しました。（保護者にも公開）';
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId&monitoring_id=$monitoringId");
exit;
