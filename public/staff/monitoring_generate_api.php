<?php
/**
 * モニタリング評価自動生成API
 * 各目標の関連分野の過去6ヶ月以内の情報からChatGPTで評価を生成
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/env.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $planId = $input['plan_id'] ?? null;
    $studentId = $input['student_id'] ?? null;
    $detailId = $input['detail_id'] ?? null; // 特定の目標のみ生成する場合

    if (!$planId || !$studentId) {
        throw new Exception('必要なパラメータが不足しています');
    }

    // 生徒情報を取得（自分の教室のみ）- 最初に確認
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND classroom_id = ?");
        $stmt->execute([$studentId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
    }
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('生徒が見つかりません、またはアクセス権限がありません');
    }

    // 計画情報を取得（生徒と紐づいているか確認）
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ? AND student_id = ?");
    $stmt->execute([$planId, $studentId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception('計画が見つかりません');
    }

    // 計画明細を取得
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
    $stmt->execute([$planId]);
    $planDetails = $stmt->fetchAll();

    if (empty($planDetails)) {
        throw new Exception('計画の明細が見つかりません');
    }

    // 過去6ヶ月の連絡帳データを取得
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    $stmt = $pdo->prepare("
        SELECT
            sr.domain1, sr.domain1_content,
            sr.domain2, sr.domain2_content,
            sr.daily_note,
            dr.record_date, dr.activity_name, dr.common_activity
        FROM student_records sr
        INNER JOIN daily_records dr ON sr.daily_record_id = dr.id
        WHERE sr.student_id = ? AND dr.record_date >= ?
        ORDER BY dr.record_date DESC
    ");
    $stmt->execute([$studentId, $sixMonthsAgo]);
    $studentRecords = $stmt->fetchAll();

    // 分野ごとに記録をグループ化
    $recordsByDomain = groupRecordsByDomain($studentRecords);

    // 各計画明細に対して評価を生成
    $generatedEvaluations = [];

    // 特定の目標のみの場合はフィルタリング
    if ($detailId) {
        $planDetails = array_filter($planDetails, function($d) use ($detailId) {
            return $d['id'] == $detailId;
        });
    }

    foreach ($planDetails as $detail) {
        $category = $detail['category'] ?? $detail['main_category'] ?? '';
        $subCategory = $detail['sub_category'] ?? '';
        $supportGoal = $detail['support_goal'] ?? '';
        $supportContent = $detail['support_content'] ?? '';

        if (empty($supportGoal)) {
            $generatedEvaluations[$detail['id']] = [
                'achievement_status' => '',
                'monitoring_comment' => '※ 支援目標が設定されていないため、評価を生成できません。'
            ];
            continue;
        }

        // この目標に関連する分野の記録を取得
        $relatedRecords = getRelatedRecords($recordsByDomain, $category, $subCategory);

        // ChatGPTで評価を生成
        $evaluation = generateEvaluationWithAI(
            $student['student_name'],
            $category,
            $subCategory,
            $supportGoal,
            $supportContent,
            $relatedRecords
        );

        $generatedEvaluations[$detail['id']] = $evaluation;
    }

    echo json_encode([
        'success' => true,
        'data' => $generatedEvaluations
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Monitoring generate error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 連絡帳の5領域キーと日本語ラベルのマッピング
 * DBには英語キー(health_life等)が保存されている
 */
function getDomainKeyToLabel() {
    return [
        'health_life' => '健康・生活',
        'motor_sensory' => '運動・感覚',
        'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション',
        'social_relations' => '人間関係・社会性'
    ];
}

/**
 * 連絡帳記録を5領域ごとにグループ化
 * DBのキー値を日本語ラベルに変換してグループ化
 */
