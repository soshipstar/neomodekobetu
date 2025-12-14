<?php
/**
 * かけはし期間の有効/無効切り替え
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kakehashi_periods.php');
    exit;
}

$pdo = getDbConnection();
$periodId = $_POST['period_id'] ?? null;

if (!$periodId) {
    $_SESSION['error'] = '期間が選択されていません。';
    header('Location: kakehashi_periods.php');
    exit;
}

try {
    // 現在の状態を取得
    $stmt = $pdo->prepare("SELECT is_active FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();

    if (!$period) {
        $_SESSION['error'] = '期間が見つかりません。';
        header('Location: kakehashi_periods.php');
        exit;
    }

    // 状態を切り替え
    $newStatus = $period['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE kakehashi_periods SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $periodId]);

    $_SESSION['success'] = $newStatus ? '期間を有効にしました。' : '期間を無効にしました。';

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header('Location: kakehashi_periods.php');
exit;
