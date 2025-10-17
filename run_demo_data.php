<?php
/**
 * デモデータ作成スクリプト実行用
 * 山田太郎のデモデータを作成します
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "===========================================\n";
echo "山田太郎のデモデータ作成を開始します\n";
echo "===========================================\n\n";

try {
    // SQLファイルを読み込む
    $sqlFile = __DIR__ . '/create_demo_data.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("SQLファイルが見つかりません: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // セミコロンで分割して実行
    $statements = explode(';', $sql);

    $successCount = 0;
    $errorCount = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // 空の文または コメントのみの行はスキップ
        if (empty($statement) || preg_match('/^--/', $statement)) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // SET文やSELECT文のエラーは無視
            if (stripos($statement, 'SET @') === 0 || stripos($statement, 'SELECT') === 0) {
                continue;
            }
            echo "警告: " . $e->getMessage() . "\n";
            echo "SQL: " . substr($statement, 0, 100) . "...\n\n";
            $errorCount++;
        }
    }

    echo "\n===========================================\n";
    echo "デモデータ作成完了\n";
    echo "===========================================\n";
    echo "成功: {$successCount}件\n";
    echo "エラー/スキップ: {$errorCount}件\n\n";

    // 作成されたデータを確認
    $stmt = $pdo->query("SELECT * FROM students WHERE student_name = '山田 太郎'");
    $student = $stmt->fetch();

    if ($student) {
        echo "✓ 生徒データ作成: 山田 太郎（ID: {$student['id']}）\n";

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM daily_records
            WHERE record_date BETWEEN '2025-02-01' AND '2025-10-01'
        ");
        $stmt->execute();
        $dailyCount = $stmt->fetch()['count'];
        echo "✓ 活動記録: {$dailyCount}件\n";

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM student_records
            WHERE student_id = ?
        ");
        $stmt->execute([$student['id']]);
        $studentRecordCount = $stmt->fetch()['count'];
        echo "✓ 個別記録: {$studentRecordCount}件\n";

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM integrated_notes
            WHERE student_id = ? AND is_sent = 1
        ");
        $stmt->execute([$student['id']]);
        $integratedCount = $stmt->fetch()['count'];
        echo "✓ 送信済み連絡帳: {$integratedCount}件\n";
    } else {
        echo "✗ 生徒データが見つかりません\n";
    }

    echo "\n保護者ログイン情報:\n";
    echo "ユーザー名: yamada_parent\n";
    echo "パスワード: （初期設定が必要です）\n";

} catch (Exception $e) {
    echo "\nエラー: " . $e->getMessage() . "\n";
    exit(1);
}
