<?php
/**
 * 利用者一括登録用CSVテンプレートダウンロード
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

// ファイル名（ASCII文字のみ使用）
$filename = 'bulk_register_template_' . date('Ymd') . '.csv';
$filenameUtf8 = '利用者一括登録テンプレート_' . date('Ymd') . '.csv';

// ヘッダー設定
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filenameUtf8));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// BOM付きUTF-8で出力（Excel対応）
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// ヘッダー行
$headers = [
    '保護者氏名',
    '生徒氏名',
    '生年月日',
    '保護者メールアドレス',
    '支援開始日',
    '学年調整',
    '通所曜日（月）',
    '通所曜日（火）',
    '通所曜日（水）',
    '通所曜日（木）',
    '通所曜日（金）',
    '通所曜日（土）'
];
fputcsv($output, $headers);

// サンプルデータ（3行：支援開始日あり/なし/通所曜日なしの例）
$sampleData = [
    [
        '山田花子',
        '山田太郎',
        '2015-04-01',
        'yamada@example.com',
        date('Y-m-d'),  // 支援開始日あり
        '0',
        '1',
        '0',
        '1',
        '0',
        '1',
        '0'
    ],
    [
        '山田花子',
        '山田次郎',
        '2018-06-15',
        '',
        '',  // 支援開始日なし（後で設定可能）
        '0',
        '1',
        '0',
        '1',
        '0',
        '0',
        '0'
    ],
    [
        '鈴木一郎',
        '鈴木健太',
        '2016-08-20',
        'suzuki@example.com',
        '',  // 支援開始日なし
        '0',
        '',  // 通所曜日も空欄可能
        '',
        '',
        '',
        '',
        ''
    ]
];

foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
