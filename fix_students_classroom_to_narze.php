<?php
/**
 * classroom_idがNULLの生徒をnarZE教室に変更するスクリプト
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    echo "=== classroom_idがNULLの生徒をnarZE教室に変更 ===\n\n";

    // 1. narZE教室のIDを確認
    echo "1. narZE教室の情報を確認...\n";
    $stmt = $pdo->query("
        SELECT id, classroom_name
        FROM classrooms
        WHERE classroom_name LIKE '%narZE%' OR classroom_name LIKE '%ナルゼ%'
    ");
    $narze = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$narze) {
        echo "エラー: narZE教室が見つかりません。\n";
        echo "\n利用可能な教室一覧:\n";
        $stmt = $pdo->query("SELECT id, classroom_name FROM classrooms");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  ID={$row['id']}: {$row['classroom_name']}\n";
        }
        exit(1);
    }

    $narzeId = $narze['id'];
    echo "  narZE教室が見つかりました: ID={$narzeId}, 名前={$narze['classroom_name']}\n\n";

    // 2. classroom_idがNULLの生徒を確認
    echo "2. classroom_idがNULLの生徒を確認...\n";
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.student_name,
            s.classroom_id,
            u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.classroom_id IS NULL
    ");
    $nullStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($nullStudents)) {
        echo "  classroom_idがNULLの生徒はいません。処理を終了します。\n";
        exit(0);
    }

    echo "  対象生徒数: " . count($nullStudents) . "名\n";
    foreach ($nullStudents as $student) {
        echo "    - ID={$student['id']}: {$student['student_name']} (保護者: " . ($student['guardian_name'] ?? '未設定') . ")\n";
    }
    echo "\n";

    // 3. 更新を実行
    echo "3. 生徒のclassroom_idを更新中...\n";
    $stmt = $pdo->prepare("
        UPDATE students
        SET classroom_id = ?
        WHERE classroom_id IS NULL
    ");
    $stmt->execute([$narzeId]);
    $updatedCount = $stmt->rowCount();
    echo "  更新完了: {$updatedCount}名の生徒をnarZE教室に変更しました。\n\n";

    // 4. 更新後の確認
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
        WHERE s.classroom_id = ?
        ORDER BY s.id
    ");
    $stmt->execute([$narzeId]);
    $narzeStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  narZE教室の生徒数: " . count($narzeStudents) . "名\n";
    foreach ($narzeStudents as $student) {
        echo "    - ID={$student['id']}: {$student['student_name']} (教室: {$student['classroom_name']}, 保護者: " . ($student['guardian_name'] ?? '未設定') . ")\n";
    }

    echo "\n処理が正常に完了しました。\n";

} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
