<?php
/**
 * 個別支援計画書・モニタリング表の非表示切り替えAPI
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$studentId = (int)($_POST['student_id'] ?? 0);
$action = $_POST['action'] ?? 'hide';

// initial_monitoringの場合はstudent_idを使用、それ以外はidを使用
if ($type === 'initial_monitoring') {
    if (!$studentId) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
} else {
    if (!$id || !in_array($type, ['plan', 'monitoring'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
}

$pdo = getDbConnection();

try {
    $isHidden = ($action === 'hide') ? 1 : 0;

    if ($type === 'plan') {
        $stmt = $pdo->prepare("UPDATE individual_support_plans SET is_hidden = ? WHERE id = ?");
        $stmt->execute([$isHidden, $id]);
    } elseif ($type === 'monitoring') {
        $stmt = $pdo->prepare("UPDATE monitoring_records SET is_hidden = ? WHERE id = ?");
        $stmt->execute([$isHidden, $id]);
    } elseif ($type === 'initial_monitoring') {
        $stmt = $pdo->prepare("UPDATE students SET hide_initial_monitoring = ? WHERE id = ?");
        $stmt->execute([$isHidden, $studentId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Toggle hide error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
