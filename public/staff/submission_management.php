<?php
/**
 * 提出期限管理ページ（スタッフ用）
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// テーブルが存在するか確認
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'submission_requests'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        die('エラー: submission_requestsテーブルが存在しません。マイグレーション（run_migration_v24.php）を実行してください。');
    }

    // カラムが存在するか確認
    $stmt = $pdo->query("SHOW COLUMNS FROM submission_requests LIKE 'attachment_path'");
    $attachmentColumnExists = $stmt->rowCount() > 0;

    if (!$attachmentColumnExists) {
        die('エラー: 添付ファイル関連のカラムが存在しません。マイグレーション（run_migration_v25.php）を実行してください。');
    }
} catch (Exception $e) {
    die('データベースエラー: ' . $e->getMessage());
}

// フィルター
$filterStatus = $_GET['status'] ?? 'pending'; // pending, completed, all

// 提出期限リクエストを取得（生徒のclassroom_idでフィルタ）
if ($classroomId) {
    $sql = "
        SELECT
            sr.id,
            sr.title,
            sr.description,
            sr.due_date,
            sr.is_completed,
            sr.completed_at,
            sr.completed_note,
            sr.created_at,
            sr.attachment_path,
            sr.attachment_original_name,
            sr.attachment_size,
            s.student_name,
            s.id as student_id,
            u.full_name as guardian_name,
            creator.full_name as created_by_name
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON sr.guardian_id = u.id
        INNER JOIN users creator ON sr.created_by = creator.id
        WHERE s.classroom_id = ?
    ";

    // ステータスフィルター
    if ($filterStatus === 'pending') {
        $sql .= " AND sr.is_completed = 0";
    } elseif ($filterStatus === 'completed') {
        $sql .= " AND sr.is_completed = 1";
    }

    $sql .= " ORDER BY sr.is_completed ASC, sr.due_date ASC, sr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$classroomId]);
    $submissions = $stmt->fetchAll();

    // 統計（完了件数は最近1か月のみ、生徒のclassroom_idでフィルタ）
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN is_completed = 1 AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        WHERE s.classroom_id = ?
    ");
    $stmt->execute([$classroomId]);
    $stats = $stmt->fetch();
} else {
    // classroom_idがない場合は全件取得
    $sql = "
        SELECT
            sr.id,
            sr.title,
            sr.description,
            sr.due_date,
            sr.is_completed,
            sr.completed_at,
            sr.completed_note,
            sr.created_at,
            sr.attachment_path,
            sr.attachment_original_name,
            sr.attachment_size,
            s.student_name,
            s.id as student_id,
            u.full_name as guardian_name,
            creator.full_name as created_by_name
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON sr.guardian_id = u.id
        INNER JOIN users creator ON sr.created_by = creator.id
        WHERE 1=1
    ";

    // ステータスフィルター
    if ($filterStatus === 'pending') {
        $sql .= " AND sr.is_completed = 0";
    } elseif ($filterStatus === 'completed') {
        $sql .= " AND sr.is_completed = 1";
    }

    $sql .= " ORDER BY sr.is_completed ASC, sr.due_date ASC, sr.created_at DESC";

    $stmt = $pdo->query($sql);
    $submissions = $stmt->fetchAll();

    // 統計
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
    ");
    $stats = $stmt->fetch();
}

// ページ開始
$currentPage = 'submission_management';
renderPageStart('staff', $currentPage, '提出期限管理');
?>

<style>
        .btn {
            padding: var(--spacing-md) 20px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: var(--text-subhead);
            text-decoration: none;
            display: inline-block;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .btn-primary {
            background: var(--primary-purple);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-purple);
        }

        .btn-secondary {
            background: var(--md-gray);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--md-gray);
        }

        .btn-success {
            background: var(--md-green);
            color: white;
        }

        .btn-success:hover {
            background: var(--md-green);
        }

        .btn-danger {
            background: var(--md-red);
            color: white;
        }

        .btn-danger:hover {
            background: var(--md-red);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: var(--text-footnote);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: var(--spacing-lg);
        }

        .stat-card {
            background: var(--md-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .stat-label {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-card.overdue .stat-value {
            color: var(--md-red);
        }

        .stat-card.pending .stat-value {
            color: #ff9800;
        }

        .stat-card.completed .stat-value {
            color: var(--md-green);
        }

        .content-box {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: var(--spacing-lg);
            border-bottom: 2px solid #e1e8ed;
        }

        .filter-tab {
            padding: var(--spacing-md) 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: var(--text-subhead);
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .filter-tab.active {
            color: var(--primary-purple);
            border-bottom-color: var(--primary-purple);
        }

        .filter-tab:hover {
            color: var(--primary-purple);
        }

        .submission-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .submission-card {
            border: 2px solid #e1e8ed;
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            transition: all var(--duration-normal) var(--ease-out);
        }

        .submission-card:hover {
            border-color: var(--primary-purple);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .submission-card.overdue {
            border-left: 4px solid var(--md-red);
        }

        .submission-card.completed {
            background: var(--md-gray-6);
            border-color: var(--md-green);
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .submission-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .submission-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .submission-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: var(--spacing-sm) 12px;
            background: #e8eaf6;
            color: var(--primary-purple);
            text-decoration: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
            transition: all var(--duration-normal) var(--ease-out);
            margin-top: 10px;
        }

        .attachment-link:hover {
            background: #c5cae9;
        }

        .due-date {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-xl);
            font-size: var(--text-footnote);
            font-weight: 600;
        }

        .due-date.overdue {
            background: rgba(255, 59, 48, 0.1);
            color: var(--md-red);
        }

        .due-date.soon {
            background: var(--md-bg-secondary);
            color: #856404;
        }

        .due-date.normal {
            background: #e8eaf6;
            color: var(--primary-purple);
        }

        .submission-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .completed-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #d4edda;
            color: #155724;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
        }

        .completed-note {
            margin-top: 10px;
            padding: var(--spacing-md);
            background: var(--md-gray-6);
            border-left: 3px solid var(--md-green);
            font-size: var(--text-footnote);
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: var(--spacing-md);
        }

        /* モーダル */
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

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: var(--spacing-lg);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid #e1e8ed;
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
            min-height: 80px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .submission-header {
                flex-direction: column;
                gap: 10px;
            }

            .submission-actions {
                flex-direction: column;
            }
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">提出期限管理</h1>
        <p class="page-subtitle">提出物の期限と状況を管理</p>
    </div>
