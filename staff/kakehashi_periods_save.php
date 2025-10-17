<?php
/**
 * かけはし期間作成処理
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

$studentId = $_POST['student_id'] ?? '';
$periodName = $_POST['period_name'] ?? '';
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';

// バリデーション
if (empty($studentId) || empty($periodName) || empty($startDate) || empty($endDate)) {
    $_SESSION['error'] = '全ての項目を入力してください。';
    header('Location: kakehashi_periods.php');
    exit;
}

// 開始日から1か月後を保護者の提出期限とする
$submissionDeadline = date('Y-m-d', strtotime($startDate . ' +1 month'));

try {
    $stmt = $pdo->prepare("
        INSERT INTO kakehashi_periods (
            student_id,
            period_name,
            start_date,
            end_date,
            submission_deadline,
            is_active
        ) VALUES (?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $studentId,
        $periodName,
        $startDate,
        $endDate,
        $submissionDeadline
    ]);

    $_SESSION['success'] = '期間を作成しました。保護者の提出期限は ' . date('Y年m月d日', strtotime($submissionDeadline)) . ' です。';

} catch (PDOException $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
}

header('Location: kakehashi_periods.php');
exit;
