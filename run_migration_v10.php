<?php
/**
 * マイグレーションv10実行スクリプト
 * 個別支援計画書テーブルを作成
 * ブラウザから実行してください: http://kobetu.narze.xyz/run_migration_v10.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>\n";
echo "<html lang='ja'>\n";
echo "<head><meta charset='UTF-8'><title>マイグレーション実行</title></head>\n";
echo "<body style='font-family: monospace; padding: 20px;'>\n";
echo "<h1>マイグレーションv10 実行 - 個別支援計画書テーブル作成</h1>\n";

try {
    // database.phpを読み込み
    $dbPath = __DIR__ . '/config/database.php';
    echo "<p>database.phpのパス: " . htmlspecialchars($dbPath) . "</p>\n";

    if (!file_exists($dbPath)) {
        throw new Exception("database.phpが見つかりません: $dbPath");
    }

    require_once $dbPath;

    if (!function_exists('getDbConnection')) {
        throw new Exception("getDbConnection関数が見つかりません");
    }

    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>ステップ1: 個別支援計画書マスターテーブルの作成</h2>\n";

    // 個別支援計画書マスターテーブル
    $createPlanTable = "
    CREATE TABLE IF NOT EXISTS individual_support_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL COMMENT '対象生徒ID',
        student_name VARCHAR(100) NOT NULL COMMENT '生徒氏名',
        created_date DATE NOT NULL COMMENT '作成年月日',
        life_intention TEXT COMMENT '利用児及び家族の生活に対する意向',
        overall_policy TEXT COMMENT '総合的な支援の方針',
        long_term_goal_date DATE COMMENT '長期目標日',
        long_term_goal_text TEXT COMMENT '長期目標内容',
        short_term_goal_date DATE COMMENT '短期目標日',
        short_term_goal_text TEXT COMMENT '短期目標内容',
        manager_name VARCHAR(100) COMMENT '管理責任者氏名',
        consent_date DATE COMMENT '同意日',
        guardian_signature VARCHAR(100) COMMENT '保護者署名',
        created_by INT COMMENT '作成者（スタッフID）',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_student_id (student_id),
        INDEX idx_created_date (created_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='個別支援計画書マスター';
    ";

    $pdo->exec($createPlanTable);
    echo "<p style='color: green;'>✓ individual_support_plansテーブルを作成しました</p>\n";

    echo "<h2>ステップ2: 個別支援計画書明細テーブルの作成</h2>\n";

    // 個別支援計画書明細テーブル
    $createDetailTable = "
    CREATE TABLE IF NOT EXISTS individual_support_plan_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL COMMENT '計画書ID',
        row_order INT NOT NULL DEFAULT 0 COMMENT '行順序',
        category VARCHAR(50) COMMENT '項目（本人支援/家族支援/地域支援等）',
        sub_category VARCHAR(100) COMMENT 'サブカテゴリ（生活習慣、コミュニケーション等）',
        support_goal TEXT COMMENT '支援目標（具体的な到達目標）',
        support_content TEXT COMMENT '支援内容（内容・支援の提供上のポイント・5領域との関連性等）',
        achievement_date DATE COMMENT '達成時期',
        staff_organization TEXT COMMENT '担当者／提供機関',
        notes TEXT COMMENT '留意事項',
        priority INT COMMENT '優先順位',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_plan_id (plan_id),
        INDEX idx_row_order (row_order),
        FOREIGN KEY (plan_id) REFERENCES individual_support_plans(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='個別支援計画書明細';
    ";

    $pdo->exec($createDetailTable);
    echo "<p style='color: green;'>✓ individual_support_plan_detailsテーブルを作成しました</p>\n";

    echo "<h2>ステップ3: 完了後の構造確認</h2>\n";

    echo "<h3>individual_support_plans</h3>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM individual_support_plans");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}\n";
    }
    echo "</pre>\n";

    echo "<h3>individual_support_plan_details</h3>\n";
    echo "<pre>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM individual_support_plan_details");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $col) {
        echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Default']}\n";
    }
    echo "</pre>\n";

    echo "<h2 style='color: green;'>✓ マイグレーション完了</h2>\n";
    echo "<p>個別支援計画書のテーブルが作成されました。</p>\n";
    echo "<p><a href='staff/kobetsu_plan.php'>個別支援計画書ページ</a></p>\n";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>データベースエラーが発生しました</h2>\n";
    echo "<pre style='color: red; background: #fff0f0; padding: 10px;'>\n";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>\n";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>エラーが発生しました</h2>\n";
    echo "<pre style='color: red; background: #fff0f0; padding: 10px;'>\n";
    echo htmlspecialchars($e->getMessage());
    echo "\n\nStack trace:\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>\n";
}

echo "</body>\n";
echo "</html>\n";
