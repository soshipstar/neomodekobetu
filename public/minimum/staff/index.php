<?php
/**
 * スタッフ用ダッシュボード
 * ミニマム版
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

// 統計情報を取得
$stats = [
    'pending_kakehashi' => 0,
    'pending_plans' => 0,
    'pending_monitoring' => 0,
    'unread_chats' => 0,
];

// 未提出かけはし数（職員分）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT kp.id)
        FROM kakehashi_periods kp
        INNER JOIN students s ON kp.student_id = s.id
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id
        WHERE s.classroom_id = ?
        AND s.is_active = 1
        AND kp.is_active = 1
        AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
        AND (ks.is_hidden = 0 OR ks.is_hidden IS NULL)
    ");
    $stmt->execute([$classroomId]);
    $stats['pending_kakehashi'] = $stmt->fetchColumn();

    // 未作成個別支援計画数
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        LEFT JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.classroom_id = ?
        AND s.is_active = 1
        AND isp.id IS NULL
    ");
    $stmt->execute([$classroomId]);
    $stats['pending_plans'] = $stmt->fetchColumn();

    // 未完了モニタリング数
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM monitoring_records mr
        INNER JOIN individual_support_plans isp ON mr.plan_id = isp.id
        INNER JOIN students s ON isp.student_id = s.id
        WHERE s.classroom_id = ?
        AND s.is_active = 1
        AND (mr.overall_comment IS NULL OR mr.overall_comment = '')
    ");
    $stmt->execute([$classroomId]);
    $stats['pending_monitoring'] = $stmt->fetchColumn();

    // 未読チャット数
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM chat_messages cm
        INNER JOIN chat_rooms cr ON cm.room_id = cr.id
        INNER JOIN students s ON cr.student_id = s.id
        WHERE s.classroom_id = ?
        AND cm.sender_type = 'guardian'
        AND cm.is_read = 0
    ");
    $stmt->execute([$classroomId]);
    $stats['unread_chats'] = $stmt->fetchColumn();
}

// ページ開始
$currentPage = 'index';
renderPageStart('staff', $currentPage, 'スタッフダッシュボード', [
    'classroom' => $classroom
]);
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">スタッフダッシュボード</h1>
        <p class="page-subtitle">
            <?php if ($classroom): ?>
                <?= htmlspecialchars($classroom['classroom_name']) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- 統計カード -->
<div class="stats-grid">
    <a href="kakehashi_staff.php" class="stat-card clickable">
        <h3>未提出かけはし</h3>
        <div class="number <?= $stats['pending_kakehashi'] > 0 ? 'warning' : '' ?>"><?= $stats['pending_kakehashi'] ?></div>
    </a>
    <a href="kobetsu_plan.php" class="stat-card clickable">
        <h3>未作成計画</h3>
        <div class="number <?= $stats['pending_plans'] > 0 ? 'warning' : '' ?>"><?= $stats['pending_plans'] ?></div>
    </a>
    <a href="kobetsu_monitoring.php" class="stat-card clickable">
        <h3>未完了モニタリング</h3>
        <div class="number <?= $stats['pending_monitoring'] > 0 ? 'warning' : '' ?>"><?= $stats['pending_monitoring'] ?></div>
    </a>
    <a href="chat.php" class="stat-card clickable">
        <h3>未読メッセージ</h3>
        <div class="number <?= $stats['unread_chats'] > 0 ? 'alert' : '' ?>"><?= $stats['unread_chats'] ?></div>
    </a>
</div>

<!-- クイックメニュー -->
<div class="menu-grid">
    <a href="chat.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span></div>
        <h3>保護者チャット</h3>
        <p>保護者とのメッセージのやり取りを行います。</p>
    </a>

    <a href="kakehashi_staff.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span></div>
        <h3>かけはし（職員）</h3>
        <p>職員用のかけはし情報を入力・管理します。</p>
    </a>

    <a href="kakehashi_guardian_view.php" class="menu-card">
        <div class="menu-card-icon">📖</div>
        <h3>かけはし（保護者）</h3>
        <p>保護者が入力したかけはし情報を確認します。</p>
    </a>

    <a href="kobetsu_plan.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span></div>
        <h3>個別支援計画</h3>
        <p>個別支援計画書の作成・管理を行います。</p>
    </a>

    <a href="kobetsu_monitoring.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span></div>
        <h3>モニタリング</h3>
        <p>モニタリング表の作成・評価を行います。</p>
    </a>
</div>

<style>
.stat-card.clickable {
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card.clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.stat-card .number.warning {
    color: #f59e0b;
}
.stat-card .number.alert {
    color: #ef4444;
}
</style>

<?php renderPageEnd(); ?>
