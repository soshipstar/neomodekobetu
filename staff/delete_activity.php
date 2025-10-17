<?php
/**
 * 活動削除処理
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// POSTデータを取得
$activityId = $_POST['activity_id'] ?? null;

if (!$activityId) {
    $_SESSION['error'] = '活動IDが指定されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 活動が自分のものか確認
    $stmt = $pdo->prepare("
        SELECT id FROM daily_records
        WHERE id = ? AND staff_id = ?
    ");
    $stmt->execute([$activityId, $currentUser['id']]);
    $activity = $stmt->fetch();

    if (!$activity) {
        throw new Exception('指定された活動が見つかりません');
    }

    // 生徒記録を削除（外部キー制約で自動削除されるが明示的に）
    $stmt = $pdo->prepare("
        DELETE FROM student_records
        WHERE daily_record_id = ?
    ");
    $stmt->execute([$activityId]);

    // 活動を削除
    $stmt = $pdo->prepare("
        DELETE FROM daily_records
        WHERE id = ?
    ");
    $stmt->execute([$activityId]);

    $pdo->commit();

    $_SESSION['success'] = '活動を削除しました';
    header('Location: renrakucho_activities.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting activity: " . $e->getMessage());

    $_SESSION['error'] = '削除中にエラーが発生しました: ' . $e->getMessage();
    header('Location: renrakucho_activities.php');
    exit;
}
