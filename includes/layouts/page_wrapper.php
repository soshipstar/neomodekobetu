<?php
/**
 * ページラッパー関数
 *
 * 使用方法:
 *   require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
 *   renderPageStart('admin', 'index', 'ダッシュボード');
 *   // ... コンテンツ ...
 *   renderPageEnd();
 */

/**
 * ページの開始部分をレンダリング
 *
 * @param string $role ユーザーロール (admin, staff, guardian, student, tablet_user)
 * @param string $currentPage 現在のページ識別子
 * @param string $pageTitle ページタイトル
 * @param array $options 追加オプション
 *   - 'additionalCss' => 追加CSSファイルパス配列
 *   - 'additionalHead' => <head>に追加するHTML
 *   - 'classroom' => 教室情報配列
 *   - 'bodyClass' => bodyタグに追加するクラス
 *   - 'noContainer' => true でcontainerを出力しない
 */
function renderPageStart(string $role, string $currentPage, string $pageTitle, array $options = []): void
{
    global $classroom, $isMaster, $menuItems;

    // オプションのデフォルト値
    $additionalCss = $options['additionalCss'] ?? [];
    $additionalHead = $options['additionalHead'] ?? '';
    $classroom = $options['classroom'] ?? ($GLOBALS['classroom'] ?? null);
    $bodyClass = $options['bodyClass'] ?? '';
    $noContainer = $options['noContainer'] ?? false;

    // isMasterの設定
    $isMaster = function_exists('isMasterAdmin') ? isMasterAdmin() : false;

    // ロール別CSS
    $roleCssMap = [
        'admin' => '/assets/css/admin.css',
        'staff' => '/assets/css/staff.css',
        'guardian' => '/assets/css/guardian.css',
        'student' => '/assets/css/student.css',
        'tablet_user' => '/assets/css/tablet.css',
    ];
    $roleCss = $roleCssMap[$role] ?? '';

    // メニュー項目を読み込み（mobile_header用）
    $menuConfig = getMenuConfig();
    $menuItems = $menuConfig[$role] ?? [];

    // ダークモード検出用のクラス
    $roleColorClass = match($role) {
        'admin' => 'role-admin',
        'staff' => 'role-staff',
        'guardian' => 'role-guardian',
        'student' => 'role-student',
        'tablet_user' => 'role-tablet',
        default => 'role-staff'
    };
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <!-- Critical Dark Mode CSS (prevents flash) -->
    <style>@media(prefers-color-scheme:dark){html,body{background:#1E1E1E;color:rgba(255,255,255,0.87)}}</style>
    <title><?= htmlspecialchars($pageTitle) ?> - きづり</title>

    <!-- Google Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- 共通CSS -->
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <link rel="stylesheet" href="/assets/css/layout.css">

    <!-- ロール別CSS -->
    <?php if ($roleCss && file_exists($_SERVER['DOCUMENT_ROOT'] . $roleCss)): ?>
    <link rel="stylesheet" href="<?= $roleCss ?>">
    <?php endif; ?>

    <!-- 追加CSS -->
    <?php foreach ($additionalCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.svg">

    <?= $additionalHead ?>
</head>
<body class="<?= $roleColorClass ?> <?= htmlspecialchars($bodyClass) ?>">
    <div class="page-wrapper">
        <?php
        // サイドバーを読み込み
        include __DIR__ . '/sidebar.php';
        ?>

        <main class="main-content">
            <?php
            // モバイルヘッダーを読み込み
            include __DIR__ . '/mobile_header.php';
            ?>

            <?php if (!$noContainer): ?>
            <div class="container">
            <?php endif; ?>
<?php
}

/**
 * ページの終了部分をレンダリング
 *
 * @param array $options 追加オプション
 *   - 'additionalJs' => 追加JSファイルパス配列
 *   - 'inlineJs' => インラインJavaScript
 *   - 'noContainer' => true でcontainer閉じタグを出力しない
 *   - 'noChatbot' => true でチャットボットを非表示
 */
function renderPageEnd(array $options = []): void
{
    $additionalJs = $options['additionalJs'] ?? [];
    $inlineJs = $options['inlineJs'] ?? '';
    $noContainer = $options['noContainer'] ?? false;
    $noChatbot = $options['noChatbot'] ?? false;

    // スタッフ・管理者向けにチャットボットを表示
    $showChatbot = !$noChatbot &&
                   isset($_SESSION['user_type']) &&
                   in_array($_SESSION['user_type'], ['staff', 'admin']);

    if (!$noContainer):
?>
            </div><!-- /.container -->
            <?php endif; ?>
        </main>
    </div><!-- /.page-wrapper -->

    <?php if ($showChatbot): ?>
    <?php include __DIR__ . '/../components/chatbot.php'; ?>
    <?php endif; ?>

    <!-- 追加JS -->
    <?php foreach ($additionalJs as $js): ?>
    <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>

    <?php if ($inlineJs): ?>
    <script>
    <?= $inlineJs ?>
    </script>
    <?php endif; ?>
</body>
</html>
<?php
}

/**
 * メニュー設定を取得
 */
function getMenuConfig(): array
{
    $isMaster = function_exists('isMasterAdmin') ? isMasterAdmin() : false;

    return [
        'admin' => [
            ['page' => 'index', 'icon' => 'home', 'label' => 'ダッシュボード', 'url' => '/admin/index.php'],
            ['page' => 'students', 'icon' => 'group', 'label' => '生徒管理', 'url' => '/admin/students.php'],
            ['page' => 'guardians', 'icon' => 'person', 'label' => '保護者管理', 'url' => '/admin/guardians.php'],
            ['page' => 'waiting_list', 'icon' => 'hourglass_empty', 'label' => '待機児童管理', 'url' => '/admin/waiting_list.php'],
            ['page' => 'staff_management', 'icon' => 'manage_accounts', 'label' => 'スタッフ管理', 'url' => '/admin/staff_management.php'],
            ['page' => 'tablet_accounts', 'icon' => 'tablet', 'label' => 'タブレットユーザー', 'url' => '/admin/tablet_accounts.php'],
            ['page' => 'events', 'icon' => 'event', 'label' => 'イベント管理', 'url' => '/admin/events.php'],
            ['page' => 'holidays', 'icon' => 'calendar_today', 'label' => '休日管理', 'url' => '/admin/holidays.php'],
            ['page' => 'classrooms', 'icon' => 'apartment', 'label' => '教室管理', 'url' => '/admin/classrooms.php', 'master_only' => true],
            ['page' => 'admin_accounts', 'icon' => 'shield_person', 'label' => '管理者アカウント', 'url' => '/admin/admin_accounts.php', 'master_only' => true],
            ['page' => 'staff_accounts', 'icon' => 'badge', 'label' => 'スタッフアカウント', 'url' => '/admin/staff_accounts.php', 'master_only' => true],
        ],
        'staff' => [
            // 日常業務
            ['page' => 'renrakucho_activities', 'icon' => 'home', 'label' => '活動管理', 'url' => '/staff/renrakucho_activities.php'],
            // チャット
            ['type' => 'divider', 'label' => 'チャット'],
            ['page' => 'chat', 'icon' => 'family_restroom', 'label' => '保護者チャット', 'url' => '/staff/chat.php'],
            ['page' => 'student_chats', 'icon' => 'child_care', 'label' => '生徒チャット', 'url' => '/staff/student_chats.php'],
            // かけはし
            ['type' => 'divider', 'label' => 'かけはし'],
            ['page' => 'kakehashi_staff', 'icon' => 'handshake', 'label' => 'かけはし（職員）', 'url' => '/staff/kakehashi_staff.php'],
            ['page' => 'kakehashi_guardian_view', 'icon' => 'menu_book', 'label' => 'かけはし（保護者）', 'url' => '/staff/kakehashi_guardian_view.php'],
            // 計画・支援
            ['type' => 'divider', 'label' => '計画・支援'],
            ['page' => 'support_plans', 'icon' => 'description', 'label' => '支援案', 'url' => '/staff/support_plans.php'],
            ['page' => 'student_weekly_plans', 'icon' => 'edit_note', 'label' => '週間計画', 'url' => '/staff/student_weekly_plans.php'],
            ['page' => 'kobetsu_plan', 'icon' => 'assignment', 'label' => '個別支援計画', 'url' => '/staff/kobetsu_plan.php'],
            ['page' => 'kobetsu_monitoring', 'icon' => 'monitoring', 'label' => 'モニタリング', 'url' => '/staff/kobetsu_monitoring.php'],
            // 提出物
            ['type' => 'divider', 'label' => '提出物'],
            ['page' => 'student_submissions', 'icon' => 'upload_file', 'label' => '生徒提出物', 'url' => '/staff/student_submissions.php'],
            ['page' => 'submission_management', 'icon' => 'folder_open', 'label' => '提出物管理', 'url' => '/staff/submission_management.php'],
            // 情報発信
            ['type' => 'divider', 'label' => '情報発信'],
            ['page' => 'newsletter_create', 'icon' => 'newspaper', 'label' => '施設通信', 'url' => '/staff/newsletter_create.php'],
            ['page' => 'newsletter_settings', 'icon' => 'tune', 'label' => '施設通信設定', 'url' => '/staff/newsletter_settings.php'],
            ['page' => 'events', 'icon' => 'event', 'label' => 'イベント', 'url' => '/staff/events.php'],
            // 管理・設定
            ['type' => 'divider', 'label' => '管理・設定'],
            ['page' => 'additional_usage', 'icon' => 'edit_calendar', 'label' => '利用日変更', 'url' => '/staff/additional_usage.php'],
            ['page' => 'makeup_requests', 'icon' => 'sync', 'label' => '振替管理', 'url' => '/staff/makeup_requests.php'],
            ['page' => 'students', 'icon' => 'group', 'label' => '生徒管理', 'url' => '/staff/students.php'],
            ['page' => 'guardians', 'icon' => 'person', 'label' => '保護者管理', 'url' => '/staff/guardians.php'],
            ['page' => 'waiting_list', 'icon' => 'hourglass_empty', 'label' => '待機児童管理', 'url' => '/admin/waiting_list.php'],
            ['page' => 'holidays', 'icon' => 'calendar_today', 'label' => '休日設定', 'url' => '/staff/holidays.php'],
            ['page' => 'school_holiday_activities', 'icon' => 'school', 'label' => '学校休業日活動', 'url' => '/staff/school_holiday_activities.php'],
            ['page' => 'manual', 'icon' => 'help', 'label' => 'マニュアル', 'url' => '/staff/manual.php'],
            ['page' => 'profile', 'icon' => 'settings', 'label' => 'プロフィール', 'url' => '/staff/profile.php'],
        ],
        'guardian' => [
            ['page' => 'dashboard', 'icon' => 'home', 'label' => 'ダッシュボード', 'url' => '/guardian/dashboard.php'],
            ['page' => 'communication_logs', 'icon' => 'library_books', 'label' => '連絡帳一覧', 'url' => '/guardian/communication_logs.php'],
            ['page' => 'chat', 'icon' => 'chat', 'label' => 'チャット', 'url' => '/guardian/chat.php'],
            ['page' => 'weekly_plan', 'icon' => 'edit_note', 'label' => '週間計画表', 'url' => '/guardian/weekly_plan.php'],
            ['page' => 'kakehashi', 'icon' => 'handshake', 'label' => 'かけはし入力', 'url' => '/guardian/kakehashi.php'],
            ['page' => 'kakehashi_history', 'icon' => 'history', 'label' => 'かけはし履歴', 'url' => '/guardian/kakehashi_history.php'],
            ['page' => 'newsletters', 'icon' => 'newspaper', 'label' => '施設通信', 'url' => '/guardian/newsletters.php'],
            ['page' => 'support_plans', 'icon' => 'assignment', 'label' => '個別支援計画書', 'url' => '/guardian/support_plans.php'],
            ['page' => 'monitoring', 'icon' => 'monitoring', 'label' => 'モニタリング表', 'url' => '/guardian/monitoring.php'],
            ['page' => 'manual', 'icon' => 'help', 'label' => 'ご利用ガイド', 'url' => '/guardian/manual.php'],
            ['page' => 'profile', 'icon' => 'person', 'label' => 'プロフィール', 'url' => '/guardian/profile.php'],
            ['page' => 'change_password', 'icon' => 'lock', 'label' => 'パスワード変更', 'url' => '/guardian/change_password.php'],
        ],
        'student' => [
            ['page' => 'dashboard', 'icon' => 'home', 'label' => 'マイページ', 'url' => '/student/dashboard.php'],
            ['page' => 'chat', 'icon' => 'chat', 'label' => 'チャット', 'url' => '/student/chat.php'],
            ['page' => 'weekly_plan', 'icon' => 'edit_note', 'label' => '週間計画', 'url' => '/student/weekly_plan.php'],
            ['page' => 'submissions', 'icon' => 'assignment', 'label' => '提出物', 'url' => '/student/submissions.php'],
            ['page' => 'schedule', 'icon' => 'event', 'label' => 'スケジュール', 'url' => '/student/schedule.php'],
            ['page' => 'change_password', 'icon' => 'lock', 'label' => 'パスワード変更', 'url' => '/student/change_password.php'],
        ],
        'tablet_user' => [
            ['page' => 'renrakucho_form', 'icon' => 'edit_note', 'label' => '本日の記録', 'url' => '/tablet/renrakucho_form.php'],
            ['page' => 'renrakucho_activities', 'icon' => 'monitoring', 'label' => '活動記録', 'url' => '/tablet/renrakucho_activities.php'],
        ],
    ];
}

/**
 * シンプルなページヘッダーをレンダリング（コンテンツエリア内）
 */
function renderPageHeader(string $title, string $subtitle = '', array $actions = []): void
{
?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <?php if ($subtitle): ?>
        <p class="page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actions): ?>
    <div class="page-header-actions">
        <?php foreach ($actions as $action): ?>
        <a href="<?= htmlspecialchars($action['url']) ?>"
           class="btn <?= htmlspecialchars($action['class'] ?? 'btn-primary') ?>">
            <?= htmlspecialchars($action['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
}
