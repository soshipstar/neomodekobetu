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

    // スタッフの教室IDを取得
    $classroomId = $_SESSION['classroom_id'] ?? null;

    // 活動が存在し、同じ教室のスタッフが作成したものか確認
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT dr.id FROM daily_records dr
            INNER JOIN users u ON dr.staff_id = u.id
            WHERE dr.id = ? AND u.classroom_id = ?
        ");
        $stmt->execute([$activityId, $classroomId]);
    } else {
        // 教室IDがない場合は、自分の活動のみ削除可能
        $stmt = $pdo->prepare("
            SELECT id FROM daily_records
            WHERE id = ? AND staff_id = ?
        ");
        $stmt->execute([$activityId, $currentUser['id']]);
    }

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
