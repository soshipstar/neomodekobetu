<?php
/**
 * 未作成タスク一覧ページ
 * 個別支援計画書、モニタリング、かけはしの未作成・未提出を一覧表示
 */

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$today = date('Y-m-d');

// is_hiddenカラムが存在するか確認し、なければ追加
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM individual_support_plans LIKE 'is_hidden'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE individual_support_plans ADD COLUMN is_hidden TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    error_log("Add is_hidden column to individual_support_plans error: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM monitoring_records LIKE 'is_hidden'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE monitoring_records ADD COLUMN is_hidden TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    error_log("Add is_hidden column to monitoring_records error: " . $e->getMessage());
}

// 初回モニタリング未作成の非表示フラグ用カラムを追加
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'hide_initial_monitoring'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN hide_initial_monitoring TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    error_log("Add hide_initial_monitoring column to students error: " . $e->getMessage());
}

// かけはし期間の自動生成（期限1ヶ月前に次の期間を生成）
try {
    autoGenerateNextKakehashiPeriods($pdo);
} catch (Exception $e) {
    error_log("Auto-generate kakehashi periods error: " . $e->getMessage());
}

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 生徒一覧を取得（最新の個別支援計画書・モニタリングを確認するため）
$studentCondition = $classroomId ? "AND u.classroom_id = ?" : "";
$studentParams = $classroomId ? [$classroomId] : [];

// 1. 個別支援計画書一覧を取得（未提出・下書き・期限切れ）
// 各生徒の最新の提出済み計画書IDを取得
$studentsNeedingPlan = [];

$sql = "
    SELECT
        s.id,
        s.student_name,
        s.support_start_date,
        isp.id as plan_id,
        isp.created_date,
        isp.is_draft,
        COALESCE(isp.is_hidden, 0) as is_hidden,
        DATEDIFF(CURDATE(), isp.created_date) as days_since_plan,
        (
            SELECT MAX(isp2.id)
            FROM individual_support_plans isp2
            WHERE isp2.student_id = s.id AND isp2.is_draft = 0
        ) as latest_submitted_plan_id
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    LEFT JOIN individual_support_plans isp ON s.id = isp.student_id
    WHERE s.is_active = 1
    {$studentCondition}
    ORDER BY s.student_name, isp.created_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($studentParams);
$allPlanData = $stmt->fetchAll();

// 生徒ごとにグループ化
$studentPlans = [];
foreach ($allPlanData as $row) {
    $studentId = $row['id'];
    if (!isset($studentPlans[$studentId])) {
        $studentPlans[$studentId] = [
            'student_name' => $row['student_name'],
            'support_start_date' => $row['support_start_date'],
            'plans' => [],
            'latest_submitted_plan_id' => $row['latest_submitted_plan_id']
        ];
    }
    if ($row['plan_id']) {
        $studentPlans[$studentId]['plans'][] = $row;
    }
}

// 次の個別支援計画書期限が1ヶ月以内かチェックする関数
function isNextPlanDeadlineWithinOneMonth($supportStartDate, $latestPlanDate) {
    if (!$supportStartDate) return false;

    $oneMonthLater = new DateTime();
    $oneMonthLater->modify('+1 month');

    if (!$latestPlanDate) {
        // 計画書がない場合、初回期限は支援開始日の前日
        $firstDeadline = new DateTime($supportStartDate);
        $firstDeadline->modify('-1 day');
        return $firstDeadline <= $oneMonthLater;
    }

    // 次の計画書期限は最新計画書から180日後
    $nextDeadline = new DateTime($latestPlanDate);
    $nextDeadline->modify('+180 days');
    return $nextDeadline <= $oneMonthLater;
}

