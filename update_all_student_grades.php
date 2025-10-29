<?php
/**
 * 全生徒の grade_level を再計算して更新するスクリプト
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/student_helper.php';

$pdo = getDbConnection();

// 全生徒を取得
$stmt = $pdo->query("SELECT id, student_name, birth_date, grade_adjustment FROM students");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$errors = 0;

foreach ($students as $student) {
    if (empty($student['birth_date'])) {
        echo "⚠ ID {$student['id']}: {$student['student_name']} - 生年月日が未設定\n";
        continue;
    }

    // 学年を再計算
    $gradeAdjustment = $student['grade_adjustment'] ?? 0;
    $newGradeLevel = calculateGradeLevel($student['birth_date'], null, $gradeAdjustment);

    // 更新
    try {
        $updateStmt = $pdo->prepare("UPDATE students SET grade_level = ? WHERE id = ?");
        $updateStmt->execute([$newGradeLevel, $student['id']]);

        echo "✓ ID {$student['id']}: {$student['student_name']} - {$newGradeLevel} (調整: {$gradeAdjustment})\n";
        $updated++;
    } catch (Exception $e) {
        echo "✗ ID {$student['id']}: {$student['student_name']} - エラー: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n";
echo "=== 完了 ===\n";
echo "更新: {$updated}件\n";
echo "エラー: {$errors}件\n";
