<?php
/**
 * AI による支援案詳細生成 API
 * 活動名、目的、内容から五領域への配慮とその他を生成
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

if (empty($activityName)) {
    echo json_encode(['success' => false, 'error' => '活動名を入力してください']);
    exit;
}

try {
    // AIに送るプロンプトを作成
    $prompt = <<<PROMPT
あなたは児童発達支援・放課後等デイサービスの支援員です。
以下の活動について、五領域への配慮とその他の注意点を生成してください。

【活動名】
{$activityName}

【活動の目的】
{$activityPurpose}

【活動の内容】
{$activityContent}

以下の形式でJSON形式で出力してください。JSONのみを出力し、他の説明は不要です。

{
    "five_domains_consideration": "五領域への配慮を一つの文字列として記載（下記フォーマット参照）",
    "other_notes": "活動を行う際の注意点、準備物、安全面での配慮など"
}

【five_domains_considerationのフォーマット】
以下の形式で、5つの領域を改行で区切って一つの文字列として記載してください：

【健康・生活】
（この活動における健康・生活面での配慮）

【運動・感覚】
（この活動における運動・感覚面での配慮）

【認知・行動】
（この活動における認知・行動面での配慮）

【言語・コミュニケーション】
（この活動における言語・コミュニケーション面での配慮）

【人間関係・社会性】
（この活動における人間関係・社会性面での配慮）

各領域の説明：
1. 健康・生活：基本的な生活習慣、健康管理に関すること
2. 運動・感覚：身体の動き、感覚の使い方に関すること
3. 認知・行動：物事の理解、問題解決、行動のコントロールに関すること
4. 言語・コミュニケーション：言葉の理解と表出、コミュニケーションに関すること
5. 人間関係・社会性：他者との関わり、社会的なルールの理解に関すること

出力は日本語で、実用的で具体的な内容にしてください。
PROMPT;

    // OpenAI APIを呼び出し
    $response = callOpenAI($prompt, 'gpt-4o-mini', 2000);

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
                'five_domains_consideration' => $response,
                'other_notes' => ''
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'five_domains_consideration' => $result['five_domains_consideration'] ?? '',
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
