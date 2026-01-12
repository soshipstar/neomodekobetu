<?php
/**
 * 個別支援計画書 署名保存処理
 *
 * 保護者・職員の署名を保存し、計画書を正式版として確定する
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kobetsu_plan.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

$planId = $_POST['plan_id'] ?? null;
$guardianSignatureImage = $_POST['guardian_signature_image'] ?? null;
$staffSignatureImage = $_POST['staff_signature_image'] ?? null;
$staffSignerName = $_POST['staff_signer_name'] ?? '';

// デバッグログ
error_log("=== 署名保存デバッグ ===");
error_log("plan_id: " . $planId);
error_log("guardian_signature_image length: " . strlen($guardianSignatureImage ?? ''));
error_log("staff_signature_image length: " . strlen($staffSignatureImage ?? ''));
error_log("staff_signer_name: " . $staffSignerName);
error_log("guardian starts with data:image: " . (strpos($guardianSignatureImage ?? '', 'data:image') === 0 ? 'yes' : 'no'));
error_log("staff starts with data:image: " . (strpos($staffSignatureImage ?? '', 'data:image') === 0 ? 'yes' : 'no'));

// バリデーション
if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 署名データの検証（職員署名は必須、保護者署名はオプション）
if (empty($staffSignatureImage) || strpos($staffSignatureImage, 'data:image') !== 0) {
    $_SESSION['error'] = '職員の署名が入力されていません。';
    header("Location: kobetsu_plan_sign.php?plan_id=$planId");
    exit;
}

// 保護者署名が不正な形式の場合はnullにする
if (!empty($guardianSignatureImage) && strpos($guardianSignatureImage, 'data:image') !== 0) {
    $guardianSignatureImage = null;
}

// 計画が存在するか確認
$stmt = $pdo->prepare("SELECT id, student_id FROM individual_support_plans WHERE id = ?");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '指定された計画が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

try {
    // 署名データと正式版フラグを保存
    $today = date('Y-m-d');
    $guardianSignatureDate = $guardianSignatureImage ? $today : null;
    $guardianConfirmed = $guardianSignatureImage ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE individual_support_plans SET
            guardian_signature_image = ?,
            guardian_signature_date = ?,
            staff_signature_image = ?,
            staff_signature_date = ?,
            staff_signer_name = ?,
            is_draft = 0,
            is_official = 1,
            guardian_confirmed = ?,
            guardian_confirmed_at = CASE WHEN ? = 1 THEN NOW() ELSE guardian_confirmed_at END,
            consent_date = COALESCE(consent_date, ?),
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $guardianSignatureImage,
        $guardianSignatureDate,
        $staffSignatureImage,
        $today,
        $staffSignerName,
        $guardianConfirmed,
        $guardianConfirmed,
        $today,
        $planId
    ]);

    $rowCount = $stmt->rowCount();
    error_log("UPDATE rows affected: " . $rowCount);

    $_SESSION['success'] = '署名を保存し、個別支援計画書を正式版として確定しました。';

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    header("Location: kobetsu_plan_sign.php?plan_id=$planId");
    exit;
}

header("Location: kobetsu_plan.php?student_id={$plan['student_id']}&plan_id=$planId");
exit;
