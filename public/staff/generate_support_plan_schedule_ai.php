<?php
/**
 * AI による活動内容生成 API（スケジュールベース）
 * 活動スケジュールと時間配分から詳細な活動内容を生成
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/openai_helper.php';

header('Content-Type: application/json; charset=utf-8');

// スタッフまたは管理者のみアクセス可能
if (!isLoggedIn() || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POSTリクエストが必要です']);
    exit;
}

// 入力データを取得
$activityName = trim($_POST['activity_name'] ?? '');
$activityPurpose = trim($_POST['activity_purpose'] ?? '');
$totalDuration = intval($_POST['total_duration'] ?? 180);
$scheduleJson = $_POST['schedule'] ?? '[]';
$targetGrade = trim($_POST['target_grade'] ?? '');

if (empty($activityName)) {
    echo json_encode(['success' => false, 'error' => '活動名を入力してください']);
    exit;
}

// スケジュールをパース
$schedule = json_decode($scheduleJson, true);
if (!is_array($schedule) || empty($schedule)) {
    echo json_encode(['success' => false, 'error' => '活動スケジュールを設定してください']);
    exit;
}

// 対象年齢層の変換
$targetGradeLabels = [
    'preschool' => '小学生未満',
    'elementary' => '小学生',
    'junior_high' => '中学生',
    'high_school' => '高校生'
];

$targetGradeText = '';
if (!empty($targetGrade)) {
    $grades = explode(',', $targetGrade);
    $labels = array_map(function($g) use ($targetGradeLabels) {
        return $targetGradeLabels[$g] ?? $g;
    }, $grades);
    $targetGradeText = implode('、', $labels);
}

// スケジュールを文字列化
$scheduleText = "";
$order = 1;
foreach ($schedule as $item) {
    $type = $item['type'] === 'routine' ? '毎日の支援' : '主活動';
    $scheduleText .= "{$order}. {$item['name']}（{$type}）- {$item['duration']}分\n";
    if (!empty($item['content'])) {
        $scheduleText .= "   内容: {$item['content']}\n";
    }
    $order++;
}

try {
    // AIに送るプロンプトを作成
    $prompt = <<<PROMPT
あなたは児童発達支援・放課後等デイサービスの経験豊富な支援員です。
以下の活動について、スケジュールと時間配分に基づいた詳細な活動内容を生成してください。

【活動名】
{$activityName}

【活動の目的】
{$activityPurpose}

【総活動時間】
{$totalDuration}分

【対象年齢層】
{$targetGradeText}

【活動スケジュール】
{$scheduleText}

以下の形式でJSON形式で出力してください。JSONのみを出力し、他の説明は不要です。

{
    "activity_content": "活動の内容（詳細な活動の流れと準備物）",
    "other_notes": "活動時の配慮事項"
}

【活動内容（activity_content）の作成ガイドライン】
1. スケジュールの順番と時間配分を厳守してください
2. タイムスケジュールの一覧表は不要です。活動の流れから始めてください
3. 以下の構成で記載してください：

■ 詳細な活動の流れ

【活動1: ○○】（○○分）
・導入：子どもたちへの声かけ、準備
・展開：具体的な活動内容
・スタッフの役割と配置

【活動2: ○○】（○○分）
...

■ 準備物
- 活動に必要な物品リスト

4. 「毎日の支援」はルーティーン活動なので、簡潔に記載
5. 「主活動」はメインの活動なので、詳細に記載
6. 子どもの発達段階に合わせた声かけの例を含める
7. 活動の切り替え時の工夫も記載

【その他（other_notes）の作成ガイドライン】
以下の内容を記載してください：
- 安全面での注意点
- 個別支援が必要な子どもへの配慮
- 活動中の見守りポイント

出力は日本語で、実用的で具体的な内容にしてください。
PROMPT;

    // OpenAI APIを呼び出し
    $response = callOpenAI($prompt, 'gpt-4o-mini', 3000);

    // JSONをパース
    $jsonStr = $response;
    if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $jsonStr = $matches[1];
    }

    $result = json_decode($jsonStr, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSONパースに失敗した場合は、レスポンスをそのまま返す
        echo json_encode([
            'success' => true,
            'data' => [
                'activity_content' => $response,
                'other_notes' => ''
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'activity_content' => $result['activity_content'] ?? '',
                'other_notes' => $result['other_notes'] ?? ''
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'AI生成に失敗しました: ' . $e->getMessage()
    ]);
}
