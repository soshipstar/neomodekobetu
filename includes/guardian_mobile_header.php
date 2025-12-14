<?php
/**
 * 保護者用モバイルヘッダー共通コンポーネント
 * 使用方法: include __DIR__ . '/../includes/guardian_mobile_header.php';
 * 必要な変数: $pageTitle (ページタイトル), $classroom (教室情報)
 */

$pageTitle = $pageTitle ?? '連絡帳';
?>
<!-- モバイル用ヘッダー -->
<div class="mobile-header">
    <div class="mobile-header-top">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path']) && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
            <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ">
        <?php else: ?>
            <span class="logo-emoji">📖</span>
        <?php endif; ?>
        <div class="mobile-header-info">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mobile-header-bottom">
        <div class="menu-dropdown">
            <button class="menu-btn" onclick="toggleMenu()">
                📑 メニュー ▼
            </button>
            <div class="menu-content" id="menuDropdown">
                <a href="dashboard.php">🏠 ダッシュボード</a>
                <a href="communication_logs.php">📚 連絡帳一覧</a>
                <a href="chat.php">💬 チャット</a>
                <a href="weekly_plan.php">📝 週間計画表</a>
                <a href="kakehashi.php">🌉 かけはし入力</a>
                <a href="kakehashi_history.php">📚 かけはし履歴</a>
                <a href="newsletters.php">📰 施設通信</a>
                <a href="support_plans.php">📋 個別支援計画書</a>
                <a href="monitoring.php">📊 モニタリング表</a>
                <a href="manual.php">📖 ご利用ガイド</a>
                <a href="profile.php">👤 プロフィール編集</a>
                <a href="change_password.php">🔐 パスワード変更</a>
            </div>
        </div>
        <span class="user-info-box">
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>さん
        </span>
        <a href="/logout.php" class="logout-btn">ログアウト</a>
    </div>
</div>

<script>
// モバイルメニュー開閉
function toggleMenu() {
    const menu = document.getElementById('menuDropdown');
    menu.classList.toggle('show');
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
