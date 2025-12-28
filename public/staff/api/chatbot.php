<?php
/**
 * チャットボット API エンドポイント
 * 操作サポート用のAIチャットボット（OpenAI使用）
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/openai_helper.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'アクセス権限がありません']);
    exit;
}

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// JSONリクエストを解析
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'error' => 'メッセージが空です']);
    exit;
}

// システムプロンプト（アプリケーションの説明）
$systemPrompt = <<<PROMPT
あなたは「きづり」システムの操作サポートアシスタントです。
放課後等デイサービス向けの業務支援システムについて、丁寧に操作方法を説明してください。

【重要なルール】
- 以下のような質問には積極的に回答してください：
  - 支援案、連絡帳、かけはし、個別支援計画など、システム機能の使い方
  - ボタンの操作方法や画面の見方
  - 生徒・保護者の登録方法
  - データの入力・編集・削除方法
- 回答は簡潔で分かりやすく、箇条書きを活用してください
- 専門用語は避け、誰でも理解できる言葉で説明してください
- 完全にシステムと無関係な質問（天気、料理、一般知識など）の場合のみ「申し訳ありませんが、本システムの操作に関するご質問にのみお答えしています。」と回答してください

【システムの主な機能】

■ 活動管理（メインメニュー → 活動管理）
- 日々の活動を登録・管理します
- 「新しい活動を追加」ボタンで活動を登録
- 登録した活動は「統合する」ボタンでAIが連絡帳を自動生成
- 生成された連絡帳は「統合内容を編集」で確認・修正できます

■ 連絡帳
- 活動内容を保護者に配信する機能
- 活動を登録後、「統合する」でAIが個別の連絡帳を生成
- 生成後「統合内容を編集」で内容確認、「連絡帳を送信」で保護者へ配信

■ 支援案管理（メニュー → 支援案）
- 繰り返し使う活動テンプレートを作成
- 「新しい支援案を作成」で登録
- 「毎日の支援を設定」で定例活動（朝の会等）を設定
- 「タグを設定」で分類用タグを管理

■ かけはし
- 個別支援計画書作成のための情報収集機能
- 「かけはし（職員）」：スタッフが記入
- 「かけはし（保護者）」：保護者の記入内容を確認

■ 個別支援計画・モニタリング
- 「個別支援計画」：計画書の作成・管理
- 「モニタリング」：計画の進捗確認表

■ 週間計画
- 生徒ごとの週間目標・計画を設定
- 毎週の支援内容を計画

■ 保護者チャット
- 保護者との個別メッセージ機能
- ピン留め機能で重要なチャットを上部表示

■ 生徒チャット
- 生徒との直接メッセージ機能

■ 生徒管理・保護者管理
- 利用者情報の登録・編集
- 通所曜日、支援開始日などを設定

■ 利用者一括登録
- CSVファイルまたはAI解析で複数の生徒・保護者を一括登録
- 登録後、ID/パスワード一覧をPDFでダウンロード可能

■ 施設通信
- 保護者向けのお知らせ作成機能
- AIによる下書き生成機能あり

■ イベント管理
- 施設のイベントを登録・管理

■ 利用日変更・振替管理
- 利用日の追加・変更リクエスト管理

■ 休日設定
- 施設の休業日を設定

【よくある質問への回答例】
Q: 連絡帳を送りたい
A: 1. 活動管理で活動を登録 → 2. 「統合する」をクリック → 3. 「統合内容を編集」で確認 → 4. 「連絡帳を送信」で配信

Q: 生徒を登録したい
A: メニューの「生徒管理」から「新規登録」ボタンをクリック。または「利用者一括登録」でCSVから一括登録も可能です。

Q: パスワードがわからない
A: 保護者管理から該当の保護者を選択し、編集画面でパスワードを確認・変更できます。
PROMPT;

// OpenAI API呼び出し
$response = callChatbotOpenAI($systemPrompt, $userMessage);

header('Content-Type: application/json');
echo json_encode($response);

/**
 * チャットボット用OpenAI API呼び出し（システムプロンプト対応）
 */
function callChatbotOpenAI($systemPrompt, $userMessage) {
    $apiKey = OPENAI_API_KEY;

    if (!$apiKey || $apiKey === 'YOUR_OPENAI_API_KEY_HERE') {
        return ['success' => false, 'error' => 'AIサービスが設定されていません。管理者にお問い合わせください。'];
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-5-mini-2025-08-07',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'max_completion_tokens' => 1000
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 60
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Chatbot API curl error: " . $error);
        return ['success' => false, 'error' => '通信エラーが発生しました'];
    }

    if ($httpCode !== 200) {
        error_log("Chatbot API HTTP error: " . $httpCode . " - " . $result);
        $errorData = json_decode($result, true);
        $errorMessage = $errorData['error']['message'] ?? 'AIサービスへの接続に失敗しました';
        return ['success' => false, 'error' => $errorMessage];
    }

    $responseData = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Chatbot API JSON parse error: " . json_last_error_msg());
        return ['success' => false, 'error' => 'レスポンスの解析に失敗しました'];
    }

    if (isset($responseData['choices'][0]['message']['content'])) {
        $content = $responseData['choices'][0]['message']['content'];
        if (empty(trim($content))) {
            return ['success' => false, 'error' => '回答が空でした。もう一度お試しください。'];
        }
        return [
            'success' => true,
            'response' => $content
        ];
    }

    error_log("Chatbot API unexpected response: " . $result);
    return ['success' => false, 'error' => '回答を取得できませんでした。もう一度お試しください。'];
}
