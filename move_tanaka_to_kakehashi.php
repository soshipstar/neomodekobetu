<?php
/**
 * 田中ボウリン健太郎をかけはし教室に移動するスクリプト
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "=== 田中ボウリン健太郎をかけはし教室に移動 ===\n\n";

    // 1. かけはし教室のIDを確認
    echo "1. かけはし教室の情報を確認...\n";
    $stmt = $pdo->query("
        SELECT id, classroom_name
        FROM classrooms
        WHERE classroom_name LIKE '%かけはし%' OR classroom_name LIKE '%カケハシ%'
    ");
    $kakehashi = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$kakehashi) {
        echo "エラー: かけはし教室が見つかりません。\n";
        echo "\n利用可能な教室一覧:\n";
        $stmt = $pdo->query("SELECT id, classroom_name FROM classrooms");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID={$row['id']}: {$row['classroom_name']}\n";
        }
        exit(1);
    }

    $kakehashiId = $kakehashi['id'];
    echo "  かけはし教室が見つかりました: ID={$kakehashiId}, 名前={$kakehashi['classroom_name']}\n\n";

    // 2. 田中ボウリン健太郎を検索
    echo "2. 田中ボウリン健太郎を検索...\n";
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            s.classroom_id,
            c.classroom_name as current_classroom,
            u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        WHERE s.student_name LIKE '%田中%'
          AND s.student_name LIKE '%ボウリン%'
          AND s.student_name LIKE '%健太郎%'
    ");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "エラー: 田中ボウリン健太郎が見つかりません。\n";
        echo "\n類似する生徒:\n";
        $stmt = $pdo->query("
            SELECT id, student_name FROM students
            WHERE student_name LIKE '%田中%' OR student_name LIKE '%ボウリン%'
            ORDER BY student_name
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID={$row['id']}: {$row['student_name']}\n";
        }
        exit(1);
    }

    $studentId = $student['id'];
    echo "  生徒が見つかりました:\n";
    echo "    ID: {$studentId}\n";
    echo "    名前: {$student['student_name']}\n";
    echo "    現在の教室: " . ($student['current_classroom'] ?? '未設定') . " (ID={$student['classroom_id']})\n";
    echo "    保護者: " . ($student['guardian_name'] ?? '未設定') . "\n\n";

    // 3. 既にかけはし教室の場合はスキップ
    if ($student['classroom_id'] == $kakehashiId) {
        echo "  この生徒は既にかけはし教室に所属しています。処理を終了します。\n";
        exit(0);
    }

    // 4. 教室を変更
    echo "3. 教室を変更中...\n";
    echo "  {$student['current_classroom']} → {$kakehashi['classroom_name']}\n";

    $stmt = $pdo->prepare("
        UPDATE students
        SET classroom_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$kakehashiId, $studentId]);
    echo "  更新完了\n\n";

    // 5. 更新後の確認
    echo "4. 更新後の確認...\n";
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_name,
            s.classroom_id,
            c.classroom_name,
            u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        LEFT JOIN classrooms c ON s.classroom_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "  生徒情報:\n";
    echo "    ID: {$updated['id']}\n";
    echo "    名前: {$updated['student_name']}\n";
    echo "    教室: {$updated['classroom_name']} (ID={$updated['classroom_id']})\n";
    echo "    保護者: " . ($updated['guardian_name'] ?? '未設定') . "\n";

    echo "\n処理が正常に完了しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
