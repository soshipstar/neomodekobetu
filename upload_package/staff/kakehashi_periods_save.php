<?php
/**
 * かけはし期間作成処理
 *
 * 【重要】かけはし期間は自動生成されます。
 * 手動での期間作成は無効化されています。
 * 日付の整合性を保つため、このファイルへの直接アクセスは拒否します。
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

// 手動での期間作成は無効化
$_SESSION['error'] = 'かけはし期間は支援開始日を基準に自動生成されます。手動での作成はできません。';
header('Location: kakehashi_periods.php');
exit;
