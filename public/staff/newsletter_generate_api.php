<?php
/**
 * 施設通信AI生成API
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 0); // ブラウザには表示しない
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

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

    // 予定イベント情報を取得（スケジュール期間、自分の教室のみ）
    $stmt = $pdo->prepare("
        SELECT * FROM events
        WHERE event_date BETWEEN ? AND ? AND classroom_id = ?
        ORDER BY event_date
    ");
    $stmt->execute([$newsletter['schedule_start_date'], $newsletter['schedule_end_date'], $classroomId]);
    $events = $stmt->fetchAll();

    // 過去のイベント情報を取得（報告期間、自分の教室のみ）
    $stmt = $pdo->prepare("
        SELECT * FROM events
        WHERE event_date BETWEEN ? AND ? AND classroom_id = ?
        ORDER BY event_date
    ");
    $stmt->execute([$newsletter['report_start_date'], $newsletter['report_end_date'], $classroomId]);
    $pastEvents = $stmt->fetchAll();
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
        $stmt->execute([$classroomId]);
        $classroom = $stmt->fetch();
        if ($classroom) {
            $classroomName = $classroom['classroom_name'];
        }
    }

    // 施設通信設定を取得
    $settings = getNewsletterSettings($pdo, $classroomId);

    // コンテンツを生成
    $generatedContent = generateNewsletterContent($pdo, $newsletter, $activities, $events, $pastEvents, $classroomName, $classroomId, $settings);

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
 * 施設通信設定を取得
 */
function getNewsletterSettings($pdo, $classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM newsletter_settings WHERE classroom_id = ?");
    $stmt->execute([$classroomId]);
    $settings = $stmt->fetch();

    // デフォルト値
    if (!$settings) {
        $settings = [
            'show_facility_name' => 1,
            'show_logo' => 1,
            'show_greeting' => 1,
            'show_event_calendar' => 1,
            'calendar_format' => 'list',
            'show_event_details' => 1,
            'show_weekly_reports' => 1,
            'show_weekly_intro' => 1,
            'show_event_results' => 1,
            'show_requests' => 1,
            'show_others' => 1,
            'show_elementary_report' => 1,
            'show_junior_report' => 1,
            'default_requests' => '',
            'default_others' => '',
            'greeting_instructions' => '',
            'event_details_instructions' => '',
            'weekly_reports_instructions' => '',
            'weekly_intro_instructions' => '',
            'event_results_instructions' => '',
            'elementary_report_instructions' => '',
            'junior_report_instructions' => '',
            'custom_section_title' => '',
            'custom_section_content' => '',
            'show_custom_section' => 0,
        ];
    }

    return $settings;
}

/**
 * 通信コンテンツを生成
 */
