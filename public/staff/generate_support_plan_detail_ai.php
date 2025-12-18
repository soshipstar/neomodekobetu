<?php
/**
 * AI による活動の目的・内容詳細生成 API
 * 活動名と簡単な入力から詳細な目的・内容を生成
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
$activityContent = trim($_POST['activity_content'] ?? '');
$targetGrade = trim($_POST['target_grade'] ?? '');

if (empty($activityName)) {
    echo json_encode(['success' => false, 'error' => '活動名を入力してください']);
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

try {
    // AIに送るプロンプトを作成
    $prompt = <<<PROMPT
あなたは児童発達支援・放課後等デイサービスの経験豊富な支援員です。
以下の活動について、詳細な「活動の目的」と「活動の内容」を生成してください。

【活動名】
{$activityName}

【現在入力されている活動の目的】
{$activityPurpose}

【現在入力されている活動の内容】
{$activityContent}

【対象年齢層】
{$targetGradeText}

以下の形式でJSON形式で出力してください。JSONのみを出力し、他の説明は不要です。

{
    "activity_purpose": "活動の目的（詳細版）",
    "activity_content": "活動の内容（詳細版）"
}

【活動の目的について】
- 児童発達支援・放課後等デイサービスの観点から、この活動を通して達成したい目標を具体的に記載
- 発達支援の視点を含める（社会性、コミュニケーション、生活スキル、運動能力など）
- 対象年齢層に適した目標設定
- 3〜5つの具体的な目標を箇条書きで記載

【活動の内容について】
- 活動の具体的な流れを時系列で記載
- 導入→展開→まとめの構成で記載
- 子どもたちへの声かけや関わり方のポイントを含める
- 所要時間の目安があれば記載
- 必要な準備物や材料も記載

入力された内容がある場合は、それを活かしてより詳細に展開してください。
入力がない場合は、活動名から想定される内容を詳細に作成してください。
出力は日本語で、実用的で具体的な内容にしてください。
PROMPT;

    // OpenAI APIを呼び出し
    $response = callOpenAI($prompt, 'gpt-4o-mini', 2500);

    // JSONをパース
    // レスポンスからJSONを抽出（マークダウンのコードブロックを除去）
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
                'activity_purpose' => $response,
                'activity_content' => ''
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'activity_purpose' => $result['activity_purpose'] ?? '',
                'activity_content' => $result['activity_content'] ?? ''
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'AI生成に失敗しました: ' . $e->getMessage()
    ]);
}
