<?php
/**
 * マイグレーションv38実行スクリプト
 * モニタリング表に短期目標・長期目標の評価項目を追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "マイグレーションv38を開始します...\n";

    // マイグレーションSQLファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v38_add_goal_evaluations.sql');

    // PREPAREステートメントを使用しているため、トランザクションなしで実行
    // セミコロンで分割して実行
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // PREPARE/EXECUTE/DEALLOCATEの構文エラーは無視（既存カラムの場合）
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'PREPARE') === false) {
                    throw $e;
                }
            }
        }
    }

    echo "マイグレーションv38が正常に完了しました。\n";
    echo "- monitoring_recordsテーブルの短期目標・長期目標の評価項目を確認/追加しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
