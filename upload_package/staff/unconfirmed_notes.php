<?php
/**
 * 未確認連絡帳一覧ページ
 * 保護者がまだ確認していない連絡帳を一覧表示
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// フィルター
$filterDays = isset($_GET['days']) ? (int)$_GET['days'] : 7; // デフォルト7日間
$filterDays = min(max($filterDays, 1), 90); // 1〜90日の範囲

// 未確認の連絡帳を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT
            inote.id,
            inote.integrated_content,
            inote.sent_at,
            inote.guardian_confirmed,
            dr.record_date,
            dr.activity_name,
            s.id as student_id,
            s.student_name,
            s.grade_level,
            u.full_name as guardian_name,
            DATEDIFF(NOW(), inote.sent_at) as days_since_sent
        FROM integrated_notes inote
        INNER JOIN students s ON inote.student_id = s.id
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        INNER JOIN users staff ON dr.staff_id = staff.id
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE inote.is_sent = 1
        AND inote.guardian_confirmed = 0
        AND inote.sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND staff.classroom_id = ?
        ORDER BY inote.sent_at ASC
    ");
    $stmt->execute([$filterDays, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT
            inote.id,
            inote.integrated_content,
            inote.sent_at,
            inote.guardian_confirmed,
            dr.record_date,
            dr.activity_name,
            s.id as student_id,
            s.student_name,
            s.grade_level,
            u.full_name as guardian_name,
            DATEDIFF(NOW(), inote.sent_at) as days_since_sent
        FROM integrated_notes inote
        INNER JOIN students s ON inote.student_id = s.id
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE inote.is_sent = 1
        AND inote.guardian_confirmed = 0
        AND inote.sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY inote.sent_at ASC
    ");
    $stmt->execute([$filterDays]);
}
$unconfirmedNotes = $stmt->fetchAll();

// 統計情報
$totalUnconfirmed = count($unconfirmedNotes);
$urgentCount = 0; // 3日以上未確認
$warningCount = 0; // 1-2日未確認
foreach ($unconfirmedNotes as $note) {
    if ($note['days_since_sent'] >= 3) {
        $urgentCount++;
    } elseif ($note['days_since_sent'] >= 1) {
        $warningCount++;
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

function getGradeBadgeClass($gradeLevel) {
    $classes = [
        'elementary' => 'badge-elementary',
        'junior_high' => 'badge-junior-high',
        'high_school' => 'badge-high-school'
    ];
    return $classes[$gradeLevel] ?? 'badge-primary';
}

// ページ開始
$currentPage = 'unconfirmed_notes';
renderPageStart('staff', $currentPage, '未確認連絡帳一覧');
?>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.stat-card {
    background: var(--apple-bg-primary);
    padding: 20px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    text-align: center;
}

.stat-card.urgent { border-left: 4px solid var(--apple-red); }
.stat-card.warning { border-left: 4px solid var(--apple-orange); }
.stat-card.total { border-left: 4px solid var(--apple-blue); }

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-number.urgent { color: var(--apple-red); }
.stat-number.warning { color: var(--apple-orange); }
.stat-number.total { color: var(--apple-blue); }
.stat-label { color: var(--text-secondary); font-size: var(--text-subhead); }

.note-card {
    background: var(--apple-bg-primary);
    padding: 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--apple-gray-4);
}

.note-card.urgent {
    border-left-color: var(--apple-red);
    background: rgba(255,59,48,0.03);
}

.note-card.warning { border-left-color: var(--apple-orange); }

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.student-info { display: flex; align-items: center; gap: 10px; }
.student-name { font-size: 18px; font-weight: bold; color: var(--text-primary); }

.grade-badge {
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: var(--text-caption-1);
    color: white;
}

.badge-elementary { background: var(--grade-elementary); }
.badge-junior-high { background: var(--grade-junior-high); }
.badge-high-school { background: var(--grade-high-school); }

.days-badge {
    padding: 6px 12px;
    border-radius: var(--radius-full);
    font-size: var(--text-caption-1);
    font-weight: bold;
}

.days-badge.urgent { background: var(--apple-red); color: white; }
.days-badge.warning { background: var(--apple-orange); color: white; }
.days-badge.normal { background: var(--apple-gray-5); color: var(--text-primary); }

.note-meta { color: var(--text-secondary); font-size: var(--text-footnote); margin-bottom: 12px; }

.note-content {
    color: var(--text-primary);
    font-size: var(--text-subhead);
    line-height: 1.6;
    white-space: pre-wrap;
    background: var(--apple-gray-6);
    padding: 12px;
    border-radius: var(--radius-sm);
    max-height: 150px;
    overflow-y: auto;
}

.guardian-info {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--apple-gray-5);
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--apple-bg-primary);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

.empty-state h2 { color: var(--apple-green); margin-bottom: var(--spacing-md); }
.empty-icon { font-size: 64px; margin-bottom: var(--spacing-md); }

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--apple-gray-5); }

.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: var(--spacing-lg);
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 768px) {
    .stats-row { grid-template-columns: 1fr; }
    .filter-row { flex-direction: column; align-items: stretch; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">未確認連絡帳一覧</h1>
        <p class="page-subtitle">保護者がまだ確認していない連絡帳</p>
    </div>
</div>

<div class="filter-row">
    <a href="renrakucho_activities.php" class="quick-link">← 活動一覧に戻る</a>
    <form class="filter-form" method="GET">
        <label>表示期間:</label>
        <select name="days" onchange="this.form.submit()" class="form-control" style="width: auto;">
            <option value="3" <?= $filterDays == 3 ? 'selected' : '' ?>>過去3日間</option>
            <option value="7" <?= $filterDays == 7 ? 'selected' : '' ?>>過去7日間</option>
            <option value="14" <?= $filterDays == 14 ? 'selected' : '' ?>>過去14日間</option>
            <option value="30" <?= $filterDays == 30 ? 'selected' : '' ?>>過去30日間</option>
        </select>
    </form>
</div>

        <!-- 統計カード -->
        <div class="stats-row">
            <div class="stat-card total">
                <div class="stat-number total"><?php echo $totalUnconfirmed; ?></div>
                <div class="stat-label">未確認の連絡帳</div>
            </div>
            <div class="stat-card urgent">
                <div class="stat-number urgent"><?php echo $urgentCount; ?></div>
                <div class="stat-label">3日以上未確認（要対応）</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number warning"><?php echo $warningCount; ?></div>
                <div class="stat-label">1〜2日未確認</div>
            </div>
        </div>

        <?php if (empty($unconfirmedNotes)): ?>
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <h2>未確認の連絡帳はありません</h2>
                <p>過去<?php echo $filterDays; ?>日間に送信した連絡帳はすべて保護者に確認されています。</p>
            </div>
        <?php else: ?>
            <?php foreach ($unconfirmedNotes as $note):
                $urgencyClass = '';
                $daysClass = 'normal';
                if ($note['days_since_sent'] >= 3) {
                    $urgencyClass = 'urgent';
                    $daysClass = 'urgent';
                } elseif ($note['days_since_sent'] >= 1) {
                    $urgencyClass = 'warning';
                    $daysClass = 'warning';
                }
            ?>
                <div class="note-card <?php echo $urgencyClass; ?>">
                    <div class="note-header">
                        <div class="student-info">
                            <span class="student-name"><?php echo htmlspecialchars($note['student_name']); ?></span>
                            <span class="grade-badge <?php echo getGradeBadgeClass($note['grade_level']); ?>">
                                <?php echo getGradeLabel($note['grade_level']); ?>
                            </span>
                        </div>
                        <span class="days-badge <?php echo $daysClass; ?>">
                            <?php if ($note['days_since_sent'] == 0): ?>
                                今日送信
                            <?php else: ?>
                                <?php echo $note['days_since_sent']; ?>日経過
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="note-meta">
                        <strong>活動:</strong> <?php echo htmlspecialchars($note['activity_name']); ?> |
                        <strong>記録日:</strong> <?php echo date('Y年n月j日', strtotime($note['record_date'])); ?> |
                        <strong>送信日時:</strong> <?php echo date('Y年n月j日 H:i', strtotime($note['sent_at'])); ?>
                    </div>
                    <div class="note-content"><?php echo htmlspecialchars($note['integrated_content']); ?></div>
                    <?php if ($note['guardian_name']): ?>
                        <div class="guardian-info">
                            👤 保護者: <?php echo htmlspecialchars($note['guardian_name']); ?>さん
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

<?php renderPageEnd(); ?>
