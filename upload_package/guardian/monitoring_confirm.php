<?php
/**
 * モニタリング表 保護者確認API
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
$monitoringId = $input['monitoring_id'] ?? null;

if (!$monitoringId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'モニタリングIDが指定されていません']);
    exit;
}

try {
    // このモニタリングが保護者の生徒のものか確認
    $stmt = $pdo->prepare("
        SELECT mr.id, mr.student_id, mr.guardian_confirmed
        FROM monitoring_records mr
        INNER JOIN students s ON mr.student_id = s.id
        WHERE mr.id = ? AND s.guardian_id = ? AND mr.is_draft = 0
    ");
    $stmt->execute([$monitoringId, $guardianId]);
    $monitoring = $stmt->fetch();

    if (!$monitoring) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'モニタリング表が見つかりません']);
        exit;
    }

    // 既に確認済みの場合
    if ($monitoring['guardian_confirmed']) {
        echo json_encode(['success' => false, 'message' => '既に確認済みです']);
        exit;
    }

    // 確認済みフラグを更新
    $stmt = $pdo->prepare("
        UPDATE monitoring_records
        SET guardian_confirmed = 1,
            guardian_confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$monitoringId]);

    echo json_encode(['success' => true, 'message' => '確認しました']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
?>
