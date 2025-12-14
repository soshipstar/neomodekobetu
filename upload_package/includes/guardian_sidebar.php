<?php
/**
 * 保護者用サイドバー共通コンポーネント
 * 使用方法: include __DIR__ . '/../includes/guardian_sidebar.php';
 * 必要な変数: $currentPage (現在のページ名), $classroom (教室情報)
 */

// 現在のページが設定されていない場合はデフォルト値
$currentPage = $currentPage ?? '';
?>
<!-- PC用サイドバー -->
<nav class="sidebar">
    <div class="sidebar-header">
        <?php if (isset($classroom) && $classroom && !empty($classroom['logo_path']) && file_exists(__DIR__ . '/../' . $classroom['logo_path'])): ?>
            <img src="../<?= htmlspecialchars($classroom['logo_path']) ?>" alt="教室ロゴ">
        <?php else: ?>
            <span class="logo-emoji">📖</span>
        <?php endif; ?>
        <div class="sidebar-header-text">
            <h1>連絡帳</h1>
            <?php if (isset($classroom) && $classroom): ?>
                <div class="classroom-name"><?= htmlspecialchars($classroom['classroom_name']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" <?= $currentPage === 'dashboard' ? 'class="active"' : '' ?>>
            <span class="menu-icon">🏠</span>ダッシュボード
        </a>
        <a href="communication_logs.php" <?= $currentPage === 'communication_logs' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📚</span>連絡帳一覧
        </a>
        <a href="chat.php" <?= $currentPage === 'chat' ? 'class="active"' : '' ?>>
            <span class="menu-icon">💬</span>チャット
        </a>
        <a href="weekly_plan.php" <?= $currentPage === 'weekly_plan' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📝</span>週間計画表
        </a>
        <a href="kakehashi.php" <?= $currentPage === 'kakehashi' ? 'class="active"' : '' ?>>
            <span class="menu-icon">🌉</span>かけはし入力
        </a>
        <a href="kakehashi_history.php" <?= $currentPage === 'kakehashi_history' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📚</span>かけはし履歴
        </a>
        <a href="newsletters.php" <?= $currentPage === 'newsletters' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📰</span>施設通信
        </a>
        <a href="support_plans.php" <?= $currentPage === 'support_plans' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📋</span>個別支援計画書
        </a>
        <a href="monitoring.php" <?= $currentPage === 'monitoring' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📊</span>モニタリング表
        </a>
        <a href="manual.php" <?= $currentPage === 'manual' ? 'class="active"' : '' ?>>
            <span class="menu-icon">📖</span>ご利用ガイド
        </a>
        <a href="profile.php" <?= $currentPage === 'profile' ? 'class="active"' : '' ?>>
            <span class="menu-icon">👤</span>プロフィール編集
        </a>
        <a href="change_password.php" <?= $currentPage === 'change_password' ? 'class="active"' : '' ?>>
            <span class="menu-icon">🔐</span>パスワード変更
        </a>
    </div>
    <div class="sidebar-footer">
        <div class="sidebar-user-info">
            <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>さん
        </div>
        <a href="/logout.php" class="sidebar-logout">ログアウト</a>
    </div>
</nav>
