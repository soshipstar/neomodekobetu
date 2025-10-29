<?php
/**
 * 学年計算のテスト
 */

// 2010/12/29生まれの場合
$birthDate = '2010-12-29';
$birth = new DateTime($birthDate);

// 現在の年度を計算
$now = new DateTime();
$currentYear = (int)$now->format('Y');
$currentMonth = (int)$now->format('n');
$fiscalYear = ($currentMonth >= 4) ? $currentYear : $currentYear - 1;

// 誕生年度を計算
$birthYear = (int)$birth->format('Y');
$birthMonth = (int)$birth->format('n');
$birthDay = (int)$birth->format('j');

if ($birthMonth < 4 || ($birthMonth == 4 && $birthDay == 1)) {
    $birthFiscalYear = $birthYear - 1;
} else {
    $birthFiscalYear = $birthYear;
}

// 年度差を計算
$gradeYear = $fiscalYear - $birthFiscalYear;

echo "=== 2010年12月29日生まれの学年計算 ===\n";
echo "現在の日付: " . $now->format('Y年m月d日') . "\n";
echo "現在の年度: {$fiscalYear}年度\n";
echo "誕生年度: {$birthFiscalYear}年度\n";
echo "年度差: {$gradeYear}\n\n";

echo "=== 実際の学年履歴 ===\n";
echo "2017年4月: 小学1年生（小1）\n";
echo "2018年4月: 小学2年生（小2）\n";
echo "2019年4月: 小学3年生（小3）\n";
echo "2020年4月: 小学4年生（小4）\n";
echo "2021年4月: 小学5年生（小5）\n";
echo "2022年4月: 小学6年生（小6）\n";
echo "2023年4月: 中学1年生（中1）\n";
echo "2024年4月: 中学2年生（中2）\n";
echo "2025年4月: 中学3年生（中3） ← 現在\n\n";

echo "小学1年生になったのは2017年度\n";
echo "2017年度 - 誕生年度({$birthFiscalYear}) = " . (2017 - $birthFiscalYear) . "\n\n";

echo "=== 正しい判定基準 ===\n";
echo "年度差7: 小学1年生\n";
echo "年度差8: 小学2年生\n";
echo "年度差9: 小学3年生\n";
echo "年度差10: 小学4年生\n";
echo "年度差11: 小学5年生\n";
echo "年度差12: 小学6年生\n";
echo "年度差13: 中学1年生\n";
echo "年度差14: 中学2年生\n";
echo "年度差15: 中学3年生 ← 2010/12/29生まれ\n";
echo "年度差16: 高校1年生\n";
echo "年度差17: 高校2年生\n";
echo "年度差18: 高校3年生\n\n";

echo "=== 現在のコードの判定基準（間違い） ===\n";
echo "小学部: 年度差 6～11\n";
echo "中学部: 年度差 12～14\n";
echo "高等部: 年度差 15～17\n\n";

echo "=== 正しい判定基準 ===\n";
echo "小学部: 年度差 7～12\n";
echo "中学部: 年度差 13～15\n";
echo "高等部: 年度差 16～18\n";
