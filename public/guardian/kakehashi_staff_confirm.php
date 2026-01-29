<?php
/**
 * 保護者がスタッフかけはしを確認するAPI
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

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
$studentId = $input['student_id'] ?? null;
$periodId = $input['period_id'] ?? null;

if (!$studentId || !$periodId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
    exit;
}

try {
    // この生徒が保護者のものか確認
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND guardian_id = ?");
    $stmt->execute([$studentId, $guardianId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'アクセス権限がありません']);
        exit;
    }

    // スタッフかけはしが提出済みか確認
    $stmt = $pdo->prepare("
        SELECT id, guardian_confirmed
        FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ? AND is_submitted = 1
    ");
    $stmt->execute([$studentId, $periodId]);
    $kakehashi = $stmt->fetch();

    if (!$kakehashi) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'スタッフかけはしが見つかりません']);
        exit;
    }

    if ($kakehashi['guardian_confirmed']) {
        echo json_encode(['success' => true, 'message' => '既に確認済みです', 'already_confirmed' => true]);
        exit;
    }

    // 確認済みに更新
    $stmt = $pdo->prepare("
        UPDATE kakehashi_staff
        SET guardian_confirmed = 1, guardian_confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$kakehashi['id']]);

    echo json_encode(['success' => true, 'message' => '確認しました']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
?>
