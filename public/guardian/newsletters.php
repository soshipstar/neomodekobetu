<?php
/**
 * 保護者向け施設通信閲覧ページ
 * PDF出力と同じレイアウトで表示
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 保護者のみアクセス可能
requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 教室情報を取得
$classroom = null;
$classroomStmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$classroomStmt->execute([$currentUser['id']]);
$classroom = $classroomStmt->fetch();

$classroomId = $classroom['id'] ?? null;
$classroomName = $classroom['classroom_name'] ?? '施設';

// 設定を取得
$settings = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM newsletter_settings WHERE classroom_id = ?");
    $stmt->execute([$classroomId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}
$calendarFormat = $settings['calendar_format'] ?? 'list';

// 発行済み通信を取得（自分の教室のもののみ、新しい順）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM newsletters
        WHERE status = 'published' AND classroom_id = ?
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE 1=0");
    $stmt->execute();
}
$newsletters = $stmt->fetchAll();

// 詳細表示用の通信
$selectedNewsletter = null;
$calendarEvents = [];
$calendarHolidays = [];

if (isset($_GET['id'])) {
    $newsletterId = $_GET['id'];
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT * FROM newsletters
            WHERE id = ? AND status = 'published' AND classroom_id = ?
        ");
        $stmt->execute([$newsletterId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE 1=0");
        $stmt->execute();
    }
    $selectedNewsletter = $stmt->fetch(PDO::FETCH_ASSOC);

    // カレンダー表形式の場合、イベントと休日を取得
    if ($selectedNewsletter && $calendarFormat === 'table' && $classroomId) {
        $scheduleStart = $selectedNewsletter['schedule_start_date'];
        $scheduleEnd = $selectedNewsletter['schedule_end_date'];

        $stmt = $pdo->prepare("
            SELECT id, event_date, event_name, event_color
            FROM events
            WHERE classroom_id = ? AND event_date BETWEEN ? AND ?
            ORDER BY event_date
        ");
        $stmt->execute([$classroomId, $scheduleStart, $scheduleEnd]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $event) {
            $date = $event['event_date'];
            if (!isset($calendarEvents[$date])) {
                $calendarEvents[$date] = [];
            }
            $calendarEvents[$date][] = [
                'name' => $event['event_name'],
                'color' => $event['event_color'] ?? '#6366f1'
            ];
        }

        $stmt = $pdo->prepare("
            SELECT holiday_date, holiday_name
            FROM holidays
            WHERE classroom_id = ? AND holiday_date BETWEEN ? AND ?
        ");
        $stmt->execute([$classroomId, $scheduleStart, $scheduleEnd]);
        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($holidays as $holiday) {
            $calendarHolidays[$holiday['holiday_date']] = $holiday['holiday_name'];
        }
    }
}

// ページ開始
$currentPage = 'newsletters';
renderPageStart('guardian', $currentPage, '施設通信', ['classroom' => $classroom]);
?>

<style>
/* 一覧表示用スタイル */
.newsletters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.newsletter-card {
    background: var(--apple-bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    text-decoration: none;
    color: inherit;
    display: block;
}

.newsletter-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.newsletter-card h3 {
    color: var(--apple-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.newsletter-meta {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.newsletter-date {
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    margin-top: var(--spacing-sm);
    padding-top: var(--spacing-sm);
    border-top: 1px solid var(--apple-gray-5);
}

/* 詳細表示用スタイル（PDF出力と同じ） */
.newsletter-detail {
    background: var(--apple-bg-primary);
    padding: 20px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    max-width: 800px;
    margin: 0 auto;
}

.detail-header {
    text-align: center;
    padding-bottom: 15px;
    margin-bottom: 20px;
    border-bottom: 3px solid #6366f1;
    position: relative;
}

.detail-header::after {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 3px;
    background: #8b5cf6;
}

.detail-facility {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 5px;
    letter-spacing: 2px;
}

.detail-title {
    font-size: 24px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 5px;
}

.detail-issue {
    font-size: 14px;
    color: #6366f1;
    font-weight: 600;
}

.detail-meta {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 10px;
}

/* あいさつ文 */
.greeting-box {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #6366f1;
}

.greeting-text {
    font-size: 14px;
    line-height: 1.9;
    color: #374151;
}

/* セクション */
.detail-section {
    margin-bottom: 20px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
    margin-bottom: 12px;
    font-size: 15px;
    font-weight: bold;
}

.section-icon {
    font-size: 16px;
}

.section-content {
    padding: 0 10px;
    font-size: 14px;
    line-height: 1.85;
    color: #374151;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* カレンダー一覧形式 */
.calendar-box {
    background: #fafafa;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 15px;
}

.calendar-content {
    font-family: monospace;
    font-size: 13px;
    line-height: 1.6;
    white-space: pre-wrap;
    color: #374151;
}

/* カレンダー表形式 */
.calendar-grid-container {
    background: #fafafa;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 15px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}

.calendar-day-header {
    text-align: center;
    padding: 6px 4px;
    font-weight: bold;
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    border-radius: 3px;
}

.calendar-day-header.sunday { color: #ef4444; }
.calendar-day-header.saturday { color: #3b82f6; }

.calendar-day {
    min-height: 60px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 4px;
    background: white;
    font-size: 11px;
}

.calendar-day.empty {
    background: #f9fafb;
    border-color: #f3f4f6;
}

.calendar-day.holiday {
    background: #fef2f2;
}

.calendar-day-num {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 3px;
}

.calendar-day-num.sunday { color: #ef4444; }
.calendar-day-num.saturday { color: #3b82f6; }

.calendar-event {
    font-size: 10px;
    line-height: 1.3;
    padding: 2px 4px;
    margin-bottom: 2px;
    border-radius: 3px;
    background: #e0e7ff;
    color: #3730a3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-holiday-name {
    font-size: 10px;
    color: #dc2626;
    font-weight: 500;
}

.calendar-month-title {
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    margin: 15px 0 10px 0;
    color: #374151;
}

/* お知らせセクション */
.notice-box {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 12px 16px;
}

.notice-content {
    font-size: 14px;
    line-height: 1.8;
    color: #92400e;
    white-space: pre-wrap;
}

/* 学年別セクション */
.grade-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.grade-section {
    background: #f9fafb;
    border-radius: 6px;
    padding: 12px;
    border: 1px solid #e5e7eb;
}

.grade-header {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: bold;
    color: #6366f1;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid #6366f1;
}

.grade-content {
    font-size: 13px;
    line-height: 1.8;
    color: #374151;
    white-space: pre-wrap;
}

/* フッター */
.detail-footer {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

.footer-text {
    font-size: 11px;
    color: #9ca3af;
}

.footer-facility {
    font-size: 13px;
    color: #6366f1;
    font-weight: 600;
    margin-top: 5px;
}

/* ボタン */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: var(--spacing-lg);
}

@media print {
    .page-header, .action-buttons { display: none; }
    .newsletter-detail { box-shadow: none; }
}

@media (max-width: 768px) {
    .newsletters-grid { grid-template-columns: 1fr; }
    .newsletter-detail { padding: 15px; }
    .grade-sections { grid-template-columns: 1fr; }
    .calendar-grid { gap: 2px; }
    .calendar-day { min-height: 50px; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">施設通信</h1>
        <p class="page-subtitle">施設からのお知らせをご確認ください</p>
    </div>
</div>

<?php if ($selectedNewsletter): ?>
    <!-- 操作ボタン -->
    <div class="action-buttons">
        <a href="newsletters.php" class="btn btn-secondary">← 一覧に戻る</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ 印刷</button>
    </div>

    <!-- 通信詳細表示（PDF出力と同じレイアウト） -->
    <div class="newsletter-detail">
        <!-- ヘッダー -->
        <div class="detail-header">
            <div class="detail-facility"><?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="detail-title"><?= htmlspecialchars($selectedNewsletter['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="detail-issue"><?= $selectedNewsletter['year'] ?>年<?= $selectedNewsletter['month'] ?>月号</div>
            <div class="detail-meta">
                報告期間: <?= date('Y/n/j', strtotime($selectedNewsletter['report_start_date'])) ?> ～ <?= date('n/j', strtotime($selectedNewsletter['report_end_date'])) ?>
                ｜予定期間: <?= date('n/j', strtotime($selectedNewsletter['schedule_start_date'])) ?> ～ <?= date('n/j', strtotime($selectedNewsletter['schedule_end_date'])) ?>
            </div>
        </div>

        <?php if (!empty($selectedNewsletter['greeting'])): ?>
        <!-- あいさつ文 -->
        <div class="greeting-box">
            <div class="greeting-text"><?= nl2br(htmlspecialchars($selectedNewsletter['greeting'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
        <?php endif; ?>

        <?php
        // カレンダーセクションの表示判定
        $hasCalendarContent = ($calendarFormat === 'table' && (!empty($calendarEvents) || !empty($calendarHolidays))) || !empty($selectedNewsletter['event_calendar']);
        if ($hasCalendarContent):
        ?>
        <!-- 今月の予定 -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">📅</span>
                今月の予定
            </div>
            <?php if ($calendarFormat === 'table'): ?>
            <!-- カレンダー表形式 -->
            <div class="calendar-grid-container">
                <?php
                $startDate = new DateTime($selectedNewsletter['schedule_start_date']);
                $endDate = new DateTime($selectedNewsletter['schedule_end_date']);
                $currentMonth = clone $startDate;
                $currentMonth->modify('first day of this month');

                while ($currentMonth <= $endDate):
                    $year = (int)$currentMonth->format('Y');
                    $month = (int)$currentMonth->format('n');
                    $daysInMonth = (int)$currentMonth->format('t');
                    $firstDayOfWeek = (int)$currentMonth->format('w');
                ?>
                <div class="calendar-month-title"><?= $year ?>年<?= $month ?>月</div>
                <div class="calendar-grid">
                    <?php
                    $weekDays = ['日', '月', '火', '水', '木', '金', '土'];
                    foreach ($weekDays as $idx => $dayName):
                        $dayClass = '';
                        if ($idx === 0) $dayClass = 'sunday';
                        if ($idx === 6) $dayClass = 'saturday';
                    ?>
                    <div class="calendar-day-header <?= $dayClass ?>"><?= $dayName ?></div>
                    <?php endforeach; ?>

                    <?php
                    for ($i = 0; $i < $firstDayOfWeek; $i++):
                    ?>
                    <div class="calendar-day empty"></div>
                    <?php endfor; ?>

                    <?php
                    for ($day = 1; $day <= $daysInMonth; $day++):
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dayOfWeek = ($firstDayOfWeek + $day - 1) % 7;
                        $isHoliday = isset($calendarHolidays[$dateStr]);
                        $dayNumClass = '';
                        if ($dayOfWeek === 0) $dayNumClass = 'sunday';
                        if ($dayOfWeek === 6) $dayNumClass = 'saturday';
                    ?>
                    <div class="calendar-day<?= $isHoliday ? ' holiday' : '' ?>">
                        <div class="calendar-day-num <?= $dayNumClass ?>"><?= $day ?></div>
                        <?php if ($isHoliday): ?>
                        <div class="calendar-holiday-name"><?= htmlspecialchars($calendarHolidays[$dateStr], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if (isset($calendarEvents[$dateStr])): ?>
                            <?php foreach ($calendarEvents[$dateStr] as $event): ?>
                            <div class="calendar-event"><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <?php
                    $currentMonth->modify('first day of next month');
                endwhile;
                ?>
            </div>
            <?php else: ?>
            <!-- カレンダー一覧形式 -->
            <div class="calendar-box">
                <div class="calendar-content"><?= htmlspecialchars($selectedNewsletter['event_calendar'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['event_details'])): ?>
        <!-- イベント詳細 -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">📝</span>
                イベント詳細
            </div>
            <div class="section-content"><?= htmlspecialchars($selectedNewsletter['event_details'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['weekly_reports'])): ?>
        <!-- 活動紹介まとめ -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">📖</span>
                活動紹介まとめ
            </div>
            <div class="section-content"><?= htmlspecialchars($selectedNewsletter['weekly_reports'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['weekly_intro'])): ?>
        <!-- 曜日別活動紹介 -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">🗓</span>
                曜日別活動紹介
            </div>
            <div class="section-content"><?= htmlspecialchars($selectedNewsletter['weekly_intro'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['event_results'])): ?>
        <!-- イベント結果報告 -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">🎉</span>
                イベント結果報告
            </div>
            <div class="section-content"><?= htmlspecialchars($selectedNewsletter['event_results'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php endif; ?>

        <?php
        // 学年別セクション
        $hasElementary = !empty($selectedNewsletter['elementary_report']);
        $hasJunior = !empty($selectedNewsletter['junior_report']);
        if ($hasElementary || $hasJunior):
        ?>
        <div class="grade-sections">
            <?php if ($hasElementary): ?>
            <div class="grade-section">
                <div class="grade-header">
                    <span>🎒</span>
                    小学生の活動
                </div>
                <div class="grade-content"><?= htmlspecialchars($selectedNewsletter['elementary_report'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php endif; ?>

            <?php if ($hasJunior): ?>
            <div class="grade-section">
                <div class="grade-header">
                    <span>📚</span>
                    中高生の活動
                </div>
                <div class="grade-content"><?= htmlspecialchars($selectedNewsletter['junior_report'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['requests'])): ?>
        <!-- 施設からのお願い -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">🙏</span>
                施設からのお願い
            </div>
            <div class="notice-box">
                <div class="notice-content"><?= htmlspecialchars($selectedNewsletter['requests'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['others'])): ?>
        <!-- その他のお知らせ -->
        <div class="detail-section">
            <div class="section-header">
                <span class="section-icon">📌</span>
                その他のお知らせ
            </div>
            <div class="notice-box">
                <div class="notice-content"><?= htmlspecialchars($selectedNewsletter['others'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- フッター -->
        <div class="detail-footer">
            <div class="footer-text">
                発行日: <?= date('Y年n月j日', strtotime($selectedNewsletter['published_at'])) ?>
            </div>
            <div class="footer-facility"><?= htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

<?php else: ?>
    <!-- 通信一覧表示 -->
    <?php if (empty($newsletters)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: var(--spacing-3xl);">
                <div style="font-size: 64px; margin-bottom: var(--spacing-lg);">📭</div>
                <p style="color: var(--text-secondary);">まだ通信が発行されていません</p>
            </div>
        </div>
    <?php else: ?>
        <div class="newsletters-grid">
            <?php foreach ($newsletters as $newsletter): ?>
                <a href="newsletters.php?id=<?= $newsletter['id'] ?>" class="newsletter-card">
                    <h3><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="newsletter-meta">
                        報告: <?= date('Y/m/d', strtotime($newsletter['report_start_date'])) ?>
                        ～ <?= date('Y/m/d', strtotime($newsletter['report_end_date'])) ?>
                    </div>
                    <div class="newsletter-meta">
                        予定: <?= date('Y/m/d', strtotime($newsletter['schedule_start_date'])) ?>
                        ～ <?= date('Y/m/d', strtotime($newsletter['schedule_end_date'])) ?>
                    </div>
                    <div class="newsletter-date">
                        発行日: <?= date('Y年m月d日', strtotime($newsletter['published_at'])) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php renderPageEnd(); ?>
