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

// ロール別メニュー定義（Material Symbols アイコン名）
$menuConfig = [
    'admin' => [
        ['page' => 'index', 'icon' => 'home', 'label' => 'ダッシュボード', 'url' => '/admin/index.php'],
        // 施設管理者専用（マスターには非表示）
        ['page' => 'students', 'icon' => 'group', 'label' => '生徒登録・変更', 'url' => '/admin/students.php', 'non_master' => true],
        ['page' => 'guardians', 'icon' => 'person', 'label' => '保護者登録・変更', 'url' => '/admin/guardians.php', 'non_master' => true],
        ['page' => 'waiting_list', 'icon' => 'hourglass_empty', 'label' => '待機児童管理', 'url' => '/admin/waiting_list.php', 'non_master' => true],
        ['page' => 'staff_management', 'icon' => 'manage_accounts', 'label' => 'スタッフ管理', 'url' => '/admin/staff_management.php', 'non_master' => true],
        ['page' => 'tablet_accounts', 'icon' => 'tablet', 'label' => 'タブレットユーザー', 'url' => '/admin/tablet_accounts.php', 'non_master' => true],
        ['page' => 'events', 'icon' => 'event', 'label' => 'イベント管理', 'url' => '/admin/events.php', 'non_master' => true],
        ['page' => 'holidays', 'icon' => 'calendar_today', 'label' => '休日管理', 'url' => '/admin/holidays.php', 'non_master' => true],
        // マスター専用
        ['page' => 'classrooms', 'icon' => 'apartment', 'label' => '教室管理', 'url' => '/admin/classrooms.php', 'master_only' => true],
        ['page' => 'admin_accounts', 'icon' => 'shield_person', 'label' => '管理者アカウント', 'url' => '/admin/admin_accounts.php', 'master_only' => true],
        ['page' => 'staff_accounts', 'icon' => 'badge', 'label' => 'スタッフアカウント', 'url' => '/admin/staff_accounts.php', 'master_only' => true],
    ],
    'staff' => [
        // 日常業務
        ['page' => 'renrakucho_activities', 'icon' => 'home', 'label' => '活動管理', 'url' => '/staff/renrakucho_activities.php'],
        ['page' => 'makeup_requests', 'icon' => 'sync', 'label' => '振替管理', 'url' => '/staff/makeup_requests.php'],
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
        ['page' => 'students', 'icon' => 'group', 'label' => '生徒登録・変更', 'url' => '/staff/students.php'],
        ['page' => 'guardians', 'icon' => 'person', 'label' => '保護者登録・変更', 'url' => '/staff/guardians.php'],
        ['page' => 'waiting_list', 'icon' => 'hourglass_empty', 'label' => '待機児童管理', 'url' => '/admin/waiting_list.php'],
        ['page' => 'additional_usage', 'icon' => 'edit_calendar', 'label' => '利用日一括変更', 'url' => '/staff/additional_usage.php'],
        ['page' => 'school_holiday_activities', 'icon' => 'school', 'label' => '学校休業日設定', 'url' => '/staff/school_holiday_activities.php'],
        ['page' => 'holidays', 'icon' => 'calendar_today', 'label' => '休日設定', 'url' => '/staff/holidays.php'],
        ['page' => 'bulk_register', 'icon' => 'upload', 'label' => '利用者一括登録', 'url' => '/staff/bulk_register.php'],
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

// ロール別タイトルとカラー（Material Symbols アイコン名）
$roleConfig = [
    'admin' => ['title' => '管理者メニュー', 'color' => 'purple', 'icon' => 'settings'],
    'staff' => ['title' => 'スタッフメニュー', 'color' => 'blue', 'icon' => 'badge'],
    'guardian' => ['title' => '連絡帳', 'color' => 'green', 'icon' => 'menu_book'],
    'student' => ['title' => '生徒メニュー', 'color' => 'orange', 'icon' => 'backpack'],
    'tablet_user' => ['title' => 'タブレット', 'color' => 'teal', 'icon' => 'tablet'],
];

$config = $roleConfig[$role] ?? $roleConfig['staff'];
$menuItems = $menuConfig[$role] ?? [];
$showSidebarNotifications = in_array($role, ['staff', 'admin', 'guardian']);

// 通知データを取得（グローバル変数から）
$notificationData = $notificationData ?? ['notifications' => [], 'totalCount' => 0];

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
<nav class="sidebar sidebar--<?= $config['color'] ?>" id="mainSidebar">
    <div class="sidebar-header">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ" class="sidebar-logo">
        <?php else: ?>
            <img src="/uploads/kiduri.png" alt="きづり" class="sidebar-logo">
        <?php endif; ?>
        <div class="sidebar-header-text">
            <h1><?= htmlspecialchars($config['title']) ?></h1>
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name'] ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showSidebarNotifications && $notificationData['totalCount'] > 0): ?>
    <div class="sidebar-notification-wrapper">
        <a href="<?= $role === 'guardian' ? '/guardian/dashboard.php' : '/staff/pending_tasks.php' ?>" class="sidebar-notification-btn">
            <span class="material-symbols-outlined">notifications</span>
            <span>通知</span>
            <span class="sidebar-notification-badge"><?= $notificationData['totalCount'] > 99 ? '99+' : $notificationData['totalCount'] ?></span>
        </a>
    </div>
    <?php endif; ?>

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
                    <span class="material-symbols-outlined menu-icon"><?= $item['icon'] ?></span>
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
        <a href="/logout.php" class="sidebar-logout">ログアウト</a>
    </div>
</nav>

<!-- サイドバートグルボタン（モバイルでは非表示） -->
<style>
@media (max-width: 768px) {
    #sidebarToggle { display: none !important; }
}
</style>
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="メニューを表示/非表示">
    <span class="sidebar-toggle-icon" id="sidebarToggleIcon">◀</span>
</button>

<script>
// サイドバートグル機能
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('sidebarToggleIcon');
    const mainContent = document.querySelector('.main-content');

    sidebar.classList.toggle('collapsed');
    toggleBtn.classList.toggle('sidebar-hidden');

    if (sidebar.classList.contains('collapsed')) {
        toggleIcon.textContent = '▶';
        if (mainContent) mainContent.classList.add('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'true');
    } else {
        toggleIcon.textContent = '◀';
        if (mainContent) mainContent.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
    }
}

// 初期状態を復元
document.addEventListener('DOMContentLoaded', function() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 768) {
        const sidebar = document.getElementById('mainSidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const toggleIcon = document.getElementById('sidebarToggleIcon');
        const mainContent = document.querySelector('.main-content');

        if (sidebar && toggleBtn && toggleIcon) {
            sidebar.classList.add('collapsed');
            toggleBtn.classList.add('sidebar-hidden');
            toggleIcon.textContent = '▶';
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
        }
    }
});
</script>
