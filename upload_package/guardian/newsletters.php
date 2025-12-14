<?php
/**
 * 保護者向け施設通信閲覧ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// 保護者のみアクセス可能
requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 教室情報を取得
$classroom = null;
$classroomStmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$classroomStmt->execute([$currentUser['id']]);
$classroom = $classroomStmt->fetch();

// 保護者の教室IDを取得
$guardianId = $_SESSION['user_id'];
$classroomId = $classroom['id'] ?? null;

// 発行済み通信を取得（自分の教室のもののみ、新しい順）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM newsletters
        WHERE status = 'published' AND classroom_id = ?
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE 1=0");
    $stmt->execute();
}
$newsletters = $stmt->fetchAll();

// 詳細表示用の通信
$selectedNewsletter = null;
if (isset($_GET['id'])) {
    $newsletterId = $_GET['id'];
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT * FROM newsletters
            WHERE id = ? AND status = 'published' AND classroom_id = ?
        ");
        $stmt->execute([$newsletterId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE 1=0");
        $stmt->execute();
    }
    $selectedNewsletter = $stmt->fetch();
}

// ページ開始
$currentPage = 'newsletters';
renderPageStart('guardian', $currentPage, '施設通信', ['classroom' => $classroom]);
?>

<style>
.newsletters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.newsletter-card {
    background: var(--apple-bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
    text-decoration: none;
    color: inherit;
    display: block;
}

.newsletter-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.newsletter-card h3 {
    color: var(--apple-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
}

.newsletter-meta {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.newsletter-date {
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    margin-top: var(--spacing-sm);
    padding-top: var(--spacing-sm);
    border-top: 1px solid var(--apple-gray-5);
}

.newsletter-detail {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    line-height: 1.8;
}

.newsletter-detail h2 {
    color: var(--text-primary);
    font-size: var(--text-title-2);
    text-align: center;
    margin-bottom: var(--spacing-lg);
    padding-bottom: var(--spacing-md);
    border-bottom: 3px solid var(--apple-purple);
}

.detail-metadata {
    text-align: right;
    color: var(--text-secondary);
    font-size: var(--text-footnote);
    margin-bottom: var(--spacing-2xl);
}

.detail-section {
    margin: var(--spacing-2xl) 0;
}

.detail-section h3 {
    color: var(--apple-purple);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
    padding: var(--spacing-md) var(--spacing-md);
    background: var(--apple-gray-6);
    border-left: 5px solid var(--apple-purple);
    border-radius: var(--radius-sm);
}

.detail-section-content {
    padding: var(--spacing-md) 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.greeting-section {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-sm);
    margin: var(--spacing-lg) 0;
    border-left: 4px solid var(--apple-orange);
}

@media print {
    .page-header, .btn { display: none; }
    .newsletter-detail { box-shadow: none; padding: var(--spacing-lg); }
}

@media (max-width: 768px) {
    .newsletters-grid { grid-template-columns: 1fr; }
    .newsletter-detail { padding: var(--spacing-lg); }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">施設通信</h1>
        <p class="page-subtitle">施設からのお知らせをご確認ください</p>
    </div>
    <?php if ($selectedNewsletter): ?>
        <button onclick="window.print()" class="btn btn-secondary">🖨️ 印刷</button>
    <?php endif; ?>
</div>

<?php if ($selectedNewsletter): ?>
    <!-- 通信詳細表示 -->
    <div class="newsletter-detail">
        <h2><?= htmlspecialchars($selectedNewsletter['title'], ENT_QUOTES, 'UTF-8') ?></h2>

        <div class="detail-metadata">
            報告期間: <?= date('Y年m月d日', strtotime($selectedNewsletter['report_start_date'])) ?>
            ～ <?= date('Y年m月d日', strtotime($selectedNewsletter['report_end_date'])) ?><br>
            予定期間: <?= date('Y年m月d日', strtotime($selectedNewsletter['schedule_start_date'])) ?>
            ～ <?= date('Y年m月d日', strtotime($selectedNewsletter['schedule_end_date'])) ?><br>
            発行日: <?= date('Y年m月d日', strtotime($selectedNewsletter['published_at'])) ?>
        </div>

        <?php if (!empty($selectedNewsletter['greeting'])): ?>
        <div class="greeting-section">
            <?= nl2br(htmlspecialchars($selectedNewsletter['greeting'], ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['event_calendar'])): ?>
        <div class="detail-section">
            <h3>📅 今月の予定</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['event_calendar'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['event_details'])): ?>
        <div class="detail-section">
            <h3>📝 イベント詳細</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['event_details'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['weekly_reports'])): ?>
        <div class="detail-section">
            <h3>📖 各曜日の活動報告</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['weekly_reports'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['event_results'])): ?>
        <div class="detail-section">
            <h3>🎉 イベント結果報告</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['event_results'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['requests'])): ?>
        <div class="detail-section">
            <h3>🙏 施設からのお願い</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['requests'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($selectedNewsletter['others'])): ?>
        <div class="detail-section">
            <h3>📌 その他</h3>
            <div class="detail-section-content">
                <?= htmlspecialchars($selectedNewsletter['others'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: var(--spacing-2xl);">
            <a href="newsletters.php" class="btn btn-primary">← 一覧に戻る</a>
        </div>
    </div>

<?php else: ?>
    <!-- 通信一覧表示 -->
    <?php if (empty($newsletters)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: var(--spacing-3xl);">
                <div style="font-size: 64px; margin-bottom: var(--spacing-lg);">📭</div>
                <p style="color: var(--text-secondary);">まだ通信が発行されていません</p>
            </div>
        </div>
    <?php else: ?>
        <div class="newsletters-grid">
            <?php foreach ($newsletters as $newsletter): ?>
                <a href="newsletters.php?id=<?= $newsletter['id'] ?>" class="newsletter-card">
                    <h3><?= htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="newsletter-meta">
                        報告: <?= date('Y/m/d', strtotime($newsletter['report_start_date'])) ?>
                        ～ <?= date('Y/m/d', strtotime($newsletter['report_end_date'])) ?>
                    </div>
                    <div class="newsletter-meta">
                        予定: <?= date('Y/m/d', strtotime($newsletter['schedule_start_date'])) ?>
                        ～ <?= date('Y/m/d', strtotime($newsletter['schedule_end_date'])) ?>
                    </div>
                    <div class="newsletter-date">
                        発行日: <?= date('Y年m月d日', strtotime($newsletter['published_at'])) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php renderPageEnd(); ?>