function groupRecordsByDomain($records) {
    $grouped = [];
    $keyToLabel = getDomainKeyToLabel();

    foreach ($records as $record) {
        // domain1の記録を追加
        if (!empty($record['domain1'])) {
            $domainKey = trim($record['domain1']);
            // キーを日本語ラベルに変換
            $domain = $keyToLabel[$domainKey] ?? $domainKey;
            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [];
            }
            $grouped[$domain][] = [
                'date' => $record['record_date'],
                'activity' => $record['activity_name'],
                'content' => $record['domain1_content'] ?? '',
                'note' => $record['daily_note'] ?? '',
                'common_activity' => $record['common_activity'] ?? ''
            ];
        }

        // domain2の記録を追加
        if (!empty($record['domain2'])) {
            $domainKey = trim($record['domain2']);
            // キーを日本語ラベルに変換
            $domain = $keyToLabel[$domainKey] ?? $domainKey;
            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [];
            }
            $grouped[$domain][] = [
                'date' => $record['record_date'],
                'activity' => $record['activity_name'],
                'content' => $record['domain2_content'] ?? '',
                'note' => $record['daily_note'] ?? '',
                'common_activity' => $record['common_activity'] ?? ''
            ];
        }
    }

    // デバッグ用ログ
    foreach ($grouped as $domain => $recs) {
        error_log("Domain '{$domain}': " . count($recs) . " records");
    }

    return $grouped;
}

/**
 * 個別支援計画書のサブカテゴリから連絡帳の領域へのマッピング
 * 計画書サブカテゴリ例: 生活習慣（健康・生活）, コミュニケーション（言語・コミュニケーション）
 * 連絡帳領域: 健康・生活, 運動・感覚, 認知・行動, 言語・コミュニケーション, 人間関係・社会性
 */
function getSubCategoryToDomainMapping() {
    return [
        // サブカテゴリに含まれるキーワード => 連絡帳の領域名
        '健康・生活' => '健康・生活',
        '生活習慣' => '健康・生活',
        '運動・感覚' => '運動・感覚',
        '運動' => '運動・感覚',
        '感覚' => '運動・感覚',
        '認知・行動' => '認知・行動',
        '学習' => '認知・行動',
        '認知' => '認知・行動',
        '言語・コミュニケーション' => '言語・コミュニケーション',
        'コミュニケーション' => '言語・コミュニケーション',
        '言語' => '言語・コミュニケーション',
        '人間関係・社会性' => '人間関係・社会性',
        '社会性' => '人間関係・社会性',
        '人間関係' => '人間関係・社会性',
    ];
}

/**
 * 目標に関連する記録を取得
 * サブカテゴリから連絡帳の5領域を特定し、その領域の記録を取得
 */
function getRelatedRecords($recordsByDomain, $category, $subCategory) {
    $related = [];
    $mapping = getSubCategoryToDomainMapping();

    // サブカテゴリからマッチする連絡帳の領域を特定
    $matchedDomains = [];
    foreach ($mapping as $keyword => $domain) {
        if (mb_strpos($subCategory, $keyword) !== false) {
            if (!in_array($domain, $matchedDomains)) {
                $matchedDomains[] = $domain;
            }
        }
    }

    // デバッグ用ログ
    error_log("SubCategory: {$subCategory}");
    error_log("Matched domains: " . implode(', ', $matchedDomains));
    error_log("Available domains in records: " . implode(', ', array_keys($recordsByDomain)));

    // 関連する分野の記録を収集
    foreach ($matchedDomains as $matchedDomain) {
        if (isset($recordsByDomain[$matchedDomain])) {
            error_log("Found " . count($recordsByDomain[$matchedDomain]) . " records for domain: {$matchedDomain}");
            $related = array_merge($related, $recordsByDomain[$matchedDomain]);
        }
    }

    error_log("Total related records: " . count($related));

    // 重複を除去
    $unique = [];
    $seen = [];
    foreach ($related as $record) {
        $key = $record['date'] . '|' . ($record['content'] ?? '');
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $record;
        }
    }

    // 日付の新しい順にソートして最大20件に制限
    usort($unique, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    return array_slice($unique, 0, 20);
}

/**
 * ChatGPT APIで評価を生成
 */
