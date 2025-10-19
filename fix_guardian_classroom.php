<?php
/**
 * 既存保護者アカウントに教室IDを設定
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<!DOCTYPE html>";
echo "<html lang='ja'><head><meta charset='UTF-8'><title>保護者教室ID設定</title></head><body>";
echo "<h1>保護者アカウントの教室ID設定</h1>";
echo "<pre>";

try {
    // classroom_idがNULLの保護者を取得
    $stmt = $pdo->query("
        SELECT id, username, full_name, classroom_id
        FROM users
        WHERE user_type = 'guardian' AND classroom_id IS NULL
    ");
    $guardiansWithoutClassroom = $stmt->fetchAll();

    if (empty($guardiansWithoutClassroom)) {
        echo "✓ すべての保護者に教室IDが設定されています\n\n";
    } else {
        echo "=== 教室IDが未設定の保護者 ===\n";
        echo "件数: " . count($guardiansWithoutClassroom) . "件\n\n";

        foreach ($guardiansWithoutClassroom as $guardian) {
            echo "ID: {$guardian['id']}, ユーザー名: {$guardian['username']}, 氏名: {$guardian['full_name']}\n";
        }

        echo "\n教室ID=1（メイン教室）を設定します...\n";

        // classroom_id = 1 を設定
        $stmt = $pdo->prepare("
            UPDATE users
            SET classroom_id = 1
            WHERE user_type = 'guardian' AND classroom_id IS NULL
        ");
        $stmt->execute();

        $affectedRows = $stmt->rowCount();
        echo "✓ {$affectedRows}件の保護者アカウントに教室ID=1を設定しました\n\n";
    }

    // 結果を表示
    echo "=== 更新後の保護者一覧 ===\n";
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.classroom_id,
            c.classroom_name
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE u.user_type = 'guardian'
        ORDER BY u.id
        LIMIT 10
    ");
    $guardians = $stmt->fetchAll();

    echo sprintf("%-5s | %-15s | %-20s | %-10s | %-20s\n", "ID", "ユーザー名", "氏名", "教室ID", "教室名");
    echo str_repeat("-", 80) . "\n";

    foreach ($guardians as $guardian) {
        echo sprintf(
            "%-5s | %-15s | %-20s | %-10s | %-20s\n",
            $guardian['id'],
            $guardian['username'],
            $guardian['full_name'],
            $guardian['classroom_id'] ?? 'NULL',
            $guardian['classroom_name'] ?? '-'
        );
    }

    echo "\n";
    echo "=== 完了 ===\n";
    echo "保護者ページで教室情報が表示されるようになりました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<p><a href='guardian/index.php'>保護者ページを確認</a> | <a href='debug_guardian_classroom.php'>デバッグ情報を見る</a></p>";
echo "</body></html>";
