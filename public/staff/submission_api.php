<?php
/**
 * 提出期限管理API（スタッフ用）
 */

session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '許可されていないメソッド']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'IDが指定されていません']);
    exit;
}

try {
    // アクセス権限チェック: 提出リクエストが自教室の生徒に関連しているか確認
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT sr.id FROM submission_requests sr
            INNER JOIN students s ON sr.student_id = s.id
            WHERE sr.id = ? AND s.classroom_id = ?
        ");
        $stmt->execute([$id, $classroomId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
            exit;
        }
    }

    // 提出完了にする
    if ($action === 'complete') {
        $note = trim($_POST['note'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE submission_requests
            SET is_completed = 1,
                completed_at = NOW(),
                completed_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$note, $id]);

        echo json_encode(['success' => true, 'message' => '提出完了にしました']);
    }

    // 未提出に戻す
    elseif ($action === 'incomplete') {
        $stmt = $pdo->prepare("
            UPDATE submission_requests
            SET is_completed = 0,
                completed_at = NULL,
                completed_note = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => '未提出に戻しました']);
    }

    else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '無効なアクション']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'データベースエラー: ' . $e->getMessage()]);
}
?>
