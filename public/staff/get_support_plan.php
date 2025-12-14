<?php
/**
 * 個別の支援案を取得するAPI
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;
$planId = $_GET['id'] ?? null;

if (!$planId) {
    echo json_encode(['error' => 'IDが指定されていません']);
    exit;
}

// 同じ教室の支援案を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM support_plans
        WHERE id = ? AND classroom_id = ?
    ");
    $stmt->execute([$planId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM support_plans
        WHERE id = ?
    ");
    $stmt->execute([$planId]);
}

$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if ($plan) {
    echo json_encode($plan);
} else {
    echo json_encode(['error' => '支援案が見つかりません']);
}
