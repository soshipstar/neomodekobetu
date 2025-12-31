<?php
/**
 * 管理者用トップページ
 * ミニマム版
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType('admin');

$pdo = getDbConnection();

// マスター管理者かどうかを確認
$isMaster = isMasterAdmin();

// 現在の管理者の教室情報を取得
$classroom = null;
if (!$isMaster && isset($_SESSION['classroom_id']) && $_SESSION['classroom_id']) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$_SESSION['classroom_id']]);
    $classroom = $stmt->fetch();
}

// 統計情報を取得
$stats = [];

if ($isMaster) {
    // マスター管理者：教室・アカウント関連の統計
    $stmt = $pdo->query("SELECT COUNT(*) FROM classrooms");
    $stats['total_classrooms'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin' AND is_active = 1");
    $stats['total_admins'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'staff' AND is_active = 1");
    $stats['total_staff'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM classrooms WHERE service_type = 'minimum'");
    $stats['minimum_classrooms'] = $stmt->fetchColumn();
} else {
    // 施設管理者：自分の教室のデータのみ
    $classroomId = $_SESSION['classroom_id'] ?? null;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['total_students'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1 AND classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['active_students'] = $stmt->fetchColumn();
}

// ページ開始
$currentPage = 'index';
renderPageStart('admin', $currentPage, '管理者ダッシュボード', [
    'classroom' => $classroom
]);
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">管理者ダッシュボード</h1>
        <p class="page-subtitle">
            <?php if ($isMaster): ?>
                マスター管理者 - 全教室管理
            <?php elseif ($classroom): ?>
                <?= htmlspecialchars($classroom['classroom_name']) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- 統計情報 -->
<div class="stats-grid">
    <?php if ($isMaster): ?>
    <div class="stat-card">
        <h3>登録教室数</h3>
        <div class="number"><?= $stats['total_classrooms'] ?></div>
    </div>
    <div class="stat-card">
        <h3>管理者数</h3>
        <div class="number"><?= $stats['total_admins'] ?></div>
    </div>
    <div class="stat-card">
        <h3>スタッフ数</h3>
        <div class="number"><?= $stats['total_staff'] ?></div>
    </div>
    <div class="stat-card">
        <h3>ミニマム版教室</h3>
        <div class="number"><?= $stats['minimum_classrooms'] ?></div>
    </div>
    <?php else: ?>
    <div class="stat-card">
        <h3>登録ユーザー数</h3>
        <div class="number"><?= $stats['total_users'] ?></div>
    </div>
    <div class="stat-card">
        <h3>登録生徒数</h3>
        <div class="number"><?= $stats['total_students'] ?></div>
    </div>
    <div class="stat-card">
        <h3>有効な生徒数</h3>
        <div class="number"><?= $stats['active_students'] ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- メニュー -->
<div class="menu-grid">
    <?php if ($isMaster): ?>
    <!-- マスター管理者用メニュー -->
    <a href="classrooms.php" class="menu-card">
        <div class="menu-card-icon">🏢</div>
        <h3>教室管理</h3>
        <p>全教室の登録・編集・削除を行います。複数教室の一括管理が可能です。</p>
    </a>

    <a href="admin_accounts.php" class="menu-card">
        <div class="menu-card-icon">👑</div>
        <h3>管理者アカウント</h3>
        <p>管理者アカウントの作成・編集・削除、教室への割り当てを管理します。</p>
    </a>

    <a href="staff_accounts.php" class="menu-card">
        <div class="menu-card-icon">👔</div>
        <h3>スタッフアカウント</h3>
        <p>スタッフアカウントの作成・編集・削除を管理します。</p>
    </a>
    <?php else: ?>
    <!-- 施設管理者用メニュー -->
    <a href="students.php" class="menu-card">
        <div class="menu-card-icon">👥</div>
        <h3>生徒管理</h3>
        <p>生徒の登録・編集・削除を行います。</p>
    </a>

    <a href="guardians.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span></div>
        <h3>保護者管理</h3>
        <p>保護者アカウントの登録・編集を行います。</p>
    </a>

    <a href="staff_management.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">badge</span></div>
        <h3>スタッフ管理</h3>
        <p>スタッフアカウントの登録・編集・削除を行います。</p>
    </a>
    <?php endif; ?>
</div>

<?php renderPageEnd(); ?>