</div>

        <!-- 統計 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">全体</div>
                <div class="stat-value"><?= $stats['total'] ?>件</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">未提出</div>
                <div class="stat-value"><?= $stats['pending'] ?>件</div>
            </div>
            <div class="stat-card overdue">
                <div class="stat-label">期限切れ</div>
                <div class="stat-value"><?= $stats['overdue'] ?>件</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-label">提出済み</div>
                <div class="stat-value"><?= $stats['completed'] ?>件</div>
            </div>
        </div>

        <!-- フィルター -->
        <div class="content-box">
            <div class="filter-tabs">
                <a href="?status=pending" class="filter-tab <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                    未提出 (<?= $stats['pending'] ?>)
                </a>
                <a href="?status=completed" class="filter-tab <?= $filterStatus === 'completed' ? 'active' : '' ?>">
                    提出済み (<?= $stats['completed'] ?>)
                </a>
                <a href="?status=all" class="filter-tab <?= $filterStatus === 'all' ? 'active' : '' ?>">
                    すべて (<?= $stats['total'] ?>)
                </a>
            </div>

            <!-- 提出期限リスト -->
            <div class="submission-list">
                <?php if (empty($submissions)): ?>
                    <div class="empty-state">
                        <h3>提出期限がありません</h3>
                        <p>チャット画面から提出期限を設定できます</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                        <?php
                        $dueDate = new DateTime($sub['due_date']);
                        $today = new DateTime();
                        $diff = $today->diff($dueDate);
                        $isOverdue = $dueDate < $today && !$sub['is_completed'];
                        $isSoon = !$isOverdue && $diff->days <= 3 && !$sub['is_completed'];

                        $dueDateClass = 'normal';
                        if ($isOverdue) $dueDateClass = 'overdue';
                        elseif ($isSoon) $dueDateClass = 'soon';
                        ?>
                        <div class="submission-card <?= $isOverdue ? 'overdue' : '' ?> <?= $sub['is_completed'] ? 'completed' : '' ?>">
                            <div class="submission-header">
                                <div>
                                    <div class="submission-title"><?= htmlspecialchars($sub['title']) ?></div>
                                    <div class="submission-meta">
                                        <div class="submission-meta-item">
                                            <span class="material-symbols-outlined">person</span> <?= htmlspecialchars($sub['student_name']) ?>
                                        </div>
                                        <div class="submission-meta-item">
                                            <span class="material-symbols-outlined">group</span> <?= htmlspecialchars($sub['guardian_name']) ?>
                                        </div>
                                        <div class="submission-meta-item">
                                            <span class="material-symbols-outlined">event</span> 作成: <?= date('Y/m/d', strtotime($sub['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($sub['is_completed']): ?>
                                        <div class="completed-badge">✓ 提出済み</div>
                                    <?php else: ?>
                                        <span class="due-date <?= $dueDateClass ?>">
                                            期限: <?= $dueDate->format('Y年n月j日') ?>
                                            <?php if ($isOverdue): ?>
                                                (<?= $diff->days ?>日経過)
                                            <?php elseif ($isSoon): ?>
                                                (残り<?= $diff->days ?>日)
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($sub['description']): ?>
                                <div class="submission-description">
                                    <?= nl2br(htmlspecialchars($sub['description'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($sub['attachment_path']): ?>
                                <a href="../<?= htmlspecialchars($sub['attachment_path']) ?>" class="attachment-link" download="<?= htmlspecialchars($sub['attachment_original_name']) ?>">
                                    <span class="material-symbols-outlined">attach_file</span> <?= htmlspecialchars($sub['attachment_original_name']) ?>
                                    (<?= number_format($sub['attachment_size'] / 1024, 1) ?> KB)
                                </a>
                            <?php endif; ?>

                            <?php if ($sub['is_completed'] && $sub['completed_note']): ?>
                                <div class="completed-note">
                                    <strong>完了メモ:</strong> <?= nl2br(htmlspecialchars($sub['completed_note'])) ?>
                                    <div style="margin-top: 5px; font-size: var(--text-caption-1);">
                                        完了日時: <?= date('Y/m/d H:i', strtotime($sub['completed_at'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="submission-actions">
                                <?php if (!$sub['is_completed']): ?>
                                    <button onclick="markAsCompleted(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['title']) ?>')" class="btn btn-success btn-sm">
                                        ✓ 提出完了にする
                                    </button>
                                <?php else: ?>
                                    <button onclick="markAsIncomplete(<?= $sub['id'] ?>)" class="btn btn-secondary btn-sm">
                                        未提出に戻す
                                    </button>
                                <?php endif; ?>
                                <a href="chat.php?room_id=<?= $sub['student_id'] ?>" class="btn btn-primary btn-sm">
                                    <span class="material-symbols-outlined">chat</span> チャットを開く
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 完了確認モーダル -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-header">提出完了の確認</h3>
            <form id="completeForm" onsubmit="submitComplete(event)">
                <input type="hidden" id="submissionId">
                <p style="margin-bottom: var(--spacing-lg);">
                    <strong id="submissionTitleDisplay"></strong> を提出完了にしますか？
                </p>
                <div class="form-group">
                    <label>完了メモ（任意）</label>
                    <textarea id="completedNote" placeholder="メモがあれば入力してください"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeCompleteModal()" class="btn btn-secondary">キャンセル</button>
                    <button type="submit" class="btn btn-success">完了にする</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function markAsCompleted(id, title) {
            document.getElementById('submissionId').value = id;
            document.getElementById('submissionTitleDisplay').textContent = title;
            document.getElementById('completeModal').classList.add('active');
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('active');
            document.getElementById('completeForm').reset();
        }

        async function submitComplete(event) {
            event.preventDefault();

            const id = document.getElementById('submissionId').value;
            const note = document.getElementById('completedNote').value;

            try {
                const formData = new FormData();
                formData.append('action', 'complete');
                formData.append('id', id);
                formData.append('note', note);

                const response = await fetch('submission_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('提出完了にしました');
                    location.reload();
                } else {
                    alert('エラー: ' + (result.error || '処理に失敗しました'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('エラーが発生しました');
            }
        }

        async function markAsIncomplete(id) {
            if (!confirm('未提出に戻しますか？')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'incomplete');
                formData.append('id', id);

                const response = await fetch('submission_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('未提出に戻しました');
                    location.reload();
                } else {
                    alert('エラー: ' + (result.error || '処理に失敗しました'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('エラーが発生しました');
            }
        }

        // モーダル外クリックで閉じる
        document.getElementById('completeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCompleteModal();
            }
        });
    </script>

<?php renderPageEnd(); ?>
