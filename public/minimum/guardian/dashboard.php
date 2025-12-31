<?php
/**
 * 保護者用ダッシュボード
 * ミニマム版
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();

// 保護者でない場合は適切なページへリダイレクト
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /minimum/index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// この保護者に紐づく生徒を取得（在籍中のみ）
$students = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, student_name, grade_level, status
        FROM students
        WHERE guardian_id = ? AND is_active = 1 AND status = 'active'
        ORDER BY student_name
    ");
    $stmt->execute([$guardianId]);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
}

// 未読チャットメッセージを取得
$unreadChatMessages = [];
$totalUnreadMessages = 0;
try {
    $stmt = $pdo->prepare("
        SELECT
            cr.id as room_id,
            s.student_name,
            COUNT(cm.id) as unread_count
        FROM chat_rooms cr
        INNER JOIN students s ON cr.student_id = s.id
        INNER JOIN chat_messages cm ON cr.id = cm.room_id
        WHERE cr.guardian_id = ?
        AND cm.sender_type = 'staff'
        AND cm.is_read = 0
        GROUP BY cr.id, s.student_name
    ");
    $stmt->execute([$guardianId]);
    $unreadChatMessages = $stmt->fetchAll();
    $totalUnreadMessages = array_sum(array_column($unreadChatMessages, 'unread_count'));
} catch (Exception $e) {
    error_log("Error fetching unread chat messages: " . $e->getMessage());
}

// 未提出かけはしを取得
$pendingKakehashi = [];
$today = date('Y-m-d');

foreach ($students as $student) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                kp.id as period_id,
                kp.period_name,
                kp.submission_deadline,
                DATEDIFF(kp.submission_deadline, ?) as days_left,
                kg.is_submitted
            FROM kakehashi_periods kp
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = ?
            WHERE kp.student_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND (kg.is_hidden = 0 OR kg.is_hidden IS NULL)
            ORDER BY kp.submission_deadline ASC
        ");
        $stmt->execute([$today, $student['id'], $student['id']]);
        $kakehashi = $stmt->fetchAll();

        foreach ($kakehashi as $k) {
            $k['student_name'] = $student['student_name'];
            $k['student_id'] = $student['id'];
            $pendingKakehashi[] = $k;
        }
    } catch (Exception $e) {
        error_log("Error fetching pending kakehashi: " . $e->getMessage());
    }
}

// 未確認の個別支援計画を取得
$pendingPlans = [];
foreach ($students as $student) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, student_name, created_date
            FROM individual_support_plans
            WHERE student_id = ?
            AND (guardian_confirmed = 0 OR guardian_confirmed IS NULL)
            ORDER BY created_date DESC
        ");
        $stmt->execute([$student['id']]);
        $plans = $stmt->fetchAll();
        foreach ($plans as $plan) {
            $plan['student_name'] = $student['student_name'];
            $pendingPlans[] = $plan;
        }
    } catch (Exception $e) {
        error_log("Error fetching pending plans: " . $e->getMessage());
    }
}

// 未確認のモニタリングを取得
$pendingMonitoring = [];
foreach ($students as $student) {
    try {
        $stmt = $pdo->prepare("
            SELECT mr.id, mr.student_name, mr.monitoring_date
            FROM monitoring_records mr
            WHERE mr.student_id = ?
            AND (mr.guardian_confirmed = 0 OR mr.guardian_confirmed IS NULL)
            ORDER BY mr.monitoring_date DESC
        ");
        $stmt->execute([$student['id']]);
        $monitoring = $stmt->fetchAll();
        foreach ($monitoring as $m) {
            $pendingMonitoring[] = $m;
        }
    } catch (Exception $e) {
        error_log("Error fetching pending monitoring: " . $e->getMessage());
    }
}

// ページ開始
$currentPage = 'dashboard';
renderPageStart('guardian', $currentPage, 'ダッシュボード', [
    'classroom' => $classroom
]);
?>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">ダッシュボード</h1>
        <p class="page-subtitle"><?= htmlspecialchars($_SESSION['full_name']) ?>さん</p>
    </div>
</div>

<!-- 通知セクション -->
<?php if ($totalUnreadMessages > 0): ?>
<div class="alert alert-info">
    <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span> 未読メッセージがあります</strong>
    <p><?= $totalUnreadMessages ?>件の新しいメッセージがあります。</p>
    <a href="chat.php" class="btn btn-primary btn-sm">チャットを開く</a>
</div>
<?php endif; ?>

<?php if (count($pendingKakehashi) > 0): ?>
<div class="alert alert-warning">
    <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span> かけはしの提出をお願いします</strong>
    <p><?= count($pendingKakehashi) ?>件の未提出かけはしがあります。</p>
    <a href="kakehashi.php" class="btn btn-warning btn-sm">かけはし入力へ</a>
</div>
<?php endif; ?>

<?php if (count($pendingPlans) > 0): ?>
<div class="alert alert-info">
    <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 個別支援計画の確認をお願いします</strong>
    <p><?= count($pendingPlans) ?>件の未確認計画があります。</p>
    <a href="support_plans.php" class="btn btn-primary btn-sm">計画を確認</a>
</div>
<?php endif; ?>

<?php if (count($pendingMonitoring) > 0): ?>
<div class="alert alert-info">
    <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> モニタリング表の確認をお願いします</strong>
    <p><?= count($pendingMonitoring) ?>件の未確認モニタリングがあります。</p>
    <a href="monitoring.php" class="btn btn-primary btn-sm">モニタリングを確認</a>
</div>
<?php endif; ?>

<!-- お子様情報 -->
<div class="card">
    <div class="card-header">
        <h2>お子様情報</h2>
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
            <p class="text-muted">登録されているお子様はいません。</p>
        <?php else: ?>
            <div class="student-list">
                <?php foreach ($students as $student): ?>
                <div class="student-card">
                    <div class="student-name"><?= htmlspecialchars($student['student_name']) ?></div>
                    <div class="student-grade">
                        <?php
                        $gradeLabels = [
                            'elementary' => '小学生',
                            'junior_high' => '中学生',
                            'high_school' => '高校生'
                        ];
                        echo $gradeLabels[$student['grade_level']] ?? $student['grade_level'];
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- クイックメニュー -->
<div class="menu-grid">
    <a href="chat.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">chat</span></div>
        <h3>チャット</h3>
        <p>スタッフとメッセージのやり取りができます。</p>
        <?php if ($totalUnreadMessages > 0): ?>
            <span class="badge badge-danger"><?= $totalUnreadMessages ?>件未読</span>
        <?php endif; ?>
    </a>

    <a href="kakehashi.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">handshake</span></div>
        <h3>かけはし入力</h3>
        <p>かけはし情報を入力します。</p>
        <?php if (count($pendingKakehashi) > 0): ?>
            <span class="badge badge-warning"><?= count($pendingKakehashi) ?>件未提出</span>
        <?php endif; ?>
    </a>

    <a href="kakehashi_history.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span></div>
        <h3>かけはし履歴</h3>
        <p>過去のかけはし情報を確認できます。</p>
    </a>

    <a href="support_plans.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span></div>
        <h3>個別支援計画書</h3>
        <p>お子様の個別支援計画を確認できます。</p>
    </a>

    <a href="monitoring.php" class="menu-card">
        <div class="menu-card-icon"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span></div>
        <h3>モニタリング表</h3>
        <p>支援の進捗状況を確認できます。</p>
    </a>
</div>

<style>
/* カード間の余白 */
.card {
    margin-bottom: 24px;
    padding: 0;
}
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--md-gray-5);
}
.card-header h2 {
    margin: 0;
    font-size: 18px;
}
.card-body {
    padding: 20px;
}
.menu-grid {
    margin-top: 0;
}
.student-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.student-card {
    background: var(--md-bg-secondary);
    padding: 16px;
    border-radius: 12px;
}
.student-name {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
}
.student-grade {
    color: var(--text-secondary);
    font-size: 14px;
}
.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 8px;
    margin-top: 8px;
}
.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}
.badge-warning {
    background: #fef3c7;
    color: #d97706;
}
.alert {
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}
.alert-info {
    background: linear-gradient(135deg, #dbeafe, #e0e7ff);
    border-left: 4px solid #3b82f6;
}
.alert-warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-left: 4px solid #f59e0b;
}
.alert strong {
    display: block;
    margin-bottom: 8px;
}
.alert .btn {
    margin-top: 8px;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
</style>

<?php renderPageEnd(); ?>
