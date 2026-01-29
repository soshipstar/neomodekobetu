<?php
/**
 * 保護者用 面談予約回答ページ
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireLogin();

if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

$requestId = $_GET['request_id'] ?? null;

if (!$requestId) {
    header('Location: dashboard.php');
    exit;
}

// 面談リクエストを取得（students テーブル経由で保護者を確認 - より堅牢）
$stmt = $pdo->prepare("
    SELECT mr.*, s.student_name, u.full_name as staff_name
    FROM meeting_requests mr
    INNER JOIN students s ON mr.student_id = s.id
    LEFT JOIN users u ON mr.staff_id = u.id
    WHERE mr.id = ? AND (mr.guardian_id = ? OR s.guardian_id = ?)
");
$stmt->execute([$requestId, $guardianId, $guardianId]);
$request = $stmt->fetch();

if (!$request) {
    $_SESSION['error'] = '指定された面談予約が見つかりません。';
    header('Location: dashboard.php');
    exit;
}

// エラー・成功メッセージ
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

$currentPage = 'meeting_response';
renderPageStart('guardian', $currentPage, '面談予約の回答');
?>

<style>
.meeting-response-container {
    max-width: 700px;
    margin: 0 auto;
    padding: var(--spacing-lg);
}

.response-card {
    background: var(--md-bg-tertiary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.response-header {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--md-gray-5);
}

.response-title {
    font-size: var(--text-title-2);
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.meeting-info {
    margin-top: var(--spacing-lg);
    padding: var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
}

.info-row {
    display: flex;
    margin-bottom: var(--spacing-sm);
}

.info-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 100px;
}

.info-value {
    color: var(--text-primary);
}

.status-badge {
    display: inline-block;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
    font-weight: 600;
}

.status-pending {
    background: rgba(255, 204, 0, 0.2);
    color: #b8860b;
}

.status-confirmed {
    background: rgba(52, 199, 89, 0.2);
    color: var(--md-green);
}

.status-guardian_counter {
    background: rgba(0, 122, 255, 0.2);
    color: var(--md-blue);
}

.status-staff_counter {
    background: rgba(175, 82, 222, 0.2);
    color: var(--md-purple);
}

.candidate-section {
    margin-top: var(--spacing-xl);
}

.section-title {
    font-size: var(--text-title-3);
    font-weight: 600;
    margin-bottom: var(--spacing-md);
    color: var(--text-primary);
}

.candidate-list {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.candidate-option {
    position: relative;
}

.candidate-option input[type="radio"] {
    display: none;
}

.candidate-option label {
    display: flex;
    align-items: center;
    padding: var(--spacing-lg);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--duration-fast);
}

.candidate-option input:checked + label {
    border-color: var(--md-green);
    background: rgba(52, 199, 89, 0.1);
}

.candidate-option label:hover {
    border-color: var(--md-gray-3);
}

.candidate-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--md-gray-4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: var(--spacing-md);
}

.candidate-option input:checked + label .candidate-number {
    background: var(--md-green);
    color: white;
}

.candidate-date {
    font-size: var(--text-body);
    font-weight: 500;
}

.counter-section {
    margin-top: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: rgba(0, 122, 255, 0.05);
    border-radius: var(--radius-md);
    border: 1px solid rgba(0, 122, 255, 0.2);
}

.counter-title {
    font-size: var(--text-subhead);
    font-weight: 600;
    color: var(--md-blue);
    margin-bottom: var(--spacing-md);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.datetime-group {
    display: flex;
    gap: var(--spacing-md);
    align-items: center;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
}

.datetime-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 80px;
}

.form-input, .form-textarea {
    flex: 1;
    min-width: 200px;
    padding: var(--spacing-md);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-md);
    font-size: var(--text-body);
    background: var(--md-bg-primary);
    color: var(--text-primary);
}

.form-textarea {
    width: 100%;
    min-height: 80px;
    resize: vertical;
    margin-top: var(--spacing-md);
}

.form-actions {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-2xl);
    flex-wrap: wrap;
}

.btn {
    padding: var(--spacing-md) var(--spacing-xl);
    border-radius: var(--radius-md);
    font-size: var(--text-body);
    font-weight: 600;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-sm);
    text-decoration: none;
}

.btn-primary {
    background: var(--md-green);
    color: white;
}

.btn-counter {
    background: var(--md-blue);
    color: white;
}

.btn-secondary {
    background: var(--md-gray-4);
    color: var(--text-primary);
}

.confirmed-section {
    margin-top: var(--spacing-xl);
    padding: var(--spacing-xl);
    background: rgba(52, 199, 89, 0.1);
    border-radius: var(--radius-md);
    border: 2px solid var(--md-green);
    text-align: center;
}

.confirmed-date {
    font-size: var(--text-title-2);
    font-weight: 600;
    color: var(--md-green);
    margin-top: var(--spacing-md);
}

.alert {
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.alert-error {
    background: rgba(255, 59, 48, 0.1);
    color: var(--md-red);
    border: 1px solid var(--md-red);
}

.alert-success {
    background: rgba(52, 199, 89, 0.1);
    color: var(--md-green);
    border: 1px solid var(--md-green);
}
</style>

<div class="meeting-response-container">
    <div class="response-card">
        <div class="response-header">
            <h1 class="response-title">
                <span class="material-symbols-outlined">calendar_month</span>
                面談予約
            </h1>
            <div class="meeting-info">
                <div class="info-row">
                    <span class="info-label">対象児童</span>
                    <span class="info-value"><?= htmlspecialchars($request['student_name']) ?>さん</span>
                </div>
                <div class="info-row">
                    <span class="info-label">面談目的</span>
                    <span class="info-value"><?= htmlspecialchars($request['purpose']) ?></span>
                </div>
                <?php if ($request['purpose_detail']): ?>
                <div class="info-row">
                    <span class="info-label">詳細</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($request['purpose_detail'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">担当者</span>
                    <span class="info-value"><?= htmlspecialchars($request['staff_name'] ?? '') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ステータス</span>
                    <span class="info-value">
                        <?php
                        $statusLabels = [
                            'pending' => ['回答待ち', 'pending'],
                            'guardian_counter' => ['別日程を提案中', 'guardian_counter'],
                            'staff_counter' => ['スタッフ再提案中', 'staff_counter'],
                            'confirmed' => ['確定', 'confirmed'],
                            'cancelled' => ['キャンセル', 'cancelled']
                        ];
                        $status = $statusLabels[$request['status']] ?? ['不明', 'pending'];
                        ?>
                        <span class="status-badge status-<?= $status[1] ?>"><?= $status[0] ?></span>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($request['status'] === 'confirmed'): ?>
            <!-- 確定済み -->
            <div class="confirmed-section">
                <span class="material-symbols-outlined" style="font-size: 48px; color: var(--md-green);">check_circle</span>
                <p style="margin-top: var(--spacing-md); font-weight: 600;">面談日時が確定しました</p>
                <div class="confirmed-date">
                    <?= date('Y年n月j日（', strtotime($request['confirmed_date'])) ?>
                    <?php
                    $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
                    echo $dayOfWeek[date('w', strtotime($request['confirmed_date']))];
                    ?>
                    <?= date('）H:i', strtotime($request['confirmed_date'])) ?>
                </div>
            </div>
            <div class="form-actions" style="justify-content: center;">
                <a href="dashboard.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined">arrow_back</span> ダッシュボードへ戻る
                </a>
            </div>

        <?php elseif ($request['status'] === 'pending' || $request['status'] === 'staff_counter'): ?>
            <!-- 回答待ち or スタッフ再提案 -->
            <form action="meeting_response_save.php" method="POST" id="responseForm">
                <input type="hidden" name="request_id" value="<?= $requestId ?>">
                <input type="hidden" name="action" value="select" id="formAction">

                <div class="candidate-section">
                    <h2 class="section-title">候補日時から選択</h2>

                    <?php if ($request['status'] === 'staff_counter'): ?>
                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                            スタッフから新たな候補日時が提案されました。
                        </p>
                        <?php
                        $candidates = [
                            $request['staff_counter_date1'],
                            $request['staff_counter_date2'],
                            $request['staff_counter_date3']
                        ];
                        ?>
                    <?php else: ?>
                        <?php
                        $candidates = [
                            $request['candidate_date1'],
                            $request['candidate_date2'],
                            $request['candidate_date3']
                        ];
                        ?>
                    <?php endif; ?>

                    <div class="candidate-list">
                        <?php foreach ($candidates as $i => $date): ?>
                            <?php if ($date): ?>
                                <div class="candidate-option">
                                    <input type="radio" name="selected_date" id="date<?= $i+1 ?>" value="<?= htmlspecialchars($date) ?>">
                                    <label for="date<?= $i+1 ?>">
                                        <span class="candidate-number"><?= $i + 1 ?></span>
                                        <span class="candidate-date">
                                            <?= date('Y年n月j日（', strtotime($date)) ?>
                                            <?php
                                            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
                                            echo $dayOfWeek[date('w', strtotime($date))];
                                            ?>
                                            <?= date('）H:i', strtotime($date)) ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="counter-section">
                    <h3 class="counter-title">
                        <span class="material-symbols-outlined">edit_calendar</span>
                        上記日程でご都合が合わない場合
                    </h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-md); font-size: var(--text-footnote);">
                        別の希望日時を3つまでご提案ください。
                    </p>
                    <div class="datetime-group">
                        <span class="datetime-label">希望日時1</span>
                        <input type="datetime-local" name="counter_date1" class="form-input" id="counterDate1">
                    </div>
                    <div class="datetime-group">
                        <span class="datetime-label">希望日時2</span>
                        <input type="datetime-local" name="counter_date2" class="form-input">
                    </div>
                    <div class="datetime-group">
                        <span class="datetime-label">希望日時3</span>
                        <input type="datetime-local" name="counter_date3" class="form-input">
                    </div>
                    <textarea name="counter_message" class="form-textarea" placeholder="メッセージ（任意）"></textarea>
                </div>

                <div class="form-actions">
                    <a href="chat.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">arrow_back</span> 戻る
                    </a>
                    <button type="submit" class="btn btn-primary" onclick="document.getElementById('formAction').value='select'">
                        <span class="material-symbols-outlined">check</span> 選択した日程で確定
                    </button>
                    <button type="submit" class="btn btn-counter" onclick="document.getElementById('formAction').value='counter'">
                        <span class="material-symbols-outlined">send</span> 別日程を提案
                    </button>
                </div>
            </form>

        <?php elseif ($request['status'] === 'guardian_counter'): ?>
            <!-- 保護者が別日程を提案中 -->
            <div class="candidate-section">
                <h2 class="section-title">ご提案いただいた日程</h2>
                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                    スタッフからの回答をお待ちください。
                </p>
                <div class="candidate-list">
                    <?php
                    $counterDates = [
                        $request['guardian_counter_date1'],
                        $request['guardian_counter_date2'],
                        $request['guardian_counter_date3']
                    ];
                    foreach ($counterDates as $i => $date):
                        if ($date):
                    ?>
                        <div class="candidate-option">
                            <label style="cursor: default; border-color: var(--md-blue);">
                                <span class="candidate-number" style="background: var(--md-blue); color: white;"><?= $i + 1 ?></span>
                                <span class="candidate-date">
                                    <?= date('Y年n月j日（', strtotime($date)) ?>
                                    <?php
                                    $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'];
                                    echo $dayOfWeek[date('w', strtotime($date))];
                                    ?>
                                    <?= date('）H:i', strtotime($date)) ?>
                                </span>
                            </label>
                        </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php if ($request['guardian_counter_message']): ?>
                    <div style="margin-top: var(--spacing-md); padding: var(--spacing-md); background: var(--md-bg-secondary); border-radius: var(--radius-md);">
                        <strong>メッセージ:</strong><br>
                        <?= nl2br(htmlspecialchars($request['guardian_counter_message'])) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <a href="chat.php" class="btn btn-secondary">
                    <span class="material-symbols-outlined">arrow_back</span> チャットへ戻る
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('responseForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const action = document.getElementById('formAction').value;
            if (action === 'select') {
                const selected = document.querySelector('input[name="selected_date"]:checked');
                if (!selected) {
                    e.preventDefault();
                    alert('日程を選択してください');
                    return false;
                }
            } else if (action === 'counter') {
                const counter1 = document.getElementById('counterDate1').value;
                if (!counter1) {
                    e.preventDefault();
                    alert('別日程を提案する場合は、少なくとも1つの希望日時を入力してください');
                    return false;
                }
            }
        });
    }
});
</script>

<?php renderPageEnd(); ?>