// 表示対象を抽出
foreach ($studentPlans as $studentId => $data) {
    $latestSubmittedId = $data['latest_submitted_plan_id'];
    $supportStartDate = $data['support_start_date'];

    // 最新の提出済み計画書の日付を取得
    $latestSubmittedPlanDate = null;
    foreach ($data['plans'] as $plan) {
        if ($plan['plan_id'] == $latestSubmittedId) {
            $latestSubmittedPlanDate = $plan['created_date'];
            break;
        }
    }

    // 計画書がない場合
    if (empty($data['plans'])) {
        // 次の期限が1ヶ月以内の場合のみ表示
        if (isNextPlanDeadlineWithinOneMonth($supportStartDate, null)) {
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => null,
                'latest_plan_date' => null,
                'days_since_plan' => null,
                'status_code' => 'none',
                'has_newer' => false,
                'is_hidden' => false
            ];
        }
        continue;
    }

    // 下書きがあるかチェック（下書きがあれば期限切れは表示しない）
    $hasDraft = false;
    $draftPlan = null;
    foreach ($data['plans'] as $plan) {
        if ($plan['is_draft'] && !$plan['is_hidden']) {
            $hasDraft = true;
            $draftPlan = $plan;
            break; // 最新の下書きを使用
        }
    }

    // 下書きがある場合は下書きのみ表示（次の期限が1ヶ月以内の場合のみ）
    if ($hasDraft && $draftPlan) {
        if (isNextPlanDeadlineWithinOneMonth($supportStartDate, $latestSubmittedPlanDate)) {
            $hasNewer = $latestSubmittedId && $draftPlan['plan_id'] != $latestSubmittedId;
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => $draftPlan['plan_id'],
                'latest_plan_date' => $draftPlan['created_date'],
                'days_since_plan' => $draftPlan['days_since_plan'],
                'status_code' => 'draft',
                'has_newer' => $hasNewer,
                'is_hidden' => false
            ];
        }
        continue; // この生徒は下書きのみ表示、期限切れは表示しない
    }

    // 下書きがない場合、最新の提出済みが期限切れかチェック
    foreach ($data['plans'] as $plan) {
        // 非表示のものはスキップ
        if ($plan['is_hidden']) continue;

        // 提出済みで150日以上経過（残り1ヶ月以内）かつ最新の提出済み
        if (!$plan['is_draft'] && $plan['days_since_plan'] >= 150 && $plan['plan_id'] == $latestSubmittedId) {
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => $plan['plan_id'],
                'latest_plan_date' => $plan['created_date'],
                'days_since_plan' => $plan['days_since_plan'],
                'status_code' => 'outdated',
                'has_newer' => false,
                'is_hidden' => false
            ];
            break; // 1件だけ表示
        }
    }
}

// 2. モニタリング一覧を取得
$studentsNeedingMonitoring = [];

$sql = "
    SELECT
        s.id,
        s.student_name,
        s.support_start_date,
        COALESCE(s.hide_initial_monitoring, 0) as hide_initial_monitoring,
        mr.id as monitoring_id,
        mr.plan_id,
        mr.monitoring_date,
        mr.is_draft,
        COALESCE(mr.is_hidden, 0) as is_hidden,
        DATEDIFF(CURDATE(), mr.monitoring_date) as days_since_monitoring,
        (
            SELECT MAX(mr2.id)
            FROM monitoring_records mr2
            WHERE mr2.student_id = s.id AND mr2.is_draft = 0
        ) as latest_submitted_monitoring_id
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    LEFT JOIN monitoring_records mr ON s.id = mr.student_id
    WHERE s.is_active = 1
    AND EXISTS (SELECT 1 FROM individual_support_plans isp WHERE isp.student_id = s.id)
    {$studentCondition}
    ORDER BY s.student_name, mr.monitoring_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($studentParams);
$allMonitoringData = $stmt->fetchAll();

// 生徒ごとにグループ化
$studentMonitorings = [];
foreach ($allMonitoringData as $row) {
    $studentId = $row['id'];
    if (!isset($studentMonitorings[$studentId])) {
        $studentMonitorings[$studentId] = [
            'student_name' => $row['student_name'],
            'support_start_date' => $row['support_start_date'],
            'hide_initial_monitoring' => $row['hide_initial_monitoring'],
            'monitorings' => [],
            'latest_submitted_monitoring_id' => $row['latest_submitted_monitoring_id']
        ];
    }
    if ($row['monitoring_id']) {
        $studentMonitorings[$studentId]['monitorings'][] = $row;
    }
}