function generateNewsletterContent($pdo, $newsletter, $activities, $events, $pastEvents, $classroomName, $classroomId, $settings = []) {
    $year = $newsletter['year'];
    $month = $newsletter['month'];

    // 支援案データを取得
    $supportPlans = getSupportPlansByClassroom($pdo, $classroomId);

    // 種別でフィルタリング
    $normalPlans = getNormalTypePlans($supportPlans);  // 通常活動用
    $eventPlans = getEventTypePlans($supportPlans);    // イベント用

    // 曜日別の支援案データを準備（通常活動のみ）
    $plansByDay = groupSupportPlansByDay($normalPlans);

    // 設定に基づいて各セクションを生成
    $greeting = '';
    $eventCalendar = '';
    $eventDetails = '';
    $weeklyReports = '';
    $eventResults = '';
    $requests = '';
    $others = '';
    $customSection = '';

    // 挨拶文
    if (!empty($settings['show_greeting'])) {
        $customInstructions = $settings['greeting_instructions'] ?? '';
        $greeting = generateSeasonalGreetingWithAI($year, $month, $classroomName, $customInstructions);
    }

    // イベントカレンダー
    if (!empty($settings['show_event_calendar'])) {
        $calendarFormat = $settings['calendar_format'] ?? 'list';
        $eventCalendar = generateEventCalendar($events, $newsletter, $calendarFormat);
    }

    // イベント詳細
    if (!empty($settings['show_event_details'])) {
        $customInstructions = $settings['event_details_instructions'] ?? '';
        $eventDetails = generateEventDetailsWithAI($events, $classroomName, $customInstructions);
    }

    // 活動紹介まとめ（通常活動の支援案のみ使用）
    if (!empty($settings['show_weekly_reports'])) {
        $customInstructions = $settings['weekly_reports_instructions'] ?? '';
        $weeklyReports = generateActivitySummaryWithAI($activities, $normalPlans, $classroomName, $customInstructions);
    }

    // 曜日別活動紹介
    $weeklyIntro = '';
    if (!empty($settings['show_weekly_intro'])) {
        $customInstructions = $settings['weekly_intro_instructions'] ?? '';
        $weeklyIntro = generateWeeklyIntroWithAI($plansByDay, $classroomName, $customInstructions);
    }

    // イベント結果報告（イベント種別の支援案を優先使用）
    if (!empty($settings['show_event_results'])) {
        $customInstructions = $settings['event_results_instructions'] ?? '';
        $eventResults = generateEventResultsWithAI($pdo, $pastEvents, $activities, $eventPlans, $classroomName, $customInstructions);
    }

    // 施設からのお願い
    if (!empty($settings['show_requests'])) {
        $defaultRequests = $settings['default_requests'] ?? '';
        $requests = !empty($defaultRequests) ? $defaultRequests : "※ 保護者の皆様へのお願い事項を記入してください。";
    }

    // その他
    if (!empty($settings['show_others'])) {
        $defaultOthers = $settings['default_others'] ?? '';
        $others = !empty($defaultOthers) ? $defaultOthers : "※ その他の連絡事項があれば記入してください。";
    }

    // カスタムセクション
    if (!empty($settings['show_custom_section']) && !empty($settings['custom_section_title'])) {
        $customSection = $settings['custom_section_content'] ?? '';
    }

    // 小学生の活動報告
    $elementaryReport = '';
    if (!empty($settings['show_elementary_report'])) {
        $customInstructions = $settings['elementary_report_instructions'] ?? '';
        $elementaryReport = generateGradeActivityReportWithAI($pdo, $newsletter, $activities, $supportPlans, $classroomName, 'elementary', $customInstructions);
    }

    // 中学生の活動報告
    $juniorReport = '';
    if (!empty($settings['show_junior_report'])) {
        $customInstructions = $settings['junior_report_instructions'] ?? '';
        $juniorReport = generateGradeActivityReportWithAI($pdo, $newsletter, $activities, $supportPlans, $classroomName, 'junior_high', $customInstructions);
    }

    return [
        'greeting' => $greeting,
        'event_calendar' => $eventCalendar,
        'event_details' => $eventDetails,
        'weekly_reports' => $weeklyReports,
        'weekly_intro' => $weeklyIntro,
        'event_results' => $eventResults,
        'requests' => $requests,
        'others' => $others,
        'elementary_report' => $elementaryReport,
        'junior_report' => $juniorReport,
        'custom_section_title' => $settings['custom_section_title'] ?? '',
        'custom_section' => $customSection
    ];
}

/**
 * 教室の支援案を取得
 */
function getSupportPlansByClassroom($pdo, $classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM support_plans
        WHERE classroom_id = ?
        ORDER BY plan_type, day_of_week, activity_name
    ");
    $stmt->execute([$classroomId]);
    return $stmt->fetchAll();
}

/**
 * 支援案を種別でフィルタリング
 */
function filterSupportPlansByType($supportPlans, $type) {
    return array_filter($supportPlans, function($plan) use ($type) {
        return ($plan['plan_type'] ?? 'normal') === $type;
    });
}

/**
 * イベント種別の支援案を取得
 */
function getEventTypePlans($supportPlans) {
    return filterSupportPlansByType($supportPlans, 'event');
}

/**
 * 通常活動の支援案を取得
 */
