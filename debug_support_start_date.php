<?php
/**
 * デバッグ: support_start_date の確認
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "=== 最近の生徒データ (support_start_date 確認) ===\n\n";

$stmt = $pdo->query("
    SELECT
        id,
        student_name,
        birth_date,
        support_start_date,
        created_at
    FROM students
    WHERE is_active = 1
    ORDER BY id DESC
    LIMIT 10
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as $student) {
    echo "生徒ID: {$student['id']}\n";
    echo "生徒名: {$student['student_name']}\n";
    echo "生年月日: {$student['birth_date']}\n";
    echo "支援開始日: " . ($student['support_start_date'] ?? 'NULL') . "\n";
    echo "作成日時: {$student['created_at']}\n";
    echo "---\n\n";
}

echo "\n=== かけはし期間データ ===\n\n";

$stmt = $pdo->query("
    SELECT
        kp.id,
        kp.student_id,
        s.student_name,
        kp.period_name,
        kp.submission_deadline,
        kp.is_active
    FROM kakehashi_periods kp
    INNER JOIN students s ON kp.student_id = s.id
    ORDER BY kp.id DESC
    LIMIT 10
");
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($periods)) {
    echo "かけはし期間データがありません。\n";
} else {
    foreach ($periods as $period) {
        echo "期間ID: {$period['id']}\n";
        echo "生徒ID: {$period['student_id']} ({$period['student_name']})\n";
        echo "期間名: {$period['period_name']}\n";
        echo "提出期限: {$period['submission_deadline']}\n";
        echo "有効: " . ($period['is_active'] ? 'はい' : 'いいえ') . "\n";
        echo "---\n\n";
    }
}
