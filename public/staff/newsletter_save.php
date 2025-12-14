<?php
/**
 * 施設通信保存・発行処理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();

try {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;

    if (!$id) {
        throw new Exception('通信IDが指定されていません');
    }

    // 通信を取得
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$id]);
    $newsletter = $stmt->fetch();

    if (!$newsletter) {
        throw new Exception('通信が見つかりません');
    }

    // データを取得
    $title = $_POST['title'] ?? $newsletter['title'];
    $greeting = $_POST['greeting'] ?? '';
    $eventCalendar = $_POST['event_calendar'] ?? '';
    $eventDetails = $_POST['event_details'] ?? '';
    $weeklyReports = $_POST['weekly_reports'] ?? '';
    $eventResults = $_POST['event_results'] ?? '';
    $requests = $_POST['requests'] ?? '';
    $others = $_POST['others'] ?? '';

    if ($action === 'save') {
        // 下書き保存
        $stmt = $pdo->prepare("
            UPDATE newsletters
            SET title = ?,
                greeting = ?,
                event_calendar = ?,
                event_details = ?,
                weekly_reports = ?,
                event_results = ?,
                requests = ?,
                others = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $title,
            $greeting,
            $eventCalendar,
            $eventDetails,
            $weeklyReports,
            $eventResults,
            $requests,
            $others,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => '下書きを保存しました']);

    } elseif ($action === 'publish') {
        // バリデーション
        if (empty($title)) {
            throw new Exception('タイトルを入力してください');
        }

        if (empty($greeting)) {
            throw new Exception('あいさつ文を入力してください');
        }

        // 発行
        $stmt = $pdo->prepare("
            UPDATE newsletters
            SET title = ?,
                greeting = ?,
                event_calendar = ?,
                event_details = ?,
                weekly_reports = ?,
                event_results = ?,
                requests = ?,
                others = ?,
                status = 'published',
                published_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $title,
            $greeting,
            $eventCalendar,
            $eventDetails,
            $weeklyReports,
            $eventResults,
            $requests,
            $others,
            $id
        ]);

        echo json_encode(['success' => true, 'message' => '通信を発行しました']);

    } else {
        throw new Exception('不正なアクションです');
    }

} catch (Exception $e) {
    error_log("Newsletter save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
