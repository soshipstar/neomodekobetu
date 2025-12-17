<?php
/**
 * ページラッパー関数
 * ミニマム版用
 */

/**
 * ページの開始部分をレンダリング
 *
 * @param string $role ユーザーロール (admin, staff, guardian)
 * @param string $currentPage 現在のページ識別子
 * @param string $pageTitle ページタイトル
 * @param array $options 追加オプション
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

    // ロール別CSS（既存のCSSを参照）
    $roleCssMap = [
        'admin' => '/assets/css/admin.css',
        'staff' => '/assets/css/staff.css',
        'guardian' => '/assets/css/guardian.css',
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
        default => 'role-staff'
    };
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - 個別支援システム（ミニマム版）</title>

    <!-- 共通CSS（既存を参照） -->
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <link rel="stylesheet" href="/assets/css/layout.css">

    <!-- ロール別CSS（既存を参照） -->
    <?php if ($roleCss): ?>
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

    <style>
    /* ミニマム版バッジ */
    .system-badge {
        display: inline-block;
        background: linear-gradient(135deg, #f093fb, #f5576c);
        color: white;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 10px;
        margin-top: 4px;
        font-weight: 600;
    }
    </style>

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
 * メニュー設定を取得（ミニマム版）
 */
function getMenuConfig(): array
{
    return [
        'admin' => [
            ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/minimum/admin/index.php'],
            ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/minimum/admin/students.php'],
            ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/minimum/admin/guardians.php'],
            ['page' => 'staff_management', 'icon' => '👨‍💼', 'label' => 'スタッフ管理', 'url' => '/minimum/admin/staff_management.php'],
            ['page' => 'classrooms', 'icon' => '🏢', 'label' => '教室管理', 'url' => '/minimum/admin/classrooms.php', 'master_only' => true],
            ['page' => 'admin_accounts', 'icon' => '👑', 'label' => '管理者アカウント', 'url' => '/minimum/admin/admin_accounts.php', 'master_only' => true],
            ['page' => 'staff_accounts', 'icon' => '👔', 'label' => 'スタッフアカウント', 'url' => '/minimum/admin/staff_accounts.php', 'master_only' => true],
        ],
        'staff' => [
            ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/minimum/staff/index.php'],
            ['type' => 'divider', 'label' => 'チャット'],
            ['page' => 'chat', 'icon' => '👨‍👩‍👧', 'label' => '保護者チャット', 'url' => '/minimum/staff/chat.php'],
            ['type' => 'divider', 'label' => 'かけはし'],
            ['page' => 'kakehashi_staff', 'icon' => '🌉', 'label' => 'かけはし（職員）', 'url' => '/minimum/staff/kakehashi_staff.php'],
            ['page' => 'kakehashi_guardian_view', 'icon' => '📖', 'label' => 'かけはし（保護者）', 'url' => '/minimum/staff/kakehashi_guardian_view.php'],
            ['type' => 'divider', 'label' => '計画・支援'],
            ['page' => 'kobetsu_plan', 'icon' => '📋', 'label' => '個別支援計画', 'url' => '/minimum/staff/kobetsu_plan.php'],
            ['page' => 'kobetsu_monitoring', 'icon' => '📊', 'label' => 'モニタリング', 'url' => '/minimum/staff/kobetsu_monitoring.php'],
        ],
        'guardian' => [
            ['page' => 'dashboard', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/minimum/guardian/dashboard.php'],
            ['page' => 'chat', 'icon' => '💬', 'label' => 'チャット', 'url' => '/minimum/guardian/chat.php'],
            ['page' => 'kakehashi', 'icon' => '🌉', 'label' => 'かけはし入力', 'url' => '/minimum/guardian/kakehashi.php'],
            ['page' => 'kakehashi_history', 'icon' => '📚', 'label' => 'かけはし履歴', 'url' => '/minimum/guardian/kakehashi_history.php'],
            ['page' => 'support_plans', 'icon' => '📋', 'label' => '個別支援計画書', 'url' => '/minimum/guardian/support_plans.php'],
            ['page' => 'monitoring', 'icon' => '📊', 'label' => 'モニタリング表', 'url' => '/minimum/guardian/monitoring.php'],
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
