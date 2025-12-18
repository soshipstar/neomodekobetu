<?php
/**
 * 毎日の支援取得 API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// スタッフまたは管理者のみアクセス可能
if (!isLoggedIn() || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    echo json_encode(['success' => false, 'error' => '教室が設定されていません']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT id, routine_name, routine_content, scheduled_time
        FROM daily_routines
        WHERE classroom_id = ? AND is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$classroomId]);
    $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $routines
    ]);

} catch (PDOException $e) {
    // テーブルが存在しない場合は空配列を返す
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'does not exist') !== false) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'テーブルが存在しません。マイグレーションを実行してください。'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'データの取得に失敗しました: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'データの取得に失敗しました: ' . $e->getMessage()
    ]);
}
