<?php
/**
 * モニタリング作成テスト
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/kakehashi_auto_generator.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

echo "<h1>モニタリング作成テスト</h1>";

// テスト対象の生徒
$studentId = 6; // 田中ボウリン健太郎
$monitoringDate = '2022-08-06'; // 初回かけはし提出期限

echo "<p>生徒ID: {$studentId}</p>";
echo "<p>モニタリング日: {$monitoringDate}</p>";
echo "<hr>";

// 生徒情報確認
echo "<h2>Step 1: 生徒情報確認</h2>";
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if ($student) {
    echo "<p style='color: green;'>✓ 生徒が見つかりました: " . htmlspecialchars($student['student_name']) . "</p>";
} else {
    echo "<p style='color: red;'>✗ 生徒が見つかりません</p>";
    exit;
}

// 個別支援計画確認
echo "<h2>Step 2: 個別支援計画確認</h2>";
$stmt = $pdo->prepare("
    SELECT * FROM individual_support_plans
    WHERE student_id = ?
    ORDER BY created_date DESC, id DESC
    LIMIT 1
");
$stmt->execute([$studentId]);
$latestPlan = $stmt->fetch();

if ($latestPlan) {
    echo "<p style='color: green;'>✓ 個別支援計画が見つかりました</p>";
    echo "<ul>";
    echo "<li>計画ID: {$latestPlan['id']}</li>";
    echo "<li>作成日: {$latestPlan['created_date']}</li>";
    echo "<li>生徒名: " . htmlspecialchars($latestPlan['student_name']) . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ 個別支援計画が見つかりません</p>";
    echo "<p>この生徒の個別支援計画を先に作成してください。</p>";
    exit;
}

// 計画の明細確認
echo "<h2>Step 3: 計画明細確認</h2>";
$stmt = $pdo->prepare("
    SELECT * FROM individual_support_plan_details
    WHERE plan_id = ?
    ORDER BY row_order
");
$stmt->execute([$latestPlan['id']]);
$planDetails = $stmt->fetchAll();

echo "<p>明細数: " . count($planDetails) . " 件</p>";

if (empty($planDetails)) {
    echo "<p style='color: orange;'>⚠ 計画の明細が0件です。モニタリングシートは作成されますが、明細は空になります。</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; font-size: 12px;'>";
    echo "<tr><th>ID</th><th>順序</th><th>項目</th><th>支援目標</th></tr>";
    foreach ($planDetails as $detail) {
        echo "<tr>";
        echo "<td>{$detail['id']}</td>";
        echo "<td>{$detail['row_order']}</td>";
        echo "<td>" . htmlspecialchars($detail['category'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars(substr($detail['support_goal'] ?? '', 0, 50)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 既存のモニタリング確認
echo "<h2>Step 4: 既存モニタリング確認</h2>";
$stmt = $pdo->prepare("
    SELECT id FROM monitoring_records
    WHERE student_id = ? AND monitoring_date = ?
");
$stmt->execute([$studentId, $monitoringDate]);
$existing = $stmt->fetch();

if ($existing) {
    echo "<p style='color: orange;'>⚠ 既にモニタリングシートが存在します (ID: {$existing['id']})</p>";
    echo "<p><a href='?delete=1&monitoring_id={$existing['id']}'>削除してから再作成</a></p>";

    if (isset($_GET['delete']) && isset($_GET['monitoring_id'])) {
        $monitoringId = (int)$_GET['monitoring_id'];
        $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id = ?")->execute([$monitoringId]);
        $pdo->prepare("DELETE FROM monitoring_records WHERE id = ?")->execute([$monitoringId]);
        echo "<p style='color: green;'>✓ 削除しました。ページをリロードして再実行してください。</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✓ 既存のモニタリングシートはありません</p>";
}

// モニタリング作成実行
echo "<h2>Step 5: モニタリング作成実行</h2>";

try {
    createMonitoringForPeriod($pdo, $studentId, $monitoringDate);
    echo "<p style='color: green; font-weight: bold;'>✓ モニタリングシート作成関数を実行しました</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 作成結果確認
echo "<h2>Step 6: 作成結果確認</h2>";
$stmt = $pdo->prepare("
    SELECT * FROM monitoring_records
    WHERE student_id = ? AND monitoring_date = ?
");
$stmt->execute([$studentId, $monitoringDate]);
$newMonitoring = $stmt->fetch();

if ($newMonitoring) {
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>✓ モニタリングシートが作成されました！</p>";
    echo "<ul>";
    echo "<li>モニタリングID: {$newMonitoring['id']}</li>";
    echo "<li>生徒名: " . htmlspecialchars($newMonitoring['student_name']) . "</li>";
    echo "<li>モニタリング日: {$newMonitoring['monitoring_date']}</li>";
    echo "<li>参照計画ID: {$newMonitoring['plan_id']}</li>";
    echo "</ul>";

    // 明細数を確認
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM monitoring_details WHERE monitoring_id = ?");
    $stmt->execute([$newMonitoring['id']]);
    $detailCount = $stmt->fetch()['count'];

    echo "<p>作成された明細数: {$detailCount} 件</p>";

    echo "<p><a href='check_monitoring_created.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>モニタリング一覧を見る</a></p>";

} else {
    echo "<p style='color: red; font-weight: bold;'>✗ モニタリングシートが作成されていません</p>";
}
