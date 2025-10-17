<?php
/**
 * 生徒関連のヘルパー関数
 */

/**
 * 生年月日から学年レベルを計算
 *
 * @param string $birthDate 生年月日 (Y-m-d形式)
 * @param string $referenceDate 基準日 (デフォルトは今日)
 * @return string 学年レベル (elementary, junior_high, high_school)
 */
function calculateGradeLevel($birthDate, $referenceDate = null) {
    if (empty($birthDate)) {
        return 'elementary'; // デフォルト
    }

    $referenceDate = $referenceDate ?? date('Y-m-d');

    $birth = new DateTime($birthDate);
    $reference = new DateTime($referenceDate);

    // 年齢を計算
    $age = $birth->diff($reference)->y;

    // 学年を計算（4月1日基準）
    $birthMonth = (int)$birth->format('n');
    $birthDay = (int)$birth->format('j');
    $referenceMonth = (int)$reference->format('n');

    // 4月1日以前生まれは早生まれとして扱う
    if ($birthMonth >= 4) {
        // 4月以降生まれ
        $schoolAge = $age;
    } else {
        // 1-3月生まれ（早生まれ）
        if ($referenceMonth >= 4) {
            $schoolAge = $age;
        } else {
            $schoolAge = $age - 1;
        }
    }

    // 学年判定
    // 小学部: 6-12歳
    // 中学部: 12-15歳
    // 高等部: 15-18歳

    if ($schoolAge >= 6 && $schoolAge < 12) {
        return 'elementary';
    } elseif ($schoolAge >= 12 && $schoolAge < 15) {
        return 'junior_high';
    } elseif ($schoolAge >= 15 && $schoolAge <= 18) {
        return 'high_school';
    } else {
        // 年齢範囲外の場合は年齢で判断
        if ($age < 12) {
            return 'elementary';
        } elseif ($age < 15) {
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
