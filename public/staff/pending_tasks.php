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
require_once __DIR__ . '/../../includes/pending_tasks_helper.php';

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

// 検索パラメータを取得
$searchName = $_GET['name'] ?? '';
$searchDocType = $_GET['doc_type'] ?? '';  // plan, monitoring, guardian_kakehashi, staff_kakehashi

// 生徒一覧を取得（最新の個別支援計画書・モニタリングを確認するため）
$studentCondition = $classroomId ? "AND u.classroom_id = ?" : "";
$studentParams = $classroomId ? [$classroomId] : [];

// 名前検索条件
$nameCondition = "";
$nameParam = null;
if (!empty($searchName)) {
    $nameCondition = " AND s.student_name LIKE ?";
    $nameParam = '%' . $searchName . '%';
}

// 次の対象期間を計算する関数
function getNextTargetPeriod($supportStartDate, $existingPlanCount) {
    if (!$supportStartDate) {
        return ['start' => null, 'end' => null, 'number' => null];
    }
    $start = new DateTime($supportStartDate);
    $planNumber = $existingPlanCount + 1;

    // 対象期間を計算（6ヶ月ごと）
    $periodStart = clone $start;
    $periodStart->modify('+' . ($existingPlanCount * 6) . ' months');

    $periodEnd = clone $periodStart;
    $periodEnd->modify('+6 months');
    $periodEnd->modify('-1 day');

    return [
        'start' => $periodStart->format('Y-m-d'),
        'end' => $periodEnd->format('Y-m-d'),
        'number' => $planNumber
    ];
}

// 対象期間を表示用にフォーマットする関数
function formatTargetPeriod($start, $end, $number = null) {
    if (!$start || !$end) return '';
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $prefix = $number ? "{$number}回目: " : '';
    return $prefix . $startDate->format('Y年n月') . '〜' . $endDate->format('Y年n月');
}

// 1. 個別支援計画書一覧を取得（未提出・下書き・期限切れ）
// 各生徒の最新の提出済み計画書IDを取得
$studentsNeedingPlan = [];
$allPlanData = [];

