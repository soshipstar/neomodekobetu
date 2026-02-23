<?php
/**
 * 事業所評価集計・公表ページ
 * 回答データの集計、AI要約、PDF出力
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';
require_once __DIR__ . '/../../includes/openai_helper.php';

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

// 教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'aggregate') {
            try {
                // 保護者評価の集計（教室でフィルタリング）
                if ($classroomId) {
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
                        LEFT JOIN users u ON e.guardian_id = u.id AND u.classroom_id = ?
                        WHERE q.question_type = 'guardian'
                        GROUP BY q.id
                    ");
                    $stmt->execute([$periodId, $classroomId]);
                } else {
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
                }
                $guardianStats = $stmt->fetchAll();

                // スタッフ評価の集計（教室でフィルタリング）
                if ($classroomId) {
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
                        LEFT JOIN users u ON e.staff_id = u.id AND u.classroom_id = ?
                        WHERE q.question_type = 'staff'
                        GROUP BY q.id
                    ");
                    $stmt->execute([$periodId, $classroomId]);
                } else {
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
                }
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
        } elseif ($_POST['action'] === 'save_self_summary') {
            // 別紙３：自己評価総括表の保存
            try {
                $itemTypes = ['strength', 'weakness'];
                foreach ($itemTypes as $itemType) {
                    for ($i = 1; $i <= 3; $i++) {
                        $description = $_POST["{$itemType}_{$i}_description"] ?? '';
                        $efforts = $_POST["{$itemType}_{$i}_efforts"] ?? '';
                        $plan = $_POST["{$itemType}_{$i}_plan"] ?? '';

                        $stmt = $pdo->prepare("
                            INSERT INTO facility_self_evaluation_summary (period_id, item_type, item_number, description, current_efforts, improvement_plan)
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                description = VALUES(description),
                                current_efforts = VALUES(current_efforts),
                                improvement_plan = VALUES(improvement_plan)
                        ");
                        $stmt->execute([$periodId, $itemType, $i, $description, $efforts, $plan]);
                    }
                }
                $message = "自己評価総括表を保存しました。";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "保存エラー: " . $e->getMessage();
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
                    // GPT-5.2を使用してコメントを要約
                    $commentsText = implode("\n", array_map(function($c, $i) {
                        return ($i + 1) . ". " . $c;
                    }, $comments, array_keys($comments)));

                    $prompt = "以下は放課後等デイサービス事業所の評価アンケートに寄せられた保護者・スタッフからの意見です。
これらの意見を分析し、以下の形式で要約してください：

【主な意見】
・ポジティブな意見を箇条書きで2-3点
・改善要望や課題を箇条書きで2-3点

【全体の傾向】
全体的な傾向を1-2文で簡潔にまとめてください。

===意見一覧===
{$commentsText}
===

日本語で、簡潔かつ分かりやすく要約してください。";

                    try {
                        $summary = callOpenAI($prompt, 'gpt-5.2-2025-12-11', 800);

                        // 保存
                        $stmt = $pdo->prepare("
                            UPDATE facility_evaluation_summaries
                            SET comment_summary = ?
                            WHERE period_id = ? AND question_id = ?
                        ");
                        $stmt->execute([$summary, $periodId, $questionId]);
                        $message = "AIがコメントを要約しました。";
                        $messageType = 'success';
                    } catch (Exception $aiError) {
                        $message = "AI要約エラー: " . $aiError->getMessage();
                        $messageType = 'error';
                    }
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

// 別紙３：自己評価総括表データを取得
$selfSummaryData = [];
$stmt = $pdo->prepare("SELECT * FROM facility_self_evaluation_summary WHERE period_id = ?");
$stmt->execute([$periodId]);
$summaryRows = $stmt->fetchAll();
foreach ($summaryRows as $row) {
    $selfSummaryData[$row['item_type']][$row['item_number']] = $row;
}

// 教室情報を取得
$classroomName = '';
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT name FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
    $classroomName = $classroom ? $classroom['name'] : '';
}

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

    /* 別紙３：自己評価総括表 */
    .summary-form-section {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .summary-form-section h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--spacing-lg);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .summary-form-section h3 .material-symbols-outlined {
        color: var(--primary-purple);
    }

    .strength-section h3 {
        color: var(--md-green);
    }

    .weakness-section h3 {
        color: var(--md-orange);
    }

    .summary-item {
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
    }

    .summary-item:last-child {
        margin-bottom: 0;
    }

    .summary-item-header {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--spacing-md);
    }

    .summary-form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--spacing-md);
        margin-bottom: var(--spacing-md);
    }

    @media (min-width: 768px) {
        .summary-form-row.two-col {
            grid-template-columns: 1fr 1fr;
        }
    }

    .summary-form-group label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .summary-form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-sm);
        font-size: 14px;
        resize: vertical;
        min-height: 80px;
    }

    .summary-form-group textarea:focus {
        outline: none;
        border-color: var(--cds-blue-60);
        box-shadow: 0 0 0 2px rgba(15, 98, 254, 0.2);
    }

    .summary-meta-info {
        background: var(--md-gray-6);
        border-radius: var(--radius-sm);
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-lg);
        font-size: 13px;
    }

    .meta-row {
        display: flex;
        gap: var(--spacing-md);
        margin-bottom: 8px;
    }

    .meta-row:last-child {
        margin-bottom: 0;
    }

    .meta-label {
        font-weight: 500;
        color: var(--text-secondary);
        min-width: 150px;
    }

    .meta-value {
        color: var(--text-primary);
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
    <div class="tab active" data-tab="guardian">保護者評価（別紙４）</div>
    <div class="tab" data-tab="staff">スタッフ自己評価（別紙５）</div>
    <div class="tab" data-tab="summary">自己評価総括表（別紙３）</div>
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

<!-- 別紙３：自己評価総括表 -->
<div class="tab-content" id="tab-summary">
    <form method="POST">
        <input type="hidden" name="action" value="save_self_summary">

        <div class="summary-meta-info">
            <div class="meta-row">
                <span class="meta-label">事業所名</span>
                <span class="meta-value"><?php echo htmlspecialchars($classroomName ?: '放課後等デイサービス'); ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">保護者評価実施期間</span>
                <span class="meta-value">
                    <?php
                    $startDate = $period['guardian_eval_start_date'] ?? $period['created_at'];
                    $endDate = $period['guardian_deadline'] ?? '';
                    echo date('Y年n月j日', strtotime($startDate)) . ' ～ ' . ($endDate ? date('Y年n月j日', strtotime($endDate)) : '');
                    ?>
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">保護者評価有効回答数</span>
                <span class="meta-value">
                    対象者数: <?php echo $guardianRespondents; ?>件 / 回答者数: <?php echo $guardianRespondents; ?>件
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">従業者評価実施期間</span>
                <span class="meta-value">
                    <?php
                    $startDate = $period['staff_eval_start_date'] ?? $period['created_at'];
                    $endDate = $period['staff_deadline'] ?? '';
                    echo date('Y年n月j日', strtotime($startDate)) . ' ～ ' . ($endDate ? date('Y年n月j日', strtotime($endDate)) : '');
                    ?>
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">従業者評価有効回答数</span>
                <span class="meta-value">
                    対象者数: <?php echo $staffRespondents; ?>件 / 回答者数: <?php echo $staffRespondents; ?>件
                </span>
            </div>
        </div>

        <!-- 強み -->
        <div class="summary-form-section strength-section">
            <h3>
                <span class="material-symbols-outlined">thumb_up</span>
                事業所の強み（より強化・充実を図ることが期待されること）
            </h3>

            <?php for ($i = 1; $i <= 3; $i++):
                $item = $selfSummaryData['strength'][$i] ?? [];
            ?>
            <div class="summary-item">
                <div class="summary-item-header"><?php echo $i; ?></div>
                <div class="summary-form-row">
                    <div class="summary-form-group">
                        <label>事業所の強みだと思われること</label>
                        <textarea name="strength_<?php echo $i; ?>_description" placeholder="強みの内容を入力"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="summary-form-row two-col">
                    <div class="summary-form-group">
                        <label>工夫していることや意識的に行っている取組等</label>
                        <textarea name="strength_<?php echo $i; ?>_efforts" placeholder="現在の取組を入力"><?php echo htmlspecialchars($item['current_efforts'] ?? ''); ?></textarea>
                    </div>
                    <div class="summary-form-group">
                        <label>さらに充実を図るための取組等</label>
                        <textarea name="strength_<?php echo $i; ?>_plan" placeholder="今後の取組を入力"><?php echo htmlspecialchars($item['improvement_plan'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- 弱み -->
        <div class="summary-form-section weakness-section">
            <h3>
                <span class="material-symbols-outlined">construction</span>
                事業所の弱み（事業所の課題や改善が必要だと思われること）
            </h3>

            <?php for ($i = 1; $i <= 3; $i++):
                $item = $selfSummaryData['weakness'][$i] ?? [];
            ?>
            <div class="summary-item">
                <div class="summary-item-header"><?php echo $i; ?></div>
                <div class="summary-form-row">
                    <div class="summary-form-group">
                        <label>事業所の弱みだと思われること</label>
                        <textarea name="weakness_<?php echo $i; ?>_description" placeholder="課題・弱みの内容を入力"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="summary-form-row two-col">
                    <div class="summary-form-group">
                        <label>事業所として考えている課題の要因等</label>
                        <textarea name="weakness_<?php echo $i; ?>_efforts" placeholder="課題の要因を入力"><?php echo htmlspecialchars($item['current_efforts'] ?? ''); ?></textarea>
                    </div>
                    <div class="summary-form-group">
                        <label>改善に向けて必要な取組や工夫が必要な点等</label>
                        <textarea name="weakness_<?php echo $i; ?>_plan" placeholder="改善策を入力"><?php echo htmlspecialchars($item['improvement_plan'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div style="text-align: center; margin-top: var(--spacing-xl);">
            <button type="submit" class="btn btn-primary" style="padding: 12px 40px;">
                <span class="material-symbols-outlined">save</span>
                自己評価総括表を保存
            </button>
        </div>
    </form>
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