// 次のモニタリング期限が1ヶ月以内かチェックする関数
// モニタリングは個別支援計画書の5ヶ月後が期限
function isNextMonitoringDeadlineWithinOneMonth($supportStartDate, $latestMonitoringDate) {
    if (!$supportStartDate) return false;

    $oneMonthLater = new DateTime();
    $oneMonthLater->modify('+1 month');

    if (!$latestMonitoringDate) {
        // モニタリングがない場合、初回期限は支援開始日から5ヶ月後
        $firstDeadline = new DateTime($supportStartDate);
        $firstDeadline->modify('+5 months');
        $firstDeadline->modify('-1 day');
        return $firstDeadline <= $oneMonthLater;
    }

    // 次のモニタリング期限は最新モニタリングから180日後
    $nextDeadline = new DateTime($latestMonitoringDate);
    $nextDeadline->modify('+180 days');
    return $nextDeadline <= $oneMonthLater;
}

// 表示対象を抽出
foreach ($studentMonitorings as $studentId => $data) {
    $latestSubmittedId = $data['latest_submitted_monitoring_id'];
    $supportStartDate = $data['support_start_date'];

    // 最新の提出済みモニタリングの日付を取得
    $latestSubmittedMonitoringDate = null;
    foreach ($data['monitorings'] as $monitoring) {
        if ($monitoring['monitoring_id'] == $latestSubmittedId) {
            $latestSubmittedMonitoringDate = $monitoring['monitoring_date'];
            break;
        }
    }

    // モニタリング期限を計算する関数
    $calcMonitoringDeadline = function($supportStartDate, $latestMonitoringDate) {
        if (!$supportStartDate) return null;

        if (!$latestMonitoringDate) {
            // 初回期限は支援開始日から5ヶ月後の前日
            $deadline = new DateTime($supportStartDate);
            $deadline->modify('+5 months');
            $deadline->modify('-1 day');
            return $deadline->format('Y-m-d');
        }

        // 次の期限は最新モニタリングから180日後
        $deadline = new DateTime($latestMonitoringDate);
        $deadline->modify('+180 days');
        return $deadline->format('Y-m-d');
    };

    // モニタリングがない場合
    if (empty($data['monitorings'])) {
        // 次の期限が1ヶ月以内の場合のみ表示（非表示フラグがセットされている場合は除外）
        if (isNextMonitoringDeadlineWithinOneMonth($supportStartDate, null) && !$data['hide_initial_monitoring']) {
            $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, null);
            $studentsNeedingMonitoring[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'monitoring_id' => null,
                'monitoring_deadline' => $monitoringDeadline,
                'days_since_monitoring' => null,
                'status_code' => 'none',
                'has_newer' => false,
                'is_hidden' => false
            ];
        }
        continue;
    }

    // 下書きがあるかチェック
    $hasDraft = false;
    $draftMonitoring = null;
    foreach ($data['monitorings'] as $monitoring) {
        if ($monitoring['is_draft'] && !$monitoring['is_hidden']) {
            $hasDraft = true;
            $draftMonitoring = $monitoring;
            break;
        }
    }

    // 下書きがある場合は下書きのみ表示（次の期限が1ヶ月以内の場合のみ）
    if ($hasDraft && $draftMonitoring) {
        if (isNextMonitoringDeadlineWithinOneMonth($supportStartDate, $latestSubmittedMonitoringDate)) {
            $hasNewer = $latestSubmittedId && $draftMonitoring['monitoring_id'] != $latestSubmittedId;
            $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, $latestSubmittedMonitoringDate);
            $studentsNeedingMonitoring[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'monitoring_id' => $draftMonitoring['monitoring_id'],
                'plan_id' => $draftMonitoring['plan_id'],
                'monitoring_deadline' => $monitoringDeadline,
                'days_since_monitoring' => $draftMonitoring['days_since_monitoring'],
                'status_code' => 'draft',
                'has_newer' => $hasNewer,
                'is_hidden' => false
            ];
        }
        continue;
    }

    // 下書きがない場合、最新の提出済みが期限切れかチェック
    foreach ($data['monitorings'] as $monitoring) {
        // 非表示のものはスキップ
        if ($monitoring['is_hidden']) continue;

        // 提出済みで150日以上経過（残り1ヶ月以内）
        if (!$monitoring['is_draft'] && $monitoring['days_since_monitoring'] >= 150 && $monitoring['monitoring_id'] == $latestSubmittedId) {
            $monitoringDeadline = $calcMonitoringDeadline($supportStartDate, $monitoring['monitoring_date']);
            $studentsNeedingMonitoring[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'monitoring_id' => $monitoring['monitoring_id'],
                'plan_id' => $monitoring['plan_id'],
                'monitoring_deadline' => $monitoringDeadline,
                'days_since_monitoring' => $monitoring['days_since_monitoring'],
                'status_code' => 'outdated',
                'has_newer' => false,
                'is_hidden' => false
            ];
            break;
        }
    }
}

