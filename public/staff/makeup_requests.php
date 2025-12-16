<?php
/**
 * 振替依頼管理画面
 * スタッフが保護者からの振替依頼を確認・承認する
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// 検索パラメータ
$status = $_GET['status'] ?? 'pending'; // pending, approved, rejected, all
$searchDate = $_GET['search_date'] ?? '';

// クエリ構築
$where = [];
$params = [];

// 振替希望日が設定されているものだけ表示（未設定は欠席扱い）
$where[] = "an.makeup_request_date IS NOT NULL";

if ($status !== 'all') {
    $where[] = "an.makeup_status = ?";
    $params[] = $status;
} else {
    $where[] = "an.makeup_status != 'none'";
}

if ($searchDate) {
    $where[] = "an.makeup_request_date = ?";
    $params[] = $searchDate;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 振替依頼一覧を取得
$stmt = $pdo->prepare("
    SELECT
        an.id,
        an.student_id,
        an.absence_date,
        an.reason,
        an.makeup_request_date,
        an.makeup_status,
        an.makeup_approved_by,
        an.makeup_approved_at,
        an.makeup_note,
        s.student_name,
        s.classroom_id,
        c.classroom_name,
        approver.full_name as approver_name,
        cm.message as notification_message,
        cm.created_at as requested_at
    FROM absence_notifications an
    INNER JOIN students s ON an.student_id = s.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users approver ON an.makeup_approved_by = approver.id
    LEFT JOIN chat_messages cm ON an.message_id = cm.id
    $whereClause
    ORDER BY
        CASE an.makeup_status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        an.makeup_request_date ASC,
        cm.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

// ページ開始
$currentPage = 'makeup_requests';
renderPageStart('staff', $currentPage, '振替依頼管理');
?>

<style>
.requests-list {
    display: grid;
    gap: 15px;
}

.request-card {
    background: var(--apple-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-sm);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid var(--apple-gray-5);
}

.request-card.pending {
    border-left-color: var(--apple-orange);
    background: var(--apple-bg-secondary);
}

.request-card.approved {
    border-left-color: var(--apple-green);
    background: var(--apple-bg-secondary);
}

.request-card.rejected {
    border-left-color: var(--apple-red);
    background: var(--apple-bg-secondary);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    gap: 15px;
}

.student-info { flex: 1; }

.student-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.classroom-name {
    font-size: var(--text-subhead);
    color: var(--text-tertiary);
}

.status-badge {
    padding: 6px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-footnote);
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.pending { background: rgba(255,149,0,0.15); color: var(--apple-orange); }
.status-badge.approved { background: rgba(52,199,89,0.15); color: var(--apple-green); }
.status-badge.rejected { background: rgba(255,59,48,0.15); color: var(--apple-red); }

.request-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
    padding: 15px;
    background: var(--apple-bg-tertiary);
    border-radius: 6px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-label {
    font-size: var(--text-caption-1);
    color: var(--text-tertiary);
    font-weight: 600;
    text-transform: uppercase;
}

.detail-value {
    font-size: 15px;
    color: var(--text-primary);
}

.detail-value.highlight {
    font-weight: 600;
    color: var(--apple-blue);
    font-size: var(--text-callout);
}

.reason-text {
    margin-bottom: 15px;
    padding: var(--spacing-md);
    background: var(--apple-gray-6);
    border-radius: 6px;
    font-size: var(--text-subhead);
    color: var(--text-secondary);
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.actions button {
    padding: var(--spacing-md) 20px;
    border: none;
    border-radius: 6px;
    font-size: var(--text-subhead);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--duration-fast) var(--ease-out);
}

.btn-approve { background: var(--apple-green); color: white; }
.btn-approve:hover { background: #28b463; }
.btn-reject { background: var(--apple-red); color: white; }
.btn-reject:hover { background: #dc2626; }
.btn-note { background: var(--apple-gray); color: white; }
.btn-note:hover { background: #4b5563; }

.approved-info {
    padding: var(--spacing-md);
    background: rgba(52,199,89,0.1);
    border-radius: 6px;
    font-size: var(--text-subhead);
    color: var(--apple-green);
}

.rejected-info {
    padding: var(--spacing-md);
    background: rgba(255,59,48,0.1);
    border-radius: 6px;
    font-size: var(--text-subhead);
    color: var(--apple-red);
}

.note-text {
    margin-top: 8px;
    font-style: italic;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-tertiary);
}

.empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.search-box {
    background: var(--apple-bg-secondary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show { display: flex; }

.modal-content {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: var(--spacing-lg);
    color: var(--text-primary);
}

.modal-body { margin-bottom: var(--spacing-lg); }

.modal-body textarea {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--apple-gray-5);
    border-radius: 6px;
    font-size: var(--text-subhead);
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.modal-footer button {
    padding: var(--spacing-md) 20px;
    border: none;
    border-radius: 6px;
    font-size: var(--text-subhead);
    font-weight: 600;
    cursor: pointer;
}

.btn-cancel { background: var(--apple-gray-5); color: var(--text-primary); }
.btn-cancel:hover { background: var(--apple-gray-4); }
.btn-confirm { background: var(--apple-blue); color: white; }
.btn-confirm:hover { background: #1d4ed8; }

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
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">振替依頼管理</h1>
        <p class="page-subtitle">保護者からの振替依頼を確認・承認</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ戻る</a>

<!-- 検索フォーム -->
<div class="search-box">
            <form class="search-form" method="GET">
                <select name="status" onchange="this.form.submit()">
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>承認待ち</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>承認済み</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>却下</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>すべて</option>
                </select>
                <input type="date" name="search_date" value="<?= htmlspecialchars($searchDate) ?>" placeholder="振替希望日">
                <button type="submit">検索</button>
                <?php if ($searchDate): ?>
                    <a href="makeup_requests.php?status=<?= urlencode($status) ?>" style="text-decoration: none;">
                        <button type="button">クリア</button>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="requests-list">
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p>該当する振替依頼はありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request):
                    $absenceDate = new DateTime($request['absence_date']);
                    $makeupDate = $request['makeup_request_date'] ? new DateTime($request['makeup_request_date']) : null;
                ?>
                    <div class="request-card <?= $request['makeup_status'] ?>">
                        <div class="request-header">
                            <div class="student-info">
                                <div class="student-name"><?= htmlspecialchars($request['student_name']) ?></div>
                                <div class="classroom-name"><?= htmlspecialchars($request['classroom_name'] ?? '未配置') ?></div>
                            </div>
                            <div class="status-badge <?= $request['makeup_status'] ?>">
                                <?php
                                    echo match($request['makeup_status']) {
                                        'pending' => '承認待ち',
                                        'approved' => '承認済み',
                                        'rejected' => '却下',
                                        default => $request['makeup_status']
                                    };
                                ?>
                            </div>
                        </div>

                        <div class="request-details">
                            <div class="detail-item">
                                <div class="detail-label">欠席日</div>
                                <div class="detail-value">
                                    <?= $absenceDate->format('Y年n月j日') ?>
                                    (<?= ['日', '月', '火', '水', '木', '金', '土'][$absenceDate->format('w')] ?>)
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">振替希望日</div>
                                <div class="detail-value highlight">
                                    <?php if ($makeupDate): ?>
                                        <?= $makeupDate->format('Y年n月j日') ?>
                                        (<?= ['日', '月', '火', '水', '木', '金', '土'][$makeupDate->format('w')] ?>)
                                    <?php else: ?>
                                        <span style="color: var(--apple-orange);">未設定</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">依頼日時</div>
                                <div class="detail-value">
                                    <?php
                                        $requestedAt = $request['requested_at'] ? new DateTime($request['requested_at']) : null;
                                        echo $requestedAt ? $requestedAt->format('Y年n月j日 H:i') : '不明';
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($request['reason']): ?>
                            <div class="reason-text">
                                <strong>欠席理由:</strong> <?= nl2br(htmlspecialchars($request['reason'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($request['makeup_status'] === 'pending'): ?>
                            <div class="actions">
                                <button class="btn-approve" onclick="approveRequest(<?= $request['id'] ?>)">
                                    ✓ 承認する
                                </button>
                                <button class="btn-reject" onclick="rejectRequest(<?= $request['id'] ?>)">
                                    × 却下する
                                </button>
                                <button class="btn-note" onclick="addNote(<?= $request['id'] ?>)">
                                    メモを追加
                                </button>
                            </div>
                        <?php elseif ($request['makeup_status'] === 'approved'): ?>
                            <div class="approved-info">
                                <strong>✓ 承認済み</strong><br>
                                承認者: <?= htmlspecialchars($request['approver_name']) ?><br>
                                承認日時: <?php
                                    $approvedAt = new DateTime($request['makeup_approved_at']);
                                    echo $approvedAt->format('Y年n月j日 H:i');
                                ?>
                                <?php if ($request['makeup_note']): ?>
                                    <div class="note-text">メモ: <?= nl2br(htmlspecialchars($request['makeup_note'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($request['makeup_status'] === 'rejected'): ?>
                            <div class="rejected-info">
                                <strong>× 却下</strong><br>
                                処理者: <?= htmlspecialchars($request['approver_name']) ?><br>
                                処理日時: <?php
                                    $approvedAt = new DateTime($request['makeup_approved_at']);
                                    echo $approvedAt->format('Y年n月j日 H:i');
                                ?>
                                <?php if ($request['makeup_note']): ?>
                                    <div class="note-text">理由: <?= nl2br(htmlspecialchars($request['makeup_note'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

<!-- メモ追加モーダル -->
<div id="noteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">メモを追加</div>
        <div class="modal-body">
            <textarea id="noteText" placeholder="メモを入力してください..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeNoteModal()">キャンセル</button>
            <button class="btn-confirm" onclick="saveNote()">保存</button>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<JS
let currentRequestId = null;

        // 承認処理
        function approveRequest(requestId) {
            if (!confirm('この振替依頼を承認しますか？\\n承認すると、生徒が振替希望日の出席予定者に自動的に追加されます。')) {
                return;
            }

            fetch('makeup_requests_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'approve',
                    request_id: requestId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('振替依頼を承認しました。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
            });
        }

        // 却下処理
        function rejectRequest(requestId) {
            if (!confirm('この振替依頼を却下しますか？')) {
                return;
            }

            fetch('makeup_requests_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reject',
                    request_id: requestId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('振替依頼を却下しました。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
            });
        }

        // メモ追加モーダルを開く
        function addNote(requestId) {
            currentRequestId = requestId;
            document.getElementById('noteText').value = '';
            document.getElementById('noteModal').classList.add('show');
        }

        // メモモーダルを閉じる
        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('show');
            currentRequestId = null;
        }

        // メモを保存
        function saveNote() {
            const note = document.getElementById('noteText').value.trim();

            if (!note) {
                alert('メモを入力してください。');
                return;
            }

            fetch('makeup_requests_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add_note',
                    request_id: currentRequestId,
                    note: note
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('メモを保存しました。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
            });
        }

        // モーダル外クリックで閉じる
        document.getElementById('noteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNoteModal();
            }
        });
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
