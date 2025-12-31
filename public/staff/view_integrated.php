<?php
/**
 * 統合内容閲覧ページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

if (!$activityId) {
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動情報を取得（同じ教室のスタッフが作成した活動も閲覧可能）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
               u.full_name as staff_name
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$activityId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id,
               u.full_name as staff_name
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ?
    ");
    $stmt->execute([$activityId]);
}
$activity = $stmt->fetch();

if (!$activity) {
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 送信済みの統合内容のみを取得（保護者確認状況も含める）
$stmt = $pdo->prepare("
    SELECT
        inote.id,
        inote.integrated_content,
        inote.is_sent,
        inote.sent_at,
        inote.created_at,
        inote.guardian_confirmed,
        inote.guardian_confirmed_at,
        s.student_name,
        s.grade_level
    FROM integrated_notes inote
    INNER JOIN students s ON inote.student_id = s.id
    WHERE inote.daily_record_id = ? AND inote.is_sent = 1
    ORDER BY inote.guardian_confirmed ASC, s.student_name
");
$stmt->execute([$activityId]);
$integratedNotes = $stmt->fetchAll();

// 確認状況の集計
$totalCount = count($integratedNotes);
$confirmedCount = 0;
$unconfirmedCount = 0;
foreach ($integratedNotes as $note) {
    if ($note['guardian_confirmed']) {
        $confirmedCount++;
    } else {
        $unconfirmedCount++;
    }
}

function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生'
    ];
    return $labels[$gradeLevel] ?? '';
}

// ページ開始
$currentPage = 'view_integrated';
renderPageStart('staff', $currentPage, '送信済み内容の閲覧');
?>

<style>
.note-card {
    background: var(--md-bg-primary);
    padding: 25px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.student-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--primary-purple);
    flex-wrap: wrap;
    gap: 10px;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-name {
    font-size: 20px;
    font-weight: bold;
    color: var(--text-primary);
}

.grade-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: var(--radius-lg);
    font-size: var(--text-caption-1);
    color: white;
    background: var(--primary-purple);
}

.status-badge {
    padding: 4px 12px;
    border-radius: var(--radius-lg);
    font-size: var(--text-caption-1);
    font-weight: bold;
}

.status-sent {
    background: rgba(52,199,89,0.15);
    color: var(--md-green);
}

.note-content {
    color: var(--text-primary);
    line-height: 1.8;
    white-space: pre-wrap;
    font-size: 15px;
    margin-bottom: 15px;
}

.note-meta {
    color: var(--text-secondary);
    font-size: var(--text-footnote);
    padding-top: 10px;
    border-top: 1px solid var(--md-gray-5);
}

.confirmation-summary {
    background: var(--md-bg-primary);
    padding: 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-icon { font-size: 24px; }
.summary-label { color: var(--text-secondary); font-size: var(--text-subhead); }
.summary-count { font-size: 24px; font-weight: bold; }
.summary-count.confirmed { color: var(--md-green); }
.summary-count.unconfirmed { color: var(--md-red); }
.summary-count.total { color: var(--text-primary); }

.status-confirmed {
    background: rgba(52,199,89,0.15);
    color: var(--md-green);
    border: 1px solid var(--md-green);
}

.status-unconfirmed {
    background: rgba(255,59,48,0.15);
    color: var(--md-red);
    border: 1px solid var(--md-red);
}

.note-card.unconfirmed { border-left: 4px solid var(--md-red); }
.note-card.confirmed { border-left: 4px solid var(--md-green); }

.confirmation-info {
    margin-top: 10px;
    padding: 10px;
    background: rgba(52,199,89,0.1);
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
    color: var(--md-green);
}

.confirmation-info.pending {
    background: rgba(255,59,48,0.1);
    color: var(--md-red);
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--md-gray-5); }

.activity-info-box {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.activity-info-box p {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    margin-bottom: var(--spacing-sm);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">送信済み内容の閲覧</h1>
        <p class="page-subtitle"><?= htmlspecialchars($activity['activity_name']) ?></p>
    </div>
</div>

<a href="renrakucho_activities.php?date=<?= $activity['record_date'] ?>" class="quick-link">← 活動一覧に戻る</a>

<div class="activity-info-box">
    <p><strong>活動名:</strong> <?= htmlspecialchars($activity['activity_name']) ?></p>
    <p><strong>記録日:</strong> <?= date('Y年n月j日', strtotime($activity['record_date'])) ?></p>
    <p><strong>作成者:</strong> <?= htmlspecialchars($activity['staff_name']) ?>
        <?php if ($activity['staff_id'] == $currentUser['id']): ?>
            <span style="color: var(--primary-purple); font-weight: bold;">(自分)</span>
        <?php endif; ?>
    </p>
</div>

        <?php if (empty($integratedNotes)): ?>
            <div class="empty-message">
                <h2>送信済みの内容がありません</h2>
                <p>「統合内容を編集」から統合内容を編集し、保護者に送信してください。</p>
            </div>
        <?php else: ?>
            <!-- 確認状況サマリー -->
            <div class="confirmation-summary">
                <div class="summary-item">
                    <span class="summary-icon"><span class="material-symbols-outlined">monitoring</span></span>
                    <span class="summary-label">保護者確認状況</span>
                </div>
                <div class="summary-item">
                    <span class="summary-count total"><?php echo $totalCount; ?></span>
                    <span class="summary-label">件中</span>
                </div>
                <div class="summary-item">
                    <span class="summary-count confirmed"><?php echo $confirmedCount; ?></span>
                    <span class="summary-label">件確認済み</span>
                </div>
                <?php if ($unconfirmedCount > 0): ?>
                <div class="summary-item">
                    <span class="summary-count unconfirmed"><?php echo $unconfirmedCount; ?></span>
                    <span class="summary-label">件未確認</span>
                </div>
                <?php endif; ?>
            </div>

            <?php foreach ($integratedNotes as $note): ?>
                <div class="note-card <?php echo $note['guardian_confirmed'] ? 'confirmed' : 'unconfirmed'; ?>">
                    <div class="student-header">
                        <div class="student-info">
                            <span class="student-name"><?php echo htmlspecialchars($note['student_name']); ?></span>
                            <span class="grade-badge"><?php echo getGradeLabel($note['grade_level']); ?></span>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <span class="status-badge status-sent">送信済み</span>
                            <?php if ($note['guardian_confirmed']): ?>
                                <span class="status-badge status-confirmed">確認済み</span>
                            <?php else: ?>
                                <span class="status-badge status-unconfirmed">未確認</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="note-content">
                        <?php echo htmlspecialchars($note['integrated_content']); ?>
                    </div>

                    <div class="note-meta">
                        統合日時: <?php echo date('Y年n月j日 H:i', strtotime($note['created_at'])); ?>
                        <?php if ($note['is_sent']): ?>
                            | 送信日時: <?php echo date('Y年n月j日 H:i', strtotime($note['sent_at'])); ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($note['guardian_confirmed']): ?>
                        <div class="confirmation-info">
                            <span class="material-symbols-outlined">check_circle</span> 保護者確認日時: <?php echo date('Y年n月j日 H:i', strtotime($note['guardian_confirmed_at'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="confirmation-info pending">
                            <span class="material-symbols-outlined">warning</span> まだ保護者が確認していません
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

<?php renderPageEnd(); ?>