function getNormalTypePlans($supportPlans) {
    return filterSupportPlansByType($supportPlans, 'normal');
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
function generateEventCalendar($events, $newsletter, $format = 'list') {
    $daysOfWeek = ['日', '月', '火', '水', '木', '金', '土'];

    if ($format === 'table') {
        return generateEventCalendarTable($events, $newsletter, $daysOfWeek);
    }

    // 一覧形式
    $calendar = "";
    foreach ($events as $event) {
        $timestamp = strtotime($event['event_date']);
        $day = date('j', $timestamp);
        $dayOfWeek = $daysOfWeek[date('w', $timestamp)];
        $calendar .= sprintf("%d日(%s) %s\n", $day, $dayOfWeek, $event['event_name']);
    }

    return $calendar;
}

/**
 * カレンダーを表形式で生成
 */
function generateEventCalendarTable($events, $newsletter, $daysOfWeek) {
    $year = $newsletter['year'];
    $month = $newsletter['month'];

    // イベントを日付でインデックス化
    $eventsByDay = [];
    foreach ($events as $event) {
        $day = (int)date('j', strtotime($event['event_date']));
        if (!isset($eventsByDay[$day])) {
            $eventsByDay[$day] = [];
        }
        $eventsByDay[$day][] = $event['event_name'];
    }

    // 月の情報
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = (int)date('t', $firstDay);
    $startWeekday = (int)date('w', $firstDay);

    // 祝日情報を取得（簡易版）
    $holidays = getJapaneseHolidays($year, $month);

    // テキスト形式のカレンダー表を生成
    $table = "【{$year}年{$month}月のカレンダー】\n\n";
    $table .= "┌────┬────┬────┬────┬────┬────┬────┐\n";
    $table .= "│ 日 │ 月 │ 火 │ 水 │ 木 │ 金 │ 土 │\n";
    $table .= "├────┼────┼────┼────┼────┼────┼────┤\n";

    $currentDay = 1;
    $weekRow = "";

    // 最初の週の空白セル
    for ($i = 0; $i < $startWeekday; $i++) {
        $weekRow .= "│    ";
    }

    // 日付を埋める
    while ($currentDay <= $daysInMonth) {
        $weekday = ($startWeekday + $currentDay - 1) % 7;

        // 日付表示（2桁に揃える）
        $dayStr = str_pad($currentDay, 2, ' ', STR_PAD_LEFT);

        // 休日・祝日マーク
        $mark = '';
        if ($weekday === 0) {
            $mark = '★'; // 日曜
        } elseif ($weekday === 6) {
            $mark = '☆'; // 土曜
        } elseif (isset($holidays[$currentDay])) {
            $mark = '◎'; // 祝日
        }

        // イベントマーク
        if (isset($eventsByDay[$currentDay])) {
            $mark = '●'; // イベントあり
        }

        $weekRow .= "│" . $dayStr . $mark . " ";

        // 週末で改行
        if ($weekday === 6) {
            $table .= $weekRow . "│\n";
            if ($currentDay < $daysInMonth) {
                $table .= "├────┼────┼────┼────┼────┼────┼────┤\n";
            }
            $weekRow = "";
        }

        $currentDay++;
    }

    // 最後の週の残りを埋める
    if ($weekRow !== "") {
        $remaining = 7 - (($startWeekday + $daysInMonth) % 7);
        if ($remaining < 7) {
            for ($i = 0; $i < $remaining; $i++) {
                $weekRow .= "│    ";
            }
        }
        $table .= $weekRow . "│\n";
    }

    $table .= "└────┴────┴────┴────┴────┴────┴────┘\n\n";

    // 凡例
    $table .= "【凡例】●:イベント ★:日曜 ☆:土曜 ◎:祝日\n\n";

    // イベント・祝日一覧
    $table .= "【今月の予定】\n";

    // 祝日を追加
    foreach ($holidays as $day => $name) {
        $weekday = $daysOfWeek[date('w', mktime(0, 0, 0, $month, $day, $year))];
        $table .= sprintf("%d日(%s) %s【祝日】\n", $day, $weekday, $name);
    }

    // イベントを追加
    foreach ($events as $event) {
        $timestamp = strtotime($event['event_date']);
        $day = date('j', $timestamp);
        $weekday = $daysOfWeek[date('w', $timestamp)];
        $table .= sprintf("%d日(%s) %s\n", $day, $weekday, $event['event_name']);
    }

    return $table;
}

/**
 * 日本の祝日を取得（簡易版）
 */
function getJapaneseHolidays($year, $month) {
    $holidays = [];

    // 固定祝日
    $fixedHolidays = [
        1 => [1 => '元日', 11 => '成人の日'],
        2 => [11 => '建国記念の日', 23 => '天皇誕生日'],
        3 => [21 => '春分の日'], // 概算
        4 => [29 => '昭和の日'],
        5 => [3 => '憲法記念日', 4 => 'みどりの日', 5 => 'こどもの日'],
        7 => [20 => '海の日'], // 第3月曜
        8 => [11 => '山の日'],
        9 => [16 => '敬老の日', 23 => '秋分の日'], // 概算
        10 => [14 => 'スポーツの日'], // 第2月曜
        11 => [3 => '文化の日', 23 => '勤労感謝の日'],
    ];

    if (isset($fixedHolidays[$month])) {
        $holidays = $fixedHolidays[$month];
    }

    return $holidays;
}

/**
 * イベント詳細をAIで魅力的に生成
 */
function generateEventDetailsWithAI($events, $classroomName, $customInstructions = '') {
    if (empty($events)) {
        return "※ 予定期間内のイベントがありません。";
    }

    require_once __DIR__ . '/../../includes/env.php';

    $eventList = "";
    foreach ($events as $event) {
        $eventList .= sprintf("・%s（%s）: %s\n",
            $event['event_name'],
            date('m月d日', strtotime($event['event_date'])),
            $event['event_description'] ?? '詳細未定'
        );
    }

    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

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
{$additionalInstructions}

文章のみを出力してください。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * イベント結果報告をAIで生成（過去イベント・連絡帳・イベント種別支援案から）
 */
function generateEventResultsWithAI($pdo, $pastEvents, $activities, $eventPlans, $classroomName, $customInstructions = '') {
    if (empty($pastEvents)) {
        return "※ 報告期間内に実施したイベントはありませんでした。";
    }

    require_once __DIR__ . '/../../includes/env.php';

    $results = "";
    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

    foreach ($pastEvents as $event) {
        $eventDate = $event['event_date'];
        $eventName = $event['event_name'];
        $eventDescription = $event['event_description'] ?? '';

        // イベント日の連絡帳データを取得
        $dailyRecordsForEvent = array_filter($activities, function($activity) use ($eventDate) {
            return $activity['record_date'] === $eventDate;
        });

        // 連絡帳の活動内容をまとめる
        $activitySummary = "";
        foreach ($dailyRecordsForEvent as $record) {
            $activitySummary .= "【{$record['activity_name']}】\n";
            if (!empty($record['common_activity'])) {
                $activitySummary .= $record['common_activity'] . "\n";
            }
            if (!empty($record['participants'])) {
                $activitySummary .= "参加: " . $record['participants'] . "\n";
            }
            $activitySummary .= "\n";
        }

        // イベント種別の支援案から関連するものを探す（イベント名、活動名、日付で検索）
        $relatedPlans = array_filter($eventPlans, function($plan) use ($eventName, $eventDate) {
            // イベント名で一致
            $nameMatch = stripos($plan['activity_name'], $eventName) !== false ||
                         stripos($eventName, $plan['activity_name']) !== false;
            // 日付で一致
            $dateMatch = isset($plan['activity_date']) && $plan['activity_date'] === $eventDate;
            return $nameMatch || $dateMatch;
        });

        $planInfo = "";
        if (!empty($relatedPlans)) {
            $planInfo .= "【イベント支援案より】\n";
            foreach ($relatedPlans as $plan) {
                $planInfo .= "活動名: " . $plan['activity_name'] . "\n";
                if (!empty($plan['activity_purpose'])) {
                    $planInfo .= "目的: " . $plan['activity_purpose'] . "\n";
                }
                if (!empty($plan['activity_content'])) {
                    $planInfo .= "内容: " . $plan['activity_content'] . "\n";
                }
                if (!empty($plan['five_domains_consideration'])) {
                    $planInfo .= "五領域への配慮: " . $plan['five_domains_consideration'] . "\n";
                }
            }
        }

        // AIでイベント結果報告を生成
        $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のイベント実施データから、保護者に向けた温かい結果報告文を作成してください。

【イベント情報】
イベント名: {$eventName}
実施日: {$eventDate}
イベント概要: {$eventDescription}

【当日の連絡帳記録】
{$activitySummary}

【関連する支援案】
{$planInfo}

【要件】
- イベントの実施結果と子どもたちの様子を具体的に伝える
- 子どもたちの楽しんでいる様子、成長、頑張りを中心に記述
- 保護者が読んで嬉しくなるような温かい文章
- 200〜300字程度
- 「です・ます」調で丁寧な表現
- 連絡帳データがない場合は、イベント概要から想像して一般的な結果報告を作成
{$additionalInstructions}

文章のみを出力してください（見出しは不要です）。
PROMPT;

        $eventReport = callChatGPTAPI($prompt);

        $formattedDate = date('n月j日', strtotime($eventDate));
        $results .= "◆ {$eventName}（{$formattedDate}）\n";
        $results .= $eventReport . "\n\n";
    }

    return $results;
}

/**
 * 学年別の活動報告をAIで生成（連絡帳・支援案から）
 */
function generateGradeActivityReportWithAI($pdo, $newsletter, $activities, $supportPlans, $classroomName, $gradeLevel, $customInstructions = '') {
    require_once __DIR__ . '/../../includes/env.php';

    // 学年ラベルと対象グレードの設定
    $gradeConfig = [
        'elementary' => [
            'label' => '小学生',
            'targets' => ['elementary']
        ],
        'junior_high' => [
            'label' => '中学生・高校生',
            'targets' => ['junior_high', 'high_school']
        ],
        'high_school' => [
            'label' => '高校生',
            'targets' => ['high_school']
        ]
    ];

    $config = $gradeConfig[$gradeLevel] ?? ['label' => $gradeLevel, 'targets' => [$gradeLevel]];
    $gradeLabel = $config['label'];
    $targetGrades = $config['targets'];

    // 報告期間の連絡帳データを整理
    $activitySummary = "";
    $activityCount = 0;
    foreach ($activities as $activity) {
        if (!empty($activity['common_activity'])) {
            $activitySummary .= "【{$activity['record_date']} {$activity['activity_name']}】\n";
            $activitySummary .= $activity['common_activity'] . "\n\n";
            $activityCount++;
        }
    }

    if ($activityCount === 0) {
        return "※ 報告期間内の活動記録がありません。";
    }

    // 該当学年向けの支援案を取得（target_gradeフィールドを使用）
    $gradePlans = array_filter($supportPlans, function($plan) use ($targetGrades) {
        // 対象学年が設定されている場合はフィルタリング
        if (!empty($plan['target_grade'])) {
            // SET型なのでカンマ区切りの可能性がある
            $planGrades = explode(',', $plan['target_grade']);
            foreach ($targetGrades as $target) {
                if (in_array($target, $planGrades)) {
                    return true;
                }
            }
            return false;
        }
        return true; // 対象学年未設定の場合は含める（全年齢対象）
    });

    $planInfo = "";
    foreach ($gradePlans as $plan) {
        $planInfo .= "【{$plan['activity_name']}】\n";
        if (!empty($plan['activity_purpose'])) {
            $planInfo .= "目的: " . $plan['activity_purpose'] . "\n";
        }
        if (!empty($plan['activity_content'])) {
            $planInfo .= "内容: " . $plan['activity_content'] . "\n";
        }
        $planInfo .= "\n";
    }

    $reportStart = $newsletter['report_start_date'];
    $reportEnd = $newsletter['report_end_date'];
    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

    $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下のデータを参考に、{$gradeLabel}向けの活動報告を作成してください。

【報告期間】
{$reportStart} ～ {$reportEnd}

【期間中の活動記録（連絡帳より）】
{$activitySummary}

【関連する支援案】
{$planInfo}

【要件】
- {$gradeLabel}の子どもたちがどのような活動に取り組んだかをまとめる
- 子どもたちの様子、成長、楽しんでいるエピソードを具体的に
- 保護者が読んで嬉しくなるような温かい文章
- 300〜500字程度
- 「です・ます」調で丁寧な表現
- 活動内容を箇条書きではなく、流れのある文章で
{$additionalInstructions}

文章のみを出力してください（見出しは不要です）。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * 期間内の活動を時系列でまとめてAIで生成（参加したくなる文言）
 */
function generateActivitySummaryWithAI($activities, $supportPlans, $classroomName, $customInstructions = '') {
    require_once __DIR__ . '/../../includes/env.php';

    if (empty($activities) && empty($supportPlans)) {
        return "※ 活動記録と支援案が登録されていません。活動内容を手動で入力してください。";
    }

    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

    // 連絡帳の活動データを時系列でまとめる
    $activitiesList = "";
    if (!empty($activities)) {
        // 日付でソート（既にソートされているはずだが念のため）
        usort($activities, function($a, $b) {
            return strcmp($a['record_date'], $b['record_date']);
        });

        foreach ($activities as $activity) {
            $date = date('n/j', strtotime($activity['record_date']));
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($activity['record_date']))];
            $activitiesList .= "【{$date}（{$dayOfWeek}）{$activity['activity_name']}】\n";
            if (!empty($activity['common_activity'])) {
                $activitiesList .= $activity['common_activity'] . "\n";
            }
            if (!empty($activity['participants'])) {
                $activitiesList .= "参加者: " . $activity['participants'] . "\n";
            }
            $activitiesList .= "\n";
        }
    }

    // 支援案の情報をまとめる（参考として）
    $plansList = "";
    if (!empty($supportPlans)) {
        $uniqueActivities = [];
        foreach ($supportPlans as $plan) {
            $key = $plan['activity_name'];
            if (!isset($uniqueActivities[$key])) {
                $uniqueActivities[$key] = $plan;
            }
        }
        foreach ($uniqueActivities as $plan) {
            $plansList .= "・{$plan['activity_name']}";
            if (!empty($plan['activity_purpose'])) {
                $plansList .= "（{$plan['activity_purpose']}）";
            }
            $plansList .= "\n";
        }
    }

    $prompt = <<<PROMPT
あなたは{$classroomName}の施設通信を作成しています。
以下の期間内の活動記録を、保護者に向けて「新しく参加したくなる」魅力的な文章にまとめてください。

【期間内の活動記録（時系列）】
{$activitiesList}

【定期的な活動プログラム（参考）】
{$plansList}

【要件】
- 活動を時系列でストーリー性を持たせて紹介する
- 子どもたちが楽しんでいる様子、成長している様子を具体的に伝える
- 「うちの子も参加させたい」「こんな活動があるなら通わせたい」と思わせる魅力的な表現
- 実際の活動の様子を活き活きと描写する
- まだ参加していない家庭が「新規に参加したい」と思えるような内容
- 全体で500〜800字程度
- 「です・ます」調で丁寧かつ温かい表現
- 専門用語は避け、分かりやすい言葉で
{$additionalInstructions}

文章のみを出力してください。見出しを入れる場合は「■」を使用してください。
PROMPT;

    return callChatGPTAPI($prompt);
}

/**
 * 曜日別活動紹介をAIで生成（その曜日に参加していない生徒向け）
 */
function generateWeeklyIntroWithAI($plansByDay, $classroomName, $customInstructions = '') {
    require_once __DIR__ . '/../../includes/env.php';

    if (empty($plansByDay)) {
        return "※ 支援案が登録されていません。活動内容を手動で入力してください。";
    }

    $reports = "";
    $dayOrder = ['月', '火', '水', '木', '金', '土'];
    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

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
以下の{$day}曜日の支援案（活動計画）を、「まだ{$day}曜日に参加していない生徒と保護者」に向けて、参加したくなるような魅力的な紹介文を作成してください。

【{$day}曜日の支援案】
{$plansList}

【要件】
- 「{$day}曜日はこんな活動をしています」という形式で紹介
- その曜日に参加することで得られる体験・成長を具体的に伝える
- 「ぜひ{$day}曜日も来てみてください」と思わせる内容
- 参加していない生徒・保護者が「うちの子も参加させたい」と思える表現
- 300字程度
- 「です・ます」調で丁寧かつ温かい表現
- 専門用語は避け、分かりやすい言葉で
{$additionalInstructions}

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
function generateSeasonalGreetingWithAI($year, $month, $classroomName, $customInstructions = '') {
    require_once __DIR__ . '/../../includes/env.php';

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
    $additionalInstructions = !empty($customInstructions) ? "\n【追加指示】\n{$customInstructions}" : '';

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
{$additionalInstructions}

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
        'model' => 'gpt-5.2',
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
        'max_completion_tokens' => 1500
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
