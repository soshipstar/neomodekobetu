<?php
/**
 * 施設通信AI生成API
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 0); // ブラウザには表示しない
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$currentUser = getCurrentUser();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $newsletterId = $input['newsletter_id'] ?? null;

    if (!$newsletterId) {
        throw new Exception('通信IDが指定されていません');
    }

    // 通信情報を取得
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$newsletterId]);
    $newsletter = $stmt->fetch();

    if (!$newsletter) {
        throw new Exception('通信が見つかりません');
    }

    // 指定期間の連絡帳データを取得
    $stmt = $pdo->prepare("
        SELECT
            dr.id,
            dr.activity_name,
            dr.common_activity,
            dr.record_date,
            u.full_name as staff_name,
            GROUP_CONCAT(DISTINCT s.student_name SEPARATOR ', ') as participants
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        LEFT JOIN student_records sr ON dr.id = sr.daily_record_id
        LEFT JOIN students s ON sr.student_id = s.id
        WHERE dr.record_date BETWEEN ? AND ?
        GROUP BY dr.id
        ORDER BY dr.record_date, dr.created_at
    ");
    $stmt->execute([$newsletter['report_start_date'], $newsletter['report_end_date']]);
    $activities = $stmt->fetchAll();

    // 教室名を取得
    $classroomName = "教室";
    $classroomId = $_SESSION['classroom_id'] ?? null;

    // イベント情報を取得（自分の教室のみ）
    $stmt = $pdo->prepare("
        SELECT * FROM events
        WHERE event_date BETWEEN ? AND ? AND classroom_id = ?
        ORDER BY event_date
    ");
    $stmt->execute([$newsletter['schedule_start_date'], $newsletter['schedule_end_date'], $classroomId]);
    $events = $stmt->fetchAll();
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
        $stmt->execute([$classroomId]);
        $classroom = $stmt->fetch();
        if ($classroom) {
            $classroomName = $classroom['classroom_name'];
        }
    }

    // コンテンツを生成
    $generatedContent = generateNewsletterContent($pdo, $newsletter, $activities, $events, $classroomName, $classroomId);

    echo json_encode([
        'success' => true,
        'data' => $generatedContent
    ]);

} catch (Exception $e) {
    error_log("Newsletter generate error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log("Newsletter generate fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'システムエラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * 通信コンテンツを生成
 */
function generateNewsletterContent($pdo, $newsletter, $activities, $events, $classroomName, $classroomId) {
    $year = $newsletter['year'];
    $month = $newsletter['month'];

    // 支援案データを取得
    $supportPlans = getSupportPlansByClassroom($pdo, $classroomId);

    // イベントカレンダーを生成
    $eventCalendar = generateEventCalendar($events);

    // イベント詳細をAIで魅力的に生成
    $eventDetails = generateEventDetailsWithAI($events, $classroomName);

    // 曜日別の支援案データを準備
    $plansByDay = groupSupportPlansByDay($supportPlans);

    // AIで各曜日の活動紹介を生成（参加したくなるような文言）
    $weeklyReports = generateWeeklyReportsFromPlansWithAI($plansByDay, $classroomName);

    // あいさつ文をAIで生成（季節のあいさつ）
    $greeting = generateSeasonalGreetingWithAI($year, $month, $classroomName);

    // イベント結果報告、施設からのお願い、その他は手動入力を促す
    $eventResults = "※ 実施したイベントの結果報告を記入してください。";
    $requests = "※ 保護者の皆様へのお願い事項を記入してください。";
    $others = "※ その他の連絡事項があれば記入してください。";

    return [
        'greeting' => $greeting,
        'event_calendar' => $eventCalendar,
        'event_details' => $eventDetails,
        'weekly_reports' => $weeklyReports,
        'event_results' => $eventResults,
        'requests' => $requests,
        'others' => $others
    ];
}

/**
 * 教室の支援案を取得
 */
