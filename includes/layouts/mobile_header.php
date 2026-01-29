<?php
/**
 * 統一モバイルヘッダーコンポーネント
 *
 * 使用方法:
 *   $role = 'admin';
 *   $pageTitle = 'ダッシュボード';
 *   include __DIR__ . '/mobile_header.php';
 *
 * 必要な変数: $role, $pageTitle
 * オプション: $classroom, $menuItems, $notificationData (page_wrapperから引き継ぎ)
 */

// 通知ベルコンポーネントを読み込み
require_once __DIR__ . '/../components/notification_bell.php';

$pageTitle = $pageTitle ?? 'メニュー';
$role = $role ?? ($_SESSION['user_type'] ?? 'staff');
$classroom = $classroom ?? null;
$notificationData = $notificationData ?? ['notifications' => [], 'totalCount' => 0];
$isMaster = $isMaster ?? (function_exists('isMasterAdmin') ? isMasterAdmin() : false);

// ロール別カラーとアイコン（Material Symbols アイコン名）
$roleConfig = [
    'admin' => ['color' => 'purple', 'icon' => 'settings'],
    'staff' => ['color' => 'blue', 'icon' => 'badge'],
    'guardian' => ['color' => 'green', 'icon' => 'menu_book'],
    'student' => ['color' => 'orange', 'icon' => 'backpack'],
    'tablet_user' => ['color' => 'teal', 'icon' => 'tablet'],
];

$config = $roleConfig[$role] ?? $roleConfig['staff'];
$userName = $_SESSION['full_name'] ?? '';
$showNotifications = in_array($role, ['staff', 'admin', 'guardian']);
?>
<!-- モバイル用ヘッダー -->
<div class="mobile-header mobile-header--<?= $config['color'] ?>">
    <div class="mobile-header-top">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ">
        <?php else: ?>
            <span class="material-symbols-outlined logo-icon" style="font-size: 32px; color: var(--primary-purple);"><?= $config['icon'] ?></span>
        <?php endif; ?>
        <div class="mobile-header-info">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name'] ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mobile-header-bottom">
        <div class="menu-dropdown">
            <button class="menu-btn" onclick="toggleMenu()">
                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">menu</span> メニュー ▼
            </button>
            <div class="menu-content" id="menuDropdown">
                <?php if (isset($menuItems) && is_array($menuItems)): ?>
                    <?php foreach ($menuItems as $item): ?>
                        <?php
                        // 区切り線の場合
                        if (isset($item['type']) && $item['type'] === 'divider'):
                        ?>
                            <div class="mobile-menu-divider">
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </div>
                        <?php
                        else:
                            if (!empty($item['master_only']) && !$isMaster) continue;
                        ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>">
                                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle; margin-right: 8px;"><?= $item['icon'] ?></span><?= htmlspecialchars($item['label']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($showNotifications): ?>
            <?php renderNotificationBell($notificationData, $role); ?>
        <?php endif; ?>
        <span class="user-info-box">
            <?= htmlspecialchars($userName) ?>さん
        </span>
        <a href="/logout.php" class="logout-btn">ログアウト</a>
    </div>
</div>

<script>
// モバイルメニュー開閉
function toggleMenu() {
    const menu = document.getElementById('menuDropdown');
    if (menu) {
        menu.classList.toggle('show');
    }
}

// メニュー外をクリックで閉じる
document.addEventListener('click', function(event) {
    const menu = document.getElementById('menuDropdown');
    const menuBtn = document.querySelector('.menu-btn');
    if (menu && menuBtn && !menu.contains(event.target) && !menuBtn.contains(event.target)) {
        menu.classList.remove('show');
    }
});
</script>
