<?php
/**
 * マイグレーションv41実行スクリプト
 * 生徒が自分で登録する提出物テーブルの作成
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーションv41を開始します...\n";

    // SQLファイルを読み込んで実行
    $sql = file_get_contents(__DIR__ . '/migration_v41_create_student_submissions.sql');

    // コメント行を除去
    $sql = preg_replace('/^--.*$/m', '', $sql);

    // 空行を除去
    $sql = preg_replace('/^\s*$/m', '', $sql);

    // ステートメントごとに分割して実行
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "実行中: " . substr($statement, 0, 100) . "...\n";
            $pdo->exec($statement);
        }
    }

    echo "マイグレーションv41が完了しました。\n";

} catch (PDOException $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
