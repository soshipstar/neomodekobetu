<?php
/**
 * 施設通信作成開始ページ
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = $_POST['newsletter_id'] ?? null;
    if ($deleteId) {
        try {
            // 自分の教室の通信のみ削除可能
            if ($classroomId) {
                $stmt = $pdo->prepare("DELETE FROM newsletters WHERE id = ? AND classroom_id = ?");
                $stmt->execute([$deleteId, $classroomId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM newsletters WHERE id = ?");
                $stmt->execute([$deleteId]);
            }
            $_SESSION['success_message'] = '通信を削除しました。';
        } catch (Exception $e) {
            $_SESSION['error_message'] = '削除に失敗しました: ' . $e->getMessage();
        }
        header('Location: newsletter_create.php');
        exit;
    }
}

// 現在の年月を取得
$currentYear = date('Y');
$currentMonth = date('m');

// 既存の通信を取得（自分の教室のみ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT * FROM newsletters
        WHERE classroom_id = ?
        ORDER BY year DESC, month DESC
        LIMIT 10
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM newsletters
        ORDER BY year DESC, month DESC
        LIMIT 10
    ");
    $stmt->execute();
}
$existingNewsletters = $stmt->fetchAll();

// ページ開始
$currentPage = 'newsletter_create';
renderPageStart('staff', $currentPage, '施設通信作成');
?>

<style>
.form-section {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.form-section h2 {
    color: var(--apple-blue);
    font-size: 20px;
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
}

.date-range {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 10px;
    align-items: center;
}

.date-range span {
    text-align: center;
    color: var(--text-secondary);
}

.submit-btn {
    width: 100%;
    padding: 15px;
    background: var(--apple-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-out);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,122,255,0.4);
}

.existing-newsletters {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
}

.existing-newsletters h2 {
    color: var(--apple-blue);
    font-size: 20px;
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
}

.newsletter-item {
    padding: 15px;
    border: 1px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all var(--duration-normal) var(--ease-out);
}

.newsletter-item:hover {
    border-color: var(--apple-blue);
    background: var(--apple-gray-6);
}

.newsletter-info { flex: 1; }

.newsletter-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.newsletter-meta {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
}

.newsletter-status {
    padding: 4px 12px;
    border-radius: var(--radius-md);
    font-size: var(--text-caption-1);
    font-weight: 600;
}

.status-draft { background: rgba(255,149,0,0.15); color: var(--apple-orange); }
.status-published { background: rgba(52,199,89,0.15); color: var(--apple-green); }

.newsletter-actions {
    display: flex;
    gap: 10px;
    margin-left: 15px;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-caption-1);
    cursor: pointer;
    text-decoration: none;
    transition: all var(--duration-normal) var(--ease-out);
}

.btn-edit { background: var(--apple-blue); color: white; }
.btn-edit:hover { background: #1d4ed8; }
.btn-view { background: var(--apple-green); color: white; }
.btn-view:hover { background: #28b463; }
.btn-delete { background: var(--apple-red); color: white; }
.btn-delete:hover { background: #c9302c; }

.success-message {
    background: rgba(52, 199, 89, 0.15);
    color: var(--apple-green);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-green);
}

.error-message {
    background: rgba(255, 59, 48, 0.15);
    color: var(--apple-red);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-red);
}

.info-box {
    background: rgba(0,122,255,0.1);
    border-left: 4px solid var(--apple-blue);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
    font-size: var(--text-subhead);
    line-height: 1.6;
}

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

@media (max-width: 768px) {
    .date-range { grid-template-columns: 1fr; }
    .date-range span { display: none; }
    .newsletter-item { flex-direction: column; align-items: flex-start; gap: 10px; }
    .newsletter-actions { margin-left: 0; width: 100%; }
    .action-btn { flex: 1; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">施設通信作成</h1>
        <p class="page-subtitle">AIで通信の下書きを自動生成</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ戻る</a>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="success-message">
    <?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?>
    <?php unset($_SESSION['success_message']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="error-message">
    <?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?>
    <?php unset($_SESSION['error_message']); ?>
</div>
<?php endif; ?>

<div class="form-section">
            <h2>新しい通信を作成</h2>

            <div class="info-box">
                💡 通信を作成すると、AIが該当期間の連絡帳データを参照して通信の下書きを自動生成します。生成後、内容を確認・編集してから発行してください。
            </div>

            <form method="POST" action="newsletter_edit.php" id="createForm">
                <div class="form-group">
                    <label>通信の年月 *</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <input type="number" name="year" value="<?php echo $currentYear; ?>" min="2020" max="2100" required>
                        </div>
                        <div>
                            <select name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                        <?php echo $m; ?>月
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>報告事項の期間 *</label>
                    <div class="date-range">
                        <input type="date" name="report_start_date" required>
                        <span>～</span>
                        <input type="date" name="report_end_date" required>
                    </div>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        過去の活動記録やイベント結果を報告する期間を指定してください
                    </small>
                </div>

                <div class="form-group">
                    <label>予定連絡の期間 *</label>
                    <div class="date-range">
                        <input type="date" name="schedule_start_date" required>
                        <span>～</span>
                        <input type="date" name="schedule_end_date" required>
                    </div>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        今後の予定イベントを掲載する期間を指定してください
                    </small>
                </div>

                <button type="submit" class="submit-btn">📝 通信を制作する</button>
            </form>
        </div>

        <?php if (!empty($existingNewsletters)): ?>
        <div class="existing-newsletters">
            <h2>既存の通信一覧</h2>

            <?php foreach ($existingNewsletters as $newsletter): ?>
                <div class="newsletter-item">
                    <div class="newsletter-info">
                        <div class="newsletter-title">
                            <?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="newsletter-meta">
                            報告: <?php echo date('Y/m/d', strtotime($newsletter['report_start_date'])); ?>
                            ～ <?php echo date('Y/m/d', strtotime($newsletter['report_end_date'])); ?>
                            | 予定: <?php echo date('Y/m/d', strtotime($newsletter['schedule_start_date'])); ?>
                            ～ <?php echo date('Y/m/d', strtotime($newsletter['schedule_end_date'])); ?>
                        </div>
                    </div>
                    <span class="newsletter-status status-<?php echo $newsletter['status']; ?>">
                        <?php echo $newsletter['status'] === 'published' ? '発行済み' : '下書き'; ?>
                    </span>
                    <div class="newsletter-actions">
                        <a href="newsletter_edit.php?id=<?php echo $newsletter['id']; ?>" class="action-btn btn-edit">
                            編集
                        </a>
                        <?php if ($newsletter['status'] === 'published'): ?>
                        <a href="newsletter_view.php?id=<?php echo $newsletter['id']; ?>" class="action-btn btn-view">
                            表示
                        </a>
                        <?php endif; ?>
                        <button type="button" class="action-btn btn-delete" onclick="confirmDelete(<?php echo $newsletter['id']; ?>, '<?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?>')">
                            削除
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

<!-- 削除用フォーム -->
<form id="deleteForm" method="POST" action="newsletter_create.php" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="newsletter_id" id="deleteNewsletterId" value="">
</form>

<?php
$inlineJs = <<<JS
// 削除確認
function confirmDelete(newsletterId, title) {
    if (confirm('「' + title + '」を削除してもよろしいですか？\\n\\nこの操作は取り消せません。')) {
        document.getElementById('deleteNewsletterId').value = newsletterId;
        document.getElementById('deleteForm').submit();
    }
}

// フォームのバリデーション
document.getElementById('createForm').addEventListener('submit', function(e) {
    const reportStart = new Date(document.querySelector('input[name="report_start_date"]').value);
    const reportEnd = new Date(document.querySelector('input[name="report_end_date"]').value);
    const scheduleStart = new Date(document.querySelector('input[name="schedule_start_date"]').value);
    const scheduleEnd = new Date(document.querySelector('input[name="schedule_end_date"]').value);

    if (reportStart > reportEnd) {
        alert('報告事項の期間が不正です。開始日は終了日より前である必要があります。');
        e.preventDefault();
        return false;
    }

    if (scheduleStart > scheduleEnd) {
        alert('予定連絡の期間が不正です。開始日は終了日より前である必要があります。');
        e.preventDefault();
        return false;
    }

    return true;
});

// 今月の日付を自動設定
window.addEventListener('DOMContentLoaded', function() {
    const year = document.querySelector('input[name="year"]').value;
    const month = document.querySelector('select[name="month"]').value.padStart(2, '0');

    // 報告期間: 前月1日～前月末日
    const lastMonth = new Date(year, month - 2, 1);
    const lastMonthEnd = new Date(year, month - 1, 0);
    document.querySelector('input[name="report_start_date"]').value =
        lastMonth.toISOString().split('T')[0];
    document.querySelector('input[name="report_end_date"]').value =
        lastMonthEnd.toISOString().split('T')[0];

    // 予定期間: 今月1日～今月末日
    const thisMonth = new Date(year, month - 1, 1);
    const thisMonthEnd = new Date(year, month, 0);
    document.querySelector('input[name="schedule_start_date"]').value =
        thisMonth.toISOString().split('T')[0];
    document.querySelector('input[name="schedule_end_date"]').value =
        thisMonthEnd.toISOString().split('T')[0];
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
