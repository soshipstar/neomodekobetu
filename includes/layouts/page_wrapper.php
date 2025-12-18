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
    <title><?= htmlspecialchars($pageTitle) ?> - きづり</title>

    <!-- 共通CSS -->
    <link rel="stylesheet" href="/assets/css/apple-design.css">
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
 */
function renderPageEnd(array $options = []): void
{
    $additionalJs = $options['additionalJs'] ?? [];
    $inlineJs = $options['inlineJs'] ?? '';
    $noContainer = $options['noContainer'] ?? false;

    if (!$noContainer):
?>
            </div><!-- /.container -->
            <?php endif; ?>
        </main>
    </div><!-- /.page-wrapper -->

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
            ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/admin/index.php'],
            ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/admin/students.php'],
            ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/admin/guardians.php'],
            ['page' => 'staff_management', 'icon' => '👨‍💼', 'label' => 'スタッフ管理', 'url' => '/admin/staff_management.php'],
            ['page' => 'tablet_accounts', 'icon' => '📱', 'label' => 'タブレットユーザー', 'url' => '/admin/tablet_accounts.php'],
            ['page' => 'events', 'icon' => '📅', 'label' => 'イベント管理', 'url' => '/admin/events.php'],
            ['page' => 'holidays', 'icon' => '🗓️', 'label' => '休日管理', 'url' => '/admin/holidays.php'],
            ['page' => 'classrooms', 'icon' => '🏢', 'label' => '教室管理', 'url' => '/admin/classrooms.php', 'master_only' => true],
            ['page' => 'admin_accounts', 'icon' => '👑', 'label' => '管理者アカウント', 'url' => '/admin/admin_accounts.php', 'master_only' => true],
            ['page' => 'staff_accounts', 'icon' => '👔', 'label' => 'スタッフアカウント', 'url' => '/admin/staff_accounts.php', 'master_only' => true],
        ],
        'staff' => [
            // 日常業務
            ['page' => 'renrakucho_activities', 'icon' => '🏠', 'label' => '活動管理', 'url' => '/staff/renrakucho_activities.php'],
            // チャット
            ['type' => 'divider', 'label' => 'チャット'],
            ['page' => 'chat', 'icon' => '👨‍👩‍👧', 'label' => '保護者チャット', 'url' => '/staff/chat.php'],
            ['page' => 'student_chats', 'icon' => '🧒', 'label' => '生徒チャット', 'url' => '/staff/student_chats.php'],
            // かけはし
            ['type' => 'divider', 'label' => 'かけはし'],
            ['page' => 'kakehashi_staff', 'icon' => '🌉', 'label' => 'かけはし（職員）', 'url' => '/staff/kakehashi_staff.php'],
            ['page' => 'kakehashi_guardian_view', 'icon' => '📖', 'label' => 'かけはし（保護者）', 'url' => '/staff/kakehashi_guardian_view.php'],
            // 計画・支援
            ['type' => 'divider', 'label' => '計画・支援'],
            ['page' => 'support_plans', 'icon' => '📄', 'label' => '支援案', 'url' => '/staff/support_plans.php'],
            ['page' => 'student_weekly_plans', 'icon' => '📝', 'label' => '週間計画', 'url' => '/staff/student_weekly_plans.php'],
            ['page' => 'kobetsu_plan', 'icon' => '📋', 'label' => '個別支援計画', 'url' => '/staff/kobetsu_plan.php'],
            ['page' => 'kobetsu_monitoring', 'icon' => '📊', 'label' => 'モニタリング', 'url' => '/staff/kobetsu_monitoring.php'],
            // 提出物
            ['type' => 'divider', 'label' => '提出物'],
            ['page' => 'student_submissions', 'icon' => '📤', 'label' => '生徒提出物', 'url' => '/staff/student_submissions.php'],
            ['page' => 'submission_management', 'icon' => '📥', 'label' => '提出物管理', 'url' => '/staff/submission_management.php'],
            // 情報発信
            ['type' => 'divider', 'label' => '情報発信'],
            ['page' => 'newsletter_create', 'icon' => '📰', 'label' => '施設通信', 'url' => '/staff/newsletter_create.php'],
            ['page' => 'newsletter_settings', 'icon' => '🔧', 'label' => '施設通信設定', 'url' => '/staff/newsletter_settings.php'],
            ['page' => 'events', 'icon' => '📅', 'label' => 'イベント', 'url' => '/staff/events.php'],
            // 管理・設定
            ['type' => 'divider', 'label' => '管理・設定'],
            ['page' => 'additional_usage', 'icon' => '📅', 'label' => '利用日変更', 'url' => '/staff/additional_usage.php'],
            ['page' => 'makeup_requests', 'icon' => '🔄', 'label' => '振替管理', 'url' => '/staff/makeup_requests.php'],
            ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/staff/students.php'],
            ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/staff/guardians.php'],
            ['page' => 'holidays', 'icon' => '🗓️', 'label' => '休日設定', 'url' => '/staff/holidays.php'],
            ['page' => 'school_holiday_activities', 'icon' => '🏫', 'label' => '学校休業日活動', 'url' => '/staff/school_holiday_activities.php'],
            ['page' => 'manual', 'icon' => '📖', 'label' => 'マニュアル', 'url' => '/staff/manual.php'],
            ['page' => 'profile', 'icon' => '⚙️', 'label' => 'プロフィール', 'url' => '/staff/profile.php'],
        ],
        'guardian' => [
            ['page' => 'dashboard', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/guardian/dashboard.php'],
            ['page' => 'communication_logs', 'icon' => '📚', 'label' => '連絡帳一覧', 'url' => '/guardian/communication_logs.php'],
            ['page' => 'chat', 'icon' => '💬', 'label' => 'チャット', 'url' => '/guardian/chat.php'],
            ['page' => 'weekly_plan', 'icon' => '📝', 'label' => '週間計画表', 'url' => '/guardian/weekly_plan.php'],
            ['page' => 'kakehashi', 'icon' => '🌉', 'label' => 'かけはし入力', 'url' => '/guardian/kakehashi.php'],
            ['page' => 'kakehashi_history', 'icon' => '📚', 'label' => 'かけはし履歴', 'url' => '/guardian/kakehashi_history.php'],
            ['page' => 'newsletters', 'icon' => '📰', 'label' => '施設通信', 'url' => '/guardian/newsletters.php'],
            ['page' => 'support_plans', 'icon' => '📋', 'label' => '個別支援計画書', 'url' => '/guardian/support_plans.php'],
            ['page' => 'monitoring', 'icon' => '📊', 'label' => 'モニタリング表', 'url' => '/guardian/monitoring.php'],
            ['page' => 'manual', 'icon' => '📖', 'label' => 'ご利用ガイド', 'url' => '/guardian/manual.php'],
            ['page' => 'profile', 'icon' => '👤', 'label' => 'プロフィール', 'url' => '/guardian/profile.php'],
            ['page' => 'change_password', 'icon' => '🔐', 'label' => 'パスワード変更', 'url' => '/guardian/change_password.php'],
        ],
        'student' => [
            ['page' => 'dashboard', 'icon' => '🏠', 'label' => 'マイページ', 'url' => '/student/dashboard.php'],
            ['page' => 'chat', 'icon' => '💬', 'label' => 'チャット', 'url' => '/student/chat.php'],
            ['page' => 'weekly_plan', 'icon' => '📝', 'label' => '週間計画', 'url' => '/student/weekly_plan.php'],
            ['page' => 'submissions', 'icon' => '📋', 'label' => '提出物', 'url' => '/student/submissions.php'],
            ['page' => 'schedule', 'icon' => '📅', 'label' => 'スケジュール', 'url' => '/student/schedule.php'],
            ['page' => 'change_password', 'icon' => '🔐', 'label' => 'パスワード変更', 'url' => '/student/change_password.php'],
        ],
        'tablet_user' => [
            ['page' => 'renrakucho_form', 'icon' => '📝', 'label' => '本日の記録', 'url' => '/tablet/renrakucho_form.php'],
            ['page' => 'renrakucho_activities', 'icon' => '📊', 'label' => '活動記録', 'url' => '/tablet/renrakucho_activities.php'],
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
