<?php
/**
 * 保護者向け事業所評価フォーム
 * 保護者が事業所を評価するアンケートフォーム
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 保護者のみアクセス可能
requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

$periodId = $_GET['period_id'] ?? null;

// 評価期間を取得（指定がなければ最新の収集中期間）
if ($periodId) {
    $stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ? AND status = 'collecting'");
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();
} else {
    $stmt = $pdo->query("SELECT * FROM facility_evaluation_periods WHERE status = 'collecting' ORDER BY fiscal_year DESC LIMIT 1");
    $period = $stmt->fetch();
}

if (!$period) {
    // 収集中の評価期間がない場合
    $currentPage = 'facility_evaluation';
    $pageTitle = '事業所評価';
    renderPageStart('guardian', $currentPage, $pageTitle);
    ?>
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">事業所評価</h1>
        </div>
    </div>
    <div style="text-align: center; padding: 60px 20px;">
        <span class="material-symbols-outlined" style="font-size: 64px; color: var(--text-secondary);">info</span>
        <h2 style="margin: 20px 0 10px;">現在、回答を受け付けている評価はありません</h2>
        <p style="color: var(--text-secondary);">評価期間が始まりましたらお知らせいたします。</p>
        <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px; padding: 12px 24px; background: var(--primary-purple); color: white; text-decoration: none; border-radius: 6px; display: inline-block;">
            ダッシュボードに戻る
        </a>
    </div>
    <?php
    renderPageEnd();
    exit;
}

$periodId = $period['id'];

// 質問一覧を取得
$stmt = $pdo->prepare("
    SELECT * FROM facility_evaluation_questions
    WHERE question_type = 'guardian' AND is_active = 1
    ORDER BY sort_order, question_number
");
$stmt->execute();
$questions = $stmt->fetchAll();

// カテゴリでグループ化
$questionsByCategory = [];
foreach ($questions as $q) {
    $questionsByCategory[$q['category']][] = $q;
}

// 既存の回答を取得または作成
$stmt = $pdo->prepare("SELECT * FROM facility_guardian_evaluations WHERE period_id = ? AND guardian_id = ?");
$stmt->execute([$periodId, $currentUser['id']]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    // 新規作成
    $pdo->prepare("INSERT INTO facility_guardian_evaluations (period_id, guardian_id) VALUES (?, ?)")
        ->execute([$periodId, $currentUser['id']]);
    $evaluationId = $pdo->lastInsertId();
} else {
    $evaluationId = $evaluation['id'];
}

// 既存の回答詳細を取得
$stmt = $pdo->prepare("
    SELECT question_id, answer, comment
    FROM facility_guardian_evaluation_answers
    WHERE evaluation_id = ?
");
$stmt->execute([$evaluationId]);
$existingAnswers = [];
foreach ($stmt->fetchAll() as $row) {
    $existingAnswers[$row['question_id']] = $row;
}

// 保存処理
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isSubmit = isset($_POST['submit_action']) && $_POST['submit_action'] === 'submit';

    try {
        $pdo->beginTransaction();

        foreach ($questions as $q) {
            $answer = $_POST['answer_' . $q['id']] ?? null;
            $comment = $_POST['comment_' . $q['id']] ?? '';

            $stmt = $pdo->prepare("
                INSERT INTO facility_guardian_evaluation_answers (evaluation_id, question_id, answer, comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE answer = VALUES(answer), comment = VALUES(comment)
            ");
            $stmt->execute([$evaluationId, $q['id'], $answer, $comment]);
        }

        if ($isSubmit) {
            $pdo->prepare("UPDATE facility_guardian_evaluations SET is_submitted = 1, submitted_at = NOW() WHERE id = ?")
                ->execute([$evaluationId]);
            $message = "ご回答いただきありがとうございました。今後のサービス向上に活用させていただきます。";
        } else {
            $message = "下書きを保存しました。後から続きを入力できます。";
        }
        $messageType = 'success';

        $pdo->commit();

        // 回答を再取得
        $stmt = $pdo->prepare("
            SELECT question_id, answer, comment
            FROM facility_guardian_evaluation_answers
            WHERE evaluation_id = ?
        ");
        $stmt->execute([$evaluationId]);
        $existingAnswers = [];
        foreach ($stmt->fetchAll() as $row) {
            $existingAnswers[$row['question_id']] = $row;
        }

        // 評価情報を再取得
        $stmt = $pdo->prepare("SELECT * FROM facility_guardian_evaluations WHERE id = ?");
        $stmt->execute([$evaluationId]);
        $evaluation = $stmt->fetch();

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "エラー: " . $e->getMessage();
        $messageType = 'error';
    }
}

$isSubmitted = $evaluation && $evaluation['is_submitted'];

// ページ開始
$currentPage = 'facility_evaluation';
$pageTitle = '事業所評価';
renderPageStart('guardian', $currentPage, $pageTitle);
?>

<style>
    .page-header {
        margin-bottom: var(--spacing-xl);
    }

    .intro-section {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-xl);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .intro-section h2 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: var(--spacing-md);
        color: var(--text-primary);
    }

    .intro-section p {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.7;
        margin-bottom: var(--spacing-sm);
    }

    .deadline-notice {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 149, 0, 0.1);
        color: var(--md-orange);
        padding: 10px 14px;
        border-radius: var(--radius-sm);
        margin-top: var(--spacing-md);
        font-size: 14px;
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

    .submitted-notice {
        background: rgba(0, 122, 255, 0.1);
        color: var(--md-blue);
        border: 1px solid var(--md-blue);
        padding: 16px;
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-xl);
        text-align: center;
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

    .category-body {
        padding: var(--spacing-lg);
    }

    .question-item {
        padding: var(--spacing-lg);
        border-bottom: 1px solid var(--cds-border-subtle-00);
    }

    .question-item:last-child {
        border-bottom: none;
    }

    .question-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        background: var(--primary-purple);
        color: white;
        border-radius: 50%;
        font-size: 13px;
        font-weight: 600;
        margin-right: 12px;
    }

    .question-text {
        font-size: 14px;
        color: var(--text-primary);
        margin-bottom: var(--spacing-md);
        line-height: 1.6;
    }

    .answer-options {
        display: flex;
        gap: var(--spacing-md);
        flex-wrap: wrap;
        margin-bottom: var(--spacing-md);
    }

    .answer-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .answer-option input[type="radio"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .answer-option label {
        font-size: 14px;
        color: var(--text-primary);
        cursor: pointer;
    }

    .comment-section {
        margin-top: var(--spacing-md);
    }

    .comment-section label {
        display: block;
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .comment-section textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: var(--radius-sm);
        font-size: 14px;
        resize: vertical;
        min-height: 80px;
    }

    .comment-section textarea:focus {
        outline: none;
        border-color: var(--cds-blue-60);
        box-shadow: 0 0 0 2px rgba(15, 98, 254, 0.2);
    }

    .form-actions {
        position: sticky;
        bottom: 0;
        background: var(--md-bg-primary);
        padding: var(--spacing-lg);
        border-top: 1px solid var(--cds-border-subtle-00);
        display: flex;
        gap: var(--spacing-md);
        justify-content: center;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
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

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    @media (max-width: 768px) {
        .answer-options {
            flex-direction: column;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <span class="material-symbols-outlined">fact_check</span>
            <?php echo htmlspecialchars($period['title']); ?>
        </h1>
        <p class="page-subtitle">事業所の評価にご協力ください</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($isSubmitted): ?>
    <div class="submitted-notice">
        <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span>
        この評価は<?php echo date('Y年n月j日 H:i', strtotime($evaluation['submitted_at'])); ?>に提出済みです。
        <br>ご協力ありがとうございました。
    </div>
<?php else: ?>
    <div class="intro-section">
        <h2>ご協力のお願い</h2>
        <p>
            日頃より当事業所をご利用いただきありがとうございます。
            皆様からのご意見・ご感想をもとに、今後のサービス向上に努めてまいりますので、
            アンケートへのご協力をお願いいたします。
        </p>
        <p>
            各質問について「はい」「どちらともいえない」「いいえ」「わからない」からお選びください。
            また、ご意見やご要望がございましたら、ご意見欄にご記入ください。
        </p>
        <?php if ($period['guardian_deadline']): ?>
            <div class="deadline-notice">
                <span class="material-symbols-outlined">schedule</span>
                回答期限: <?php echo date('Y年n月j日', strtotime($period['guardian_deadline'])); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="POST" id="evaluationForm">
    <?php foreach ($questionsByCategory as $category => $categoryQuestions): ?>
        <div class="category-section">
            <div class="category-header">
                <?php echo htmlspecialchars($category); ?>
            </div>
            <div class="category-body">
                <?php foreach ($categoryQuestions as $q):
                    $existing = $existingAnswers[$q['id']] ?? null;
                ?>
                    <div class="question-item">
                        <div class="question-text">
                            <span class="question-number"><?php echo $q['question_number']; ?></span>
                            <?php echo htmlspecialchars($q['question_text']); ?>
                        </div>

                        <div class="answer-options">
                            <div class="answer-option">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" id="answer_<?php echo $q['id']; ?>_yes"
                                       value="yes" <?php echo ($existing['answer'] ?? '') === 'yes' ? 'checked' : ''; ?>
                                       <?php echo $isSubmitted ? 'disabled' : ''; ?>>
                                <label for="answer_<?php echo $q['id']; ?>_yes">はい</label>
                            </div>
                            <div class="answer-option">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" id="answer_<?php echo $q['id']; ?>_neutral"
                                       value="neutral" <?php echo ($existing['answer'] ?? '') === 'neutral' ? 'checked' : ''; ?>
                                       <?php echo $isSubmitted ? 'disabled' : ''; ?>>
                                <label for="answer_<?php echo $q['id']; ?>_neutral">どちらともいえない</label>
                            </div>
                            <div class="answer-option">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" id="answer_<?php echo $q['id']; ?>_no"
                                       value="no" <?php echo ($existing['answer'] ?? '') === 'no' ? 'checked' : ''; ?>
                                       <?php echo $isSubmitted ? 'disabled' : ''; ?>>
                                <label for="answer_<?php echo $q['id']; ?>_no">いいえ</label>
                            </div>
                            <div class="answer-option">
                                <input type="radio" name="answer_<?php echo $q['id']; ?>" id="answer_<?php echo $q['id']; ?>_unknown"
                                       value="unknown" <?php echo ($existing['answer'] ?? '') === 'unknown' ? 'checked' : ''; ?>
                                       <?php echo $isSubmitted ? 'disabled' : ''; ?>>
                                <label for="answer_<?php echo $q['id']; ?>_unknown">わからない</label>
                            </div>
                        </div>

                        <div class="comment-section">
                            <label for="comment_<?php echo $q['id']; ?>">ご意見（任意）</label>
                            <textarea name="comment_<?php echo $q['id']; ?>" id="comment_<?php echo $q['id']; ?>"
                                      placeholder="ご意見やご要望がありましたらご記入ください" <?php echo $isSubmitted ? 'disabled' : ''; ?>><?php echo htmlspecialchars($existing['comment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$isSubmitted): ?>
        <div class="form-actions">
            <button type="submit" name="submit_action" value="save" class="btn btn-secondary">
                <span class="material-symbols-outlined">save</span>
                下書き保存
            </button>
            <button type="submit" name="submit_action" value="submit" class="btn btn-success"
                    onclick="return confirm('提出すると修正できなくなります。提出してよろしいですか？');">
                <span class="material-symbols-outlined">send</span>
                回答を提出する
            </button>
        </div>
    <?php endif; ?>
</form>

<?php renderPageEnd(); ?>
