<?php
/**
 * 個別支援計画書 保護者確認API
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$planId = $input['plan_id'] ?? null;

if (!$planId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'プランIDが指定されていません']);
    exit;
}

try {
    // この計画が保護者の生徒のものか確認
    $stmt = $pdo->prepare("
        SELECT isp.id, isp.student_id, isp.guardian_confirmed
        FROM individual_support_plans isp
        INNER JOIN students s ON isp.student_id = s.id
        WHERE isp.id = ? AND s.guardian_id = ? AND isp.is_draft = 0
    ");
    $stmt->execute([$planId, $guardianId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '計画が見つかりません']);
        exit;
    }

    // 既に確認済みの場合
    if ($plan['guardian_confirmed']) {
        echo json_encode(['success' => false, 'message' => '既に確認済みです']);
        exit;
    }

    // 確認済みフラグを更新
    $stmt = $pdo->prepare("
        UPDATE individual_support_plans
        SET guardian_confirmed = 1,
            guardian_confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$planId]);

    echo json_encode(['success' => true, 'message' => '確認しました']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
?>
