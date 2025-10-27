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

    // イベント情報を取得
    $stmt = $pdo->prepare("
        SELECT * FROM events
        WHERE event_date BETWEEN ? AND ?
        ORDER BY event_date
    ");
    $stmt->execute([$newsletter['schedule_start_date'], $newsletter['schedule_end_date']]);
    $events = $stmt->fetchAll();

    // 教室名を取得
    $classroomName = "教室";
    $classroomId = $_SESSION['classroom_id'] ?? null;
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
        $stmt->execute([$classroomId]);
        $classroom = $stmt->fetch();
        if ($classroom) {
            $classroomName = $classroom['classroom_name'];
        }
    }

    // コンテンツを生成
    $generatedContent = generateNewsletterContent($newsletter, $activities, $events, $classroomName);

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
function generateNewsletterContent($newsletter, $activities, $events, $classroomName) {
    $year = $newsletter['year'];
    $month = $newsletter['month'];

    // イベントカレンダーを生成
    $eventCalendar = generateEventCalendar($events);

    // イベント詳細を生成（各イベント100字程度）
    $eventDetails = generateEventDetails($events);

    // 曜日別の活動データを準備
    $activitiesByDay = groupActivitiesByDay($activities);

    // AIで各曜日の活動報告を生成
    $weeklyReports = generateWeeklyReportsWithAI($activitiesByDay, $year, $month, $classroomName);

    // あいさつ文をAIで生成
    $greeting = generateGreetingWithAI($year, $month, $classroomName, $activitiesByDay, $events);

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
 * イベント詳細を生成（各100字程度）
 */
function generateEventDetails($events) {
    $details = "";

    foreach ($events as $event) {
        $details .= sprintf("◆ %s（%s）\n",
            $event['event_name'],
            date('m月d日', strtotime($event['event_date']))
        );

        if (!empty($event['event_description'])) {
            // 100字程度に制限
            $description = mb_substr($event['event_description'], 0, 100);
            if (mb_strlen($event['event_description']) > 100) {
                $description .= "...";
            }
            $details .= $description . "\n\n";
        } else {
            $details .= "詳細は後日お知らせします。\n\n";
        }
    }

    return $details;
}

/**
 * 活動を曜日別にグループ化
 */
function groupActivitiesByDay($activities) {
    $activitiesByDay = [];
    $daysOfWeek = ['日', '月', '火', '水', '木', '金', '土'];

    foreach ($activities as $activity) {
        $dayIndex = date('w', strtotime($activity['record_date']));
        $dayName = $daysOfWeek[$dayIndex];

        if (!isset($activitiesByDay[$dayName])) {
            $activitiesByDay[$dayName] = [];
        }

        $activitiesByDay[$dayName][] = [
            'date' => $activity['record_date'],
            'activity_name' => $activity['activity_name'],
            'common_activity' => $activity['common_activity']
        ];
    }

    return $activitiesByDay;
}

/**
 * AIを使って各曜日の活動報告を生成
 */
function generateWeeklyReportsWithAI($activitiesByDay, $year, $month, $classroomName) {
    require_once __DIR__ . '/../includes/env.php';

    $reports = "";
    $dayOrder = ['月', '火', '水', '木', '金', '土', '日'];

    foreach ($dayOrder as $day) {
        if (!isset($activitiesByDay[$day]) || empty($activitiesByDay[$day])) {
            continue;
        }

        $dayActivities = $activitiesByDay[$day];

        // 各日の「本日の活動（共通）」を抽出
        $commonActivities = [];
        foreach ($dayActivities as $activity) {
            if (!empty($activity['common_activity'])) {
                $commonActivities[] = $activity['common_activity'];
            }
        }

        if (empty($commonActivities)) {
            continue;
        }

        // AIでまとめる
        $prompt = buildWeeklyReportPrompt($day, $commonActivities, $classroomName);
        $summary = callChatGPTAPI($prompt);

        $reports .= "■ {$day}曜日\n";
        $reports .= $summary . "\n\n";
    }

    return $reports;
}

/**
 * 曜日別活動報告のプロンプトを作成
 */
function buildWeeklyReportPrompt($day, $commonActivities, $classroomName) {
    $activitiesList = implode("\n", array_map(function($act, $index) {
        return ($index + 1) . ". " . $act;
    }, $commonActivities, array_keys($commonActivities)));

    return <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下の{$day}曜日の活動記録を、保護者向けの施設通信用に200〜300字程度でまとめてください。

【{$day}曜日の活動記録】
{$activitiesList}

【まとめ方のポイント】
- 複数の活動を自然な文章でまとめてください
- 子どもたちの様子や成長が伝わるように書いてください
- 「です・ます」調で丁寧な表現を使用してください
- 活動の具体的な内容と、子どもたちがどのように取り組んだかを含めてください

まとめた文章のみを出力してください（見出しや前置きは不要です）。
PROMPT;
}

/**
 * あいさつ文をAIで生成
 */
function generateGreetingWithAI($year, $month, $classroomName, $activitiesByDay, $events) {
    require_once __DIR__ . '/../includes/env.php';

    // 活動の総数を取得
    $totalActivities = 0;
    foreach ($activitiesByDay as $activities) {
        $totalActivities += count($activities);
    }

    // イベント数
    $eventCount = count($events);

    $prompt = <<<PROMPT
あなたは{$classroomName}の{$year}年{$month}月の施設通信のあいさつ文を書いています。

【情報】
- 教室名: {$classroomName}
- 対象月: {$year}年{$month}月
- 報告期間中の活動数: {$totalActivities}件
- 今月の予定イベント数: {$eventCount}件

【要件】
- 保護者への挨拶と感謝を述べる
- この月の活動報告と予定について簡潔に触れる
- 子どもたちの成長への期待を表現する
- 150〜200字程度
- 「です・ます」調で丁寧な表現

あいさつ文のみを出力してください（見出しや前置きは不要です）。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * ChatGPT APIを呼び出す
 */
function callChatGPTAPI($prompt) {
    $apiKey = env('CHATGPT_API_KEY', '');

    if (empty($apiKey)) {
        // APIキーがない場合は簡易的な文章を返す
        return "※ ChatGPT APIキーが設定されていないため、自動生成できません。手動で入力してください。";
    }

    $url = 'https://api.openai.com/v1/chat/completions';

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは個別支援教育の経験豊富な教員です。保護者に向けて温かく丁寧な文章を書きます。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
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
