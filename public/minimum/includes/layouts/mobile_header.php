<?php
/**
 * 統一モバイルヘッダーコンポーネント
 * ミニマム版用
 */

$pageTitle = $pageTitle ?? 'メニュー';
$role = $role ?? ($_SESSION['user_type'] ?? 'staff');
$classroom = $classroom ?? null;
$isMaster = $isMaster ?? false;

// ロール別カラーとアイコン（ミニマム版：3ロールのみ）
$roleConfig = [
    'admin' => ['color' => 'purple', 'icon' => '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">settings</span>'],
    'staff' => ['color' => 'blue', 'icon' => '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span>'],
    'guardian' => ['color' => 'green', 'icon' => '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span>'],
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
            <span class="system-badge-mobile">ミニマム版</span>
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
                                <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <span class="user-info-box">
            <?= htmlspecialchars($userName) ?>さん
        </span>
        <a href="/minimum/logout.php" class="logout-btn">ログアウト</a>
    </div>
</div>

<style>
.system-badge-mobile {
    display: inline-block;
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
    font-size: 9px;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 8px;
    font-weight: 600;
    vertical-align: middle;
}
</style>

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
