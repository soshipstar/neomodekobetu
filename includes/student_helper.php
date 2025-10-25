<?php
/**
 * 生徒関連のヘルパー関数
 */

/**
 * 生年月日から学年レベルを計算
 *
 * @param string $birthDate 生年月日 (Y-m-d形式)
 * @param string $referenceDate 基準日 (デフォルトは今日)
 * @param int $gradeAdjustment 学年調整 (-2, -1, 0, +1, +2)
 * @return string 学年レベル (elementary, junior_high, high_school)
 */
function calculateGradeLevel($birthDate, $referenceDate = null, $gradeAdjustment = 0) {
    if (empty($birthDate)) {
        return 'elementary'; // デフォルト
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
    // 小学1年生は満6歳になる年度（誕生年度 + 6）
    $gradeYear = $fiscalYear - $birthFiscalYear;

    // 学年調整を適用
    $gradeYear += $gradeAdjustment;

    // 学年判定
    // 小学部: 小1～小6（学年差6～11）
    // 中学部: 中1～中3（学年差12～14）
    // 高等部: 高1～高3（学年差15～17）

    if ($gradeYear >= 6 && $gradeYear <= 11) {
        return 'elementary';
    } elseif ($gradeYear >= 12 && $gradeYear <= 14) {
        return 'junior_high';
    } elseif ($gradeYear >= 15 && $gradeYear <= 17) {
        return 'high_school';
    } else {
        // 年齢範囲外の場合は年齢で判断
        if ($gradeYear < 12) {
            return 'elementary';
        } elseif ($gradeYear < 15) {
            return 'junior_high';
        } else {
            return 'high_school';
        }
    }
}

/**
 * 学年レベルの日本語表記を取得
 *
 * @param string $gradeLevel
 * @return string
 */
function getGradeLevelLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
    ];

    return $labels[$gradeLevel] ?? '不明';
}

/**
 * 生年月日から年齢を計算
 *
 * @param string $birthDate 生年月日 (Y-m-d形式)
 * @param string $referenceDate 基準日 (デフォルトは今日)
 * @return int 年齢
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