// 3. かけはし未提出の生徒を取得
// ※ 各生徒の最新期間のみを対象とする（より新しい期間が提出済みなら古い期間は表示しない）

// 3-1. 保護者かけはし未提出（各生徒の最新の未提出期間のみ、非表示を除外、1ヶ月以内のみ）
$pendingGuardianKakehashi = [];
$guardianSql = "
    SELECT
        s.id as student_id,
        s.student_name,
        kp.id as period_id,
        kp.period_name,
        kp.submission_deadline,
        kp.start_date,
        kp.end_date,
        DATEDIFF(kp.submission_deadline, ?) as days_left,
        kg.id as kakehashi_id,
        kg.is_submitted,
        COALESCE(kg.is_hidden, 0) as is_hidden
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
    LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
    WHERE s.is_active = 1
    AND kp.is_active = 1
    AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
    AND COALESCE(kg.is_hidden, 0) = 0
    AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    AND kp.submission_deadline = (
        SELECT MAX(kp2.submission_deadline)
        FROM kakehashi_periods kp2
        WHERE kp2.student_id = s.id AND kp2.is_active = 1
        AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    )
    " . ($classroomId ? "AND u.classroom_id = ?" : "") . "
    ORDER BY kp.submission_deadline ASC, s.student_name
";
try {
    $stmt = $pdo->prepare($guardianSql);
    $params = $classroomId ? [$today, $classroomId] : [$today];
    $stmt->execute($params);
    $pendingGuardianKakehashi = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Guardian kakehashi fetch error: " . $e->getMessage());
}

// 3-2. スタッフかけはし未作成（各生徒の最新の未提出期間のみ、非表示を除外、1ヶ月以内のみ）
$pendingStaffKakehashi = [];
$staffSql = "
    SELECT
        s.id as student_id,
        s.student_name,
        kp.id as period_id,
        kp.period_name,
        kp.submission_deadline,
        kp.start_date,
        kp.end_date,
        DATEDIFF(kp.submission_deadline, ?) as days_left,
        ks.id as kakehashi_id,
        ks.is_submitted,
        COALESCE(ks.is_hidden, 0) as is_hidden
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
    LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
    WHERE s.is_active = 1
    AND kp.is_active = 1
    AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
    AND COALESCE(ks.is_hidden, 0) = 0
    AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    AND kp.submission_deadline = (
        SELECT MAX(kp2.submission_deadline)
        FROM kakehashi_periods kp2
        WHERE kp2.student_id = s.id AND kp2.is_active = 1
        AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
    )
    " . ($classroomId ? "AND u.classroom_id = ?" : "") . "
    ORDER BY kp.submission_deadline ASC, s.student_name
";
try {
    $stmt = $pdo->prepare($staffSql);
    $params = $classroomId ? [$today, $classroomId] : [$today];
    $stmt->execute($params);
    $pendingStaffKakehashi = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Staff kakehashi fetch error: " . $e->getMessage());
}

