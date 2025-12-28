<?php
/**
 * 利用者一括登録 - AI解析処理
 * 任意のCSVファイルをAIで解析し、必要な情報を抽出する
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/openai_helper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bulk_register.php');
    exit;
}

requireCsrfToken();

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

/**
 * 文字コードを検出してUTF-8に変換
 */
function convertToUtf8ForAi($content) {
    // BOMを除去
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    // 文字コードを検出
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ASCII'], true);

    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    return $content;
}

// ファイルアップロードチェック
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['bulk_register_error'] = 'ファイルのアップロードに失敗しました。';
    header('Location: bulk_register.php');
    exit;
}

$file = $_FILES['csv_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($ext !== 'csv') {
    $_SESSION['bulk_register_error'] = 'CSVファイルのみアップロード可能です。';
    header('Location: bulk_register.php');
    exit;
}

// CSVファイルを読み込み
$csvContent = file_get_contents($file['tmp_name']);
if ($csvContent === false) {
    $_SESSION['bulk_register_error'] = 'ファイルの読み込みに失敗しました。';
    header('Location: bulk_register.php');
    exit;
}

// 文字コード変換
$csvContent = convertToUtf8ForAi($csvContent);

// CSVが大きすぎる場合は制限（APIのトークン制限対策）
$lines = explode("\n", $csvContent);
$maxLines = 100; // 最大100行
if (count($lines) > $maxLines + 1) { // ヘッダー+データ
    $_SESSION['bulk_register_error'] = "CSVの行数が多すぎます。{$maxLines}行以下にしてください。";
    header('Location: bulk_register.php');
    exit;
}

// AIに送信するプロンプトを作成
$prompt = <<<PROMPT
以下のCSVデータから、保護者と生徒の情報を抽出してJSON形式で返してください。

【抽出する情報】
- 保護者氏名（必須）
- 生徒氏名（必須）
- 生年月日（必須、YYYY-MM-DD形式に変換）
- 保護者メールアドレス（あれば）
- 支援開始日（あれば、YYYY-MM-DD形式に変換）
- 通所曜日（あれば、月火水木金土のうち該当するもの）

【重要なルール】
1. 同じ保護者に複数の子供がいる場合は、保護者氏名を同じにしてください
2. 生年月日は必ずYYYY-MM-DD形式に変換してください（例：2015年4月1日 → 2015-04-01）
3. 列名が日本語でも英語でも、内容から適切に判断してください
4. 保護者と生徒の関係が明確でない場合は、同じ行のデータを1組として扱ってください

【出力形式】
以下のJSON形式で出力してください。説明文は不要です。JSONのみを返してください。
```json
{
  "guardians": [
    {
      "name": "保護者氏名",
      "email": "メールアドレス（あれば）"
    }
  ],
  "students": [
    {
      "guardian_name": "保護者氏名",
      "name": "生徒氏名",
      "birth_date": "YYYY-MM-DD",
      "support_start_date": "YYYY-MM-DD（あれば）",
      "scheduled_days": ["月", "水", "金"]
    }
  ]
}
```

【CSVデータ】
{$csvContent}
PROMPT;

try {
    // OpenAI APIを呼び出し
    $response = callOpenAI($prompt, 'gpt-4o', 4000);

    // JSONを抽出（マークダウンのコードブロックを除去）
    $response = trim($response);
    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $response = $matches[1];
    } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $response = $matches[1];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('AIの応答をパースできませんでした: ' . json_last_error_msg());
    }

    if (empty($data['guardians']) || empty($data['students'])) {
        throw new Exception('AIが保護者・生徒情報を抽出できませんでした。CSVの形式を確認してください。');
    }

    // 保護者データを整形
    $guardians = [];
    $guardianMap = [];
    $gIndex = 1;

    foreach ($data['guardians'] as $g) {
        $name = trim($g['name'] ?? '');
        if (empty($name)) continue;

        if (!isset($guardianMap[$name])) {
            $id = 'G' . $gIndex;
            $guardianMap[$name] = $id;
            $guardians[$id] = [
                'id' => $id,
                'name' => $name,
                'email' => trim($g['email'] ?? ''),
                'students' => []
            ];
            $gIndex++;
        }
    }

    // 生徒データを整形
    $students = [];
    foreach ($data['students'] as $idx => $s) {
        $guardianName = trim($s['guardian_name'] ?? '');
        $studentName = trim($s['name'] ?? '');
        $birthDate = trim($s['birth_date'] ?? '');

        if (empty($guardianName) || empty($studentName)) continue;

        // 保護者がまだ登録されていなければ追加
        if (!isset($guardianMap[$guardianName])) {
            $id = 'G' . $gIndex;
            $guardianMap[$guardianName] = $id;
            $guardians[$id] = [
                'id' => $id,
                'name' => $guardianName,
                'email' => '',
                'students' => []
            ];
            $gIndex++;
        }

        $guardianId = $guardianMap[$guardianName];

        // 通所曜日を変換
        $scheduledDays = $s['scheduled_days'] ?? [];
        $scheduledMon = in_array('月', $scheduledDays) ? 1 : 0;
        $scheduledTue = in_array('火', $scheduledDays) ? 1 : 0;
        $scheduledWed = in_array('水', $scheduledDays) ? 1 : 0;
        $scheduledThu = in_array('木', $scheduledDays) ? 1 : 0;
        $scheduledFri = in_array('金', $scheduledDays) ? 1 : 0;
        $scheduledSat = in_array('土', $scheduledDays) ? 1 : 0;

        $students[] = [
            'index' => $idx,
            'guardian_id' => $guardianId,
            'guardian_name' => $guardianName,
            'name' => $studentName,
            'birth_date' => $birthDate,
            'support_start_date' => trim($s['support_start_date'] ?? ''),
            'grade_adjustment' => 0,
            'grade_level' => 'elementary',
            'scheduled_monday' => $scheduledMon,
            'scheduled_tuesday' => $scheduledTue,
            'scheduled_wednesday' => $scheduledWed,
            'scheduled_thursday' => $scheduledThu,
            'scheduled_friday' => $scheduledFri,
            'scheduled_saturday' => $scheduledSat,
            'line_number' => $idx + 2
        ];

        // 保護者の生徒リストに追加
        $guardians[$guardianId]['students'][] = $studentName;
    }

    if (empty($guardians) || empty($students)) {
        throw new Exception('有効な保護者・生徒情報が見つかりませんでした。');
    }

    // セッションに保存
    $_SESSION['bulk_register_data'] = [
        'guardians' => $guardians,
        'students' => $students,
        'errors' => [],
        'warnings' => ['AIが自動解析しました。内容を確認・修正してください。'],
        'ai_parsed' => true
    ];

    // 一時CSVファイルを保存（確認画面からのキャンセル時用）
    $tmpPath = sys_get_temp_dir() . '/bulk_register_' . session_id() . '_' . time() . '.csv';
    file_put_contents($tmpPath, $csvContent);
    $_SESSION['bulk_register_csv'] = $tmpPath;

    // 確認ページへリダイレクト
    header('Location: bulk_register_ai_confirm.php');
    exit;

} catch (Exception $e) {
    error_log("AI parse error: " . $e->getMessage());
    $_SESSION['bulk_register_error'] = 'AI解析エラー: ' . $e->getMessage();
    header('Location: bulk_register.php');
    exit;
}
