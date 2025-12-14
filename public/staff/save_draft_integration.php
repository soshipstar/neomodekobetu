<?php
/**
 * 活動内容統合の途中保存API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

// 強力なtrim処理関数
if (!function_exists('powerTrim')) {
    function powerTrim($text) {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);
    }
}

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$activityId = $_POST['activity_id'] ?? null;
$notes = $_POST['notes'] ?? [];

if (!$activityId) {
    echo json_encode(['success' => false, 'error' => '活動IDが指定されていません']);
    exit;
}

try {
    // 活動へのアクセス権限を確認（同じ教室のスタッフが作成した活動も統合可能）
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT dr.id
            FROM daily_records dr
            INNER JOIN users u ON dr.staff_id = u.id
            WHERE dr.id = ? AND u.classroom_id = ?
        ");
        $stmt->execute([$activityId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id FROM daily_records WHERE id = ?
        ");
        $stmt->execute([$activityId]);
    }

    $activity = $stmt->fetch();

    if (!$activity) {
        echo json_encode(['success' => false, 'error' => 'この活動にアクセスする権限がありません']);
        exit;
    }

    $pdo->beginTransaction();

    $savedCount = 0;
    foreach ($notes as $studentId => $content) {
        // 強力なtrim処理（全角スペース、特殊文字も削除）
        $content = powerTrim($content);

        // 空の内容はスキップ
        if (empty($content)) {
            continue;
        }

        // 既に送信済みの記録は更新しない
        $stmt = $pdo->prepare("
            SELECT is_sent FROM integrated_notes
            WHERE daily_record_id = ? AND student_id = ?
        ");
        $stmt->execute([$activityId, $studentId]);
        $existing = $stmt->fetch();

        if ($existing && $existing['is_sent'] == 1) {
            continue; // 送信済みの場合はスキップ
        }

        // INSERT ... ON DUPLICATE KEY UPDATE を使用
        $stmt = $pdo->prepare("
            INSERT INTO integrated_notes (daily_record_id, student_id, integrated_content, is_sent)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                integrated_content = VALUES(integrated_content),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$activityId, $studentId, $content]);
        $savedCount++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "{$savedCount}件の統合内容を途中保存しました",
        'saved_count' => $savedCount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Draft save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => '保存中にエラーが発生しました: ' . $e->getMessage()]);
}
