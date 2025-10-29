<?php
/**
 * 生徒の学年計算をデバッグするスクリプト
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/student_helper.php';

$pdo = getDbConnection();

// 諏訪芳穏のデータを取得
echo "=== 諏訪芳穏 ===\n";
$stmt = $pdo->prepare("SELECT id, student_name, birth_date, grade_level, grade_adjustment FROM students WHERE student_name LIKE ?");
$stmt->execute(['%諏訪%']);
$student1 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student1) {
    echo "ID: " . $student1['id'] . "\n";
    echo "生徒名: " . $student1['student_name'] . "\n";
    echo "生年月日: " . $student1['birth_date'] . "\n";
    echo "grade_level (DB): " . $student1['grade_level'] . "\n";
    echo "grade_adjustment (DB): " . ($student1['grade_adjustment'] ?? '0') . "\n";

    // 学年を再計算
    $calculatedGrade = calculateGradeLevel($student1['birth_date'], null, $student1['grade_adjustment'] ?? 0);
    echo "計算された学年: " . $calculatedGrade . "\n";

    // 現在の年度での学年を詳細に計算
    $birth = new DateTime($student1['birth_date']);
    $now = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth = (int)$now->format('n');
    $fiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

    $birthYear = (int)$birth->format('Y');
    $birthMonth = (int)$birth->format('n');
    $birthDay = (int)$birth->format('j');

    if ($birthMonth < 4 || ($birthMonth == 4 && $birthDay == 1)) {
        $birthFiscalYear = $birthYear - 1;
    } else {
        $birthFiscalYear = $birthYear;
    }

    $gradeYear = $fiscalYear - $birthFiscalYear;
    $adjustedGradeYear = $gradeYear + ($student1['grade_adjustment'] ?? 0);

    echo "現在の年度: " . $fiscalYear . "\n";
    echo "誕生年度: " . $birthFiscalYear . "\n";
    echo "学年年数: " . $gradeYear . "\n";
    echo "調整後学年年数: " . $adjustedGradeYear . "\n";
} else {
    echo "諏訪芳穏が見つかりません\n";
}

echo "\n";

// ルパート祥のデータを取得
echo "=== ルパート祥 ===\n";
$stmt = $pdo->prepare("SELECT id, student_name, birth_date, grade_level, grade_adjustment FROM students WHERE student_name LIKE ?");
$stmt->execute(['%ルパート%']);
$student2 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student2) {
    echo "ID: " . $student2['id'] . "\n";
    echo "生徒名: " . $student2['student_name'] . "\n";
    echo "生年月日: " . $student2['birth_date'] . "\n";
    echo "grade_level (DB): " . $student2['grade_level'] . "\n";
    echo "grade_adjustment (DB): " . ($student2['grade_adjustment'] ?? '0') . "\n";

    // 学年を再計算
    $calculatedGrade = calculateGradeLevel($student2['birth_date'], null, $student2['grade_adjustment'] ?? 0);
    echo "計算された学年: " . $calculatedGrade . "\n";

    // 現在の年度での学年を詳細に計算
    $birth = new DateTime($student2['birth_date']);
    $now = new DateTime();
    $currentYear = (int)$now->format('Y');
    $currentMonth = (int)$now->format('n');
    $fiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

    $birthYear = (int)$birth->format('Y');
    $birthMonth = (int)$birth->format('n');
    $birthDay = (int)$birth->format('j');

    if ($birthMonth < 4 || ($birthMonth == 4 && $birthDay == 1)) {
        $birthFiscalYear = $birthYear - 1;
    } else {
        $birthFiscalYear = $birthYear;
    }

    $gradeYear = $fiscalYear - $birthFiscalYear;
    $adjustedGradeYear = $gradeYear + ($student2['grade_adjustment'] ?? 0);

    echo "現在の年度: " . $fiscalYear . "\n";
    echo "誕生年度: " . $birthFiscalYear . "\n";
    echo "学年年数: " . $gradeYear . "\n";
    echo "調整後学年年数: " . $adjustedGradeYear . "\n";
} else {
    echo "ルパート祥が見つかりません\n";
}
