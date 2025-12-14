<?php
/**
 * スタッフかけはし自動生成処理
 * OpenAI APIを使用して連絡帳データから五領域の課題と目標を生成
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/database.php';

// openai_helper.phpが存在するかチェック
$openaiHelperPath = __DIR__ . '/../../includes/openai_helper.php';
if (!file_exists($openaiHelperPath)) {
    die("エラー: openai_helper.php が見つかりません: " . $openaiHelperPath);
}
require_once $openaiHelperPath;

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// POSTデータ取得
$studentId = $_POST['student_id'] ?? null;
$periodId = $_POST['period_id'] ?? null;

if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒IDまたは期間IDが指定されていません。';
    header('Location: kakehashi_staff.php');
    exit;
}

try {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('生徒が見つかりません。');
    }

    // 選択された期間情報を取得
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $currentPeriod = $stmt->fetch();

    if (!$currentPeriod) {
        throw new Exception('期間が見つかりません。');
    }

    // 対象期間の開始日から5か月前の日付を計算
    $periodStart = new DateTime($currentPeriod['start_date']);
    $fiveMonthsAgo = clone $periodStart;
    $fiveMonthsAgo->modify('-5 months');

    // 直近5か月の連絡帳データを取得（domain1, domain2形式）
    $stmt = $pdo->prepare("
        SELECT
            dr.record_date,
            sr.domain1,
            sr.domain1_content,
            sr.domain2,
            sr.domain2_content
        FROM student_records sr
        INNER JOIN daily_records dr ON sr.daily_record_id = dr.id
        WHERE sr.student_id = ?
        AND dr.record_date >= ?
        AND dr.record_date < ?
        AND (sr.domain1_content IS NOT NULL OR sr.domain2_content IS NOT NULL)
        ORDER BY dr.record_date DESC
        LIMIT 100
    ");
    $stmt->execute([
        $studentId,
        $fiveMonthsAgo->format('Y-m-d'),
        $periodStart->format('Y-m-d')
    ]);
    $recentRecords = $stmt->fetchAll();

    if (empty($recentRecords)) {
        throw new Exception('直近5か月の連絡帳データが見つかりません。この生徒の連絡帳データを入力してください。');
    }

    // 領域名のマッピング
    $domainNames = [
        'health_life' => '健康・生活',
        'motor_sensory' => '運動・感覚',
        'cognitive_behavior' => '認知・行動',
        'language_communication' => '言語・コミュニケーション',
        'social_relations' => '人間関係・社会性'
    ];

    // 領域ごとにデータを集約
    $domainData = [
        'health_life' => [],
        'motor_sensory' => [],
        'cognitive_behavior' => [],
        'language_communication' => [],
        'social_relations' => []
    ];

    foreach ($recentRecords as $record) {
        $date = date('Y年m月d日', strtotime($record['record_date']));

        if ($record['domain1'] && $record['domain1_content']) {
            $domainData[$record['domain1']][] = "【{$date}】" . $record['domain1_content'];
        }
        if ($record['domain2'] && $record['domain2_content']) {
            $domainData[$record['domain2']][] = "【{$date}】" . $record['domain2_content'];
        }
    }

    // 連絡帳データを整形してAIプロンプト用テキストを作成
    $recordsSummary = "";
    foreach ($domainData as $domain => $contents) {
        if (!empty($contents)) {
            $recordsSummary .= "\n■ " . $domainNames[$domain] . "\n";
            // 最新10件を表示
            $recordsSummary .= implode("\n", array_slice($contents, 0, 10)) . "\n";
        }
    }

    if (empty(trim($recordsSummary))) {
        throw new Exception('五領域の記録データが見つかりません。');
    }

    // 前回のスタッフかけはしの長期目標を取得
    $stmt = $pdo->prepare("
        SELECT ks.long_term_goal
        FROM kakehashi_staff ks
        INNER JOIN kakehashi_periods kp ON ks.period_id = kp.id
        WHERE ks.student_id = ?
        AND kp.period_number < ?
        AND ks.long_term_goal IS NOT NULL
        AND ks.long_term_goal != ''
        ORDER BY kp.period_number DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $currentPeriod['period_number']]);
    $previousKakehashi = $stmt->fetch();
    $previousLongTermGoal = $previousKakehashi['long_term_goal'] ?? null;

    // OpenAI APIで五領域の課題を生成
    $domainsPrompt = "あなたは発達支援・特別支援教育の専門スタッフです。以下の生徒の直近5か月の連絡帳記録を詳細に分析し、今後6か月間の具体的な支援課題を各領域ごとに250文字程度でまとめてください。

【生徒情報】
名前: " . $student['student_name'] . "

【直近5か月の連絡帳記録】
" . $recordsSummary . "

【分析と課題作成の指針】
以下の5つの領域について、記録から読み取れる具体的な事実を基に、今後6か月間で取り組むべき課題を250文字程度で詳細に記述してください。

■ 健康・生活
- 食事、排泄、睡眠、衛生管理、身だしなみ、安全意識などの実態
- 観察された具体的な行動や変化
- 今後の支援目標と具体的なアプローチ

■ 運動・感覚
- 粗大運動（歩く、走る、跳ぶ等）、微細運動（書く、切る、つまむ等）の状況
- 感覚過敏・鈍麻、協調性、身体の使い方の特徴
- 改善が見られた点と今後強化すべき点

■ 認知・行動
- 注意集中、記憶、理解力、問題解決能力の実態
- こだわりやパターン、衝動性、活動への取り組み方
- 成長が見られた認知面と支援が必要な行動面

■ 言語・コミュニケーション
- 言語理解（指示理解、質問理解等）と言語表出（発語、文章構成等）
- 非言語コミュニケーション（視線、ジェスチャー、表情等）
- コミュニケーション意欲や手段の変化

■ 人間関係・社会性
- 他者への関心、対人距離、集団参加の様子
- 感情表現、感情調整、共感性の発達
- 友達関係、協調性、ルール理解の状況

**重要**: 以下のJSON形式のみを出力してください。各領域250文字程度で、観察事実に基づく具体的な内容を記述してください。
{
  \"domain_health_life\": \"健康・生活の課題（250文字程度、具体的な観察事実と支援方針）\",
  \"domain_motor_sensory\": \"運動・感覚の課題（250文字程度、具体的な観察事実と支援方針）\",
  \"domain_cognitive_behavior\": \"認知・行動の課題（250文字程度、具体的な観察事実と支援方針）\",
  \"domain_language_communication\": \"言語・コミュニケーションの課題（250文字程度、具体的な観察事実と支援方針）\",
  \"domain_social_relations\": \"人間関係・社会性の課題（250文字程度、具体的な観察事実と支援方針）\"
}";

    $domainsResponse = callOpenAI($domainsPrompt, 'gpt-4.0', 2500);

    // レスポンスからJSONを抽出（```json ``` で囲まれている場合があるため）
    $jsonText = $domainsResponse;
    if (preg_match('/```json\s*(\{.*?\})\s*```/s', $domainsResponse, $matches)) {
        $jsonText = $matches[1];
    } elseif (preg_match('/(\{.*?\})/s', $domainsResponse, $matches)) {
        $jsonText = $matches[1];
    }

    // JSONをパース
    $domainsData = json_decode($jsonText, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // パース失敗時はレスポンス内容をログ
        error_log("OpenAI Response: " . $domainsResponse);
        throw new Exception('五領域の課題生成に失敗しました。生成された内容: ' . substr($domainsResponse, 0, 200));
    }

    // 短期目標を生成
    $shortTermPrompt = "以下の五領域の課題分析から、今後6か月間の短期目標を300文字程度で作成してください。

【五領域の課題】
■健康・生活
" . ($domainsData['domain_health_life'] ?? '') . "

■運動・感覚
" . ($domainsData['domain_motor_sensory'] ?? '') . "

■認知・行動
" . ($domainsData['domain_cognitive_behavior'] ?? '') . "

■言語・コミュニケーション
" . ($domainsData['domain_language_communication'] ?? '') . "

■人間関係・社会性
" . ($domainsData['domain_social_relations'] ?? '') . "

【短期目標作成の要点】
- 各領域の課題を総合的に考慮し、優先度の高いものから記述
- 具体的で測定可能な目標（例：「〜ができるようになる」）
- 6か月後に達成可能な現実的な目標設定
- 本人の強みや興味を活かした支援の視点

短期目標（6か月）を300文字程度の文章で記述してください（JSON不要、テキストのみ）：";

    $shortTermGoal = callOpenAI($shortTermPrompt, 'gpt-4.0', 800);

    // 長期目標を生成
    $longTermPrompt = "以下の情報から、今後1年以上を見据えた長期目標を350文字程度で作成してください。

【短期目標（今後6か月）】
" . $shortTermGoal;

    if ($previousLongTermGoal) {
        $longTermPrompt .= "

【前回の長期目標】
" . $previousLongTermGoal . "

【長期目標作成の要点】
- 前回の長期目標からの成長や変化を考慮
- 短期目標の達成を踏まえた次のステップ
- 1年後～数年後の本人の姿を具体的にイメージ
- 社会参加や自立に向けた視点を含める
- 本人の可能性を信じた前向きな目標設定

前回の長期目標を参考にしつつ、短期目標の達成を加味して、より発展的な長期目標を350文字程度で記述してください。";
    } else {
        $longTermPrompt .= "

【長期目標作成の要点】
- 短期目標の延長線上にある1年後～数年後の姿
- 生活の質（QOL）向上や社会参加の視点
- 本人の特性や強みを活かした自立への道筋
- 将来の進路や生活場面を見据えた具体的な目標
- 本人と家族が希望する将来像

短期目標を踏まえて、1年以上先を見据えた長期的な成長目標を350文字程度で記述してください。";
    }

    $longTermPrompt .= "

長期目標（1年以上）を文章で記述してください（JSON不要、テキストのみ）：";

    $longTermGoal = callOpenAI($longTermPrompt, 'gpt-4.0', 900);

    // 生成されたデータをセッションに保存
    $_SESSION['generated_kakehashi'] = [
        'domain_health_life' => $domainsData['domain_health_life'] ?? '',
        'domain_motor_sensory' => $domainsData['domain_motor_sensory'] ?? '',
        'domain_cognitive_behavior' => $domainsData['domain_cognitive_behavior'] ?? '',
        'domain_language_communication' => $domainsData['domain_language_communication'] ?? '',
        'domain_social_relations' => $domainsData['domain_social_relations'] ?? '',
        'short_term_goal' => trim($shortTermGoal),
        'long_term_goal' => trim($longTermGoal),
        'other_challenges' => '' // 空のまま
    ];

    $_SESSION['success'] = 'かけはしの内容を自動生成しました。内容を確認して必要に応じて修正してください。';
    header('Location: kakehashi_staff.php?student_id=' . $studentId . '&period_id=' . $periodId);
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    header('Location: kakehashi_staff.php?student_id=' . $studentId . '&period_id=' . $periodId);
    exit;
}
