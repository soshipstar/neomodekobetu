<?php
/**
 * かけはし期間削除処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

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
    // 期間を削除（外部キー制約により関連データも削除される）
    $stmt = $pdo->prepare("DELETE FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$periodId]);

    $_SESSION['success'] = '期間を削除しました。';

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header('Location: kakehashi_periods.php');
exit;
