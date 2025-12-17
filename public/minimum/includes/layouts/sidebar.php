<?php
/**
 * 統一サイドバーコンポーネント
 * ミニマム版用（マスター、管理者、スタッフ、保護者の4ロール）
 */

// デフォルト値
$role = $role ?? ($_SESSION['user_type'] ?? 'staff');
$currentPage = $currentPage ?? '';
$classroom = $classroom ?? null;
$isMaster = function_exists('isMasterAdmin') ? isMasterAdmin() : false;

// ロール別メニュー定義（ミニマム版）
$menuConfig = [
    'admin' => [
        ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/minimum/admin/index.php'],
        // 施設管理者専用（マスターには非表示）
        ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/minimum/admin/students.php', 'non_master' => true],
        ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/minimum/admin/guardians.php', 'non_master' => true],
        ['page' => 'staff_management', 'icon' => '👨‍💼', 'label' => 'スタッフ管理', 'url' => '/minimum/admin/staff_management.php', 'non_master' => true],
        // マスター専用
        ['page' => 'classrooms', 'icon' => '🏢', 'label' => '教室管理', 'url' => '/minimum/admin/classrooms.php', 'master_only' => true],
        ['page' => 'admin_accounts', 'icon' => '👑', 'label' => '管理者アカウント', 'url' => '/minimum/admin/admin_accounts.php', 'master_only' => true],
        ['page' => 'staff_accounts', 'icon' => '👔', 'label' => 'スタッフアカウント', 'url' => '/minimum/admin/staff_accounts.php', 'master_only' => true],
    ],
    'staff' => [
        // ダッシュボード
        ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/minimum/staff/index.php'],
        // チャット
        ['type' => 'divider', 'label' => 'チャット'],
        ['page' => 'chat', 'icon' => '👨‍👩‍👧', 'label' => '保護者チャット', 'url' => '/minimum/staff/chat.php'],
        // かけはし
        ['type' => 'divider', 'label' => 'かけはし'],
        ['page' => 'kakehashi_staff', 'icon' => '🌉', 'label' => 'かけはし（職員）', 'url' => '/minimum/staff/kakehashi_staff.php'],
        ['page' => 'kakehashi_guardian_view', 'icon' => '📖', 'label' => 'かけはし（保護者）', 'url' => '/minimum/staff/kakehashi_guardian_view.php'],
        // 計画・支援
        ['type' => 'divider', 'label' => '計画・支援'],
        ['page' => 'kobetsu_plan', 'icon' => '📋', 'label' => '個別支援計画', 'url' => '/minimum/staff/kobetsu_plan.php'],
        ['page' => 'kobetsu_monitoring', 'icon' => '📊', 'label' => 'モニタリング', 'url' => '/minimum/staff/kobetsu_monitoring.php'],
        // 管理
        ['type' => 'divider', 'label' => '管理'],
        ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/minimum/staff/students.php'],
        ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/minimum/staff/guardians.php'],
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

// ロール別タイトルとカラー（ミニマム版：3ロールのみ）
$roleConfig = [
    'admin' => ['title' => '管理者メニュー', 'color' => 'purple', 'icon' => '⚙️'],
    'staff' => ['title' => 'スタッフメニュー', 'color' => 'blue', 'icon' => '👨‍💼'],
    'guardian' => ['title' => '保護者メニュー', 'color' => 'green', 'icon' => '📖'],
];

$config = $roleConfig[$role] ?? $roleConfig['staff'];
$menuItems = $menuConfig[$role] ?? [];

// ユーザー情報
$userName = $_SESSION['full_name'] ?? '';
$userTypeLabel = match($role) {
    'admin' => $isMaster ? 'マスター管理者' : '管理者',
    'staff' => 'スタッフ',
    'guardian' => '保護者',
    default => ''
};
?>
<!-- PC用サイドバー -->
<nav class="sidebar sidebar--<?= $config['color'] ?>">
    <div class="sidebar-header">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ" class="sidebar-logo">
        <?php else: ?>
            <span class="logo-emoji"><?= $config['icon'] ?></span>
        <?php endif; ?>
        <div class="sidebar-header-text">
            <h1><?= htmlspecialchars($config['title']) ?></h1>
            <div class="system-badge">ミニマム版</div>
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name'] ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-menu">
        <?php foreach ($menuItems as $item): ?>
            <?php
            // 区切り線の場合
            if (isset($item['type']) && $item['type'] === 'divider'):
            ?>
                <div class="menu-divider">
                    <span class="divider-label"><?= htmlspecialchars($item['label']) ?></span>
                </div>
            <?php
            else:
                // マスター専用項目のチェック
                if (!empty($item['master_only']) && !$isMaster) continue;
                // 施設管理者専用項目のチェック（マスターには非表示）
                if (!empty($item['non_master']) && $isMaster) continue;

                $isActive = ($currentPage === $item['page']);
                $activeClass = $isActive ? 'active' : '';
                $masterClass = !empty($item['master_only']) ? 'master-only' : '';
            ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $activeClass ?> <?= $masterClass ?>">
                    <span class="menu-icon"><?= $item['icon'] ?></span>
                    <?= htmlspecialchars($item['label']) ?>
                    <?php if (!empty($item['master_only'])): ?>
                        <span class="master-badge">★</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <span class="user-name"><?= htmlspecialchars($userName) ?>さん</span>
            <span class="user-type"><?= htmlspecialchars($userTypeLabel) ?></span>
        </div>
        <a href="/minimum/logout.php" class="sidebar-logout">ログアウト</a>
    </div>
</nav>
