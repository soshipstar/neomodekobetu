<?php
/**
 * タブレットユーザーアカウントの有効/無効切り替え
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// 管理者のみアクセス可能
requireUserType('admin');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$isMaster = isMasterAdmin();
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tablet_accounts.php');
    exit;
}

$userId = $_POST['user_id'] ?? null;
$isActive = $_POST['is_active'] ?? 0;

if (!$userId) {
    $_SESSION['error'] = 'ユーザーIDが指定されていません';
    header('Location: tablet_accounts.php');
    exit;
}

try {
    // 編集権限チェック
    if ($isMaster) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'tablet_user'");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'tablet_user' AND classroom_id = ?");
        $stmt->execute([$userId, $classroomId]);
    }

    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'このユーザーの状態を変更する権限がありません';
        header('Location: tablet_accounts.php');
        exit;
    }

    // 状態を更新
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$isActive, $userId]);

    $message = $isActive ? 'ユーザーを有効化しました' : 'ユーザーを無効化しました';
    header('Location: tablet_accounts.php?success=' . urlencode($message));
    exit;

} catch (PDOException $e) {
    error_log("Tablet user toggle error: " . $e->getMessage());
    $_SESSION['error'] = '状態変更中にエラーが発生しました';
    header('Location: tablet_accounts.php');
    exit;
}
