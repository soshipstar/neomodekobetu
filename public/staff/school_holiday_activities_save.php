<?php
/**
 * 学校休業日活動設定保存処理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error_message'] = '教室が選択されていません';
    header('Location: school_holiday_activities.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: school_holiday_activities.php');
    exit;
}

$year = (int)($_POST['year'] ?? date('Y'));
$month = (int)($_POST['month'] ?? date('n'));
$activityDates = $_POST['activity_dates'] ?? [];

try {
    $pdo->beginTransaction();

    // この月の既存データを削除
    $stmt = $pdo->prepare("
        DELETE FROM school_holiday_activities
        WHERE classroom_id = ? AND YEAR(activity_date) = ? AND MONTH(activity_date) = ?
    ");
    $stmt->execute([$classroomId, $year, $month]);

    // 新しいデータを挿入
    if (!empty($activityDates)) {
        $stmt = $pdo->prepare("
            INSERT INTO school_holiday_activities (activity_date, classroom_id, created_by)
            VALUES (?, ?, ?)
        ");

        foreach ($activityDates as $date) {
            // 日付のバリデーション
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $stmt->execute([$date, $classroomId, $currentUser['id']]);
            }
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = $year . '年' . $month . '月の学校休業日活動設定を保存しました';

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = '保存に失敗しました: ' . $e->getMessage();
}

header("Location: school_holiday_activities.php?year=$year&month=$month");
exit;
