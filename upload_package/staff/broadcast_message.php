<?php
/**
 * 保護者への一斉送信API
 */
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    echo json_encode(['success' => false, 'error' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$guardianIds = $input['guardian_ids'] ?? [];

// バリデーション
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'メッセージが空です']);
    exit;
}

if (empty($guardianIds) || !is_array($guardianIds)) {
    echo json_encode(['success' => false, 'error' => '送信先が選択されていません']);
    exit;
}

try {
    $pdo->beginTransaction();

    $successCount = 0;
    $failedCount = 0;

    foreach ($guardianIds as $guardianId) {
        // 保護者に紐づく生徒を取得
        $stmt = $pdo->prepare("
            SELECT id FROM students
            WHERE guardian_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$guardianId]);
        $student = $stmt->fetch();

        if (!$student) {
            $failedCount++;
            continue;
        }

        $studentId = $student['id'];

        // チャットルームを取得または作成
        $stmt = $pdo->prepare("
            SELECT id FROM chat_rooms
            WHERE student_id = ? AND guardian_id = ?
        ");
        $stmt->execute([$studentId, $guardianId]);
        $room = $stmt->fetch();

        if ($room) {
            $roomId = $room['id'];
        } else {
            // チャットルームを作成
            $stmt = $pdo->prepare("
                INSERT INTO chat_rooms (student_id, guardian_id, last_message_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$studentId, $guardianId]);
            $roomId = $pdo->lastInsertId();
        }

        // メッセージを送信
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (room_id, sender_id, sender_type, message, is_read, created_at)
            VALUES (?, ?, 'staff', ?, 0, NOW())
        ");
        $stmt->execute([$roomId, $staffId, $message]);

        // チャットルームの最終メッセージ日時を更新
        $stmt = $pdo->prepare("
            UPDATE chat_rooms
            SET last_message_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$roomId]);

        $successCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'sent_count' => $successCount,
        'failed_count' => $failedCount
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => 'メッセージの送信に失敗しました: ' . $e->getMessage()
    ]);
}
