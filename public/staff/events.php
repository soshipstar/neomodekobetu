<?php
/**
 * スタッフ用 - イベント管理ページ
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();

// 教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室の対象学年設定を取得
$targetGrades = ['preschool', 'elementary', 'junior_high', 'high_school']; // デフォルト
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT target_grades FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
    if ($classroom && !empty($classroom['target_grades'])) {
        $targetGrades = explode(',', $classroom['target_grades']);
    }
}

// 検索パラメータを取得
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';
$searchTargetAudience = $_GET['target_audience'] ?? '';

// イベント一覧を取得（検索機能付き、教室でフィルタリング）
$sql = "
    SELECT
        e.id,
        e.event_date,
        e.event_name,
        e.event_description,
        e.staff_comment,
        e.guardian_message,
        e.event_color,
        e.target_audience,
        e.created_at,
        u.full_name as created_by_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.classroom_id = ?
";

$params = [$classroomId];

// キーワード検索
if (!empty($searchKeyword)) {
    $sql .= " AND (e.event_name LIKE ? OR e.event_description LIKE ?)";
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
}

// 期間検索（開始日）
if (!empty($searchStartDate)) {
    $sql .= " AND e.event_date >= ?";
    $params[] = $searchStartDate;
}

// 期間検索（終了日）
if (!empty($searchEndDate)) {
    $sql .= " AND e.event_date <= ?";
    $params[] = $searchEndDate;
}

// 対象者フィルター（複数選択対応）
if (!empty($searchTargetAudience)) {
    $sql .= " AND (e.target_audience LIKE ? OR e.target_audience = 'all')";
    $params[] = '%' . $searchTargetAudience . '%';
}

$sql .= " ORDER BY e.event_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$targetAudienceLabels = [
    'all' => '全体',
    'preschool' => '未就学児',
    'elementary' => '小学生',
    'junior_high' => '中学生',
    'high_school' => '高校生',
    'guardian' => '保護者',
    'other' => 'その他'
];

// 学年ラベル（教室設定連動用）
$gradeLabels = [
    'preschool' => '未就学児',
    'elementary' => '小学生',
    'junior_high' => '中学生',
    'high_school' => '高校生'
];

// ページ開始
$currentPage = 'events';
renderPageStart('staff', $currentPage, 'イベント管理');
?>

<style>
.event-color-badge {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    vertical-align: middle;
}
.color-preview {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}
.color-option {
    width: 30px;
    height: 30px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}
.color-option:hover {
    border-color: var(--text-primary);
}
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-dialog {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
    position: relative;
}
.modal-header {
    margin-bottom: var(--spacing-lg);
    padding-bottom: 15px;
    border-bottom: 2px solid var(--apple-blue);
}
.modal-header h2 {
    color: var(--text-primary);
    font-size: var(--text-title-3);
    margin: 0;
}
.modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-secondary);
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
}
.modal-close:hover {
    color: var(--text-primary);
}
.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
}
.checkbox-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--duration-fast);
    border: 1px solid var(--apple-gray-5);
}
.checkbox-item:hover {
    background: var(--apple-gray-5);
}
.checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--apple-blue);
    cursor: pointer;
}
.checkbox-item label {
    cursor: pointer;
    font-size: var(--text-subhead);
    color: var(--text-primary);
}
.target-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin: 1px;
}
.target-badge.all { background: #e0e7ff; color: #3730a3; }
.target-badge.preschool { background: #fce7f3; color: #9d174d; }
.target-badge.elementary { background: #d1fae5; color: #065f46; }
.target-badge.junior_high { background: #dbeafe; color: #1e40af; }
.target-badge.high_school { background: #fef3c7; color: #92400e; }
.target-badge.guardian { background: #ede9fe; color: #5b21b6; }
.target-badge.other { background: #f3f4f6; color: #374151; }
.modal-footer {
    margin-top: var(--spacing-lg);
    padding-top: 20px;
    border-top: 1px solid var(--apple-gray-5);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">イベント管理</h1>
        <p class="page-subtitle">施設イベントの登録と管理</p>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php
        switch ($_GET['success']) {
            case 'created': echo 'イベントを登録しました。'; break;
            case 'updated': echo 'イベントを更新しました。'; break;
            case 'deleted': echo 'イベントを削除しました。'; break;
            default: echo '処理が完了しました。';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">エラー: <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">新規イベント登録</h2>
        <form action="events_save.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">日付 *</label>
                    <input type="date" name="event_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">イベント名 *</label>
                    <input type="text" name="event_name" class="form-control" required placeholder="例: 運動会、遠足、文化祭など">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">説明</label>
                <textarea name="event_description" class="form-control" placeholder="イベントの詳細説明（任意）"></textarea>
                <small style="color: var(--text-secondary);">イベントの詳細を記入してください（省略可）</small>
            </div>
            <div class="form-group">
                <label class="form-label">スタッフ向けコメント</label>
                <textarea name="staff_comment" class="form-control" placeholder="スタッフ間で共有する内部メモ（任意）"></textarea>
                <small style="color: var(--text-secondary);">スタッフ間でのみ共有される内部用コメントです（保護者には表示されません）</small>
            </div>
            <div class="form-group">
                <label class="form-label">保護者・生徒連絡用</label>
                <textarea name="guardian_message" class="form-control" placeholder="保護者・生徒に伝えたい内容（任意）"></textarea>
                <small style="color: var(--text-secondary);">保護者や生徒に向けた連絡事項を記入してください</small>
            </div>
            <div class="form-group">
                <label class="form-label">対象者 *</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="all" id="ta_all">
                        <label for="ta_all">全体</label>
                    </div>
                    <?php foreach ($targetGrades as $grade): ?>
                        <?php if (isset($gradeLabels[$grade])): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_audience[]" value="<?= $grade ?>" id="ta_<?= $grade ?>">
                            <label for="ta_<?= $grade ?>"><?= $gradeLabels[$grade] ?></label>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="guardian" id="ta_guardian">
                        <label for="ta_guardian">保護者</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="other" id="ta_other">
                        <label for="ta_other">その他</label>
                    </div>
                </div>
                <small style="color: var(--text-secondary);">このイベントの対象者を選択してください（複数選択可）</small>
            </div>
            <div class="form-group">
                <label class="form-label">カレンダー表示色</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="color" name="event_color" id="event_color" value="#28a745" style="width: 60px; height: 40px; border: none; cursor: pointer;">
                    <span style="color: var(--text-secondary);">選択した色でカレンダーに表示されます</span>
                </div>
                <div class="color-preview">
                    <div class="color-option" style="background: var(--apple-green);" onclick="document.getElementById('event_color').value='#28a745'"></div>
                    <div class="color-option" style="background: var(--apple-blue);" onclick="document.getElementById('event_color').value='#007bff'"></div>
                    <div class="color-option" style="background: var(--apple-orange);" onclick="document.getElementById('event_color').value='#ffc107'"></div>
                    <div class="color-option" style="background: var(--apple-red);" onclick="document.getElementById('event_color').value='#dc3545'"></div>
                    <div class="color-option" style="background: #17a2b8;" onclick="document.getElementById('event_color').value='#17a2b8'"></div>
                    <div class="color-option" style="background: #6f42c1;" onclick="document.getElementById('event_color').value='#6f42c1'"></div>
                </div>
            </div>
            <div style="text-align: right;">
                <button type="submit" class="btn btn-success">登録する</button>
            </div>
        </form>
    </div>
</div>

<!-- 検索フォーム -->
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">イベント検索</h2>
        <form method="GET" action="events.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">期間（開始日）</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($searchStartDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">期間（終了日）</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($searchEndDate) ?>">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">対象者</label>
                    <select name="target_audience" class="form-control">
                        <option value="">すべて</option>
                        <?php foreach ($targetAudienceLabels as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $searchTargetAudience === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="イベント名または説明で検索">
                </div>
            </div>
            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <a href="events.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- イベント一覧 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-headline); margin-bottom: var(--spacing-lg); color: var(--apple-blue);">登録済みイベント一覧</h2>

        <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
            <div class="alert alert-info">
                <strong>検索結果:</strong> <?= count($events) ?>件のイベントが見つかりました
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>イベント名</th>
                    <th>対象者</th>
                    <th>色</th>
                    <th>登録者</th>
                    <th>登録日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: var(--spacing-2xl); color: var(--text-secondary);">
                            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchTargetAudience)): ?>
                                検索条件に一致するイベントが見つかりませんでした
                            <?php else: ?>
                                登録されているイベントがありません
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($event['event_date']))] . '）', strtotime($event['event_date'])) ?></td>
                            <td><?= htmlspecialchars($event['event_name']) ?></td>
                            <td>
                                <?php
                                $audiences = explode(',', $event['target_audience'] ?? 'all');
                                foreach ($audiences as $aud):
                                    $aud = trim($aud);
                                    if (isset($targetAudienceLabels[$aud])):
                                ?>
                                    <span class="target-badge <?= $aud ?>"><?= $targetAudienceLabels[$aud] ?></span>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </td>
                            <td>
                                <span class="event-color-badge" style="background: <?= htmlspecialchars($event['event_color']) ?>;"></span>
                            </td>
                            <td><?= htmlspecialchars($event['created_by_name'] ?? '-') ?></td>
                            <td><?= date('Y/m/d', strtotime($event['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8') ?>)">編集</button>
                                <form method="POST" action="events_save.php" style="display: inline;" onsubmit="return confirm('このイベントを削除しますか？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-dialog">
        <button class="modal-close" onclick="closeEditModal()">&times;</button>
        <div class="modal-header">
            <h2>イベント編集</h2>
        </div>
        <form id="editForm" method="POST" action="events_save.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="event_id" id="edit_event_id">

            <div class="form-group">
                <label class="form-label">日付 *</label>
                <input type="date" name="event_date" id="edit_event_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">イベント名 *</label>
                <input type="text" name="event_name" id="edit_event_name" class="form-control" required placeholder="例: 運動会">
            </div>

            <div class="form-group">
                <label class="form-label">説明</label>
                <textarea name="event_description" id="edit_event_description" class="form-control" placeholder="イベントの詳細を入力してください（任意）"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">スタッフ向けコメント</label>
                <textarea name="staff_comment" id="edit_staff_comment" class="form-control" placeholder="スタッフ間で共有する内部メモ（任意）"></textarea>
                <small style="color: var(--text-secondary);">スタッフのみ閲覧できます</small>
            </div>

            <div class="form-group">
                <label class="form-label">保護者・生徒連絡用</label>
                <textarea name="guardian_message" id="edit_guardian_message" class="form-control" placeholder="保護者・生徒に伝えたい内容（任意）"></textarea>
                <small style="color: var(--text-secondary);">保護者・生徒がカレンダーで確認できます</small>
            </div>

            <div class="form-group">
                <label class="form-label">対象者</label>
                <div class="checkbox-group" id="edit_target_audience_group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="all" id="edit_ta_all">
                        <label for="edit_ta_all">全体</label>
                    </div>
                    <?php foreach ($targetGrades as $grade): ?>
                        <?php if (isset($gradeLabels[$grade])): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_audience[]" value="<?= $grade ?>" id="edit_ta_<?= $grade ?>">
                            <label for="edit_ta_<?= $grade ?>"><?= $gradeLabels[$grade] ?></label>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="guardian" id="edit_ta_guardian">
                        <label for="edit_ta_guardian">保護者</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="target_audience[]" value="other" id="edit_ta_other">
                        <label for="edit_ta_other">その他</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">イベント色</label>
                <input type="color" name="event_color" id="edit_event_color" value="#667eea" style="width: 60px; height: 40px; border: none; cursor: pointer;">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">キャンセル</button>
                <button type="submit" class="btn btn-success">更新する</button>
            </div>
        </form>
    </div>
</div>

<script>
// 編集モーダルを開く
function openEditModal(event) {
    document.getElementById('edit_event_id').value = event.id;
    document.getElementById('edit_event_date').value = event.event_date;
    document.getElementById('edit_event_name').value = event.event_name;
    document.getElementById('edit_event_description').value = event.event_description || '';
    document.getElementById('edit_staff_comment').value = event.staff_comment || '';
    document.getElementById('edit_guardian_message').value = event.guardian_message || '';
    document.getElementById('edit_event_color').value = event.event_color || '#667eea';

    // チェックボックスをリセットして設定
    const checkboxes = document.querySelectorAll('#edit_target_audience_group input[type="checkbox"]');
    const audiences = (event.target_audience || 'all').split(',').map(s => s.trim());
    checkboxes.forEach(cb => {
        cb.checked = audiences.includes(cb.value);
    });

    document.getElementById('editModal').classList.add('active');
}

// 編集モーダルを閉じる
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// モーダル外クリックで閉じる
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php renderPageEnd(); ?>
