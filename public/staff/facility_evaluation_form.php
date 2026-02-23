<?php
/**
 * スタッフ自己評価フォーム
 * 事業所評価シートのスタッフ向け自己評価を入力
 */
header('Content-Type: text/html; charset=UTF-8');

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

// 質問一覧を取得
$stmt = $pdo->prepare("
    SELECT * FROM facility_evaluation_questions
    WHERE question_type = 'staff' AND is_active = 1
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
$stmt = $pdo->prepare("SELECT * FROM facility_staff_evaluations WHERE period_id = ? AND staff_id = ?");
$stmt->execute([$periodId, $currentUser['id']]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    // 新規作成
    $pdo->prepare("INSERT INTO facility_staff_evaluations (period_id, staff_id) VALUES (?, ?)")
        ->execute([$periodId, $currentUser['id']]);
    $evaluationId = $pdo->lastInsertId();
} else {
    $evaluationId = $evaluation['id'];
}

// 既存の回答詳細を取得
$stmt = $pdo->prepare("
    SELECT question_id, answer, comment, improvement_plan
    FROM facility_staff_evaluation_answers
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

// 未回答の質問IDを追跡
$unansweredQuestions = [];
// 改善計画が未記入の「いいえ」回答を追跡
$missingImprovementQuestions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isSubmit = isset($_POST['submit_action']) && $_POST['submit_action'] === 'submit';

    // 提出時のバリデーション
    if ($isSubmit) {
        foreach ($questions as $q) {
            $answer = $_POST['answer_' . $q['id']] ?? null;
            $improvement = trim($_POST['improvement_' . $q['id']] ?? '');

            // 未回答チェック
            if (empty($answer)) {
                $unansweredQuestions[] = $q['id'];
            }
            // 「いいえ」の場合、改善計画が必須
            elseif ($answer === 'no' && empty($improvement)) {
                $missingImprovementQuestions[] = $q['id'];
            }
        }

        if (!empty($unansweredQuestions)) {
            $message = "未回答の質問があります。すべての質問に回答してください。";
            $messageType = 'error';
            $isSubmit = false;
        } elseif (!empty($missingImprovementQuestions)) {
            $message = "「いいえ」と回答した質問には改善計画を入力してください。";
            $messageType = 'error';
            $isSubmit = false;
        }
    }

    if ((empty($unansweredQuestions) && empty($missingImprovementQuestions)) || !$isSubmit) {
        try {
            $pdo->beginTransaction();

            foreach ($questions as $q) {
                $answer = $_POST['answer_' . $q['id']] ?? null;
                $comment = $_POST['comment_' . $q['id']] ?? '';
                $improvement = $_POST['improvement_' . $q['id']] ?? '';

                $stmt = $pdo->prepare("
                    INSERT INTO facility_staff_evaluation_answers (evaluation_id, question_id, answer, comment, improvement_plan)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE answer = VALUES(answer), comment = VALUES(comment), improvement_plan = VALUES(improvement_plan)
                ");
                $stmt->execute([$evaluationId, $q['id'], $answer, $comment, $improvement]);
            }

            if ($isSubmit) {
                // 提出済みフラグを更新
                $pdo->prepare("UPDATE facility_staff_evaluations SET is_submitted = 1, submitted_at = NOW() WHERE id = ?")
                    ->execute([$evaluationId]);
                $message = "自己評価を提出しました。ご協力ありがとうございました。";
            } else {
                $message = "下書きを保存しました。";
            }
            $messageType = 'success';

            $pdo->commit();

            // 回答を再取得
            $stmt = $pdo->prepare("
                SELECT question_id, answer, comment, improvement_plan
                FROM facility_staff_evaluation_answers
                WHERE evaluation_id = ?
            ");
            $stmt->execute([$evaluationId]);
            $existingAnswers = [];
            foreach ($stmt->fetchAll() as $row) {
                $existingAnswers[$row['question_id']] = $row;
            }

            // 評価情報を再取得
            $stmt = $pdo->prepare("SELECT * FROM facility_staff_evaluations WHERE id = ?");
            $stmt->execute([$evaluationId]);
            $evaluation = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "エラー: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

$isSubmitted = $evaluation && $evaluation['is_submitted'];

// ページ開始
$currentPage = 'facility_evaluation';
$pageTitle = 'スタッフ自己評価';
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

    .question-item.unanswered {
        background: rgba(255, 59, 48, 0.08);
        border-left: 4px solid var(--md-red);
        animation: pulse-warning 1s ease-in-out;
    }

    @keyframes pulse-warning {
        0% { background: rgba(255, 59, 48, 0.2); }
        100% { background: rgba(255, 59, 48, 0.08); }
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

    .btn-primary:hover {
        background: var(--primary-purple-dark);
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
            <?php echo htmlspecialchars($period['title']); ?> - スタッフ自己評価
        </h1>
        <p class="page-subtitle">各項目について、現在の事業所の状況を評価してください</p>
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

<?php if ($isSubmitted): ?>
    <div class="submitted-notice">
        <span class="material-symbols-outlined" style="vertical-align: middle;">check_circle</span>
        この評価は<?php echo date('Y年n月j日 H:i', strtotime($evaluation['submitted_at'])); ?>に提出済みです。
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
                    <div class="question-item" id="question_<?php echo $q['id']; ?>" data-question-id="<?php echo $q['id']; ?>">
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
                        </div>

                        <div class="comment-section">
                            <label for="comment_<?php echo $q['id']; ?>">工夫している点・課題や改善すべき点</label>
                            <textarea name="comment_<?php echo $q['id']; ?>" id="comment_<?php echo $q['id']; ?>"
                                      placeholder="任意で記入してください" <?php echo $isSubmitted ? 'disabled' : ''; ?>><?php echo htmlspecialchars($existing['comment'] ?? ''); ?></textarea>
                        </div>

                        <div class="comment-section">
                            <label for="improvement_<?php echo $q['id']; ?>">改善計画（いいえの場合）</label>
                            <textarea name="improvement_<?php echo $q['id']; ?>" id="improvement_<?php echo $q['id']; ?>"
                                      placeholder="改善計画を記入してください" <?php echo $isSubmitted ? 'disabled' : ''; ?>><?php echo htmlspecialchars($existing['improvement_plan'] ?? ''); ?></textarea>
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
                提出する
            </button>
        </div>
    <?php endif; ?>
</form>

<?php if (!empty($unansweredQuestions) || !empty($missingImprovementQuestions)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 未回答の質問をハイライト
    var unansweredIds = <?php echo json_encode($unansweredQuestions); ?>;
    // 改善計画未記入の質問をハイライト
    var missingImprovementIds = <?php echo json_encode($missingImprovementQuestions); ?>;

    unansweredIds.forEach(function(id) {
        var element = document.getElementById('question_' + id);
        if (element) {
            element.classList.add('unanswered');
        }
    });

    missingImprovementIds.forEach(function(id) {
        var element = document.getElementById('question_' + id);
        if (element) {
            element.classList.add('unanswered');
            // 改善計画のテキストエリアをハイライト
            var improvementField = document.getElementById('improvement_' + id);
            if (improvementField) {
                improvementField.style.borderColor = 'var(--md-red)';
                improvementField.style.boxShadow = '0 0 0 2px rgba(255, 59, 48, 0.3)';
            }
        }
    });

    // 最初の問題のある質問にスクロール
    var firstProblemId = unansweredIds.length > 0 ? unansweredIds[0] : (missingImprovementIds.length > 0 ? missingImprovementIds[0] : null);
    if (firstProblemId) {
        var firstProblem = document.getElementById('question_' + firstProblemId);
        if (firstProblem) {
            setTimeout(function() {
                firstProblem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 300);
        }
    }
});
</script>
<?php endif; ?>

<?php renderPageEnd(); ?>
