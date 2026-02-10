<?php
/**
 * 保護者用 - 連絡帳一覧・検索ページ
 * 検索、フィルタリング、統計機能付き
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

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// 検索パラメータを取得
$selectedStudentId = $_GET['student_id'] ?? '';
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';
$searchDomain = $_GET['domain'] ?? '';

// 検索条件が何も指定されていない場合は直近1か月のみ表示
$isSearching = !empty($selectedStudentId) || !empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($searchDomain);
$defaultStartDate = date('Y-m-d', strtotime('-1 month'));

// この保護者に紐づく生徒を取得
$stmt = $pdo->prepare("
    SELECT id, student_name, grade_level, birth_date
    FROM students
    WHERE guardian_id = ? AND is_active = 1
    ORDER BY student_name
");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 学年表示用のラベル
function getGradeLabel($gradeLevel) {
    $labels = [
        'elementary' => '小学生',
        'junior_high' => '中学生',
        'high_school' => '高校生'
    ];
    return $labels[$gradeLevel] ?? '';
}

// 領域ラベル
$domainLabels = [
    'health_life' => '健康・生活',
    'motor_sensory' => '運動・感覚',
    'cognitive_behavior' => '認知・行動',
    'language_communication' => '言語・コミュニケーション',
    'social_relations' => '人間関係・社会性'
];

// 連絡帳を検索
$sql = "
    SELECT
        inote.id,
        inote.integrated_content,
        inote.sent_at,
        inote.guardian_confirmed,
        inote.guardian_confirmed_at,
        dr.activity_name,
        dr.common_activity,
        dr.record_date,
        s.id as student_id,
        s.student_name,
        s.grade_level,
        sr.domain1,
        sr.domain2,
        sr.daily_note
    FROM integrated_notes inote
    INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
    INNER JOIN students s ON inote.student_id = s.id
    LEFT JOIN student_records sr ON sr.daily_record_id = dr.id AND sr.student_id = s.id
    WHERE s.guardian_id = ? AND inote.is_sent = 1
";

$params = [$guardianId];

if (!empty($selectedStudentId)) {
    $sql .= " AND s.id = ?";
    $params[] = $selectedStudentId;
}

if (!empty($searchKeyword)) {
    $sql .= " AND (inote.integrated_content LIKE ? OR dr.activity_name LIKE ? OR dr.common_activity LIKE ?)";
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
}

if (!empty($searchStartDate)) {
    $sql .= " AND dr.record_date >= ?";
    $params[] = $searchStartDate;
}

if (!empty($searchEndDate)) {
    $sql .= " AND dr.record_date <= ?";
    $params[] = $searchEndDate;
}

// 検索条件がない場合はデフォルトで直近1か月のみ表示
if (!$isSearching) {
    $sql .= " AND dr.record_date >= ?";
    $params[] = $defaultStartDate;
}

if (!empty($searchDomain)) {
    $sql .= " AND (sr.domain1 = ? OR sr.domain2 = ?)";
    $params[] = $searchDomain;
    $params[] = $searchDomain;
}

$sql .= " ORDER BY dr.record_date DESC, inote.sent_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

// 統計データの計算
$stats = [
    'total_count' => count($notes),
    'domain_counts' => [],
    'monthly_counts' => []
];

foreach ($domainLabels as $key => $label) {
    $stats['domain_counts'][$key] = 0;
}

foreach ($notes as $note) {
    if (!empty($note['domain1'])) {
        $stats['domain_counts'][$note['domain1']]++;
    }
    if (!empty($note['domain2']) && $note['domain2'] !== $note['domain1']) {
        $stats['domain_counts'][$note['domain2']]++;
    }

    $month = date('Y-m', strtotime($note['record_date']));
    if (!isset($stats['monthly_counts'][$month])) {
        $stats['monthly_counts'][$month] = 0;
    }
    $stats['monthly_counts'][$month]++;
}

krsort($stats['monthly_counts']);

// ページ開始
$currentPage = 'communication_logs';
renderPageStart('guardian', $currentPage, '連絡帳一覧', ['classroom' => $classroom]);
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.stat-card {
    background: var(--md-gray-6);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    text-align: center;
    border-left: 4px solid var(--md-purple);
}

.stat-value {
    font-size: var(--text-title-2);
    font-weight: bold;
    color: var(--md-purple);
    margin-bottom: 5px;
}

.stat-label {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.domain-bar {
    margin-bottom: var(--spacing-md);
}

.domain-bar-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: var(--text-subhead);
}

.domain-bar-bg {
    background: var(--md-gray-5);
    height: 24px;
    border-radius: var(--radius-md);
    overflow: hidden;
}

.domain-bar-fill {
    background: var(--cds-purple-60);
    height: 100%;
    border-radius: var(--radius-md);
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
    color: white;
    font-size: var(--text-caption-1);
    font-weight: bold;
}

.note-item {
    background: var(--md-gray-6);
    padding: var(--spacing-lg);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
    border-left: 4px solid var(--md-purple);
}

.note-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.activity-name {
    font-weight: bold;
    color: var(--md-purple);
    font-size: var(--text-callout);
    margin-bottom: 5px;
}

.student-name {
    color: var(--text-secondary);
    font-size: var(--text-subhead);
}

.note-meta {
    text-align: right;
    color: var(--text-secondary);
    font-size: var(--text-subhead);
}

.note-date {
    font-weight: bold;
    color: var(--text-primary);
}

.note-badges {
    display: flex;
    gap: 5px;
    margin-top: 5px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.domain-badge {
    background: var(--cds-blue-60);
    color: white;
    padding: 3px 10px;
    border-radius: var(--radius-md);
    font-size: 11px;
    font-weight: bold;
}

.note-content {
    color: var(--text-primary);
    line-height: 1.8;
    white-space: pre-wrap;
    font-size: var(--text-subhead);
}

.search-info {
    background: var(--cds-blue-60);
    border-left: 4px solid var(--cds-blue-60);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    color: white;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .note-header { flex-direction: column; align-items: flex-start; }
    .note-meta { text-align: left; }
    .note-badges { justify-content: flex-start; }
}

.confirmation-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-md);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--md-gray-5);
}

.confirmation-checkbox {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.confirmation-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.confirmation-checkbox label {
    cursor: pointer;
    font-weight: 500;
    color: var(--text-primary);
}

.confirmation-checkbox.confirmed label {
    color: var(--md-green);
}

.confirmation-date {
    font-size: var(--text-footnote);
    color: var(--md-green);
}

.note-item.unconfirmed {
    border-left-color: var(--md-orange);
}

.note-item.confirmed {
    border-left-color: var(--md-green);
}

@media print {
    .search-section, .btn { display: none !important; }
    .note-item { page-break-inside: avoid; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">連絡帳一覧・検索</h1>
        <p class="page-subtitle">過去の活動記録を検索・確認できます</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">print</span> 印刷</button>
</div>

<!-- 検索フォーム -->
<div class="card search-section" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-body); margin-bottom: var(--spacing-lg); color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">search</span> 検索・フィルター</h2>
        <form method="GET" action="communication_logs.php">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-md);">
                <div class="form-group">
                    <label class="form-label">お子様</label>
                    <select name="student_id" class="form-control">
                        <option value="">すべて</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $selectedStudentId == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">期間（開始）</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($searchStartDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">期間（終了）</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($searchEndDate) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">領域</label>
                    <select name="domain" class="form-control">
                        <option value="">すべて</option>
                        <?php foreach ($domainLabels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $searchDomain === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($searchKeyword) ?>" placeholder="活動内容や様子で検索">
                </div>
            </div>
            <div style="display: flex; gap: var(--spacing-sm); justify-content: flex-end; margin-top: var(--spacing-md);">
                <a href="communication_logs.php" class="btn btn-secondary">クリア</a>
                <button type="submit" class="btn btn-primary">検索</button>
            </div>
        </form>
    </div>
</div>

<!-- 統計情報 -->
<?php if ($stats['total_count'] > 0): ?>
<div class="card" style="margin-bottom: var(--spacing-lg);">
    <div class="card-body">
        <h2 style="font-size: var(--text-body); margin-bottom: var(--spacing-lg); color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">monitoring</span> 統計情報</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_count'] ?></div>
                <div class="stat-label">件の記録</div>
            </div>
            <?php if (!empty($stats['monthly_counts'])): ?>
                <?php $latestMonth = array_key_first($stats['monthly_counts']); ?>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['monthly_counts'][$latestMonth] ?></div>
                    <div class="stat-label">今月の記録</div>
                </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_unique(array_column($notes, 'record_date'))) ?></div>
                <div class="stat-label">活動日数</div>
            </div>
        </div>

        <div style="margin-top: var(--spacing-lg);">
            <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary); font-size: var(--text-callout);">支援領域別の記録数</h3>
            <?php
            $maxCount = max(array_values($stats['domain_counts']));
            foreach ($domainLabels as $key => $label):
                $count = $stats['domain_counts'][$key];
                $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
            ?>
                <div class="domain-bar">
                    <div class="domain-bar-label">
                        <span><?= $label ?></span>
                        <span><strong><?= $count ?>件</strong></span>
                    </div>
                    <div class="domain-bar-bg">
                        <div class="domain-bar-fill" style="width: <?= $percentage ?>%">
                            <?php if ($percentage > 15): ?><?= $count ?>件<?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 検索結果 -->
<div class="card">
    <div class="card-body">
        <h2 style="font-size: var(--text-body); margin-bottom: var(--spacing-lg); color: var(--md-purple);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 連絡帳一覧</h2>

        <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($selectedStudentId) || !empty($searchDomain)): ?>
            <div class="search-info">
                <strong>検索結果:</strong> <?= count($notes) ?>件の連絡帳が見つかりました
            </div>
        <?php else: ?>
            <div class="search-info" style="background: var(--cds-purple-60); border-left-color: var(--cds-purple-60);">
                <strong><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">event</span> 直近1か月分を表示中</strong>（<?= date('Y年n月j日', strtotime($defaultStartDate)) ?>以降）<br>
                <span style="font-size: var(--text-caption-1);">過去の連絡帳を見るには、上の検索フォームで期間を指定してください</span>
            </div>
        <?php endif; ?>

        <?php if (empty($notes)): ?>
            <div style="text-align: center; padding: var(--spacing-3xl); color: var(--text-secondary);">
                <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($selectedStudentId) || !empty($searchDomain)): ?>
                    <h3 style="margin-bottom: var(--spacing-md);">検索条件に一致する連絡帳が見つかりませんでした</h3>
                    <p>検索条件を変更してお試しください</p>
                <?php else: ?>
                    <h3 style="margin-bottom: var(--spacing-md);">直近1か月に連絡帳はありません</h3>
                    <p>過去の連絡帳を見るには、上の検索フォームで期間を指定してください</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <div class="note-item <?= $note['guardian_confirmed'] ? 'confirmed' : 'unconfirmed' ?>">
                    <div class="note-header">
                        <div class="note-title">
                            <div class="activity-name"><?= htmlspecialchars($note['activity_name']) ?></div>
                            <div class="student-name"><?= htmlspecialchars($note['student_name']) ?>（<?= getGradeLabel($note['grade_level']) ?>）</div>
                        </div>
                        <div class="note-meta">
                            <div class="note-date">
                                <?= date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($note['record_date']))] . '）', strtotime($note['record_date'])) ?>
                            </div>
                            <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 3px;">
                                送信: <?= date('m/d H:i', strtotime($note['sent_at'])) ?>
                            </div>
                            <div class="note-badges">
                                <?php if (!empty($note['domain1'])): ?>
                                    <span class="domain-badge"><?= $domainLabels[$note['domain1']] ?? '' ?></span>
                                <?php endif; ?>
                                <?php if (!empty($note['domain2']) && $note['domain2'] !== $note['domain1']): ?>
                                    <span class="domain-badge"><?= $domainLabels[$note['domain2']] ?? '' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="note-content"><?= nl2br(htmlspecialchars($note['integrated_content'])) ?></div>
                    <div class="confirmation-box">
                        <div class="confirmation-checkbox <?= $note['guardian_confirmed'] ? 'confirmed' : '' ?>">
                            <input
                                type="checkbox"
                                id="confirm_<?= $note['id'] ?>"
                                <?= $note['guardian_confirmed'] ? 'checked disabled' : '' ?>
                                onchange="confirmNote(<?= $note['id'] ?>)"
                            >
                            <label for="confirm_<?= $note['id'] ?>">確認しました</label>
                        </div>
                        <?php if ($note['guardian_confirmed'] && $note['guardian_confirmed_at']): ?>
                            <span class="confirmation-date">
                                確認日時: <?= date('Y年n月j日 H:i', strtotime($note['guardian_confirmed_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$inlineJs = <<<JS
function confirmNote(noteId) {
    if (!confirm('この連絡帳を「確認しました」にしてよろしいですか?')) {
        document.getElementById('confirm_' + noteId).checked = false;
        return;
    }

    fetch('confirm_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'note_id=' + noteId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('エラーが発生しました: ' + (data.error || '不明なエラー'));
            document.getElementById('confirm_' + noteId).checked = false;
        }
    })
    .catch(error => {
        alert('通信エラーが発生しました');
        console.error('Error:', error);
        document.getElementById('confirm_' + noteId).checked = false;
    });
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
