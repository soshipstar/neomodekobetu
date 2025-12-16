<?php
/**
 * 施設通信設定ページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error_message'] = '教室が選択されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 既存設定を確認
        $stmt = $pdo->prepare("SELECT id FROM newsletter_settings WHERE classroom_id = ?");
        $stmt->execute([$classroomId]);
        $existingSettings = $stmt->fetch();

        $data = [
            'classroom_id' => $classroomId,
            'show_facility_name' => isset($_POST['show_facility_name']) ? 1 : 0,
            'show_logo' => isset($_POST['show_logo']) ? 1 : 0,
            'show_greeting' => isset($_POST['show_greeting']) ? 1 : 0,
            'show_event_calendar' => isset($_POST['show_event_calendar']) ? 1 : 0,
            'calendar_format' => $_POST['calendar_format'] ?? 'list',
            'show_event_details' => isset($_POST['show_event_details']) ? 1 : 0,
            'show_weekly_reports' => isset($_POST['show_weekly_reports']) ? 1 : 0,
            'show_weekly_intro' => isset($_POST['show_weekly_intro']) ? 1 : 0,
            'show_event_results' => isset($_POST['show_event_results']) ? 1 : 0,
            'show_requests' => isset($_POST['show_requests']) ? 1 : 0,
            'show_others' => isset($_POST['show_others']) ? 1 : 0,
            'show_elementary_report' => isset($_POST['show_elementary_report']) ? 1 : 0,
            'show_junior_report' => isset($_POST['show_junior_report']) ? 1 : 0,
            'default_requests' => trim($_POST['default_requests'] ?? ''),
            'default_others' => trim($_POST['default_others'] ?? ''),
            'greeting_instructions' => trim($_POST['greeting_instructions'] ?? ''),
            'event_details_instructions' => trim($_POST['event_details_instructions'] ?? ''),
            'weekly_reports_instructions' => trim($_POST['weekly_reports_instructions'] ?? ''),
            'weekly_intro_instructions' => trim($_POST['weekly_intro_instructions'] ?? ''),
            'event_results_instructions' => trim($_POST['event_results_instructions'] ?? ''),
            'elementary_report_instructions' => trim($_POST['elementary_report_instructions'] ?? ''),
            'junior_report_instructions' => trim($_POST['junior_report_instructions'] ?? ''),
            'custom_section_title' => trim($_POST['custom_section_title'] ?? ''),
            'custom_section_content' => trim($_POST['custom_section_content'] ?? ''),
            'show_custom_section' => isset($_POST['show_custom_section']) ? 1 : 0,
        ];

        if ($existingSettings) {
            // 更新
            $sql = "UPDATE newsletter_settings SET
                show_facility_name = ?,
                show_logo = ?,
                show_greeting = ?,
                show_event_calendar = ?,
                calendar_format = ?,
                show_event_details = ?,
                show_weekly_reports = ?,
                show_weekly_intro = ?,
                show_event_results = ?,
                show_requests = ?,
                show_others = ?,
                show_elementary_report = ?,
                show_junior_report = ?,
                default_requests = ?,
                default_others = ?,
                greeting_instructions = ?,
                event_details_instructions = ?,
                weekly_reports_instructions = ?,
                weekly_intro_instructions = ?,
                event_results_instructions = ?,
                elementary_report_instructions = ?,
                junior_report_instructions = ?,
                custom_section_title = ?,
                custom_section_content = ?,
                show_custom_section = ?
                WHERE classroom_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['show_facility_name'],
                $data['show_logo'],
                $data['show_greeting'],
                $data['show_event_calendar'],
                $data['calendar_format'],
                $data['show_event_details'],
                $data['show_weekly_reports'],
                $data['show_weekly_intro'],
                $data['show_event_results'],
                $data['show_requests'],
                $data['show_others'],
                $data['show_elementary_report'],
                $data['show_junior_report'],
                $data['default_requests'],
                $data['default_others'],
                $data['greeting_instructions'],
                $data['event_details_instructions'],
                $data['weekly_reports_instructions'],
                $data['weekly_intro_instructions'],
                $data['event_results_instructions'],
                $data['elementary_report_instructions'],
                $data['junior_report_instructions'],
                $data['custom_section_title'],
                $data['custom_section_content'],
                $data['show_custom_section'],
                $classroomId
            ]);
        } else {
            // 新規作成
            $sql = "INSERT INTO newsletter_settings (
                classroom_id, show_facility_name, show_logo, show_greeting, show_event_calendar, calendar_format, show_event_details,
                show_weekly_reports, show_weekly_intro, show_event_results, show_requests, show_others,
                show_elementary_report, show_junior_report,
                default_requests, default_others, greeting_instructions, event_details_instructions,
                weekly_reports_instructions, weekly_intro_instructions, event_results_instructions,
                elementary_report_instructions, junior_report_instructions,
                custom_section_title, custom_section_content, show_custom_section
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['classroom_id'],
                $data['show_facility_name'],
                $data['show_logo'],
                $data['show_greeting'],
                $data['show_event_calendar'],
                $data['calendar_format'],
                $data['show_event_details'],
                $data['show_weekly_reports'],
                $data['show_weekly_intro'],
                $data['show_event_results'],
                $data['show_requests'],
                $data['show_others'],
                $data['show_elementary_report'],
                $data['show_junior_report'],
                $data['default_requests'],
                $data['default_others'],
                $data['greeting_instructions'],
                $data['event_details_instructions'],
                $data['weekly_reports_instructions'],
                $data['weekly_intro_instructions'],
                $data['event_results_instructions'],
                $data['elementary_report_instructions'],
                $data['junior_report_instructions'],
                $data['custom_section_title'],
                $data['custom_section_content'],
                $data['show_custom_section']
            ]);
        }

        $_SESSION['success_message'] = '設定を保存しました';
        header('Location: newsletter_settings.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = '保存に失敗しました: ' . $e->getMessage();
    }
}

// 現在の設定を取得
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

// ページ開始
$currentPage = 'newsletter_settings';
renderPageStart('staff', $currentPage, '施設通信設定');
?>

<style>
.settings-section {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.settings-section h2 {
    color: var(--apple-blue);
    font-size: 18px;
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
    display: flex;
    align-items: center;
    gap: 10px;
}

.toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--apple-gray-5);
}

.toggle-item:last-child {
    border-bottom: none;
}

.toggle-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.toggle-label strong {
    color: var(--text-primary);
    font-size: var(--text-subhead);
}

.toggle-label small {
    color: var(--text-secondary);
    font-size: var(--text-caption-1);
}

.toggle-switch {
    position: relative;
    width: 50px;
    height: 28px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--apple-gray-4);
    transition: .3s;
    border-radius: 28px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
}

.toggle-switch input:checked + .toggle-slider {
    background-color: var(--apple-green);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
}

.instruction-group {
    margin-top: var(--spacing-lg);
}

.instruction-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.instruction-group textarea {
    width: 100%;
    padding: var(--spacing-md);
    border: 2px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    resize: vertical;
    min-height: 80px;
    background: var(--apple-bg-secondary);
    color: var(--text-primary);
}

.instruction-group textarea:focus {
    outline: none;
    border-color: var(--apple-blue);
}

.instruction-group small {
    display: block;
    margin-top: 5px;
    color: var(--text-secondary);
    font-size: var(--text-caption-1);
}

.custom-section-group {
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-lg);
    border-top: 1px dashed var(--apple-gray-4);
}

.submit-section {
    text-align: center;
    padding: var(--spacing-lg) 0;
}

.submit-btn {
    padding: 15px 60px;
    background: var(--apple-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,122,255,0.4);
}

.success-message {
    background: rgba(52, 199, 89, 0.15);
    color: var(--apple-green);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-green);
}

.error-message {
    background: rgba(255, 59, 48, 0.15);
    color: var(--apple-red);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-red);
}

.info-box {
    background: rgba(0,122,255,0.1);
    border-left: 4px solid var(--apple-blue);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
    font-size: var(--text-subhead);
    line-height: 1.6;
}

.preview-section {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-sm);
    margin-top: var(--spacing-lg);
}

.preview-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: var(--spacing-md);
}

.preview-order {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.preview-item {
    padding: 6px 12px;
    background: var(--apple-blue);
    color: white;
    border-radius: var(--radius-sm);
    font-size: var(--text-caption-1);
    font-weight: 500;
}

.preview-item.disabled {
    background: var(--apple-gray-4);
    color: var(--text-secondary);
    text-decoration: line-through;
}

@media (max-width: 768px) {
    .settings-section {
        padding: var(--spacing-lg);
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">施設通信設定</h1>
        <p class="page-subtitle">通信に含めるコンテンツを設定</p>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="success-message">
    <?= htmlspecialchars($_SESSION['success_message']) ?>
    <?php unset($_SESSION['success_message']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="error-message">
    <?= htmlspecialchars($_SESSION['error_message']) ?>
    <?php unset($_SESSION['error_message']); ?>
</div>
<?php endif; ?>

<div class="info-box">
    ここで設定した内容は、新しい施設通信を作成する際に反映されます。セクションの表示/非表示、デフォルト文章、AI生成時の指示などを設定できます。
</div>

<form method="POST" action="">
    <!-- ヘッダー設定 -->
    <div class="settings-section">
        <h2>ヘッダー設定</h2>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>施設名</strong>
                <small>通信のヘッダーに施設名を表示</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_facility_name" <?= $settings['show_facility_name'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>ロゴ</strong>
                <small>通信のヘッダーにロゴを表示</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_logo" <?= $settings['show_logo'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <!-- セクション表示設定 -->
    <div class="settings-section">
        <h2>通信に含めるセクション</h2>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>挨拶文</strong>
                <small>季節のあいさつと教室からのメッセージ</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_greeting" <?= $settings['show_greeting'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>イベントカレンダー</strong>
                <small>予定期間のイベント一覧</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_event_calendar" <?= $settings['show_event_calendar'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <!-- カレンダー形式選択 -->
        <div class="format-selection" style="margin-left: 20px; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem;">カレンダー形式:</label>
            <div style="display: flex; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="calendar_format" value="list" <?= ($settings['calendar_format'] ?? 'list') === 'list' ? 'checked' : '' ?>>
                    <span>一覧形式（日付と名前）</span>
                </label>
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="radio" name="calendar_format" value="table" <?= ($settings['calendar_format'] ?? 'list') === 'table' ? 'checked' : '' ?>>
                    <span>表形式（カレンダー表）</span>
                </label>
            </div>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>イベント詳細</strong>
                <small>各イベントの魅力を伝える詳細説明（AI生成）</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_event_details" <?= $settings['show_event_details'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>活動紹介まとめ</strong>
                <small>期間内の活動を時系列でまとめた紹介文（AI生成）</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_weekly_reports" <?= $settings['show_weekly_reports'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>曜日別活動紹介</strong>
                <small>各曜日の活動を紹介し参加を促す（AI生成）</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_weekly_intro" <?= $settings['show_weekly_intro'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>イベント結果報告</strong>
                <small>報告期間のイベント実施結果（AI生成）</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_event_results" <?= $settings['show_event_results'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>施設からのお願い</strong>
                <small>保護者への連絡事項・お願い</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_requests" <?= $settings['show_requests'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>その他</strong>
                <small>その他の連絡事項</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_others" <?= $settings['show_others'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>小学生の活動報告</strong>
                <small>報告期間の連絡帳・支援案から小学生の活動をAI生成</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_elementary_report" <?= $settings['show_elementary_report'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="toggle-item">
            <div class="toggle-label">
                <strong>中学生・高校生の活動報告</strong>
                <small>報告期間の連絡帳・支援案から中学生・高校生の活動をAI生成</small>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="show_junior_report" <?= $settings['show_junior_report'] ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>

        <!-- カスタムセクション -->
        <div class="custom-section-group">
            <div class="toggle-item">
                <div class="toggle-label">
                    <strong>カスタムセクション</strong>
                    <small>任意の独自セクションを追加</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="show_custom_section" <?= $settings['show_custom_section'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="instruction-group">
                <label>カスタムセクションのタイトル</label>
                <input type="text" name="custom_section_title" class="form-control"
                       value="<?= htmlspecialchars($settings['custom_section_title'] ?? '') ?>"
                       placeholder="例: 今月のお誕生日">
            </div>

            <div class="instruction-group">
                <label>カスタムセクションの内容</label>
                <textarea name="custom_section_content" placeholder="毎回の通信に含める内容を入力..."><?= htmlspecialchars($settings['custom_section_content'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- プレビュー -->
        <div class="preview-section">
            <div class="preview-title">通信の構成プレビュー</div>
            <div class="preview-order" id="previewOrder">
                <!-- JavaScriptで更新 -->
            </div>
        </div>
    </div>

    <!-- デフォルト内容設定 -->
    <div class="settings-section">
        <h2>デフォルト内容</h2>

        <div class="instruction-group">
            <label>施設からのお願い（デフォルト文）</label>
            <textarea name="default_requests" placeholder="毎回の通信に含めるお願い事項があれば入力..."><?= htmlspecialchars($settings['default_requests'] ?? '') ?></textarea>
            <small>新規通信作成時に自動的にこの内容が入力されます</small>
        </div>

        <div class="instruction-group">
            <label>その他（デフォルト文）</label>
            <textarea name="default_others" placeholder="毎回の通信に含めるその他事項があれば入力..."><?= htmlspecialchars($settings['default_others'] ?? '') ?></textarea>
            <small>新規通信作成時に自動的にこの内容が入力されます</small>
        </div>
    </div>

    <!-- AI生成設定 -->
    <div class="settings-section">
        <h2>AI生成の追加指示</h2>

        <div class="info-box" style="margin-bottom: var(--spacing-lg);">
            AIが文章を生成する際に、教室独自の方針や表現を追加指示できます。空欄の場合はデフォルトの指示が使用されます。
        </div>

        <div class="instruction-group">
            <label>挨拶文の生成指示</label>
            <textarea name="greeting_instructions" placeholder="例: 教室のキャラクター「たけのこくん」を登場させてください"><?= htmlspecialchars($settings['greeting_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>イベント詳細の生成指示</label>
            <textarea name="event_details_instructions" placeholder="例: 持ち物リストを箇条書きで含めてください"><?= htmlspecialchars($settings['event_details_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>活動紹介まとめの生成指示</label>
            <textarea name="weekly_reports_instructions" placeholder="例: 子どもたちが楽しんでいる様子を具体的に伝えてください"><?= htmlspecialchars($settings['weekly_reports_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>曜日別活動紹介の生成指示</label>
            <textarea name="weekly_intro_instructions" placeholder="例: 各曜日の活動の楽しさと子どもの成長を具体的に伝えてください"><?= htmlspecialchars($settings['weekly_intro_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>イベント結果報告の生成指示</label>
            <textarea name="event_results_instructions" placeholder="例: 子どもたちの成長エピソードを入れてください"><?= htmlspecialchars($settings['event_results_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>小学生活動報告の生成指示</label>
            <textarea name="elementary_report_instructions" placeholder="例: 低学年と高学年の活動を分けて記載してください"><?= htmlspecialchars($settings['elementary_report_instructions'] ?? '') ?></textarea>
        </div>

        <div class="instruction-group">
            <label>中学生・高校生活動報告の生成指示</label>
            <textarea name="junior_report_instructions" placeholder="例: 進路準備や自立に向けた取り組みを含めてください"><?= htmlspecialchars($settings['junior_report_instructions'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="submit-section">
        <button type="submit" class="submit-btn">設定を保存</button>
    </div>
</form>

<?php
$inlineJs = <<<JS
// プレビュー更新
function updatePreview() {
    const sections = [
        { name: 'show_facility_name', label: '施設名' },
        { name: 'show_logo', label: 'ロゴ' },
        { name: 'show_greeting', label: '挨拶文' },
        { name: 'show_event_calendar', label: 'カレンダー' },
        { name: 'show_event_details', label: 'イベント詳細' },
        { name: 'show_weekly_reports', label: '活動紹介' },
        { name: 'show_weekly_intro', label: '曜日別' },
        { name: 'show_event_results', label: '結果報告' },
        { name: 'show_requests', label: 'お願い' },
        { name: 'show_others', label: 'その他' },
        { name: 'show_elementary_report', label: '小学生活動' },
        { name: 'show_junior_report', label: '中高生活動' },
        { name: 'show_custom_section', label: 'カスタム' }
    ];

    const preview = document.getElementById('previewOrder');
    preview.innerHTML = '';

    sections.forEach(section => {
        const checkbox = document.querySelector(`input[name="${section.name}"]`);
        const item = document.createElement('span');
        item.className = 'preview-item' + (checkbox.checked ? '' : ' disabled');
        item.textContent = section.label;
        preview.appendChild(item);
    });
}

// チェックボックス変更時にプレビュー更新
document.querySelectorAll('.toggle-switch input').forEach(checkbox => {
    checkbox.addEventListener('change', updatePreview);
});

// 初期表示
updatePreview();
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
