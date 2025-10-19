<?php
/**
 * Migration v19 実行スクリプト
 * 保護者確認機能の追加
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "<h2>Migration v19: 保護者確認機能の追加</h2>";

    // マイグレーションファイルを読み込み
    $sql = file_get_contents(__DIR__ . '/migration_v19_add_guardian_confirmation.sql');

    // コメント行を除去
    $sql = preg_replace('/^--.*$/m', '', $sql);

    // セミコロンで分割して各ステートメントを実行
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    echo "<h3>ステップ1: 個別支援計画書テーブルに保護者確認フィールドを追加</h3>";

    // individual_support_plansテーブルにカラムが既に存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM individual_support_plans LIKE 'guardian_confirmed'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ individual_support_plansテーブルのguardian_confirmedカラムは既に存在します。スキップします。</p>";
    } else {
        $pdo->exec("ALTER TABLE individual_support_plans
            ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ（0:未確認, 1:確認済み）',
            ADD COLUMN guardian_confirmed_at DATETIME NULL COMMENT '保護者確認日時'");
        echo "<p style='color: green;'>✓ individual_support_plansテーブルに保護者確認フィールドを追加しました。</p>";
    }

    echo "<h3>ステップ2: モニタリング表テーブルに保護者確認フィールドを追加</h3>";

    // monitoring_recordsテーブルにカラムが既に存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM monitoring_records LIKE 'guardian_confirmed'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ monitoring_recordsテーブルのguardian_confirmedカラムは既に存在します。スキップします。</p>";
    } else {
        $pdo->exec("ALTER TABLE monitoring_records
            ADD COLUMN guardian_confirmed TINYINT(1) DEFAULT 0 COMMENT '保護者確認済みフラグ（0:未確認, 1:確認済み）',
            ADD COLUMN guardian_confirmed_at DATETIME NULL COMMENT '保護者確認日時'");
        echo "<p style='color: green;'>✓ monitoring_recordsテーブルに保護者確認フィールドを追加しました。</p>";
    }

    echo "<h3>マイグレーション完了</h3>";
    echo "<p style='color: green; font-weight: bold;'>✓ すべてのマイグレーションが正常に完了しました。</p>";
    echo "<p><a href='/admin/index.php'>管理画面に戻る</a></p>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>エラーが発生しました</h3>";
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>マイグレーションを中止しました。</p>";
}
?>
