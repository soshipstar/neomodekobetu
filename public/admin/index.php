<?php
/**
 * 管理者用トップページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

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
$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'active_students' => 0,
    'total_records' => 0,
];

// 通常管理者の場合は教室でフィルタリング
$classroomId = (!$isMaster && isset($_SESSION['classroom_id'])) ? $_SESSION['classroom_id'] : null;

if ($classroomId) {
    // 通常管理者：自分の教室のデータのみ
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['total_students'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1 AND classroom_id = ?");
    $stmt->execute([$classroomId]);
    $stats['active_students'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE u.classroom_id = ?
    ");
    $stmt->execute([$classroomId]);
    $stats['total_records'] = $stmt->fetchColumn();
} else {
    // マスター管理者：全教室のデータ
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $stats['total_students'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $stats['active_students'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM daily_records");
    $stats['total_records'] = $stmt->fetchColumn();
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
    <div class="stat-card">
        <h3>総記録数</h3>
        <div class="number"><?= $stats['total_records'] ?></div>
    </div>
</div>

<!-- メニュー -->
<div class="menu-grid">
    <a href="students.php" class="menu-card">
        <div class="menu-card-icon">👥</div>
        <h3>生徒管理</h3>
        <p>生徒の登録・編集・削除を行います。学年や保護者の紐付け設定も可能です。</p>
    </a>

    <a href="guardians.php" class="menu-card">
        <div class="menu-card-icon">👤</div>
        <h3>保護者管理</h3>
        <p>保護者アカウントの登録・編集を行います。生徒との紐付け管理も可能です。</p>
    </a>

    <a href="staff_management.php" class="menu-card">
        <div class="menu-card-icon">👨‍💼</div>
        <h3>スタッフ管理</h3>
        <p>スタッフアカウントの登録・編集・削除を行います。</p>
    </a>

    <a href="tablet_accounts.php" class="menu-card">
        <div class="menu-card-icon">📱</div>
        <h3>タブレットユーザー管理</h3>
        <p>タブレットユーザーアカウントの登録・編集を行います。</p>
    </a>

    <a href="events.php" class="menu-card">
        <div class="menu-card-icon">📅</div>
        <h3>イベント管理</h3>
        <p>施設のイベントや予定を管理します。</p>
    </a>

    <a href="holidays.php" class="menu-card">
        <div class="menu-card-icon">🗓️</div>
        <h3>休日管理</h3>
        <p>休日・祝日の設定を管理します。</p>
    </a>

    <a href="classroom_settings.php" class="menu-card">
        <div class="menu-card-icon">⚙️</div>
        <h3>教室基本設定</h3>
        <p>教室の基本情報や対象学年の設定を行います。</p>
    </a>

    <?php if ($isMaster): ?>
    <a href="classrooms.php" class="menu-card master-only">
        <div class="menu-card-icon">🏢</div>
        <h3>教室管理 <span class="master-badge">★マスター専用</span></h3>
        <p>全教室の登録・編集・削除を行います。複数教室の一括管理が可能です。</p>
    </a>

    <a href="admin_accounts.php" class="menu-card master-only">
        <div class="menu-card-icon">👑</div>
        <h3>管理者アカウント <span class="master-badge">★マスター専用</span></h3>
        <p>管理者アカウントの作成・編集・削除、教室への割り当てを管理します。</p>
    </a>

    <a href="staff_accounts.php" class="menu-card master-only">
        <div class="menu-card-icon">👔</div>
        <h3>スタッフアカウント <span class="master-badge">★マスター専用</span></h3>
        <p>スタッフアカウントの作成・編集・削除を管理します。</p>
    </a>
    <?php endif; ?>
</div>

<?php renderPageEnd(); ?>
