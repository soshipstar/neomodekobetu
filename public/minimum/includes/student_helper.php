<?php
/**
 * 生徒関連のヘルパー関数
 * ミニマム版
 */

/**
 * 生年月日から詳細な学年を計算
 *
 * @param string $birthDate 生年月日 (Y-m-d形式)
 * @param string $referenceDate 基準日 (デフォルトは今日)
 * @param int $gradeAdjustment 学年調整 (-2, -1, 0, +1, +2)
 * @return string 詳細な学年 (preschool, elementary_1～6, junior_high_1～3, high_school_1～3)
 */
function calculateGradeLevel($birthDate, $referenceDate = null, $gradeAdjustment = 0) {
    if (empty($birthDate)) {
        return 'elementary_1'; // デフォルト
    }

    $referenceDate = $referenceDate ?? date('Y-m-d');

    $birth = new DateTime($birthDate);
    $reference = new DateTime($referenceDate);

    // 現在の年度を計算（4月1日基準）
    $currentYear = (int)$reference->format('Y');
    $currentMonth = (int)$reference->format('n');
    $fiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

    // 誕生年度を計算（4月2日～翌年4月1日が同じ年度）
    $birthYear = (int)$birth->format('Y');
    $birthMonth = (int)$birth->format('n');
    $birthDay = (int)$birth->format('j');

    // 4月1日生まれは前年度扱い（早生まれの最後）
    if ($birthMonth < 4 || ($birthMonth == 4 && $birthDay == 1)) {
        $birthFiscalYear = $birthYear - 1;
    } else {
        $birthFiscalYear = $birthYear;
    }

    // その年度での学年を計算
    // 小学1年生は誕生年度から7年後（満6歳になる年度の翌年4月入学）
    $gradeYear = $fiscalYear - $birthFiscalYear;

    // 学年調整を適用
    $gradeYear += $gradeAdjustment;

    // 詳細な学年を返す
    if ($gradeYear < 7) {
        return 'preschool';
    } elseif ($gradeYear >= 7 && $gradeYear <= 12) {
        $grade = $gradeYear - 6;
        return 'elementary_' . $grade;
    } elseif ($gradeYear >= 13 && $gradeYear <= 15) {
        $grade = $gradeYear - 12;
        return 'junior_high_' . $grade;
    } elseif ($gradeYear >= 16 && $gradeYear <= 18) {
        $grade = $gradeYear - 15;
        return 'high_school_' . $grade;
    } else {
        return 'high_school_3';
    }
}

/**
 * 学年レベルのカテゴリを取得（検索用）
 */
function getGradeCategory($gradeLevel) {
    if ($gradeLevel === 'preschool') {
        return 'preschool';
    } elseif (strpos($gradeLevel, 'elementary') === 0) {
        return 'elementary';
    } elseif (strpos($gradeLevel, 'junior_high') === 0) {
        return 'junior_high';
    } elseif (strpos($gradeLevel, 'high_school') === 0) {
        return 'high_school';
    }
    return 'elementary';
}

/**
 * 学年レベルの日本語表記を取得
 */
function getGradeLevelLabel($gradeLevel) {
    $labels = [
        'preschool' => '未就学児',
        'elementary_1' => '小学1年生',
        'elementary_2' => '小学2年生',
        'elementary_3' => '小学3年生',
        'elementary_4' => '小学4年生',
        'elementary_5' => '小学5年生',
        'elementary_6' => '小学6年生',
        'junior_high_1' => '中学1年生',
        'junior_high_2' => '中学2年生',
        'junior_high_3' => '中学3年生',
        'high_school_1' => '高校1年生',
        'high_school_2' => '高校2年生',
        'high_school_3' => '高校3年生',
        'elementary' => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生'
    ];
    return $labels[$gradeLevel] ?? '不明';
}

/**
 * 生年月日から年齢を計算
 */
function calculateAge($birthDate, $referenceDate = null) {
    if (empty($birthDate)) {
        return 0;
    }
    $referenceDate = $referenceDate ?? date('Y-m-d');
    $birth = new DateTime($birthDate);
    $reference = new DateTime($referenceDate);
    return $birth->diff($reference)->y;
}

/**
 * 教室の対象学年設定を取得
 */
function getClassroomTargetGrades($pdo, $classroomId) {
    if (!$classroomId) {
        return ['preschool', 'elementary', 'junior_high', 'high_school'];
    }
    $stmt = $pdo->prepare("SELECT target_grades FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $result = $stmt->fetch();
    if ($result && !empty($result['target_grades'])) {
        return explode(',', $result['target_grades']);
    }
    return ['preschool', 'elementary', 'junior_high', 'high_school'];
}

/**
 * 学年がターゲット学年に含まれるかチェック
 */
function isGradeInTarget($gradeLevel, $targetGrades) {
    $category = getGradeCategory($gradeLevel);
    return in_array($category, $targetGrades);
}
