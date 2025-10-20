<?php
/**
 * 提出期限管理ページ（スタッフ用）
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ログインチェック
requireLogin();
checkUserType('staff');

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

// 提出期限リクエストを取得
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
        INNER JOIN users guardian_user ON sr.guardian_id = guardian_user.id
        INNER JOIN users creator ON sr.created_by = creator.id
        WHERE guardian_user.classroom_id = ?
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

    // 統計
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN is_completed = 0 AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
        FROM submission_requests sr
        INNER JOIN students s ON sr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE u.classroom_id = ?
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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>提出期限管理 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 600;
            color: #333;
        }

        .stat-card.overdue .stat-value {
            color: #dc3545;
        }

        .stat-card.pending .stat-value {
            color: #ff9800;
        }

        .stat-card.completed .stat-value {
            color: #28a745;
        }

        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e1e8ed;
        }

        .filter-tab {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
        }

        .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .filter-tab:hover {
            color: #667eea;
        }

        .submission-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .submission-card {
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
        }

        .submission-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .submission-card.overdue {
            border-left: 4px solid #dc3545;
        }

        .submission-card.completed {
            background: #f8f9fa;
            border-color: #28a745;
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
            color: #333;
            margin-bottom: 5px;
        }

        .submission-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .submission-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .submission-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #e8eaf6;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .attachment-link:hover {
            background: #c5cae9;
        }

        .due-date {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .due-date.overdue {
            background: #fee;
            color: #dc3545;
        }

        .due-date.soon {
            background: #fff3cd;
            color: #856404;
        }

        .due-date.normal {
            background: #e8eaf6;
            color: #667eea;
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
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
        }

        .completed-note {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-left: 3px solid #28a745;
            font-size: 13px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
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
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            min-height: 80px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
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
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 提出期限管理</h1>
            <div class="header-actions">
                <a href="renrakucho_activities.php" class="btn btn-secondary btn-sm">戻る</a>
                <a href="../logout.php" class="btn btn-danger btn-sm">ログアウト</a>
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
                                            👤 <?= htmlspecialchars($sub['student_name']) ?>
                                        </div>
                                        <div class="submission-meta-item">
                                            👨‍👩‍👧 <?= htmlspecialchars($sub['guardian_name']) ?>
                                        </div>
                                        <div class="submission-meta-item">
                                            📅 作成: <?= date('Y/m/d', strtotime($sub['created_at'])) ?>
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
                                    📎 <?= htmlspecialchars($sub['attachment_original_name']) ?>
                                    (<?= number_format($sub['attachment_size'] / 1024, 1) ?> KB)
                                </a>
                            <?php endif; ?>

                            <?php if ($sub['is_completed'] && $sub['completed_note']): ?>
                                <div class="completed-note">
                                    <strong>完了メモ:</strong> <?= nl2br(htmlspecialchars($sub['completed_note'])) ?>
                                    <div style="margin-top: 5px; font-size: 12px;">
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
                                    💬 チャットを開く
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
                <p style="margin-bottom: 20px;">
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
</body>
</html>
