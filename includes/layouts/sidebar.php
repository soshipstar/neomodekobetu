<?php
/**
 * 統一サイドバーコンポーネント
 *
 * 使用方法:
 *   $role = 'admin';  // admin, staff, guardian, student, tablet
 *   $currentPage = 'index';
 *   include __DIR__ . '/sidebar.php';
 *
 * 必要な変数: $role, $currentPage
 * オプション: $classroom (教室情報)
 */

// デフォルト値
$role = $role ?? ($_SESSION['user_type'] ?? 'staff');
$currentPage = $currentPage ?? '';
$classroom = $classroom ?? null;
$isMaster = function_exists('isMasterAdmin') ? isMasterAdmin() : false;

// ロール別メニュー定義
$menuConfig = [
    'admin' => [
        ['page' => 'index', 'icon' => '🏠', 'label' => 'ダッシュボード', 'url' => '/admin/index.php'],
        ['page' => 'students', 'icon' => '👥', 'label' => '生徒管理', 'url' => '/admin/students.php'],
        ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/admin/guardians.php'],
        ['page' => 'staff_management', 'icon' => '👨‍💼', 'label' => 'スタッフ管理', 'url' => '/admin/staff_management.php'],
        ['page' => 'tablet_accounts', 'icon' => '📱', 'label' => 'タブレットユーザー', 'url' => '/admin/tablet_accounts.php'],
        ['page' => 'events', 'icon' => '📅', 'label' => 'イベント管理', 'url' => '/admin/events.php'],
        ['page' => 'holidays', 'icon' => '🗓️', 'label' => '休日管理', 'url' => '/admin/holidays.php'],
        // マスター専用
        ['page' => 'classrooms', 'icon' => '🏢', 'label' => '教室管理', 'url' => '/admin/classrooms.php', 'master_only' => true],
        ['page' => 'admin_accounts', 'icon' => '👑', 'label' => '管理者アカウント', 'url' => '/admin/admin_accounts.php', 'master_only' => true],
        ['page' => 'staff_accounts', 'icon' => '👔', 'label' => 'スタッフアカウント', 'url' => '/admin/staff_accounts.php', 'master_only' => true],
    ],
    'staff' => [
        ['page' => 'renrakucho_activities', 'icon' => '🏠', 'label' => '活動管理', 'url' => '/staff/renrakucho_activities.php'],
        ['page' => 'students', 'icon' => '👥', 'label' => '生徒一覧', 'url' => '/staff/students.php'],
        ['page' => 'chat', 'icon' => '💬', 'label' => 'チャット', 'url' => '/staff/chat.php'],
        ['page' => 'kakehashi_staff', 'icon' => '🌉', 'label' => 'かけはし（職員）', 'url' => '/staff/kakehashi_staff.php'],
        ['page' => 'kakehashi_guardian_view', 'icon' => '📖', 'label' => 'かけはし（保護者）', 'url' => '/staff/kakehashi_guardian_view.php'],
        ['page' => 'kakehashi_periods', 'icon' => '📆', 'label' => 'かけはし期間', 'url' => '/staff/kakehashi_periods.php'],
        ['page' => 'student_weekly_plans', 'icon' => '📝', 'label' => '週間計画', 'url' => '/staff/student_weekly_plans.php'],
        ['page' => 'support_plans', 'icon' => '📋', 'label' => '個別支援計画', 'url' => '/staff/support_plans.php'],
        ['page' => 'kobetsu_monitoring', 'icon' => '📊', 'label' => 'モニタリング', 'url' => '/staff/kobetsu_monitoring.php'],
        ['page' => 'newsletter_create', 'icon' => '📰', 'label' => '施設通信', 'url' => '/staff/newsletter_create.php'],
        ['page' => 'events', 'icon' => '📅', 'label' => 'イベント', 'url' => '/staff/events.php'],
        ['page' => 'holidays', 'icon' => '🗓️', 'label' => '休日設定', 'url' => '/staff/holidays.php'],
        ['page' => 'guardians', 'icon' => '👤', 'label' => '保護者管理', 'url' => '/staff/guardians.php'],
        ['page' => 'student_chats', 'icon' => '💭', 'label' => '生徒チャット', 'url' => '/staff/student_chats.php'],
        ['page' => 'student_submissions', 'icon' => '📤', 'label' => '生徒提出物', 'url' => '/staff/student_submissions.php'],
        ['page' => 'submission_management', 'icon' => '📥', 'label' => '提出物管理', 'url' => '/staff/submission_management.php'],
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

// ロール別タイトルとカラー
$roleConfig = [
    'admin' => ['title' => '管理者メニュー', 'color' => 'purple', 'icon' => '⚙️'],
    'staff' => ['title' => 'スタッフメニュー', 'color' => 'blue', 'icon' => '👨‍💼'],
    'guardian' => ['title' => '連絡帳', 'color' => 'green', 'icon' => '📖'],
    'student' => ['title' => '生徒メニュー', 'color' => 'orange', 'icon' => '🎒'],
    'tablet_user' => ['title' => 'タブレット', 'color' => 'teal', 'icon' => '📱'],
];

$config = $roleConfig[$role] ?? $roleConfig['staff'];
$menuItems = $menuConfig[$role] ?? [];

// ユーザー情報
$userName = $_SESSION['full_name'] ?? '';
$userTypeLabel = match($role) {
    'admin' => $isMaster ? 'マスター管理者' : '管理者',
    'staff' => 'スタッフ',
    'guardian' => '保護者',
    'student' => '生徒',
    'tablet_user' => 'タブレット',
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
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name'] ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-menu">
        <?php foreach ($menuItems as $item): ?>
            <?php
            // マスター専用項目のチェック
            if (!empty($item['master_only']) && !$isMaster) continue;

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
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <span class="user-name"><?= htmlspecialchars($userName) ?>さん</span>
            <span class="user-type"><?= htmlspecialchars($userTypeLabel) ?></span>
        </div>
        <a href="/logout.php" class="sidebar-logout">ログアウト</a>
    </div>
</nav>
