<?php
/**
 * かけはし期間削除処理
 *
 * 【重要】かけはし期間の削除は無効化されています。
 * 日付の整合性を保つため、このファイルへの直接アクセスは拒否します。
 * 削除が必要な場合は管理者用の修正ツールを使用してください。
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

// 削除機能は無効化
$_SESSION['error'] = 'かけはし期間の削除はシステムの整合性を保つため無効化されています。';
header('Location: kakehashi_periods.php');
exit;