if (empty($searchDocType) || $searchDocType === 'plan') {
    $sql = "
        SELECT
            s.id,
            s.student_name,
            s.support_start_date,
            isp.id as plan_id,
            isp.created_date,
            isp.is_draft,
            COALESCE(isp.is_hidden, 0) as is_hidden,
            COALESCE(isp.guardian_confirmed, 0) as guardian_confirmed,
            DATEDIFF(CURDATE(), isp.created_date) as days_since_plan,
            isp.target_period_start,
            isp.target_period_end,
            isp.plan_number,
            (
                SELECT MAX(isp2.id)
                FROM individual_support_plans isp2
                WHERE isp2.student_id = s.id AND isp2.is_draft = 0
            ) as latest_submitted_plan_id,
            (
                SELECT COUNT(*)
                FROM individual_support_plans isp3
                WHERE isp3.student_id = s.id
            ) as total_plans
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        LEFT JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        {$studentCondition}
        {$nameCondition}
        ORDER BY s.student_name, isp.created_date DESC
    ";
    $params = $studentParams;
    if ($nameParam) $params[] = $nameParam;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allPlanData = $stmt->fetchAll();
}

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
        $row['guardian_confirmed'] = (int)$row['guardian_confirmed'];
        $studentPlans[$studentId]['plans'][] = $row;
    }
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
            $nextPeriod = getNextTargetPeriod($supportStartDate, 0);
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => null,
                'latest_plan_date' => null,
                'days_since_plan' => null,
                'status_code' => 'none',
                'has_newer' => false,
                'is_hidden' => false,
                'target_period_start' => $nextPeriod['start'],
                'target_period_end' => $nextPeriod['end'],
                'plan_number' => $nextPeriod['number']
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
            // 下書きの対象期間を使用、なければ計算
            $targetStart = $draftPlan['target_period_start'] ?? null;
            $targetEnd = $draftPlan['target_period_end'] ?? null;
            $planNumber = $draftPlan['plan_number'] ?? null;
            if (!$targetStart) {
                $submittedCount = 0;
                foreach ($data['plans'] as $p) {
                    if (!$p['is_draft']) $submittedCount++;
                }
                $nextPeriod = getNextTargetPeriod($supportStartDate, $submittedCount);
                $targetStart = $nextPeriod['start'];
                $targetEnd = $nextPeriod['end'];
                $planNumber = $nextPeriod['number'];
            }
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => $draftPlan['plan_id'],
                'latest_plan_date' => $draftPlan['created_date'],
                'days_since_plan' => $draftPlan['days_since_plan'],
                'status_code' => 'draft',
                'has_newer' => $hasNewer,
                'is_hidden' => false,
                'target_period_start' => $targetStart,
                'target_period_end' => $targetEnd,
                'plan_number' => $planNumber
            ];
        }
        continue; // この生徒は下書きのみ表示、期限切れは表示しない
    }

    // 下書きがない場合、提出済みで保護者確認が必要かチェック
    $needsGuardianConfirm = false;
    foreach ($data['plans'] as $plan) {
        // 非表示のものはスキップ
        if ($plan['is_hidden']) continue;

        // 提出済みで保護者未確認かつ最新の提出済み
        if (!$plan['is_draft'] && !$plan['guardian_confirmed'] && $plan['plan_id'] == $latestSubmittedId) {
            $studentsNeedingPlan[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'plan_id' => $plan['plan_id'],
                'latest_plan_date' => $plan['created_date'],
                'days_since_plan' => $plan['days_since_plan'],
                'status_code' => 'needs_confirm',
                'has_newer' => false,
                'is_hidden' => false,
                'guardian_confirmed' => false,
                'target_period_start' => $plan['target_period_start'] ?? null,
                'target_period_end' => $plan['target_period_end'] ?? null,
                'plan_number' => $plan['plan_number'] ?? null
            ];
            $needsGuardianConfirm = true;
            break;
        }
    }

    // 保護者確認が必要でない場合、期限切れかチェック
    if (!$needsGuardianConfirm) {
        foreach ($data['plans'] as $plan) {
            // 非表示のものはスキップ
            if ($plan['is_hidden']) continue;

            // 提出済みで150日以上経過（残り1ヶ月以内）かつ最新の提出済み
            if (!$plan['is_draft'] && $plan['days_since_plan'] >= 150 && $plan['plan_id'] == $latestSubmittedId) {
                // 次の計画書の対象期間を計算
                $submittedCount = 0;
                foreach ($data['plans'] as $p) {
                    if (!$p['is_draft']) $submittedCount++;
                }
                $nextPeriod = getNextTargetPeriod($supportStartDate, $submittedCount);
                $studentsNeedingPlan[] = [
                    'id' => $studentId,
                    'student_name' => $data['student_name'],
                    'support_start_date' => $supportStartDate,
                    'plan_id' => $plan['plan_id'],
                    'latest_plan_date' => $plan['created_date'],
                    'days_since_plan' => $plan['days_since_plan'],
                    'status_code' => 'outdated',
                    'has_newer' => false,
                    'is_hidden' => false,
                    'guardian_confirmed' => $plan['guardian_confirmed'],
                    'target_period_start' => $nextPeriod['start'],
                    'target_period_end' => $nextPeriod['end'],
                    'plan_number' => $nextPeriod['number']
                ];
                break; // 1件だけ表示
            }
        }
    }
}

// 2. モニタリング一覧を取得
$studentsNeedingMonitoring = [];
$allMonitoringData = [];

if (empty($searchDocType) || $searchDocType === 'monitoring') {
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
            COALESCE(mr.guardian_confirmed, 0) as guardian_confirmed,
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
        {$nameCondition}
        ORDER BY s.student_name, mr.monitoring_date DESC
    ";
    $params = $studentParams;
    if ($nameParam) $params[] = $nameParam;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allMonitoringData = $stmt->fetchAll();
}

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
        $row['guardian_confirmed'] = (int)$row['guardian_confirmed'];
        $studentMonitorings[$studentId]['monitorings'][] = $row;
    }
}

// 表示対象を抽出（個別支援計画期限の1ヶ月前 = かけはしと同じ期限）
$todayDt = new DateTime();
$oneMonthLaterDt = new DateTime();
$oneMonthLaterDt->modify('+1 month');

