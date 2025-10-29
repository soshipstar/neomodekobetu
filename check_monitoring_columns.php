<?php
/**
 * monitoring_recordsテーブルのカラムを確認
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "monitoring_recordsテーブルのカラムを確認しています...\n\n";

    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'monitoring_records'
        ORDER BY ORDINAL_POSITION
    ");

    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "全カラム:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-40s %-20s %s\n", "カラム名", "型", "コメント");
    echo str_repeat("-", 80) . "\n";

    $goalColumns = [];
    foreach ($columns as $col) {
        printf("%-40s %-20s %s\n",
            $col['COLUMN_NAME'],
            $col['COLUMN_TYPE'],
            $col['COLUMN_COMMENT']
        );

        if (strpos($col['COLUMN_NAME'], 'goal') !== false) {
            $goalColumns[] = $col['COLUMN_NAME'];
        }
    }

    echo "\n目標関連のカラム:\n";
    echo str_repeat("-", 80) . "\n";
    if (!empty($goalColumns)) {
        foreach ($goalColumns as $col) {
            echo "✓ {$col}\n";
        }
    } else {
        echo "目標関連のカラムが見つかりません。\n";
    }

    echo "\n必要なカラムの確認:\n";
    echo str_repeat("-", 80) . "\n";
    $requiredColumns = [
        'short_term_goal_achievement',
        'short_term_goal_comment',
        'long_term_goal_achievement',
        'long_term_goal_comment'
    ];

    foreach ($requiredColumns as $required) {
        $exists = in_array($required, $goalColumns);
        echo ($exists ? "✓" : "✗") . " {$required}\n";
    }

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
