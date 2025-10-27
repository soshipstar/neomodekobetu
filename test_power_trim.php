<?php
// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PowerTrim テスト</h1>";

// text_utils.phpを読み込み
try {
    require_once __DIR__ . '/includes/text_utils.php';
    echo "<p style='color: green;'>✓ text_utils.php の読み込み成功</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ text_utils.php の読み込み失敗: " . $e->getMessage() . "</p>";
    exit;
}

// powerTrim関数が存在するか確認
if (function_exists('powerTrim')) {
    echo "<p style='color: green;'>✓ powerTrim関数が定義されています</p>";
} else {
    echo "<p style='color: red;'>✗ powerTrim関数が見つかりません</p>";
    exit;
}

// テスト
$testCases = [
    '通常のテキスト',
    '  前にスペース',
    '後ろにスペース  ',
    '  前後にスペース  ',
    '　全角スペース',
    "\t\tタブ文字",
    "\n\n改行文字",
    "　\t  混在  \n\t　",
];

echo "<h2>テスト結果</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>元の文字列</th><th>長さ</th><th>結果</th><th>長さ</th></tr>";

foreach ($testCases as $test) {
    $result = powerTrim($test);
    echo "<tr>";
    echo "<td>[" . htmlspecialchars($test) . "]</td>";
    echo "<td>" . strlen($test) . "</td>";
    echo "<td>[" . htmlspecialchars($result) . "]</td>";
    echo "<td>" . strlen($result) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='cleanup_all_spaces.php'>cleanup_all_spaces.php にアクセス</a></p>";