// ページ開始
$currentPage = 'pending_tasks';
$pageTitle = '未作成タスク一覧';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
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
            border-bottom: 3px solid var(--primary-purple);
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-purple);
        }

        .count-badge {
            background: var(--apple-red);
            color: white;
            padding: 5px 15px;
            border-radius: var(--radius-xl);
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .count-badge.success {
            background: var(--apple-green);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: var(--radius-md);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--apple-bg-primary);
        }

        th {
            background: var(--apple-bg-secondary);
            color: var(--text-primary);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: var(--text-subhead);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e1e8ed;
        }

        tr:hover {
            background: var(--apple-gray-6);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-md);
            font-size: var(--text-caption-1);
            font-weight: 600;
        }

        .status-badge.none {
            background: var(--apple-red);
            color: white;
        }

        .status-badge.outdated {
            background: var(--apple-orange);
            color: var(--text-primary);
        }

        .status-badge.overdue {
            background: var(--apple-gray);
            color: white;
        }

        .status-badge.urgent {
            background: var(--apple-red);
            color: white;
        }

        .status-badge.warning {
            background: var(--apple-orange);
            color: var(--text-primary);
        }

        .has-newer-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            font-size: var(--text-caption-2);
            font-weight: 600;
            background: var(--apple-blue);
            color: white;
            margin-left: 8px;
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
            background: var(--apple-bg-secondary);
            color: var(--text-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--apple-green);
            color: white;
        }

        .btn-success:hover {
            background: var(--apple-green);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--text-secondary);
        }

        .empty-state h3 {
            color: var(--apple-green);
            margin-bottom: var(--spacing-md);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: var(--spacing-2xl);
        }

        .summary-card {
            background: var(--apple-bg-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-purple);
        }

        .summary-card.urgent {
            border-left-color: var(--apple-red);
        }

        .summary-card.warning {
            border-left-color: var(--apple-orange);
        }

        .summary-card.success {
            border-left-color: var(--apple-green);
        }

        .summary-card-title {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .summary-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .btn-hide {
            padding: 6px 12px;
            background: var(--apple-gray);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: var(--text-footnote);
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            margin-left: 10px;
        }

        .btn-hide:hover {
            background: var(--apple-gray);
        }

        .btn-hide:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">未作成タスク一覧</h1>
        <p class="page-subtitle">個別支援計画書・モニタリング・かけはしの未作成タスク</p>
    </div>
    <div class="page-header-actions">
        <a href="renrakucho_activities.php" class="btn btn-secondary">← 活動管理に戻る</a>
    </div>
</div>

        <div class="content">
            <!-- サマリーカード -->
            <div class="summary-cards">
                <div class="summary-card <?php echo !empty($studentsNeedingPlan) ? 'urgent' : 'success'; ?>">
                    <div class="summary-card-title">個別支援計画書</div>
                    <div class="summary-card-value"><?php echo count($studentsNeedingPlan); ?>件</div>
                </div>
                <div class="summary-card <?php echo !empty($studentsNeedingMonitoring) ? 'warning' : 'success'; ?>">
                    <div class="summary-card-title">モニタリング</div>
                    <div class="summary-card-value"><?php echo count($studentsNeedingMonitoring); ?>件</div>
                </div>
                <div class="summary-card <?php echo !empty($pendingGuardianKakehashi) ? 'warning' : 'success'; ?>">
                    <div class="summary-card-title">保護者かけはし</div>
                    <div class="summary-card-value"><?php echo count($pendingGuardianKakehashi); ?>件</div>
                </div>
                <div class="summary-card <?php echo !empty($pendingStaffKakehashi) ? 'warning' : 'success'; ?>">
                    <div class="summary-card-title">スタッフかけはし</div>
                    <div class="summary-card-value"><?php echo count($pendingStaffKakehashi); ?>件</div>
                </div>
            </div>

            <!-- 個別支援計画書セクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📝 個別支援計画書</h2>
                    <?php if (!empty($studentsNeedingPlan)): ?>
                        <span class="count-badge"><?php echo count($studentsNeedingPlan); ?>件の対応が必要です</span>
                    <?php else: ?>
                        <span class="count-badge success">すべて最新です</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($studentsNeedingPlan)): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>生徒名</th>
                                    <th>支援開始日</th>
                                    <th>最新計画日</th>
                                    <th>状態</th>
                                    <th>アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsNeedingPlan as $student):
                                    $statusCode = $student['status_code'];
                                    $daysSince = $student['days_since_plan'];
                                    $hasNewer = $student['has_newer'];

                                    if ($statusCode === 'none') {
                                        $statusLabel = 'なし';
                                        $statusClass = 'none';
                                    } elseif ($statusCode === 'draft') {
                                        $statusLabel = '下書きあり（未提出）';
                                        $statusClass = 'warning';
                                    } elseif ($daysSince >= 180) {
                                        $statusLabel = '期限切れ（' . floor($daysSince / 30) . 'ヶ月経過）';
                                        $statusClass = 'overdue';
                                    } else {
                                        $daysLeft = 180 - $daysSince;
                                        $statusLabel = '1か月以内（残り' . $daysLeft . '日）';
                                        $statusClass = 'urgent';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo $student['support_start_date'] ? date('Y年n月j日', strtotime($student['support_start_date'])) : '-'; ?></td>
                                        <td><?php echo $student['latest_plan_date'] ? date('Y年n月j日', strtotime($student['latest_plan_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($statusLabel); ?>
                                            </span>
                                            <?php if ($hasNewer): ?>
                                                <span class="has-newer-badge">最新あり</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="kobetsu_plan.php?student_id=<?php echo $student['id']; ?><?php echo $student['plan_id'] ? '&plan_id=' . $student['plan_id'] : ''; ?>" class="btn btn-primary">
                                                    計画書を作成
                                                </a>
                                                <?php if ($student['plan_id']): ?>
                                                    <button class="btn-hide" onclick="hideItem('plan', <?php echo $student['plan_id']; ?>, this)">
                                                        非表示
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>✅ すべての生徒の個別支援計画書が最新です</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- モニタリングセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📊 モニタリング</h2>
                    <?php if (!empty($studentsNeedingMonitoring)): ?>
                        <span class="count-badge"><?php echo count($studentsNeedingMonitoring); ?>件の対応が必要です</span>
                    <?php else: ?>
                        <span class="count-badge success">すべて最新です</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($studentsNeedingMonitoring)): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>生徒名</th>
                                    <th>支援開始日</th>
                                    <th>モニタリング期限</th>
                                    <th>状態</th>
                                    <th>アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsNeedingMonitoring as $student):
                                    $statusCode = $student['status_code'];
                                    $hasNewer = $student['has_newer'];
                                    $monitoringDeadline = $student['monitoring_deadline'] ?? null;

                                    // 期限までの日数を計算
                                    $daysUntilDeadline = null;
                                    if ($monitoringDeadline) {
                                        $deadlineDate = new DateTime($monitoringDeadline);
                                        $today = new DateTime();
                                        $diff = $today->diff($deadlineDate);
                                        $daysUntilDeadline = $diff->invert ? -$diff->days : $diff->days;
                                    }

                                    if ($statusCode === 'none') {
                                        $statusLabel = '初回モニタリング未作成';
                                        $statusClass = 'none';
                                    } elseif ($statusCode === 'draft') {
                                        $statusLabel = '下書きあり（未提出）';
                                        $statusClass = 'warning';
                                    } elseif ($daysUntilDeadline !== null && $daysUntilDeadline < 0) {
                                        $statusLabel = '期限切れ（' . abs($daysUntilDeadline) . '日超過）';
                                        $statusClass = 'overdue';
                                    } elseif ($daysUntilDeadline !== null && $daysUntilDeadline <= 30) {
                                        $statusLabel = '1か月以内（残り' . $daysUntilDeadline . '日）';
                                        $statusClass = 'urgent';
                                    } else {
                                        $statusLabel = '対応が必要';
                                        $statusClass = 'warning';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo $student['support_start_date'] ? date('Y年n月j日', strtotime($student['support_start_date'])) : '-'; ?></td>
                                        <td><?php echo $monitoringDeadline ? date('Y年n月j日', strtotime($monitoringDeadline)) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($statusLabel); ?>
                                            </span>
                                            <?php if ($hasNewer): ?>
                                                <span class="has-newer-badge">最新あり</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="kobetsu_monitoring.php?student_id=<?php echo $student['id']; ?><?php echo $student['monitoring_id'] ? '&monitoring_id=' . $student['monitoring_id'] : ''; ?>" class="btn btn-primary">
                                                    モニタリング作成
                                                </a>
                                                <?php if ($student['monitoring_id']): ?>
                                                    <button class="btn-hide" onclick="hideItem('monitoring', <?php echo $student['monitoring_id']; ?>, this)">
                                                        非表示
                                                    </button>
                                                <?php elseif ($student['status_code'] === 'none'): ?>
                                                    <button class="btn-hide" onclick="hideInitialMonitoring(<?php echo $student['id']; ?>, this)">
                                                        非表示
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>✅ すべての生徒のモニタリングが最新です</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 保護者かけはしセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">🌉 保護者かけはし</h2>
                    <?php if (!empty($pendingGuardianKakehashi)): ?>
                        <span class="count-badge"><?php echo count($pendingGuardianKakehashi); ?>件の未提出があります</span>
                    <?php else: ?>
                        <span class="count-badge success">すべて提出済みです</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pendingGuardianKakehashi)): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>生徒名</th>
                                    <th>期間名</th>
                                    <th>対象期間</th>
                                    <th>提出期限</th>
                                    <th>状態</th>
                                    <th>アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingGuardianKakehashi as $kakehashi): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kakehashi['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($kakehashi['period_name']); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($kakehashi['start_date'])) . ' ～ ' . date('Y/m/d', strtotime($kakehashi['end_date'])); ?></td>
                                        <td><?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?></td>
                                        <td>
                                            <?php if ($kakehashi['days_left'] < 0): ?>
                                                <span class="status-badge overdue">期限切れ（<?php echo abs($kakehashi['days_left']); ?>日経過）</span>
                                            <?php elseif ($kakehashi['days_left'] <= 7): ?>
                                                <span class="status-badge urgent">緊急（残り<?php echo $kakehashi['days_left']; ?>日）</span>
                                            <?php else: ?>
                                                <span class="status-badge warning">未提出（残り<?php echo $kakehashi['days_left']; ?>日）</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="kakehashi_guardian_view.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                                    確認・催促
                                                </a>
                                                <button class="btn-hide" onclick="hideKakehashi('guardian', <?php echo $kakehashi['period_id']; ?>, <?php echo $kakehashi['student_id']; ?>, this)">
                                                    非表示
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>✅ すべての保護者かけはしが提出済みです</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- スタッフかけはしセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">🌉 スタッフかけはし</h2>
                    <?php if (!empty($pendingStaffKakehashi)): ?>
                        <span class="count-badge"><?php echo count($pendingStaffKakehashi); ?>件の未作成があります</span>
                    <?php else: ?>
                        <span class="count-badge success">すべて作成済みです</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($pendingStaffKakehashi)): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>生徒名</th>
                                    <th>期間名</th>
                                    <th>対象期間</th>
                                    <th>提出期限</th>
                                    <th>状態</th>
                                    <th>アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingStaffKakehashi as $kakehashi): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kakehashi['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($kakehashi['period_name']); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($kakehashi['start_date'])) . ' ～ ' . date('Y/m/d', strtotime($kakehashi['end_date'])); ?></td>
                                        <td><?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?></td>
                                        <td>
                                            <?php if ($kakehashi['days_left'] < 0): ?>
                                                <span class="status-badge overdue">期限切れ（<?php echo abs($kakehashi['days_left']); ?>日経過）</span>
                                            <?php elseif ($kakehashi['days_left'] <= 7): ?>
                                                <span class="status-badge urgent">緊急（残り<?php echo $kakehashi['days_left']; ?>日）</span>
                                            <?php else: ?>
                                                <span class="status-badge warning">未作成（残り<?php echo $kakehashi['days_left']; ?>日）</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                                    作成する
                                                </a>
                                                <button class="btn-hide" onclick="hideKakehashi('staff', <?php echo $kakehashi['period_id']; ?>, <?php echo $kakehashi['student_id']; ?>, this)">
                                                    非表示
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>✅ すべてのスタッフかけはしが作成済みです</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <script>
        function hideItem(type, id, button) {
            if (!confirm('この項目を非表示にしますか？\n非表示にした項目は、タスク一覧に表示されなくなります。')) {
                return;
            }

            button.disabled = true;
            button.textContent = '処理中...';

            fetch('pending_tasks_toggle_hide.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${type}&id=${id}&action=hide`
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
                    button.textContent = '非表示';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                button.disabled = false;
                button.textContent = '非表示';
            });
        }

        function hideInitialMonitoring(studentId, button) {
            if (!confirm('この生徒の初回モニタリング未作成を非表示にしますか？\n非表示にすると、タスク一覧に表示されなくなります。')) {
                return;
            }

            button.disabled = true;
            button.textContent = '処理中...';

            fetch('pending_tasks_toggle_hide.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=initial_monitoring&student_id=${studentId}&action=hide`
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
                    button.textContent = '非表示';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                button.disabled = false;
                button.textContent = '非表示';
            });
        }

        function hideKakehashi(type, periodId, studentId, button) {
            if (!confirm('このかけはしを非表示にしますか？\n非表示にしたかけはしは、タスク一覧に表示されなくなります。')) {
                return;
            }

            button.disabled = true;
            button.textContent = '処理中...';

            fetch('kakehashi_toggle_hide.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${type}&period_id=${periodId}&student_id=${studentId}&action=hide`
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
                    button.textContent = '非表示';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                button.disabled = false;
                button.textContent = '非表示';
            });
        }
    </script>

<?php renderPageEnd(); ?>