function getSupportPlansByClassroom($pdo, $classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM support_plans
        WHERE classroom_id = ?
        ORDER BY day_of_week, activity_name
    ");
    $stmt->execute([$classroomId]);
    return $stmt->fetchAll();
}

/**
 * 支援案を曜日別にグループ化
 */
function groupSupportPlansByDay($supportPlans) {
    $plansByDay = [];
    $dayMapping = [
        'monday' => '月',
        'tuesday' => '火',
        'wednesday' => '水',
        'thursday' => '木',
        'friday' => '金',
        'saturday' => '土',
        'sunday' => '日'
    ];

    foreach ($supportPlans as $plan) {
        if (empty($plan['day_of_week'])) continue;

        // day_of_weekはカンマ区切りの可能性がある
        $days = explode(',', $plan['day_of_week']);
        foreach ($days as $day) {
            $day = trim($day);
            $dayName = $dayMapping[$day] ?? $day;

            if (!isset($plansByDay[$dayName])) {
                $plansByDay[$dayName] = [];
            }
            $plansByDay[$dayName][] = $plan;
        }
    }

    return $plansByDay;
}

/**
 * イベントカレンダーを生成
 */
function generateEventCalendar($events) {
    $calendar = "";
    $daysOfWeek = ['日', '月', '火', '水', '木', '金', '土'];

    foreach ($events as $event) {
        $timestamp = strtotime($event['event_date']);
        $day = date('j', $timestamp);
        $dayOfWeek = $daysOfWeek[date('w', $timestamp)];
        $calendar .= sprintf("%d日(%s) %s\n", $day, $dayOfWeek, $event['event_name']);
    }

    return $calendar;
}

/**
 * イベント詳細をAIで魅力的に生成
 */
