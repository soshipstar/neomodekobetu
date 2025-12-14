<?php
/**
 * タブレットユーザー用活動削除API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// タブレットユーザーのみアクセス可能
requireUserType('tablet_user');

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// JSONデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$activityId = $input['id'] ?? null;

if (!$activityId) {
    echo json_encode(['success' => false, 'error' => '活動IDが指定されていません']);
    exit;
}

try {
    // 削除権限を確認（同じ教室の活動のみ削除可能）
    $stmt = $pdo->prepare("
        SELECT dr.id
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$activityId, $classroomId]);
    $activity = $stmt->fetch();

    if (!$activity) {
        echo json_encode(['success' => false, 'error' => 'この活動を削除する権限がありません']);
        exit;
    }

    // 関連データも削除（トランザクション使用）
    $pdo->beginTransaction();

    // 学生記録を削除
    $stmt = $pdo->prepare("DELETE FROM student_records WHERE daily_record_id = ?");
    $stmt->execute([$activityId]);

    // 統合連絡帳を削除
    $stmt = $pdo->prepare("DELETE FROM integrated_notes WHERE daily_record_id = ?");
    $stmt->execute([$activityId]);

    // 活動を削除
    $stmt = $pdo->prepare("DELETE FROM daily_records WHERE id = ?");
    $stmt->execute([$activityId]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => '削除中にエラーが発生しました']);
}
