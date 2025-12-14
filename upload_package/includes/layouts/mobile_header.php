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
 * オプション: $classroom, $menuItems (sidebar.phpから引き継ぎ)
 */

$pageTitle = $pageTitle ?? 'メニュー';
$role = $role ?? ($_SESSION['user_type'] ?? 'staff');
$classroom = $classroom ?? null;

// ロール別カラーとアイコン
$roleConfig = [
    'admin' => ['color' => 'purple', 'icon' => '⚙️'],
    'staff' => ['color' => 'blue', 'icon' => '👨‍💼'],
    'guardian' => ['color' => 'green', 'icon' => '📖'],
    'student' => ['color' => 'orange', 'icon' => '🎒'],
    'tablet_user' => ['color' => 'teal', 'icon' => '📱'],
];

$config = $roleConfig[$role] ?? $roleConfig['staff'];
$userName = $_SESSION['full_name'] ?? '';
?>
<!-- モバイル用ヘッダー -->
<div class="mobile-header mobile-header--<?= $config['color'] ?>">
    <div class="mobile-header-top">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path'])): ?>
            <img src="/<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ">
        <?php else: ?>
            <span class="logo-emoji"><?= $config['icon'] ?></span>
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
                📑 メニュー ▼
            </button>
            <div class="menu-content" id="menuDropdown">
                <?php if (isset($menuItems) && is_array($menuItems)): ?>
                    <?php foreach ($menuItems as $item): ?>
                        <?php if (!empty($item['master_only']) && !$isMaster) continue; ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>">
                            <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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
