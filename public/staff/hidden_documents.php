<?php
/**
 * 非表示ドキュメント一覧ページ
 * 非表示にされた個別支援計画書、モニタリング、かけはしを一覧表示
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 検索パラメータを取得
$searchName = $_GET['name'] ?? '';
$searchDocType = $_GET['doc_type'] ?? '';  // plan, monitoring, guardian_kakehashi, staff_kakehashi

// ソートパラメータを取得
$sortBy = $_GET['sort'] ?? 'hidden_at';  // student_name, created_date, status, hidden_by, hidden_at
$sortDir = $_GET['dir'] ?? 'desc';  // asc, desc
$sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

// ソートURL生成関数
function getSortUrl($field, $currentSort, $currentDir) {
    $params = $_GET;
    $params['sort'] = $field;
    // 同じフィールドをクリックしたら方向を反転
    $params['dir'] = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

// ソートアイコン取得関数
function getSortIcon($field, $currentSort, $currentDir) {
    if ($currentSort !== $field) {
        return '<span class="sort-icon">⇅</span>';
    }
    return $currentDir === 'asc'
        ? '<span class="sort-icon active">↑</span>'
        : '<span class="sort-icon active">↓</span>';
}

// ORDER BY句を生成する関数（個別支援計画書・モニタリング用）
function getOrderByClause($sortBy, $sortDir, $type = 'plan') {
    $dir = strtoupper($sortDir);

    switch ($sortBy) {
        case 'student_name':
            return "ORDER BY s.student_name {$dir}";
        case 'created_date':
            if ($type === 'plan') {
                return "ORDER BY isp.created_date {$dir}";
            } elseif ($type === 'monitoring') {
                return "ORDER BY mr.monitoring_date {$dir}";
            } elseif ($type === 'guardian_kakehashi' || $type === 'staff_kakehashi') {
                return "ORDER BY kp.start_date {$dir}";
            }
            return "ORDER BY created_at {$dir}";
        case 'hidden_by':
            if ($type === 'plan') {
                return "ORDER BY isp.hidden_by {$dir}, s.student_name ASC";
            } elseif ($type === 'monitoring') {
                return "ORDER BY mr.hidden_by {$dir}, s.student_name ASC";
            } elseif ($type === 'guardian_kakehashi') {
                return "ORDER BY kg.hidden_by {$dir}, s.student_name ASC";
            } elseif ($type === 'staff_kakehashi') {
                return "ORDER BY ks.hidden_by {$dir}, s.student_name ASC";
            }
            return "ORDER BY hidden_by {$dir}";
        case 'status':
            // ステータスソートはPHPで行うため、デフォルトのソートを使用
            return "ORDER BY s.student_name ASC";
        case 'hidden_at':
        default:
            if ($type === 'plan') {
                return "ORDER BY COALESCE(isp.hidden_at, isp.updated_at) {$dir}, s.student_name ASC";
            } elseif ($type === 'monitoring') {
                return "ORDER BY COALESCE(mr.hidden_at, mr.updated_at) {$dir}, s.student_name ASC";
            } elseif ($type === 'guardian_kakehashi') {
                return "ORDER BY COALESCE(kg.hidden_at, kg.updated_at) {$dir}, s.student_name ASC";
            } elseif ($type === 'staff_kakehashi') {
                return "ORDER BY COALESCE(ks.hidden_at, ks.updated_at) {$dir}, s.student_name ASC";
            }
            return "ORDER BY hidden_at {$dir}";
    }
}

// ステータスでソートする関数
function sortByStatus($items, $hasContentFunc, $sortDir) {
    usort($items, function($a, $b) use ($hasContentFunc, $sortDir) {
        $aHasContent = $hasContentFunc($a) ? 1 : 0;
        $bHasContent = $hasContentFunc($b) ? 1 : 0;
        $result = $aHasContent - $bHasContent;
        return $sortDir === 'asc' ? $result : -$result;
    });
    return $items;
}

// ユーザーID→名前のマッピングを取得する関数
function getUserName($pdo, $userId) {
    if (!$userId) return null;
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : null;
}

// 個別支援計画書に内容があるかチェック
function planHasContent($plan) {
    return !empty(trim($plan['life_intention'] ?? '')) ||
           !empty(trim($plan['overall_policy'] ?? '')) ||
           !empty(trim($plan['long_term_goal_text'] ?? '')) ||
           !empty(trim($plan['short_term_goal_text'] ?? ''));
}

// モニタリングに内容があるかチェック
function monitoringHasContent($monitoring) {
    return !empty(trim($monitoring['overall_comment'] ?? '')) ||
           !empty(trim($monitoring['short_term_goal_comment'] ?? '')) ||
           !empty(trim($monitoring['long_term_goal_comment'] ?? ''));
}

// 保護者かけはしに内容があるかチェック
function guardianKakehashiHasContent($kakehashi) {
    return !empty(trim($kakehashi['student_wish'] ?? '')) ||
           !empty(trim($kakehashi['home_challenges'] ?? '')) ||
           !empty(trim($kakehashi['short_term_goal'] ?? '')) ||
           !empty(trim($kakehashi['long_term_goal'] ?? ''));
}

// スタッフかけはしに内容があるかチェック
function staffKakehashiHasContent($kakehashi) {
    return !empty(trim($kakehashi['student_wish'] ?? '')) ||
           !empty(trim($kakehashi['short_term_goal'] ?? '')) ||
           !empty(trim($kakehashi['long_term_goal'] ?? ''));
}

// 教室条件
$classroomCondition = $classroomId ? "AND u.classroom_id = ?" : "";
$classroomParams = $classroomId ? [$classroomId] : [];

// 名前検索条件を追加
$nameCondition = "";
if (!empty($searchName)) {
    $nameCondition = " AND s.student_name LIKE ?";
}

// 1. 非表示の個別支援計画書を取得（未提出のみ）
$hiddenPlans = [];
if (empty($searchDocType) || $searchDocType === 'plan') {
    $orderBy = getOrderByClause($sortBy, $sortDir, 'plan');
    $sql = "
        SELECT
            isp.id,
            isp.student_id,
            s.student_name,
            s.grade_level,
            isp.created_date,
            isp.is_draft,
            isp.hidden_by,
            isp.hidden_at,
            isp.life_intention,
            isp.overall_policy,
            isp.long_term_goal_text,
            isp.short_term_goal_text
        FROM individual_support_plans isp
        INNER JOIN students s ON isp.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE isp.is_hidden = 1 AND isp.is_draft = 1
        {$classroomCondition}
        {$nameCondition}
        {$orderBy}
    ";
    try {
        $params = $classroomParams;
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hiddenPlans = $stmt->fetchAll();
        // ステータスでソート
        if ($sortBy === 'status') {
            $hiddenPlans = sortByStatus($hiddenPlans, 'planHasContent', $sortDir);
        }
    } catch (Exception $e) {
        error_log("Hidden plans fetch error: " . $e->getMessage());
    }
}

// 2. 非表示のモニタリングを取得（未提出のみ）
$hiddenMonitorings = [];
if (empty($searchDocType) || $searchDocType === 'monitoring') {
    $orderBy = getOrderByClause($sortBy, $sortDir, 'monitoring');
    $sql = "
        SELECT
            mr.id,
            mr.student_id,
            s.student_name,
            s.grade_level,
            mr.monitoring_date,
            mr.is_draft,
            mr.hidden_by,
            mr.hidden_at,
            mr.overall_comment,
            mr.short_term_goal_comment,
            mr.long_term_goal_comment
        FROM monitoring_records mr
        INNER JOIN students s ON mr.student_id = s.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE mr.is_hidden = 1 AND mr.is_draft = 1
        {$classroomCondition}
        {$nameCondition}
        {$orderBy}
    ";
    try {
        $params = $classroomParams;
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hiddenMonitorings = $stmt->fetchAll();
        // ステータスでソート
        if ($sortBy === 'status') {
            $hiddenMonitorings = sortByStatus($hiddenMonitorings, 'monitoringHasContent', $sortDir);
        }
    } catch (Exception $e) {
        error_log("Hidden monitorings fetch error: " . $e->getMessage());
    }
}

// 3. 初回モニタリングが非表示の生徒を取得
$hiddenInitialMonitorings = [];
if (empty($searchDocType) || $searchDocType === 'monitoring') {
    // 初回モニタリング用のソート
    $initMonOrderBy = match($sortBy) {
        'student_name' => "ORDER BY s.student_name " . strtoupper($sortDir),
        'hidden_by' => "ORDER BY s.hide_initial_monitoring_by " . strtoupper($sortDir) . ", s.student_name ASC",
        default => "ORDER BY COALESCE(s.hide_initial_monitoring_at, s.updated_at) " . strtoupper($sortDir) . ", s.student_name ASC"
    };
    $sql = "
        SELECT
            s.id as student_id,
            s.student_name,
            s.grade_level,
            s.hide_initial_monitoring_by,
            s.hide_initial_monitoring_at
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.hide_initial_monitoring = 1
        {$classroomCondition}
        {$nameCondition}
        {$initMonOrderBy}
    ";
    try {
        $params = $classroomParams;
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hiddenInitialMonitorings = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Hidden initial monitorings fetch error: " . $e->getMessage());
    }
}

// 4. 非表示の保護者かけはしを取得（未提出のみ）
$hiddenGuardianKakehashi = [];
if (empty($searchDocType) || $searchDocType === 'guardian_kakehashi') {
    $orderBy = getOrderByClause($sortBy, $sortDir, 'guardian_kakehashi');
    $sql = "
        SELECT
            kg.id,
            kg.period_id,
            kg.student_id,
            s.student_name,
            s.grade_level,
            kp.period_name,
            kp.start_date,
            kp.end_date,
            kp.submission_deadline,
            kg.hidden_by,
            kg.hidden_at,
            kg.student_wish,
            kg.home_challenges,
            kg.short_term_goal,
            kg.long_term_goal
        FROM kakehashi_guardian kg
        INNER JOIN students s ON kg.student_id = s.id
        INNER JOIN kakehashi_periods kp ON kg.period_id = kp.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE kg.is_hidden = 1 AND kg.is_submitted = 0
        {$classroomCondition}
        {$nameCondition}
        {$orderBy}
    ";
    try {
        $params = $classroomParams;
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hiddenGuardianKakehashi = $stmt->fetchAll();
        // ステータスでソート
        if ($sortBy === 'status') {
            $hiddenGuardianKakehashi = sortByStatus($hiddenGuardianKakehashi, 'guardianKakehashiHasContent', $sortDir);
        }
    } catch (Exception $e) {
        error_log("Hidden guardian kakehashi fetch error: " . $e->getMessage());
    }
}

// 5. 非表示のスタッフかけはしを取得（未提出のみ）
$hiddenStaffKakehashi = [];
if (empty($searchDocType) || $searchDocType === 'staff_kakehashi') {
    $orderBy = getOrderByClause($sortBy, $sortDir, 'staff_kakehashi');
    $sql = "
        SELECT
            ks.id,
            ks.period_id,
            ks.student_id,
            s.student_name,
            s.grade_level,
            kp.period_name,
            kp.start_date,
            kp.end_date,
            kp.submission_deadline,
            ks.hidden_by,
            ks.hidden_at,
            ks.student_wish,
            ks.short_term_goal,
            ks.long_term_goal
        FROM kakehashi_staff ks
        INNER JOIN students s ON ks.student_id = s.id
        INNER JOIN kakehashi_periods kp ON ks.period_id = kp.id
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE ks.is_hidden = 1 AND ks.is_submitted = 0
        {$classroomCondition}
        {$nameCondition}
        {$orderBy}
    ";
    try {
        $params = $classroomParams;
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $hiddenStaffKakehashi = $stmt->fetchAll();
        // ステータスでソート
        if ($sortBy === 'status') {
            $hiddenStaffKakehashi = sortByStatus($hiddenStaffKakehashi, 'staffKakehashiHasContent', $sortDir);
        }
    } catch (Exception $e) {
        error_log("Hidden staff kakehashi fetch error: " . $e->getMessage());
    }
}

// 総件数を計算
$totalHiddenCount = count($hiddenPlans) + count($hiddenMonitorings) + count($hiddenInitialMonitorings) + count($hiddenGuardianKakehashi) + count($hiddenStaffKakehashi);

// ページ開始
$currentPage = 'hidden_documents';
$pageTitle = '非表示ドキュメント一覧';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
    .filter-area {
        background: var(--md-bg-primary);
        padding: 16px 20px;
        margin-bottom: var(--spacing-lg);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .form-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .form-control {
        padding: 8px 12px;
        border: 1px solid var(--cds-border-subtle-00);
        border-radius: 0;
        font-size: 14px;
        min-width: 180px;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--cds-blue-60);
        box-shadow: 0 0 0 2px rgba(15, 98, 254, 0.2);
    }

    .filter-buttons {
        display: flex;
        gap: 8px;
    }

    .result-count {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--cds-border-subtle-00);
    }

    .content {
        padding: var(--spacing-2xl);
    }

    .section {
        margin-bottom: 40px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-lg);
        padding-bottom: 10px;
        border-bottom: 3px solid var(--md-gray);
    }

    .section-title {
        font-size: 22px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .count-badge {
        background: var(--md-gray);
        color: white;
        padding: 5px 15px;
        border-radius: var(--radius-xl);
        font-size: var(--text-subhead);
        font-weight: 600;
    }

    .count-badge.has-items {
        background: var(--md-orange);
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: var(--radius-md);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: var(--md-bg-primary);
    }

    th {
        background: var(--md-bg-secondary);
        color: var(--text-primary);
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: var(--text-subhead);
    }

    td {
        padding: 15px;
        border-bottom: 1px solid var(--cds-border-subtle-00);
    }

    tr:hover {
        background: var(--md-gray-6);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: var(--radius-md);
        font-size: var(--text-caption-1);
        font-weight: 600;
    }

    .status-badge.draft {
        background: var(--md-purple);
        color: white;
    }

    .status-badge.submitted {
        background: var(--md-green);
        color: white;
    }

    .status-badge.not-created {
        background: var(--md-gray);
        color: white;
    }

    /* ソート可能なヘッダー */
    th.sortable {
        cursor: pointer;
        user-select: none;
        transition: background-color 0.2s;
    }

    th.sortable:hover {
        background: var(--md-gray-6);
    }

    th.sortable a {
        color: inherit;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sort-icon {
        font-size: 12px;
        opacity: 0.4;
    }

    .sort-icon.active {
        opacity: 1;
        color: var(--md-blue);
    }

    .hidden-info {
        font-size: var(--text-footnote);
        color: var(--text-secondary);
    }

    .hidden-by {
        font-weight: 600;
        color: var(--text-primary);
    }

    .btn {
        padding: var(--spacing-sm) 16px;
        border: none;
        border-radius: 6px;
        font-size: var(--text-subhead);
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all var(--duration-normal) var(--ease-out);
        font-weight: 500;
    }

    .btn-primary {
        background: var(--md-bg-secondary);
        color: var(--text-primary);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-show {
        padding: 6px 12px;
        background: var(--md-teal);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: var(--text-footnote);
        cursor: pointer;
        transition: all var(--duration-normal) var(--ease-out);
        margin-left: 10px;
    }

    .btn-show:hover {
        background: #00897b;
    }

    .btn-show:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .empty-state {
        text-align: center;
        padding: var(--spacing-2xl);
        color: var(--text-secondary);
    }

    .empty-state h3 {
        color: var(--md-green);
        margin-bottom: var(--spacing-md);
    }

    .summary-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: var(--spacing-lg);
    }

    .summary-card {
        display: flex;
        align-items: center;
        gap: 10px;
        background: var(--md-bg-primary);
        padding: 10px 16px;
        border-radius: var(--radius-sm);
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border-left: 3px solid var(--md-gray);
    }

    .summary-card.has-items {
        border-left-color: var(--md-orange);
        background: rgba(255, 149, 0, 0.05);
    }

    .summary-card-title {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .summary-card-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }

    @media (max-width: 768px) {
        .filter-form {
            flex-direction: column;
        }

        .filter-group {
            width: 100%;
        }

        .form-control {
            width: 100%;
        }

        .filter-buttons {
            width: 100%;
        }

        .filter-buttons .btn {
            flex: 1;
        }

        .table-wrapper {
            font-size: 14px;
        }

        th, td {
            padding: 10px;
        }

        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
    }
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">非表示ドキュメント一覧</h1>
        <p class="page-subtitle">非表示ボタンで隠した未提出ドキュメントを確認・復元</p>
    </div>
    <div class="page-header-actions">
        <a href="pending_tasks.php" class="btn btn-secondary"><- 未作成タスク一覧に戻る</a>
    </div>
</div>

<!-- 検索フィルター -->
<div class="filter-area">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label class="form-label">生徒名で検索</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($searchName); ?>" placeholder="生徒名を入力...">
        </div>
        <div class="filter-group">
            <label class="form-label">ドキュメントの種類</label>
            <select name="doc_type" class="form-control">
                <option value="">すべて</option>
                <option value="plan" <?php echo $searchDocType === 'plan' ? 'selected' : ''; ?>>個別支援計画書</option>
                <option value="monitoring" <?php echo $searchDocType === 'monitoring' ? 'selected' : ''; ?>>モニタリング</option>
                <option value="guardian_kakehashi" <?php echo $searchDocType === 'guardian_kakehashi' ? 'selected' : ''; ?>>保護者かけはし</option>
                <option value="staff_kakehashi" <?php echo $searchDocType === 'staff_kakehashi' ? 'selected' : ''; ?>>スタッフかけはし</option>
            </select>
        </div>
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">search</span> 検索
            </button>
            <a href="?" class="btn btn-secondary">リセット</a>
        </div>
    </form>
    <div class="result-count">
        <?php echo $totalHiddenCount; ?>件の非表示ドキュメント
        <?php if (!empty($searchName) || !empty($searchDocType)): ?>
            （フィルター適用中）
        <?php endif; ?>
    </div>
</div>

<div class="content">
    <!-- サマリーカード -->
    <div class="summary-cards">
        <div class="summary-card <?php echo count($hiddenPlans) > 0 ? 'has-items' : ''; ?>">
            <span class="summary-card-title">個別支援計画書</span>
            <span class="summary-card-value"><?php echo count($hiddenPlans); ?>件</span>
        </div>
        <div class="summary-card <?php echo (count($hiddenMonitorings) + count($hiddenInitialMonitorings)) > 0 ? 'has-items' : ''; ?>">
            <span class="summary-card-title">モニタリング</span>
            <span class="summary-card-value"><?php echo count($hiddenMonitorings) + count($hiddenInitialMonitorings); ?>件</span>
        </div>
        <div class="summary-card <?php echo count($hiddenGuardianKakehashi) > 0 ? 'has-items' : ''; ?>">
            <span class="summary-card-title">保護者かけはし</span>
            <span class="summary-card-value"><?php echo count($hiddenGuardianKakehashi); ?>件</span>
        </div>
        <div class="summary-card <?php echo count($hiddenStaffKakehashi) > 0 ? 'has-items' : ''; ?>">
            <span class="summary-card-title">スタッフかけはし</span>
            <span class="summary-card-value"><?php echo count($hiddenStaffKakehashi); ?>件</span>
        </div>
    </div>

    <?php if ($totalHiddenCount === 0): ?>
        <div class="empty-state">
            <h3><span class="material-symbols-outlined">visibility</span> 非表示のドキュメントはありません</h3>
            <p>すべてのドキュメントが表示されています。</p>
        </div>
    <?php else: ?>

        <!-- 個別支援計画書セクション -->
        <?php if (!empty($hiddenPlans)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><span class="material-symbols-outlined">edit_note</span> 個別支援計画書</h2>
                <span class="count-badge has-items"><?php echo count($hiddenPlans); ?>件</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable"><a href="<?php echo getSortUrl('student_name', $sortBy, $sortDir); ?>">生徒名 <?php echo getSortIcon('student_name', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('created_date', $sortBy, $sortDir); ?>">作成日 <?php echo getSortIcon('created_date', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('status', $sortBy, $sortDir); ?>">状態 <?php echo getSortIcon('status', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_by', $sortBy, $sortDir); ?>">非表示にした人 <?php echo getSortIcon('hidden_by', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_at', $sortBy, $sortDir); ?>">非表示日時 <?php echo getSortIcon('hidden_at', $sortBy, $sortDir); ?></a></th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hiddenPlans as $plan):
                            $hiddenByName = getUserName($pdo, $plan['hidden_by']);
                        ?>
                            <tr data-type="plan" data-id="<?php echo $plan['id']; ?>">
                                <td><?php echo htmlspecialchars($plan['student_name']); ?></td>
                                <td><?php echo $plan['created_date'] ? date('Y年n月j日', strtotime($plan['created_date'])) : '-'; ?></td>
                                <td>
                                    <?php if (planHasContent($plan)): ?>
                                        <span class="status-badge draft">下書き</span>
                                    <?php else: ?>
                                        <span class="status-badge not-created">未作成</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hidden-by"><?php echo $hiddenByName ? htmlspecialchars($hiddenByName) : '不明'; ?></span>
                                </td>
                                <td>
                                    <span class="hidden-info"><?php echo $plan['hidden_at'] ? date('Y/m/d H:i', strtotime($plan['hidden_at'])) : '-'; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="kobetsu_plan.php?student_id=<?php echo $plan['student_id']; ?>&plan_id=<?php echo $plan['id']; ?>" class="btn btn-primary">
                                            表示
                                        </a>
                                        <button class="btn-show" onclick="showItem('plan', <?php echo $plan['id']; ?>, this)">
                                            復元
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- モニタリングセクション -->
        <?php if (!empty($hiddenMonitorings) || !empty($hiddenInitialMonitorings)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><span class="material-symbols-outlined">monitoring</span> モニタリング</h2>
                <span class="count-badge has-items"><?php echo count($hiddenMonitorings) + count($hiddenInitialMonitorings); ?>件</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable"><a href="<?php echo getSortUrl('student_name', $sortBy, $sortDir); ?>">生徒名 <?php echo getSortIcon('student_name', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('created_date', $sortBy, $sortDir); ?>">モニタリング日 <?php echo getSortIcon('created_date', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('status', $sortBy, $sortDir); ?>">状態 <?php echo getSortIcon('status', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_by', $sortBy, $sortDir); ?>">非表示にした人 <?php echo getSortIcon('hidden_by', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_at', $sortBy, $sortDir); ?>">非表示日時 <?php echo getSortIcon('hidden_at', $sortBy, $sortDir); ?></a></th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hiddenMonitorings as $monitoring):
                            $hiddenByName = getUserName($pdo, $monitoring['hidden_by']);
                        ?>
                            <tr data-type="monitoring" data-id="<?php echo $monitoring['id']; ?>">
                                <td><?php echo htmlspecialchars($monitoring['student_name']); ?></td>
                                <td><?php echo $monitoring['monitoring_date'] ? date('Y年n月j日', strtotime($monitoring['monitoring_date'])) : '-'; ?></td>
                                <td>
                                    <?php if (monitoringHasContent($monitoring)): ?>
                                        <span class="status-badge draft">下書き</span>
                                    <?php else: ?>
                                        <span class="status-badge not-created">未作成</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hidden-by"><?php echo $hiddenByName ? htmlspecialchars($hiddenByName) : '不明'; ?></span>
                                </td>
                                <td>
                                    <span class="hidden-info"><?php echo $monitoring['hidden_at'] ? date('Y/m/d H:i', strtotime($monitoring['hidden_at'])) : '-'; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="kobetsu_monitoring.php?student_id=<?php echo $monitoring['student_id']; ?>&monitoring_id=<?php echo $monitoring['id']; ?>" class="btn btn-primary">
                                            表示
                                        </a>
                                        <button class="btn-show" onclick="showItem('monitoring', <?php echo $monitoring['id']; ?>, this)">
                                            復元
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($hiddenInitialMonitorings as $student):
                            $hiddenByName = getUserName($pdo, $student['hide_initial_monitoring_by']);
                        ?>
                            <tr data-type="initial_monitoring" data-student-id="<?php echo $student['student_id']; ?>">
                                <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td>-</td>
                                <td>
                                    <span class="status-badge not-created">初回モニタリング未作成</span>
                                </td>
                                <td>
                                    <span class="hidden-by"><?php echo $hiddenByName ? htmlspecialchars($hiddenByName) : '不明'; ?></span>
                                </td>
                                <td>
                                    <span class="hidden-info"><?php echo $student['hide_initial_monitoring_at'] ? date('Y/m/d H:i', strtotime($student['hide_initial_monitoring_at'])) : '-'; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="kobetsu_monitoring.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-primary">
                                            作成
                                        </a>
                                        <button class="btn-show" onclick="showInitialMonitoring(<?php echo $student['student_id']; ?>, this)">
                                            復元
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- 保護者かけはしセクション -->
        <?php if (!empty($hiddenGuardianKakehashi)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><span class="material-symbols-outlined">handshake</span> 保護者かけはし</h2>
                <span class="count-badge has-items"><?php echo count($hiddenGuardianKakehashi); ?>件</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable"><a href="<?php echo getSortUrl('student_name', $sortBy, $sortDir); ?>">生徒名 <?php echo getSortIcon('student_name', $sortBy, $sortDir); ?></a></th>
                            <th>期間名</th>
                            <th class="sortable"><a href="<?php echo getSortUrl('created_date', $sortBy, $sortDir); ?>">対象期間 <?php echo getSortIcon('created_date', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('status', $sortBy, $sortDir); ?>">状態 <?php echo getSortIcon('status', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_by', $sortBy, $sortDir); ?>">非表示にした人 <?php echo getSortIcon('hidden_by', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_at', $sortBy, $sortDir); ?>">非表示日時 <?php echo getSortIcon('hidden_at', $sortBy, $sortDir); ?></a></th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hiddenGuardianKakehashi as $kakehashi):
                            $hiddenByName = getUserName($pdo, $kakehashi['hidden_by']);
                        ?>
                            <tr data-type="guardian" data-period-id="<?php echo $kakehashi['period_id']; ?>" data-student-id="<?php echo $kakehashi['student_id']; ?>">
                                <td><?php echo htmlspecialchars($kakehashi['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($kakehashi['period_name']); ?></td>
                                <td><?php echo date('Y/m/d', strtotime($kakehashi['start_date'])) . ' ~ ' . date('Y/m/d', strtotime($kakehashi['end_date'])); ?></td>
                                <td>
                                    <?php if (guardianKakehashiHasContent($kakehashi)): ?>
                                        <span class="status-badge draft">下書き</span>
                                    <?php else: ?>
                                        <span class="status-badge not-created">未作成</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hidden-by"><?php echo $hiddenByName ? htmlspecialchars($hiddenByName) : '不明'; ?></span>
                                </td>
                                <td>
                                    <span class="hidden-info"><?php echo $kakehashi['hidden_at'] ? date('Y/m/d H:i', strtotime($kakehashi['hidden_at'])) : '-'; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="kakehashi_guardian_view.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                            表示
                                        </a>
                                        <button class="btn-show" onclick="showKakehashi('guardian', <?php echo $kakehashi['period_id']; ?>, <?php echo $kakehashi['student_id']; ?>, this)">
                                            復元
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- スタッフかけはしセクション -->
        <?php if (!empty($hiddenStaffKakehashi)): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><span class="material-symbols-outlined">handshake</span> スタッフかけはし</h2>
                <span class="count-badge has-items"><?php echo count($hiddenStaffKakehashi); ?>件</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th class="sortable"><a href="<?php echo getSortUrl('student_name', $sortBy, $sortDir); ?>">生徒名 <?php echo getSortIcon('student_name', $sortBy, $sortDir); ?></a></th>
                            <th>期間名</th>
                            <th class="sortable"><a href="<?php echo getSortUrl('created_date', $sortBy, $sortDir); ?>">対象期間 <?php echo getSortIcon('created_date', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('status', $sortBy, $sortDir); ?>">状態 <?php echo getSortIcon('status', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_by', $sortBy, $sortDir); ?>">非表示にした人 <?php echo getSortIcon('hidden_by', $sortBy, $sortDir); ?></a></th>
                            <th class="sortable"><a href="<?php echo getSortUrl('hidden_at', $sortBy, $sortDir); ?>">非表示日時 <?php echo getSortIcon('hidden_at', $sortBy, $sortDir); ?></a></th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hiddenStaffKakehashi as $kakehashi):
                            $hiddenByName = getUserName($pdo, $kakehashi['hidden_by']);
                        ?>
                            <tr data-type="staff" data-period-id="<?php echo $kakehashi['period_id']; ?>" data-student-id="<?php echo $kakehashi['student_id']; ?>">
                                <td><?php echo htmlspecialchars($kakehashi['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($kakehashi['period_name']); ?></td>
                                <td><?php echo date('Y/m/d', strtotime($kakehashi['start_date'])) . ' ~ ' . date('Y/m/d', strtotime($kakehashi['end_date'])); ?></td>
                                <td>
                                    <?php if (staffKakehashiHasContent($kakehashi)): ?>
                                        <span class="status-badge draft">下書き</span>
                                    <?php else: ?>
                                        <span class="status-badge not-created">未作成</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="hidden-by"><?php echo $hiddenByName ? htmlspecialchars($hiddenByName) : '不明'; ?></span>
                                </td>
                                <td>
                                    <span class="hidden-info"><?php echo $kakehashi['hidden_at'] ? date('Y/m/d H:i', strtotime($kakehashi['hidden_at'])) : '-'; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                            表示
                                        </a>
                                        <button class="btn-show" onclick="showKakehashi('staff', <?php echo $kakehashi['period_id']; ?>, <?php echo $kakehashi['student_id']; ?>, this)">
                                            復元
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    function showItem(type, id, button) {
        if (!confirm('このドキュメントを復元しますか？\n復元すると、タスク一覧に再表示されます。')) {
            return;
        }

        button.disabled = true;
        button.textContent = '処理中...';

        fetch('pending_tasks_toggle_hide.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `type=${type}&id=${id}&action=show`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = button.closest('tr');
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';

                setTimeout(() => {
                    row.remove();
                    // テーブルが空になったらページをリロード
                    const tbody = row.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                alert('エラー: ' + (data.error || '不明なエラーが発生しました'));
                button.disabled = false;
                button.textContent = '復元';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
            button.disabled = false;
            button.textContent = '復元';
        });
    }

    function showInitialMonitoring(studentId, button) {
        if (!confirm('この初回モニタリングタスクを復元しますか？\n復元すると、タスク一覧に再表示されます。')) {
            return;
        }

        button.disabled = true;
        button.textContent = '処理中...';

        fetch('pending_tasks_toggle_hide.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `type=initial_monitoring&student_id=${studentId}&action=show`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = button.closest('tr');
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';

                setTimeout(() => {
                    row.remove();
                    const tbody = row.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                alert('エラー: ' + (data.error || '不明なエラーが発生しました'));
                button.disabled = false;
                button.textContent = '復元';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
            button.disabled = false;
            button.textContent = '復元';
        });
    }

    function showKakehashi(type, periodId, studentId, button) {
        if (!confirm('このかけはしを復元しますか？\n復元すると、タスク一覧に再表示されます。')) {
            return;
        }

        button.disabled = true;
        button.textContent = '処理中...';

        fetch('kakehashi_toggle_hide.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `type=${type}&period_id=${periodId}&student_id=${studentId}&action=show`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = button.closest('tr');
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';

                setTimeout(() => {
                    row.remove();
                    const tbody = row.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                alert('エラー: ' + (data.error || '不明なエラーが発生しました'));
                button.disabled = false;
                button.textContent = '復元';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
            button.disabled = false;
            button.textContent = '復元';
        });
    }
</script>

<?php renderPageEnd(); ?>
