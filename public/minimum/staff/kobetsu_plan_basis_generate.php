<?php
/**
 * 個別支援計画の根拠文書 AI生成処理
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$planId = $_GET['plan_id'] ?? null;
$regenerate = isset($_GET['regenerate']);

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 計画書を取得
$stmt = $pdo->prepare("
    SELECT isp.*, s.student_name as current_student_name
    FROM individual_support_plans isp
    JOIN students s ON isp.student_id = s.id
    WHERE isp.id = ?
");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '計画書が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

$studentId = $plan['student_id'];
$studentName = $plan['student_name'] ?: $plan['current_student_name'];

// 計画の作成日に近いかけはし期間を探す
$planDate = new DateTime($plan['created_date']);
$stmt = $pdo->prepare("
    SELECT kp.*
    FROM kakehashi_periods kp
    WHERE kp.student_id = ?
    AND kp.submission_deadline <= ?
    ORDER BY kp.submission_deadline DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$period = $stmt->fetch();

// 保護者かけはしデータを取得
$guardianKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $guardianKakehashi = $stmt->fetch();
}

// スタッフかけはしデータを取得
$staffKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $staffKakehashi = $stmt->fetch();
}

// 直近のモニタリングを取得
$stmt = $pdo->prepare("
    SELECT mr.*, GROUP_CONCAT(
        CONCAT(
            COALESCE(ispd.category, ''), '|',
            COALESCE(ispd.sub_category, ''), '|',
            COALESCE(md.achievement_status, ''), '|',
            COALESCE(md.monitoring_comment, '')
        ) SEPARATOR '###'
    ) as monitoring_items
    FROM monitoring_records mr
    LEFT JOIN monitoring_details md ON mr.id = md.monitoring_id
    LEFT JOIN individual_support_plan_details ispd ON md.plan_detail_id = ispd.id
    WHERE mr.student_id = ?
    AND mr.monitoring_date <= ?
    GROUP BY mr.id
    ORDER BY mr.monitoring_date DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$monitoring = $stmt->fetch();

// プロンプトの構築
$prompt = "あなたは児童発達支援・放課後等デイサービスの専門家です。\n";
$prompt .= "以下のデータに基づいて、この個別支援計画に対する「全体所感」を作成してください。\n";
$prompt .= "保護者に向けて、計画がどのような考えに基づいて作成されたかを説明する文書です。\n\n";

$prompt .= "【重要な指示】\n";
$prompt .= "- 保護者に分かりやすい丁寧な言葉で説明してください\n";
$prompt .= "- 保護者・スタッフからのかけはしの内容を踏まえた説明をしてください\n";
$prompt .= "- 計画の目標がどのように本人・家族の願いを反映しているか説明してください\n";
$prompt .= "- お子様の強みや成長の可能性についても触れてください\n";
$prompt .= "- 600〜1000文字程度でまとめてください\n\n";

$prompt .= "【生徒名】\n" . $studentName . "\n\n";

$prompt .= "【個別支援計画の内容】\n";
$prompt .= "作成日: " . $plan['created_date'] . "\n";
$prompt .= "利用児及び家族の意向: " . ($plan['life_intention'] ?? '（未記入）') . "\n";
$prompt .= "総合的な支援の方針: " . ($plan['overall_policy'] ?? '（未記入）') . "\n";
$prompt .= "長期目標: " . ($plan['long_term_goal_text'] ?? '（未記入）') . "\n";
$prompt .= "短期目標: " . ($plan['short_term_goal_text'] ?? '（未記入）') . "\n\n";

if ($guardianKakehashi) {
    $prompt .= "【保護者からのかけはし（提出日: " . ($guardianKakehashi['submitted_at'] ?? '不明') . "）】\n";
    $prompt .= "本人の願い: " . ($guardianKakehashi['student_wish'] ?? '') . "\n";
    $prompt .= "家庭での願い: " . ($guardianKakehashi['home_challenges'] ?? '') . "\n";
    $prompt .= "短期目標: " . ($guardianKakehashi['short_term_goal'] ?? '') . "\n";
    $prompt .= "長期目標: " . ($guardianKakehashi['long_term_goal'] ?? '') . "\n";
    $prompt .= "健康・生活: " . ($guardianKakehashi['domain_health_life'] ?? '') . "\n";
    $prompt .= "運動・感覚: " . ($guardianKakehashi['domain_motor_sensory'] ?? '') . "\n";
    $prompt .= "認知・行動: " . ($guardianKakehashi['domain_cognitive_behavior'] ?? '') . "\n";
    $prompt .= "言語・コミュニケーション: " . ($guardianKakehashi['domain_language_communication'] ?? '') . "\n";
    $prompt .= "人間関係・社会性: " . ($guardianKakehashi['domain_social_relations'] ?? '') . "\n\n";
}

if ($staffKakehashi) {
    $prompt .= "【スタッフからのかけはし（提出日: " . ($staffKakehashi['submitted_at'] ?? '不明') . "）】\n";
    $prompt .= "本人の願い: " . ($staffKakehashi['student_wish'] ?? '') . "\n";
    $prompt .= "短期目標: " . ($staffKakehashi['short_term_goal'] ?? '') . "\n";
    $prompt .= "長期目標: " . ($staffKakehashi['long_term_goal'] ?? '') . "\n";
    $prompt .= "健康・生活: " . ($staffKakehashi['domain_health_life'] ?? '') . "\n";
    $prompt .= "運動・感覚: " . ($staffKakehashi['domain_motor_sensory'] ?? '') . "\n";
    $prompt .= "認知・行動: " . ($staffKakehashi['domain_cognitive_behavior'] ?? '') . "\n";
    $prompt .= "言語・コミュニケーション: " . ($staffKakehashi['domain_language_communication'] ?? '') . "\n";
    $prompt .= "人間関係・社会性: " . ($staffKakehashi['domain_social_relations'] ?? '') . "\n\n";
}

if ($monitoring) {
    $prompt .= "【直近のモニタリング（実施日: " . $monitoring['monitoring_date'] . "）】\n";
    $prompt .= "総合所見: " . ($monitoring['overall_comment'] ?? '') . "\n";
    if ($monitoring['monitoring_items']) {
        $items = explode('###', $monitoring['monitoring_items']);
        foreach ($items as $item) {
            $parts = explode('|', $item);
            if (count($parts) >= 4 && !empty($parts[0])) {
                $prompt .= $parts[0] . " - " . $parts[1] . ": " . $parts[2] . " / " . $parts[3] . "\n";
            }
        }
    }
    $prompt .= "\n";
}

$prompt .= "上記のデータを踏まえて、この個別支援計画がどのような根拠に基づいて作成されたかを説明する文書を作成してください。\n";
$prompt .= "見出しをつけず、自然な文章で記述してください。";

// ChatGPT API呼び出し
$apiKey = getenv('CHATGPT_API_KEY') ?: ($_ENV['CHATGPT_API_KEY'] ?? '');

// .envファイルから読み込み（開発環境用）
if (empty($apiKey)) {
    $envPath = __DIR__ . '/../../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'CHATGPT_API_KEY=') === 0) {
                $apiKey = substr($line, strlen('CHATGPT_API_KEY='));
                break;
            }
        }
    }
}

if (empty($apiKey)) {
    $_SESSION['error'] = 'ChatGPT APIキーが設定されていません。';
    header("Location: kobetsu_plan_basis.php?plan_id=$planId");
    exit;
}

try {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'あなたは児童発達支援・放課後等デイサービスの専門家です。保護者に対して丁寧で分かりやすい説明を行います。'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_completion_tokens' => 2000,
            'temperature' => 0.7
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('API呼び出しに失敗しました（HTTP ' . $httpCode . '）');
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('APIからの応答が不正です');
    }

    $basisContent = $result['choices'][0]['message']['content'];

    // データベースに保存
    $stmt = $pdo->prepare("
        UPDATE individual_support_plans
        SET basis_content = ?,
            basis_generated_at = NOW(),
            source_period_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$basisContent, $period['id'] ?? null, $planId]);

    $_SESSION['success'] = '全体所感を生成しました。';
    header("Location: kobetsu_plan_basis.php?plan_id=$planId");
    exit;

} catch (Exception $e) {
    error_log('全体所感生成エラー: ' . $e->getMessage());
    $_SESSION['error'] = '全体所感の生成に失敗しました: ' . $e->getMessage();
    header("Location: kobetsu_plan_basis.php?plan_id=$planId");
    exit;
}
