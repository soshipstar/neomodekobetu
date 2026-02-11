<?php
/**
 * 事業所評価集計・公表ページ
 * 回答データの集計、AI要約、PDF出力
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

$periodId = $_GET['period_id'] ?? null;
if (!$periodId) {
    header('Location: facility_evaluation.php');
    exit;
}

// 評価期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ?");
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period) {
    header('Location: facility_evaluation.php');
    exit;
}

// 集計処理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'aggregate') {
            try {
                // 保護者評価の集計
                $stmt = $pdo->prepare("
                    SELECT q.id as question_id, q.question_type,
                           SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count,
                           SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
                           SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count,
                           SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count,
                           COUNT(a.answer) as total_count
                    FROM facility_evaluation_questions q
                    LEFT JOIN facility_guardian_evaluation_answers a ON q.id = a.question_id
                    LEFT JOIN facility_guardian_evaluations e ON a.evaluation_id = e.id AND e.period_id = ? AND e.is_submitted = 1
                    WHERE q.question_type = 'guardian'
                    GROUP BY q.id
                ");
                $stmt->execute([$periodId]);
                $guardianStats = $stmt->fetchAll();

                // スタッフ評価の集計
                $stmt = $pdo->prepare("
                    SELECT q.id as question_id, q.question_type,
                           SUM(CASE WHEN a.answer = 'yes' THEN 1 ELSE 0 END) as yes_count,
                           SUM(CASE WHEN a.answer = 'neutral' THEN 1 ELSE 0 END) as neutral_count,
                           SUM(CASE WHEN a.answer = 'no' THEN 1 ELSE 0 END) as no_count,
                           SUM(CASE WHEN a.answer = 'unknown' THEN 1 ELSE 0 END) as unknown_count,
                           COUNT(a.answer) as total_count
                    FROM facility_evaluation_questions q
                    LEFT JOIN facility_staff_evaluation_answers a ON q.id = a.question_id
                    LEFT JOIN facility_staff_evaluations e ON a.evaluation_id = e.id AND e.period_id = ? AND e.is_submitted = 1
                    WHERE q.question_type = 'staff'
                    GROUP BY q.id
                ");
                $stmt->execute([$periodId]);
                $staffStats = $stmt->fetchAll();

                // 集計結果を保存
                $insertStmt = $pdo->prepare("
                    INSERT INTO facility_evaluation_summaries (period_id, question_id, yes_count, neutral_count, no_count, unknown_count, yes_percentage)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        yes_count = VALUES(yes_count),
                        neutral_count = VALUES(neutral_count),
                        no_count = VALUES(no_count),
                        unknown_count = VALUES(unknown_count),
                        yes_percentage = VALUES(yes_percentage)
                ");

                foreach (array_merge($guardianStats, $staffStats) as $stat) {
                    $total = $stat['yes_count'] + $stat['neutral_count'] + $stat['no_count'];
                    $yesPercentage = $total > 0 ? round(($stat['yes_count'] / $total) * 100, 1) : 0;
                    $insertStmt->execute([
                        $periodId,
                        $stat['question_id'],
                        $stat['yes_count'],
                        $stat['neutral_count'],
                        $stat['no_count'],
                        $stat['unknown_count'],
                        $yesPercentage
                    ]);
                }

                $pdo->prepare("UPDATE facility_evaluation_periods SET status = 'aggregating' WHERE id = ?")->execute([$periodId]);
                $message = "集計が完了しました。";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "集計エラー: " . $e->getMessage();
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'save_comment') {
            $questionId = (int)$_POST['question_id'];
            $facilityComment = $_POST['facility_comment'];

            try {
                $stmt = $pdo->prepare("
                    UPDATE facility_evaluation_summaries
                    SET facility_comment = ?
                    WHERE period_id = ? AND question_id = ?
                ");
                $stmt->execute([$facilityComment, $periodId, $questionId]);
                $message = "事業所コメントを保存しました。";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "保存エラー: " . $e->getMessage();
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'publish') {
            try {
                $pdo->prepare("UPDATE facility_evaluation_periods SET status = 'published' WHERE id = ?")->execute([$periodId]);
                $message = "公表しました。";
                $messageType = 'success';
                // 状態を更新
                $stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ?");
                $stmt->execute([$periodId]);
                $period = $stmt->fetch();
            } catch (Exception $e) {
                $message = "公表エラー: " . $e->getMessage();
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'ai_summarize') {
            $questionId = (int)$_POST['question_id'];
            $questionType = $_POST['question_type'];

            try {
                // コメントを取得
                if ($questionType === 'guardian') {
                    $stmt = $pdo->prepare("
                        SELECT a.comment
                        FROM facility_guardian_evaluation_answers a
                        JOIN facility_guardian_evaluations e ON a.evaluation_id = e.id
                        WHERE e.period_id = ? AND a.question_id = ? AND e.is_submitted = 1 AND a.comment IS NOT NULL AND a.comment != ''
                    ");
                } else {
                    $stmt = $pdo->prepare("
                        SELECT a.comment
                        FROM facility_staff_evaluation_answers a
                        JOIN facility_staff_evaluations e ON a.evaluation_id = e.id
                        WHERE e.period_id = ? AND a.question_id = ? AND e.is_submitted = 1 AND a.comment IS NOT NULL AND a.comment != ''
                    ");
                }
                $stmt->execute([$periodId, $questionId]);
                $comments = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (empty($comments)) {
                    $message = "コメントがありません。";
                    $messageType = 'error';
                } else {
                    // AI要約（簡易版 - 実際にはOpenAI等のAPIを使用）
                    $summary = "【主な意見】\n";
                    foreach ($comments as $i => $comment) {
                        if ($i >= 5) break; // 最大5件
                        $summary .= "・" . mb_substr($comment, 0, 100) . (mb_strlen($comment) > 100 ? "..." : "") . "\n";
                    }

                    // 保存
                    $stmt = $pdo->prepare("
                        UPDATE facility_evaluation_summaries
                        SET comment_summary = ?
                        WHERE period_id = ? AND question_id = ?
                    ");
                    $stmt->execute([$summary, $periodId, $questionId]);
                    $message = "コメントを要約しました。";
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = "要約エラー: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// 質問と集計結果を取得
$stmt = $pdo->prepare("
    SELECT q.*, s.yes_count, s.neutral_count, s.no_count, s.unknown_count, s.yes_percentage, s.comment_summary, s.facility_comment
    FROM facility_evaluation_questions q
    LEFT JOIN facility_evaluation_summaries s ON q.id = s.question_id AND s.period_id = ?
    WHERE q.is_active = 1
    ORDER BY q.question_type, q.sort_order, q.question_number
");
$stmt->execute([$periodId]);
$allQuestions = $stmt->fetchAll();

// タイプ別にグループ化
$guardianQuestions = [];
$staffQuestions = [];
foreach ($allQuestions as $q) {
    if ($q['question_type'] === 'guardian') {
        $guardianQuestions[$q['category']][] = $q;
    } else {
        $staffQuestions[$q['category']][] = $q;
    }
}

// 回答数を取得
$guardianCount = $pdo->prepare("SELECT COUNT(*) FROM facility_guardian_evaluations WHERE period_id = ? AND is_submitted = 1");
$guardianCount->execute([$periodId]);
$guardianRespondents = $guardianCount->fetchColumn();

$staffCount = $pdo->prepare("SELECT COUNT(*) FROM facility_staff_evaluations WHERE period_id = ? AND is_submitted = 1");
$staffCount->execute([$periodId]);
$staffRespondents = $staffCount->fetchColumn();

// ページ開始
$currentPage = 'facility_evaluation';
$pageTitle = '評価集計・公表';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
    .page-header {
        margin-bottom: var(--spacing-xl);
    }

    .message {
        padding: 12px 16px;
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-lg);
    }

    .message.success {
        background: rgba(52, 199, 89, 0.1);
        color: var(--md-green);
        border: 1px solid var(--md-green);
    }

    .message.error {
        background: rgba(255, 59, 48, 0.1);
        color: var(--md-red);
        border: 1px solid var(--md-red);
    }

    .summary-header {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--spacing-lg);
    }

    .summary-stats {
        display: flex;
        gap: var(--spacing-xl);
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-purple);
    }

    .stat-label {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .action-buttons {
        display: flex;
        gap: var(--spacing-sm);
        flex-wrap: wrap;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary {
        background: var(--primary-purple);
        color: white;
    }

    .btn-secondary {
        background: var(--md-bg-secondary);
        color: var(--text-primary);
    }

    .btn-success {
        background: var(--md-green);
        color: white;
    }

    .btn-warning {
        background: var(--md-orange);
        color: var(--text-primary);
    }

    .tabs {
        display: flex;
        border-bottom: 2px solid var(--cds-border-subtle-00);
        margin-bottom: var(--spacing-xl);
    }

    .tab {
        padding: 12px 24px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s ease;
    }

    .tab:hover {
        color: var(--text-primary);
    }

    .tab.active {
        color: var(--primary-purple);
        border-bottom-color: var(--primary-purple);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .category-section {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .category-header {
        background: var(--primary-purple);
        color: white;
        padding: 14px 20px;
        font-size: 16px;
        font-weight: 600;
    }

    .question-row {
        padding: var(--spacing-lg);
        border-bottom: 1px solid var(--cds-border-subtle-00);
    }

    .question-row:last-child {
        border-bottom: none;
    }

    .question-text {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: var(--spacing-md);
    }

    .question-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: var(--primary-purple);
        color: white;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 600;
        margin-right: 10px;
    }

    .result-bar {
        display: flex;
        gap: var(--spacing-md);
        align-items: center;
        margin-bottom: var(--spacing-md);
    }

    .bar-container {
        flex: 1;
        height: 24px;
        display: flex;
        border-radius: 4px;
        overflow: hidden;
        background: var(--md-gray-5);
    }

    .bar-yes {
        background: var(--md-green);
    }

    .bar-neutral {
        background: var(--md-orange);
    }

    .bar-no {
        background: var(--md-red);
    }

    .bar-unknown {
        background: var(--md-gray);
    }

    .result-counts {
        display: flex;
        gap: var(--spacing-lg);
        font-size: 13px;
        color: var(--text-secondary);
    }

    .count-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .count-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .count-dot.yes { background: var(--md-green); }
    .count-dot.neutral { background: var(--md-orange); }
    .count-dot.no { background: var(--md-red); }
    .count-dot.unknown { background: var(--md-gray); }

    .comment-section {
        margin-top: var(--spacing-md);
        padding: var(--spacing-md);
        background: var(--md-gray-6);
        border-radius: var(--radius-sm);
    }

    .comment-section h4 {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: var(--spacing-sm);
    }

    .comment-section p {
        font-size: 13px;
        color: var(--text-primary);
        white-space: pre-wrap;
    }

    .comment-section textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-sm);
        font-size: 13px;
        resize: vertical;
        min-height: 60px;
    }

    .comment-actions {
        display: flex;
        gap: var(--spacing-sm);
        margin-top: var(--spacing-sm);
    }

    .btn-small {
        padding: 6px 12px;
        font-size: 12px;
    }

    @media print {
        .action-buttons, .btn, .tabs, .comment-actions, textarea {
            display: none !important;
        }

        .tab-content {
            display: block !important;
        }

        .category-section {
            break-inside: avoid;
        }
    }

    @media (max-width: 768px) {
        .summary-header {
            flex-direction: column;
            text-align: center;
        }

        .summary-stats {
            justify-content: center;
        }

        .action-buttons {
            justify-content: center;
        }

        .result-counts {
            flex-wrap: wrap;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-symbols-outlined">summarize</span>
            <?php echo htmlspecialchars($period['title']); ?> - 集計結果
        </h1>
    </div>
    <div class="page-header-actions">
        <a href="facility_evaluation.php" class="btn btn-secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            戻る
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="summary-header">
    <div class="summary-stats">
        <div class="stat-item">
            <div class="stat-value"><?php echo $guardianRespondents; ?></div>
            <div class="stat-label">保護者回答数</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?php echo $staffRespondents; ?></div>
            <div class="stat-label">スタッフ回答数</div>
        </div>
    </div>
    <div class="action-buttons">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="aggregate">
            <button type="submit" class="btn btn-warning">
                <span class="material-symbols-outlined">refresh</span>
                再集計
            </button>
        </form>
        <a href="facility_evaluation_pdf.php?period_id=<?php echo $periodId; ?>" class="btn btn-secondary" target="_blank">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            PDF出力
        </a>
        <?php if ($period['status'] !== 'published'): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="publish">
                <button type="submit" class="btn btn-success" onclick="return confirm('公表してよろしいですか？');">
                    <span class="material-symbols-outlined">public</span>
                    公表する
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="tabs">
    <div class="tab active" data-tab="guardian">保護者評価</div>
    <div class="tab" data-tab="staff">スタッフ自己評価</div>
</div>

<!-- 保護者評価結果 -->
<div class="tab-content active" id="tab-guardian">
    <?php foreach ($guardianQuestions as $category => $questions): ?>
        <div class="category-section">
            <div class="category-header"><?php echo htmlspecialchars($category); ?></div>
            <?php foreach ($questions as $q):
                $total = ($q['yes_count'] ?? 0) + ($q['neutral_count'] ?? 0) + ($q['no_count'] ?? 0);
                $yesPercent = $total > 0 ? ($q['yes_count'] / $total * 100) : 0;
                $neutralPercent = $total > 0 ? ($q['neutral_count'] / $total * 100) : 0;
                $noPercent = $total > 0 ? ($q['no_count'] / $total * 100) : 0;
            ?>
                <div class="question-row">
                    <div class="question-text">
                        <span class="question-number"><?php echo $q['question_number']; ?></span>
                        <?php echo htmlspecialchars($q['question_text']); ?>
                    </div>

                    <div class="result-bar">
                        <div class="bar-container">
                            <?php if ($total > 0): ?>
                                <div class="bar-yes" style="width: <?php echo $yesPercent; ?>%"></div>
                                <div class="bar-neutral" style="width: <?php echo $neutralPercent; ?>%"></div>
                                <div class="bar-no" style="width: <?php echo $noPercent; ?>%"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="result-counts">
                        <div class="count-item">
                            <span class="count-dot yes"></span>
                            はい: <?php echo $q['yes_count'] ?? 0; ?> (<?php echo round($yesPercent); ?>%)
                        </div>
                        <div class="count-item">
                            <span class="count-dot neutral"></span>
                            どちらとも: <?php echo $q['neutral_count'] ?? 0; ?> (<?php echo round($neutralPercent); ?>%)
                        </div>
                        <div class="count-item">
                            <span class="count-dot no"></span>
                            いいえ: <?php echo $q['no_count'] ?? 0; ?> (<?php echo round($noPercent); ?>%)
                        </div>
                        <div class="count-item">
                            <span class="count-dot unknown"></span>
                            わからない: <?php echo $q['unknown_count'] ?? 0; ?>
                        </div>
                    </div>

                    <?php if ($q['comment_summary']): ?>
                        <div class="comment-section">
                            <h4>ご意見の要約</h4>
                            <p><?php echo htmlspecialchars($q['comment_summary']); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="comment-section">
                        <h4>事業所コメント</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_comment">
                            <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                            <textarea name="facility_comment" placeholder="事業所からの回答・改善策を入力"><?php echo htmlspecialchars($q['facility_comment'] ?? ''); ?></textarea>
                            <div class="comment-actions">
                                <button type="submit" class="btn btn-primary btn-small">保存</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="ai_summarize">
                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                    <input type="hidden" name="question_type" value="guardian">
                                    <button type="submit" class="btn btn-secondary btn-small">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">auto_awesome</span>
                                        意見を要約
                                    </button>
                                </form>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- スタッフ自己評価結果 -->
<div class="tab-content" id="tab-staff">
    <?php foreach ($staffQuestions as $category => $questions): ?>
        <div class="category-section">
            <div class="category-header"><?php echo htmlspecialchars($category); ?></div>
            <?php foreach ($questions as $q):
                $total = ($q['yes_count'] ?? 0) + ($q['neutral_count'] ?? 0) + ($q['no_count'] ?? 0);
                $yesPercent = $total > 0 ? ($q['yes_count'] / $total * 100) : 0;
                $neutralPercent = $total > 0 ? ($q['neutral_count'] / $total * 100) : 0;
                $noPercent = $total > 0 ? ($q['no_count'] / $total * 100) : 0;
            ?>
                <div class="question-row">
                    <div class="question-text">
                        <span class="question-number"><?php echo $q['question_number']; ?></span>
                        <?php echo htmlspecialchars($q['question_text']); ?>
                    </div>

                    <div class="result-bar">
                        <div class="bar-container">
                            <?php if ($total > 0): ?>
                                <div class="bar-yes" style="width: <?php echo $yesPercent; ?>%"></div>
                                <div class="bar-neutral" style="width: <?php echo $neutralPercent; ?>%"></div>
                                <div class="bar-no" style="width: <?php echo $noPercent; ?>%"></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="result-counts">
                        <div class="count-item">
                            <span class="count-dot yes"></span>
                            はい: <?php echo $q['yes_count'] ?? 0; ?> (<?php echo round($yesPercent); ?>%)
                        </div>
                        <div class="count-item">
                            <span class="count-dot neutral"></span>
                            どちらとも: <?php echo $q['neutral_count'] ?? 0; ?> (<?php echo round($neutralPercent); ?>%)
                        </div>
                        <div class="count-item">
                            <span class="count-dot no"></span>
                            いいえ: <?php echo $q['no_count'] ?? 0; ?> (<?php echo round($noPercent); ?>%)
                        </div>
                    </div>

                    <div class="comment-section">
                        <h4>事業所コメント・改善計画</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_comment">
                            <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                            <textarea name="facility_comment" placeholder="改善計画・取組内容を入力"><?php echo htmlspecialchars($q['facility_comment'] ?? ''); ?></textarea>
                            <div class="comment-actions">
                                <button type="submit" class="btn btn-primary btn-small">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});
</script>

<?php renderPageEnd(); ?>
