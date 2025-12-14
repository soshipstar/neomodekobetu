<?php
/**
 * 統合内容を1から生成しなおすページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = $_GET['activity_id'] ?? null;

if (!$activityId) {
    $_SESSION['error'] = '活動IDが指定されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動情報を取得（同じ教室のスタッフが作成した活動も統合可能）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id FROM daily_records dr
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
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: renrakucho_activities.php');
    exit;
}

try {
    // 既存の統合内容を全て削除（送信済みを除く）
    $stmt = $pdo->prepare("
        DELETE FROM integrated_notes
        WHERE daily_record_id = ? AND is_sent = 0
    ");
    $stmt->execute([$activityId]);

    $_SESSION['success'] = '既存の統合内容を削除しました。新しく統合を開始します。';
    header('Location: integrate_activity.php?activity_id=' . $activityId);
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    header('Location: renrakucho_activities.php');
    exit;
}