function generateEvaluationWithAI($studentName, $category, $subCategory, $supportGoal, $supportContent, $relatedRecords) {
    $apiKey = env('OPENAI_API_KEY', '');

    if (empty($apiKey)) {
        return [
            'achievement_status' => '',
            'monitoring_comment' => '※ ChatGPT APIキーが設定されていないため、自動生成できません。手動で入力してください。'
        ];
    }

    // 関連記録がない場合
    if (empty($relatedRecords)) {
        return [
            'achievement_status' => '',
            'monitoring_comment' => '※ 過去6ヶ月間にこの分野に関連する記録がありませんでした。手動で評価を入力してください。'
        ];
    }

    // 記録を文字列に整形
    $recordsText = "";
    foreach ($relatedRecords as $index => $record) {
        $date = date('Y/m/d', strtotime($record['date']));
        $recordsText .= ($index + 1) . ". [{$date}] 活動: {$record['activity']}\n";
        if (!empty($record['content'])) {
            $recordsText .= "   この領域での記録: {$record['content']}\n";
        }
        if (!empty($record['common_activity'])) {
            $commonShort = mb_substr($record['common_activity'], 0, 150);
            $recordsText .= "   活動の様子: {$commonShort}\n";
        }
        if (!empty($record['note'])) {
            $noteShort = mb_substr($record['note'], 0, 100);
            $recordsText .= "   個別メモ: {$noteShort}\n";
        }
        $recordsText .= "\n";
    }

    $recordCount = count($relatedRecords);

    $prompt = <<<PROMPT
あなたは児童発達支援施設の児童発達支援管理責任者です。
以下の支援目標に対して、過去6ヶ月間の連絡帳記録（{$recordCount}件）を分析し、モニタリング評価を行ってください。

【児童氏名】
{$studentName}

【支援目標の分野】
{$category} > {$subCategory}

【支援目標】
{$supportGoal}

【支援内容（施設での取り組み）】
{$supportContent}

【過去6ヶ月間の連絡帳記録（この分野に関する記録）】
{$recordsText}

【評価の観点】
1. 上記の連絡帳記録から、支援目標に対する子どもの取り組みや変化を読み取ってください
2. 具体的なエピソードや行動を踏まえて評価してください
3. 支援内容が適切に実施されているか、効果が出ているかを判断してください

【出力形式】
以下の形式でJSONのみを出力してください。他の文字は一切出力しないでください。

{
  "achievement_status": "達成状況（「達成」「進行中」「未着手」「継続中」「見直し必要」のいずれか）",
  "monitoring_comment": "評価コメント（150〜200字程度。連絡帳の記録を踏まえた具体的な評価と、今後の支援の方向性を含める）"
}
PROMPT;

    $response = callChatGPTAPI($prompt);

    // Markdownコードブロックを除去（```json ... ``` や ``` ... ```）
    $cleanedResponse = $response;
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches)) {
        $cleanedResponse = trim($matches[1]);
    }

    // JSONをパース
    $result = json_decode($cleanedResponse, true);

    if ($result && isset($result['achievement_status']) && isset($result['monitoring_comment'])) {
        return [
            'achievement_status' => $result['achievement_status'],
            'monitoring_comment' => $result['monitoring_comment']
        ];
    }

    // パースに失敗した場合、テキストとして返す
    error_log("JSON parse failed. Original response: " . $response);
    return [
        'achievement_status' => '',
        'monitoring_comment' => $cleanedResponse
    ];
}

/**
 * ChatGPT APIを呼び出す
 */
function callChatGPTAPI($prompt) {
    $apiKey = env('OPENAI_API_KEY', '');

    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-5.2',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは個別支援教育の経験豊富な児童発達支援管理責任者です。モニタリング評価を専門的かつ保護者にも分かりやすく行います。指定された形式でのみ回答してください。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.5,
        'max_completion_tokens' => 800
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("ChatGPT API cURL Error: " . $error);
        return '※ AI生成中にエラーが発生しました（接続エラー）。手動で入力してください。';
    }

    if ($httpCode !== 200) {
        error_log("ChatGPT API HTTP Error: " . $httpCode . " Response: " . $response);
        return '※ AI生成中にエラーが発生しました（HTTP ' . $httpCode . '）。手動で入力してください。';
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    }

    error_log("ChatGPT API Invalid Response: " . $response);
    return '※ AI生成に失敗しました。手動で入力してください。';
}
