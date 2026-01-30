<?php
/**
 * 面談記録から「本人の願い」を自動生成
 * OpenAI APIを使用して面談記録の「児童の願い」を編集・整理
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/openai_helper.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo json_encode(['success' => false, 'error' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// POSTデータ取得
$studentId = $_POST['student_id'] ?? null;

if (!$studentId) {
    echo json_encode(['success' => false, 'error' => '生徒IDが指定されていません。']);
    exit;
}

try {
    // 生徒情報を取得（自分の教室のみ）
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND classroom_id = ?");
        $stmt->execute([$studentId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
    }
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('生徒が見つかりません、またはアクセス権限がありません。');
    }

    // 6か月以内の面談記録を取得（児童の願いがあるもの）
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    $stmt = $pdo->prepare("
        SELECT id, interview_date, child_wish, interview_content
        FROM student_interviews
        WHERE student_id = ?
        AND interview_date >= ?
        AND child_wish IS NOT NULL
        AND child_wish != ''
        ORDER BY interview_date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId, $sixMonthsAgo]);
    $interviews = $stmt->fetchAll();

    if (empty($interviews)) {
        throw new Exception('6か月以内に「児童の願い」が記録された面談記録がありません。');
    }

    // 面談記録の「児童の願い」を集約
    $wishesText = "";
    foreach ($interviews as $interview) {
        $date = date('Y年m月d日', strtotime($interview['interview_date']));
        $wishesText .= "【{$date}の面談】\n";
        $wishesText .= $interview['child_wish'] . "\n\n";
    }

    // OpenAI APIで「本人の願い」を生成
    $prompt = "あなたは発達支援・特別支援教育の専門スタッフです。以下は生徒との面談記録から抜粋した「児童の願い」です。これらの内容を整理・統合して、個別支援計画に記載する「本人の願い」として200〜300文字程度でまとめてください。

【生徒名】
" . $student['student_name'] . "

【面談記録からの「児童の願い」】
" . $wishesText . "

【作成の指針】
- 複数の面談記録がある場合は、共通するテーマや一貫した願いを中心にまとめる
- 児童の言葉や表現をできるだけ活かしながら、読みやすく整理する
- 抽象的すぎる表現は避け、具体的な願いや目標として記述する
- 肯定的で前向きな表現を心がける
- 「〜したい」「〜になりたい」「〜ができるようになりたい」などの表現を使う
- 本人の気持ちや希望を尊重した内容にする

「本人の願い」を200〜300文字程度の文章で記述してください（JSON不要、テキストのみ）：";

    $generatedWish = callOpenAI($prompt, 'gpt-5.2', 600);

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'wish' => trim($generatedWish)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