foreach ($studentMonitorings as $studentId => $data) {
    $latestSubmittedId = $data['latest_submitted_monitoring_id'];
    $supportStartDate = $data['support_start_date'];

    // 支援開始日がない場合はスキップ
    if (!$supportStartDate) {
        continue;
    }

    // 次の個別支援計画期限を計算（支援開始日から6ヶ月ごと）
    $startDate = new DateTime($supportStartDate);
    $nextPlanDeadline = clone $startDate;

    // 次の計画期限を見つける（過去の期限はスキップ）
    while ($nextPlanDeadline <= $todayDt) {
        $nextPlanDeadline->modify('+6 months');
    }

    // モニタリング期限 = 個別支援計画期限の1ヶ月前（かけはしと同じ）
    $deadline = clone $nextPlanDeadline;
    $deadline->modify('-1 month');
    $monitoringDeadline = $deadline->format('Y-m-d');
    $daysLeft = (int)$todayDt->diff($deadline)->format('%r%a');
    $isOverdue = $deadline < $todayDt;

    // 期限が1ヶ月以上先の場合はスキップ
    if ($deadline > $oneMonthLaterDt) {
        continue;
    }

    // 個別支援計画が存在するか確認
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_support_plans WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $hasPlan = $stmt->fetchColumn() > 0;
    if (!$hasPlan) {
        continue;
    }

    // 最新の提出済みモニタリングの日付を取得
    $latestSubmittedMonitoringDate = null;
    foreach ($data['monitorings'] as $monitoring) {
        if ($monitoring['monitoring_id'] == $latestSubmittedId) {
            $latestSubmittedMonitoringDate = $monitoring['monitoring_date'];
            break;
        }
    }

    // この期限に対応するモニタリングが既に作成されているか確認（期限の前後30日以内）
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM monitoring_records
        WHERE student_id = ?
        AND ABS(DATEDIFF(monitoring_date, ?)) <= 30
        AND is_draft = 0
    ");
    $stmt->execute([$studentId, $monitoringDeadline]);
    $hasMonitoringForPeriod = $stmt->fetchColumn() > 0;

    // 既にこの期間のモニタリングが作成済みならスキップ
    if ($hasMonitoringForPeriod) {
        continue;
    }

    // モニタリングがない場合
    if (empty($data['monitorings'])) {
        // 非表示フラグがセットされている場合は除外
        if (!$data['hide_initial_monitoring']) {
            if ($isOverdue) {
                $statusCode = 'outdated';
            } elseif ($daysLeft <= 7) {
                $statusCode = 'urgent';
            } else {
                $statusCode = 'none';
            }
            $studentsNeedingMonitoring[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'monitoring_id' => null,
                'monitoring_deadline' => $monitoringDeadline,
                'days_since_monitoring' => null,
                'status_code' => $statusCode,
                'has_newer' => false,
                'is_hidden' => false,
                'next_plan_deadline' => $nextPlanDeadline->format('Y-m-d')
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

    // 下書きがある場合
    if ($hasDraft && $draftMonitoring) {
        $hasNewer = $latestSubmittedId && $draftMonitoring['monitoring_id'] != $latestSubmittedId;
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
            'is_hidden' => false,
            'next_plan_deadline' => $nextPlanDeadline->format('Y-m-d')
        ];
        continue;
    }

    // 下書きがない場合、提出済みで保護者確認が必要かチェック
    $needsGuardianConfirm = false;
    foreach ($data['monitorings'] as $monitoring) {
        // 非表示のものはスキップ
        if ($monitoring['is_hidden']) continue;

        // 提出済みで保護者未確認かつ最新の提出済み
        if (!$monitoring['is_draft'] && !$monitoring['guardian_confirmed'] && $monitoring['monitoring_id'] == $latestSubmittedId) {
            $studentsNeedingMonitoring[] = [
                'id' => $studentId,
                'student_name' => $data['student_name'],
                'support_start_date' => $supportStartDate,
                'monitoring_id' => $monitoring['monitoring_id'],
                'plan_id' => $monitoring['plan_id'],
                'monitoring_deadline' => $monitoringDeadline,
                'days_since_monitoring' => $monitoring['days_since_monitoring'],
                'status_code' => 'needs_confirm',
                'has_newer' => false,
                'is_hidden' => false,
                'guardian_confirmed' => false,
                'next_plan_deadline' => $nextPlanDeadline->format('Y-m-d')
            ];
            $needsGuardianConfirm = true;
            break;
        }
    }

    // 保護者確認が必要でない場合、新しいモニタリングが必要かチェック
    if (!$needsGuardianConfirm) {
        if ($isOverdue) {
            $statusCode = 'outdated';
        } elseif ($daysLeft <= 7) {
            $statusCode = 'urgent';
        } else {
            $statusCode = 'none';
        }
        $studentsNeedingMonitoring[] = [
            'id' => $studentId,
            'student_name' => $data['student_name'],
            'support_start_date' => $supportStartDate,
            'monitoring_id' => null,
            'plan_id' => null,
            'monitoring_deadline' => $monitoringDeadline,
            'days_since_monitoring' => null,
            'status_code' => $statusCode,
            'has_newer' => false,
            'is_hidden' => false,
            'guardian_confirmed' => false,
            'next_plan_deadline' => $nextPlanDeadline->format('Y-m-d')
        ];
    }
}

// 3. かけはし未提出の生徒を取得
// ※ 各生徒の最新期間のみを対象とする（より新しい期間が提出済みなら古い期間は表示しない）

// 3-1. 保護者かけはし未提出（各生徒の最新の未提出期間のみ、非表示を除外、1ヶ月以内のみ）
$pendingGuardianKakehashi = [];
if (empty($searchDocType) || $searchDocType === 'guardian_kakehashi') {
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
        " . (!empty($searchName) ? "AND s.student_name LIKE ?" : "") . "
        ORDER BY kp.submission_deadline ASC, s.student_name
    ";
    try {
        $stmt = $pdo->prepare($guardianSql);
        $params = $classroomId ? [$today, $classroomId] : [$today];
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt->execute($params);
        $pendingGuardianKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
    }
}

// 3-2. スタッフかけはし（未作成・下書き・要保護者確認を含む）
$pendingStaffKakehashi = [];
if (empty($searchDocType) || $searchDocType === 'staff_kakehashi') {
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
            COALESCE(ks.is_hidden, 0) as is_hidden,
            COALESCE(ks.guardian_confirmed, 0) as guardian_confirmed
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
        WHERE s.is_active = 1
        AND kp.is_active = 1
        AND (
            ks.is_submitted = 0
            OR ks.is_submitted IS NULL
            OR (ks.is_submitted = 1 AND COALESCE(ks.guardian_confirmed, 0) = 0)
        )
        AND COALESCE(ks.is_hidden, 0) = 0
        AND kp.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        AND kp.submission_deadline = (
            SELECT MAX(kp2.submission_deadline)
            FROM kakehashi_periods kp2
            WHERE kp2.student_id = s.id AND kp2.is_active = 1
            AND kp2.submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        )
        " . ($classroomId ? "AND u.classroom_id = ?" : "") . "
        " . (!empty($searchName) ? "AND s.student_name LIKE ?" : "") . "
        ORDER BY kp.submission_deadline ASC, s.student_name
    ";
    try {
        $stmt = $pdo->prepare($staffSql);
        $params = $classroomId ? [$today, $classroomId] : [$today];
        if (!empty($searchName)) $params[] = '%' . $searchName . '%';
        $stmt->execute($params);
        $pendingStaffKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
    }
}

// ページ開始
$currentPage = 'pending_tasks';
$pageTitle = '未作成タスク一覧';
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
            border-bottom: 3px solid var(--primary-purple);
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-purple);
        }

        .count-badge {
            background: var(--md-red);
            color: white;
            padding: 5px 15px;
            border-radius: var(--radius-xl);
            font-size: var(--text-subhead);
            font-weight: 600;
        }

        .count-badge.success {
            background: var(--md-green);
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

        .status-badge.none {
            background: var(--md-red);
            color: white;
        }

        .status-badge.outdated {
            background: var(--md-orange);
            color: var(--text-primary);
        }

        .status-badge.overdue {
            background: var(--md-gray);
            color: white;
        }

        .status-badge.urgent {
            background: var(--md-red);
            color: white;
        }

        .status-badge.warning {
            background: var(--md-orange);
            color: var(--text-primary);
        }

        .status-badge.draft {
            background: var(--md-purple);
            color: white;
        }

        .status-badge.needs-confirm {
            background: var(--md-teal);
            color: white;
        }

        .has-newer-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            font-size: var(--text-caption-2);
            font-weight: 600;
            background: var(--md-blue);
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
            background: var(--md-bg-secondary);
            color: var(--text-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: var(--md-green);
            color: white;
        }

        .btn-success:hover {
            background: var(--md-green);
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
            border-left: 3px solid var(--primary-purple);
        }

        .summary-card.urgent {
            border-left-color: var(--md-red);
            background: rgba(255, 59, 48, 0.05);
        }

        .summary-card.warning {
            border-left-color: var(--md-orange);
            background: rgba(255, 149, 0, 0.05);
        }

        .summary-card.success {
            border-left-color: var(--md-green);
            background: rgba(52, 199, 89, 0.05);
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

        .btn-hide {
            padding: 6px 12px;
            background: var(--md-gray);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: var(--text-footnote);
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
            margin-left: 10px;
        }

        .btn-hide:hover {
            background: var(--md-gray);
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
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">未作成タスク一覧</h1>
        <p class="page-subtitle">個別支援計画書・モニタリング・かけはしの未作成タスク</p>
    </div>
    <div class="page-header-actions">
        <a href="hidden_documents.php" class="btn btn-secondary" style="margin-right: 10px;">
            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">visibility_off</span>
            非表示一覧
        </a>
        <a href="renrakucho_activities.php" class="btn btn-secondary"><- 活動管理に戻る</a>
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
    <?php
    $totalCount = count($studentsNeedingPlan) + count($studentsNeedingMonitoring) + count($pendingGuardianKakehashi) + count($pendingStaffKakehashi);
    ?>
    <div class="result-count">
        <?php echo $totalCount; ?>件の未作成タスク
        <?php if (!empty($searchName) || !empty($searchDocType)): ?>
            （フィルター適用中）
        <?php endif; ?>
    </div>
</div>

        <div class="content">
            <!-- サマリーカード -->
            <div class="summary-cards">
                <div class="summary-card <?php echo !empty($studentsNeedingPlan) ? 'urgent' : 'success'; ?>">
                    <span class="summary-card-title">個別支援計画書</span>
                    <span class="summary-card-value"><?php echo count($studentsNeedingPlan); ?>件</span>
                </div>
                <div class="summary-card <?php echo !empty($studentsNeedingMonitoring) ? 'warning' : 'success'; ?>">
                    <span class="summary-card-title">モニタリング</span>
                    <span class="summary-card-value"><?php echo count($studentsNeedingMonitoring); ?>件</span>
                </div>
                <div class="summary-card <?php echo !empty($pendingGuardianKakehashi) ? 'warning' : 'success'; ?>">
                    <span class="summary-card-title">保護者かけはし</span>
                    <span class="summary-card-value"><?php echo count($pendingGuardianKakehashi); ?>件</span>
                </div>
                <div class="summary-card <?php echo !empty($pendingStaffKakehashi) ? 'warning' : 'success'; ?>">
                    <span class="summary-card-title">スタッフかけはし</span>
                    <span class="summary-card-value"><?php echo count($pendingStaffKakehashi); ?>件</span>
                </div>
            </div>

            <!-- 個別支援計画書セクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><span class="material-symbols-outlined">edit_note</span> 個別支援計画書</h2>
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
                                    <th>対象期間</th>
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

                                    // 期限状態を計算（下書き・要保護者確認の場合も表示するため）
                                    $deadlineLabel = '';
                                    $deadlineClass = '';
                                    if ($daysSince !== null) {
                                        if ($daysSince >= 180) {
                                            $deadlineLabel = '期限切れ（' . floor($daysSince / 30) . 'ヶ月経過）';
                                            $deadlineClass = 'overdue';
                                        } elseif ($daysSince >= 150) {
                                            $daysLeft = 180 - $daysSince;
                                            $deadlineLabel = '残り' . $daysLeft . '日';
                                            $deadlineClass = 'urgent';
                                        }
                                    }

                                    // メイン状態を設定
                                    if ($statusCode === 'none') {
                                        $statusLabel = '未作成';
                                        $statusClass = 'none';
                                    } elseif ($statusCode === 'needs_confirm') {
                                        $statusLabel = '要保護者確認';
                                        $statusClass = 'needs-confirm';
                                    } elseif ($statusCode === 'draft') {
                                        $statusLabel = '下書き';
                                        $statusClass = 'draft';
                                    } elseif ($daysSince >= 180) {
                                        $statusLabel = '期限切れ（' . floor($daysSince / 30) . 'ヶ月経過）';
                                        $statusClass = 'overdue';
                                        $deadlineLabel = ''; // 期限切れの場合は重複表示しない
                                    } else {
                                        $daysLeft = 180 - $daysSince;
                                        $statusLabel = '1か月以内（残り' . $daysLeft . '日）';
                                        $statusClass = 'urgent';
                                        $deadlineLabel = ''; // 1か月以内の場合は重複表示しない
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td>
                                            <?php
                                            $targetPeriod = formatTargetPeriod(
                                                $student['target_period_start'] ?? null,
                                                $student['target_period_end'] ?? null,
                                                $student['plan_number'] ?? null
                                            );
                                            echo $targetPeriod ?: '-';
                                            ?>
                                        </td>
                                        <td><?php echo $student['latest_plan_date'] ? date('Y年n月j日', strtotime($student['latest_plan_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($statusLabel); ?>
                                            </span>
                                            <?php if ($deadlineLabel): ?>
                                                <span class="status-badge <?php echo $deadlineClass; ?>">
                                                    <?php echo htmlspecialchars($deadlineLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($hasNewer): ?>
                                                <span class="has-newer-badge">最新あり</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($statusCode === 'needs_confirm'): ?>
                                                    <a href="kobetsu_plan.php?student_id=<?php echo $student['id']; ?>&plan_id=<?php echo $student['plan_id']; ?>" class="btn btn-primary">
                                                        確認依頼
                                                    </a>
                                                <?php else: ?>
                                                    <a href="kobetsu_plan.php?student_id=<?php echo $student['id']; ?><?php echo $student['plan_id'] ? '&plan_id=' . $student['plan_id'] : ''; ?>" class="btn btn-primary">
                                                        計画書を作成
                                                    </a>
                                                <?php endif; ?>
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
                        <h3><span class="material-symbols-outlined">check_circle</span> すべての生徒の個別支援計画書が最新です</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- モニタリングセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><span class="material-symbols-outlined">monitoring</span> モニタリング</h2>
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

                                    // 期限状態を計算（下書き・要保護者確認の場合も表示するため）
                                    $deadlineLabel = '';
                                    $deadlineClass = '';
                                    if ($daysUntilDeadline !== null) {
                                        if ($daysUntilDeadline < 0) {
                                            $deadlineLabel = '期限切れ（' . abs($daysUntilDeadline) . '日超過）';
                                            $deadlineClass = 'overdue';
                                        } elseif ($daysUntilDeadline <= 30) {
                                            $deadlineLabel = '残り' . $daysUntilDeadline . '日';
                                            $deadlineClass = 'urgent';
                                        }
                                    }

                                    // メイン状態を設定
                                    if ($statusCode === 'none') {
                                        $statusLabel = '初回モニタリング未作成';
                                        $statusClass = 'none';
                                    } elseif ($statusCode === 'needs_confirm') {
                                        $statusLabel = '要保護者確認';
                                        $statusClass = 'needs-confirm';
                                    } elseif ($statusCode === 'draft') {
                                        $statusLabel = '下書き';
                                        $statusClass = 'draft';
                                    } elseif ($daysUntilDeadline !== null && $daysUntilDeadline < 0) {
                                        $statusLabel = '期限切れ（' . abs($daysUntilDeadline) . '日超過）';
                                        $statusClass = 'overdue';
                                        $deadlineLabel = ''; // 期限切れの場合は重複表示しない
                                    } elseif ($daysUntilDeadline !== null && $daysUntilDeadline <= 30) {
                                        $statusLabel = '1か月以内（残り' . $daysUntilDeadline . '日）';
                                        $statusClass = 'urgent';
                                        $deadlineLabel = ''; // 1か月以内の場合は重複表示しない
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
                                            <?php if ($deadlineLabel): ?>
                                                <span class="status-badge <?php echo $deadlineClass; ?>">
                                                    <?php echo htmlspecialchars($deadlineLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($hasNewer): ?>
                                                <span class="has-newer-badge">最新あり</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($statusCode === 'needs_confirm'): ?>
                                                    <a href="kobetsu_monitoring.php?student_id=<?php echo $student['id']; ?>&monitoring_id=<?php echo $student['monitoring_id']; ?>" class="btn btn-primary">
                                                        確認依頼
                                                    </a>
                                                <?php else: ?>
                                                    <a href="kobetsu_monitoring.php?student_id=<?php echo $student['id']; ?><?php echo $student['monitoring_id'] ? '&monitoring_id=' . $student['monitoring_id'] : ''; ?>" class="btn btn-primary">
                                                        モニタリング作成
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($student['monitoring_id']): ?>
                                                    <button class="btn-hide" onclick="hideItem('monitoring', <?php echo $student['monitoring_id']; ?>, this)">
                                                        非表示
                                                    </button>
                                                <?php elseif (!$student['monitoring_id']): ?>
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
                        <h3><span class="material-symbols-outlined">check_circle</span> すべての生徒のモニタリングが最新です</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 保護者かけはしセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><span class="material-symbols-outlined">handshake</span> 保護者かけはし</h2>
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
                        <h3><span class="material-symbols-outlined">check_circle</span> すべての保護者かけはしが提出済みです</h3>
                        <p>対応が必要な項目はありません。</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- スタッフかけはしセクション -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><span class="material-symbols-outlined">handshake</span> スタッフかけはし</h2>
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
                                <?php foreach ($pendingStaffKakehashi as $kakehashi):
                                    // 状態を判定
                                    $isNotCreated = empty($kakehashi['kakehashi_id']); // レコードなし = 未作成
                                    $isDraft = !empty($kakehashi['kakehashi_id']) && !$kakehashi['is_submitted']; // レコードあり & 未提出 = 下書き
                                    $isNeedsGuardianConfirm = !empty($kakehashi['kakehashi_id']) && $kakehashi['is_submitted'] && !$kakehashi['guardian_confirmed']; // 提出済み & 未確認 = 要保護者確認
                                    $daysLeft = $kakehashi['days_left'];

                                    // 期限状態を計算（下書き・要保護者確認の場合も表示するため）
                                    $deadlineLabel = '';
                                    $deadlineClass = '';
                                    if ($daysLeft < 0) {
                                        $deadlineLabel = '期限切れ（' . abs($daysLeft) . '日経過）';
                                        $deadlineClass = 'overdue';
                                    } elseif ($daysLeft <= 30) {
                                        $deadlineLabel = '残り' . $daysLeft . '日';
                                        $deadlineClass = ($daysLeft <= 7) ? 'urgent' : 'warning';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kakehashi['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($kakehashi['period_name']); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($kakehashi['start_date'])) . ' ～ ' . date('Y/m/d', strtotime($kakehashi['end_date'])); ?></td>
                                        <td><?php echo date('Y年n月j日', strtotime($kakehashi['submission_deadline'])); ?></td>
                                        <td>
                                            <?php if ($isNeedsGuardianConfirm): ?>
                                                <span class="status-badge needs-confirm">要保護者確認</span>
                                                <?php if ($deadlineLabel): ?>
                                                    <span class="status-badge <?php echo $deadlineClass; ?>"><?php echo $deadlineLabel; ?></span>
                                                <?php endif; ?>
                                            <?php elseif ($isDraft): ?>
                                                <span class="status-badge draft">下書き</span>
                                                <?php if ($deadlineLabel): ?>
                                                    <span class="status-badge <?php echo $deadlineClass; ?>"><?php echo $deadlineLabel; ?></span>
                                                <?php endif; ?>
                                            <?php elseif ($daysLeft < 0): ?>
                                                <span class="status-badge overdue">期限切れ（<?php echo abs($daysLeft); ?>日経過）</span>
                                            <?php elseif ($daysLeft <= 7): ?>
                                                <span class="status-badge urgent">緊急（残り<?php echo $daysLeft; ?>日）</span>
                                            <?php else: ?>
                                                <span class="status-badge warning">未作成（残り<?php echo $daysLeft; ?>日）</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($isNeedsGuardianConfirm): ?>
                                                    <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                                        確認依頼
                                                    </a>
                                                <?php else: ?>
                                                    <a href="kakehashi_staff.php?student_id=<?php echo $kakehashi['student_id']; ?>&period_id=<?php echo $kakehashi['period_id']; ?>" class="btn btn-primary">
                                                        <?php echo $isDraft ? '編集する' : '作成する'; ?>
                                                    </a>
                                                <?php endif; ?>
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
                        <h3><span class="material-symbols-outlined">check_circle</span> すべてのスタッフかけはしが作成済みです</h3>
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
            if (!confirm('このモニタリングタスクを非表示にしますか？\n非表示にすると、タスク一覧に表示されなくなります。')) {
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
