<?php
/**
 * チャットルームピン留めAPI
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// テーブル存在チェック
try {
    $pdo->query("SELECT 1 FROM chat_room_pins LIMIT 1");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'ピン留め機能が利用できません。マイグレーションを実行してください。']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '許可されていないメソッド']);
    exit;
}

$action = $_POST['action'] ?? '';
$roomId = $_POST['room_id'] ?? null;

if (!$roomId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ルームIDが指定されていません']);
    exit;
}

try {
    if ($action === 'pin') {
        // ピン留め
        $stmt = $pdo->prepare("INSERT IGNORE INTO chat_room_pins (room_id, staff_id) VALUES (?, ?)");
        $stmt->execute([$roomId, $staffId]);
        echo json_encode(['success' => true, 'message' => 'ピン留めしました']);
    } elseif ($action === 'unpin') {
        // ピン解除
        $stmt = $pdo->prepare("DELETE FROM chat_room_pins WHERE room_id = ? AND staff_id = ?");
        $stmt->execute([$roomId, $staffId]);
        echo json_encode(['success' => true, 'message' => 'ピン解除しました']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '無効なアクション']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'データベースエラー: ' . $e->getMessage()]);
}
