<?php
/**
 * 既存生徒の学年を生年月日から自動更新するスクリプト
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/student_helper.php';

$pdo = getDbConnection();

try {
    // 生年月日が設定されている全生徒を取得
    $stmt = $pdo->query("
        SELECT id, student_name, birth_date, grade_level
        FROM students
        WHERE birth_date IS NOT NULL
    ");
    $students = $stmt->fetchAll();

    $updatedCount = 0;
    $skippedCount = 0;

    echo "生徒の学年を更新中...\n\n";

    foreach ($students as $student) {
        // 生年月日から学年を計算
        $calculatedGrade = calculateGradeLevel($student['birth_date']);

        // 現在の学年と異なる場合のみ更新
        if ($calculatedGrade !== $student['grade_level']) {
            $stmt = $pdo->prepare("
                UPDATE students
                SET grade_level = ?
                WHERE id = ?
            ");
            $stmt->execute([$calculatedGrade, $student['id']]);

            echo "✓ {$student['student_name']} (ID: {$student['id']}): {$student['grade_level']} → {$calculatedGrade}\n";
            $updatedCount++;
        } else {
            echo "- {$student['student_name']} (ID: {$student['id']}): {$student['grade_level']} (変更なし)\n";
            $skippedCount++;
        }
    }

    echo "\n完了！\n";
    echo "更新: {$updatedCount}件\n";
    echo "スキップ: {$skippedCount}件\n";

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
