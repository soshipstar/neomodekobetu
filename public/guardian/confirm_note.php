<?php
/**
 * 連絡帳の保護者確認処理
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// ログインチェック
requireLogin();

// 保護者でない場合はエラー
if ($_SESSION['user_type'] !== 'guardian') {
    echo json_encode(['success' => false, 'error' => '権限がありません']);
    exit;
}

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

// note_idを取得
$noteId = $_POST['note_id'] ?? null;

if (!$noteId) {
    echo json_encode(['success' => false, 'error' => '連絡帳IDが指定されていません']);
    exit;
}

try {
    $pdo = getDbConnection();
    $guardianId = $_SESSION['user_id'];

    // この連絡帳が保護者の子供のものかチェック
    $stmt = $pdo->prepare("
        SELECT inote.id, inote.guardian_confirmed
        FROM integrated_notes inote
        INNER JOIN students s ON inote.student_id = s.id
        WHERE inote.id = ? AND s.guardian_id = ? AND inote.is_sent = 1
    ");
    $stmt->execute([$noteId, $guardianId]);
    $note = $stmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'この連絡帳にアクセスする権限がありません']);
        exit;
    }

    // すでに確認済みの場合
    if ($note['guardian_confirmed']) {
        echo json_encode(['success' => false, 'error' => 'この連絡帳は既に確認済みです']);
        exit;
    }

    // 確認フラグを更新
    $stmt = $pdo->prepare("
        UPDATE integrated_notes
        SET guardian_confirmed = 1,
            guardian_confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$noteId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Error confirming note: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました']);
}