function generateEventDetailsWithAI($events, $classroomName) {
    if (empty($events)) {
        return "※ 予定期間内のイベントがありません。";
    }

    require_once __DIR__ . '/../includes/env.php';

    $eventList = "";
    foreach ($events as $event) {
        $eventList .= sprintf("・%s（%s）: %s\n",
            $event['event_name'],
            date('m月d日', strtotime($event['event_date'])),
            $event['event_description'] ?? '詳細未定'
        );
    }

    $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のイベント情報を、保護者に魅力的に伝え、参加・申し込みを促す文章にしてください。

【イベント一覧】
{$eventList}

【要件】
- 各イベントについて、内容の魅力を伝える
- 子どもたちがどのような体験ができるか想像させる
- 参加や申し込みを促す温かい文言を入れる
- 各イベント150〜200字程度
- 「です・ます」調で丁寧な表現
- イベントごとに「◆ イベント名（日付）」の見出しをつける

文章のみを出力してください。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * 支援案から曜日別活動紹介をAIで生成（参加したくなる文言）
 */
function generateWeeklyReportsFromPlansWithAI($plansByDay, $classroomName) {
    require_once __DIR__ . '/../includes/env.php';

    if (empty($plansByDay)) {
        return "※ 支援案が登録されていません。活動内容を手動で入力してください。";
    }

    $reports = "";
    $dayOrder = ['月', '火', '水', '木', '金', '土'];

    foreach ($dayOrder as $day) {
        if (!isset($plansByDay[$day]) || empty($plansByDay[$day])) {
            continue;
        }

        $plans = $plansByDay[$day];

        // 支援案の情報をまとめる
        $plansList = "";
        foreach ($plans as $plan) {
            $plansList .= sprintf("【%s】\n", $plan['activity_name']);
            if (!empty($plan['activity_purpose'])) {
                $plansList .= "目的: " . $plan['activity_purpose'] . "\n";
            }
            if (!empty($plan['activity_content'])) {
                $plansList .= "内容: " . $plan['activity_content'] . "\n";
            }
            if (!empty($plan['five_domains_consideration'])) {
                $plansList .= "五領域への配慮: " . $plan['five_domains_consideration'] . "\n";
            }
            $plansList .= "\n";
        }

        $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下の{$day}曜日の支援案（活動計画）を、保護者に向けて「参加したくなる」魅力的な文章にまとめてください。

【{$day}曜日の支援案】
{$plansList}

【要件】
- 各活動の目的や内容を分かりやすく伝える
- 子どもたちがどのような体験・成長ができるか想像させる
- 保護者が「参加させたい」と思えるような魅力的な表現
- 各曜日200〜300字程度
- 「です・ます」調で丁寧かつ温かい表現
- 専門用語は避け、分かりやすい言葉で

文章のみを出力してください（見出しは不要です）。
PROMPT;

        $summary = callChatGPTAPI($prompt);

        $reports .= "■ {$day}曜日\n";
        $reports .= $summary . "\n\n";
    }

    return $reports;
}

/**
 * 季節のあいさつ文をAIで生成
 */
function generateSeasonalGreetingWithAI($year, $month, $classroomName) {
    require_once __DIR__ . '/../includes/env.php';

    // 季節を判定
    $season = match(true) {
        $month >= 3 && $month <= 5 => '春',
        $month >= 6 && $month <= 8 => '夏',
        $month >= 9 && $month <= 11 => '秋',
        default => '冬'
    };

    // 月の特徴
    $monthFeatures = [
        1 => '新年を迎え、心新たにスタートする',
        2 => '節分や立春を迎え、少しずつ春の訪れを感じる',
        3 => '卒業・進級の季節を迎え、子どもたちの成長を実感する',
        4 => '新年度がスタートし、新しい出会いと挑戦の',
        5 => '新緑が美しく、爽やかな風が心地よい',
        6 => '梅雨の季節を迎え、室内活動も充実する',
        7 => '夏本番を迎え、プールや夏祭りなど楽しいイベントが盛りだくさんの',
        8 => '暑さの中でも元気いっぱいに活動する',
        9 => '実りの秋を迎え、運動会やお月見など行事が楽しみな',
        10 => 'ハロウィンや秋の遠足など、楽しいイベントが続く',
        11 => '紅葉が美しく、落ち着いて活動に取り組める',
        12 => 'クリスマスや年末行事で賑わう、一年の締めくくりの'
    ];

    $monthFeature = $monthFeatures[$month] ?? '';

    $prompt = <<<PROMPT
あなたは{$classroomName}の{$year}年{$month}月の施設通信のあいさつ文を書いています。

【情報】
- 教室名: {$classroomName}
- 対象月: {$year}年{$month}月
- 季節: {$season}
- 月の特徴: {$monthFeature}

【要件】
- {$month}月にふさわしい季節のあいさつで始める
- 教室名「{$classroomName}」を自然に含める
- 保護者への日頃の感謝を述べる
- 今月の活動への期待や意気込みを簡潔に
- 150〜200字程度
- 「です・ます」調で丁寧かつ温かい表現
- 時候の挨拶は自然で読みやすいものに

あいさつ文のみを出力してください（見出しや前置きは不要です）。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * ChatGPT APIを呼び出す
 */
function callChatGPTAPI($prompt) {
    $apiKey = env('OPENAI_API_KEY', env('CHATGPT_API_KEY', ''));

    if (empty($apiKey)) {
        // APIキーがない場合は簡易的な文章を返す
        return "※ OpenAI APIキーが設定されていないため、自動生成できません。手動で入力してください。";
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧で、参加したくなるような魅力的な文章を書きます。専門用語は避け、分かりやすい表現を心がけます。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1500
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("ChatGPT API cURL Error: " . $error);
        return "※ AI生成中にエラーが発生しました（接続エラー）。手動で入力してください。";
    }

    if ($httpCode !== 200) {
        error_log("ChatGPT API HTTP Error: " . $httpCode . " Response: " . $response);
        return "※ AI生成中にエラーが発生しました（HTTP $httpCode）。手動で入力してください。";
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    }

    error_log("ChatGPT API Invalid Response: " . $response);
    return "※ AI生成に失敗しました。手動で入力してください。";
}
