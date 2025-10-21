<?php
/**
 * 未作成タスク一覧ページ
 * 個別支援計画書、モニタリング、かけはしの未作成・未提出を一覧表示
 */

// エラー表示を有効化（デバッグ用）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$today = date('Y-m-d');

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 1. 個別支援計画書が未作成または古い生徒を取得（自分の教室のみ）
$studentsNeedingPlan = [];

if ($classroomId) {
    // 1-1. 個別支援計画書が1つも作成されていない生徒
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date,
               NULL as latest_plan_date,
               'なし' as status
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id
        )
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
    $studentsNeedingPlan = array_merge($studentsNeedingPlan, $stmt->fetchAll());

    // 1-2. 最新の個別支援計画書から6ヶ月以上経過している生徒
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date,
               MAX(isp.created_date) as latest_plan_date,
               '最新から6ヶ月以上経過' as status
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(isp.created_date)) >= 180
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
    $studentsNeedingPlan = array_merge($studentsNeedingPlan, $stmt->fetchAll());
} else {
    // 1-1. 個別支援計画書が1つも作成されていない生徒
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date,
               NULL as latest_plan_date,
               'なし' as status
        FROM students s
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM individual_support_plans isp
            WHERE isp.student_id = s.id
        )
        ORDER BY s.student_name
    ");
    $studentsNeedingPlan = array_merge($studentsNeedingPlan, $stmt->fetchAll());

    // 1-2. 最新の個別支援計画書から6ヶ月以上経過している生徒
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date,
               MAX(isp.created_date) as latest_plan_date,
               '最新から6ヶ月以上経過' as status
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(isp.created_date)) >= 180
        ORDER BY s.student_name
    ");
    $studentsNeedingPlan = array_merge($studentsNeedingPlan, $stmt->fetchAll());
}

// 2. モニタリングが未作成または古い生徒を取得（自分の教室のみ）
$studentsNeedingMonitoring = [];

if ($classroomId) {
    // 2-1. モニタリングが1つも作成されていない生徒（個別支援計画書がある生徒のみ）
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.student_name, s.support_start_date,
               NULL as latest_monitoring_date,
               'なし' as status
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
    $studentsNeedingMonitoring = array_merge($studentsNeedingMonitoring, $stmt->fetchAll());

    // 2-2. 最新のモニタリングから3ヶ月以上経過している生徒
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date,
               MAX(mr.monitoring_date) as latest_monitoring_date,
               '最新から3ヶ月以上経過' as status
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) >= 90
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
    $studentsNeedingMonitoring = array_merge($studentsNeedingMonitoring, $stmt->fetchAll());
} else {
    // 2-1. モニタリングが1つも作成されていない生徒（個別支援計画書がある生徒のみ）
    $stmt = $pdo->query("
        SELECT DISTINCT s.id, s.student_name, s.support_start_date,
               NULL as latest_monitoring_date,
               'なし' as status
        FROM students s
        INNER JOIN individual_support_plans isp ON s.id = isp.student_id
        WHERE s.is_active = 1
        AND NOT EXISTS (
            SELECT 1 FROM monitoring_records mr
            WHERE mr.student_id = s.id
        )
        ORDER BY s.student_name
    ");
    $studentsNeedingMonitoring = array_merge($studentsNeedingMonitoring, $stmt->fetchAll());

    // 2-2. 最新のモニタリングから3ヶ月以上経過している生徒
    $stmt = $pdo->query("
        SELECT s.id, s.student_name, s.support_start_date,
               MAX(mr.monitoring_date) as latest_monitoring_date,
               '最新から3ヶ月以上経過' as status
        FROM students s
        INNER JOIN monitoring_records mr ON s.id = mr.student_id
        WHERE s.is_active = 1
        GROUP BY s.id
        HAVING DATEDIFF(CURDATE(), MAX(mr.monitoring_date)) >= 90
        ORDER BY s.student_name
    ");
    $studentsNeedingMonitoring = array_merge($studentsNeedingMonitoring, $stmt->fetchAll());
}

// 3. かけはし未提出の生徒を取得

// 3-1. 保護者かけはし未提出（期限切れも含む、非表示を除外、自分の教室のみ）
$pendingGuardianKakehashi = [];
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
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
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today, $classroomId]);
        $pendingGuardianKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしで取得
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
        $stmt = $pdo->prepare("
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
                kg.is_submitted
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today, $classroomId]);
        $pendingGuardianKakehashi = $stmt->fetchAll();
    }
} else {
    try {
        $stmt = $pdo->prepare("
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
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            AND COALESCE(kg.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today]);
        $pendingGuardianKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしで取得
        error_log("Guardian kakehashi fetch error: " . $e->getMessage());
        $stmt = $pdo->prepare("
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
                kg.is_submitted
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (kg.is_submitted = 0 OR kg.is_submitted IS NULL)
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today]);
        $pendingGuardianKakehashi = $stmt->fetchAll();
    }
}

// 3-2. スタッフかけはし未作成（期限切れも含む、非表示を除外、自分の教室のみ）
$pendingStaffKakehashi = [];
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
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
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today, $classroomId]);
        $pendingStaffKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしで取得
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
        $stmt = $pdo->prepare("
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
                ks.is_submitted
            FROM students s
            INNER JOIN users u ON s.guardian_id = u.id
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1 AND u.classroom_id = ?
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today, $classroomId]);
        $pendingStaffKakehashi = $stmt->fetchAll();
    }
} else {
    try {
        $stmt = $pdo->prepare("
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
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            AND COALESCE(ks.is_hidden, 0) = 0
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today]);
        $pendingStaffKakehashi = $stmt->fetchAll();
    } catch (Exception $e) {
        // is_hiddenカラムが存在しない場合は、非表示チェックなしで取得
        error_log("Staff kakehashi fetch error: " . $e->getMessage());
        $stmt = $pdo->prepare("
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
                ks.is_submitted
            FROM students s
            INNER JOIN kakehashi_periods kp ON s.id = kp.student_id
            LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = s.id
            WHERE s.is_active = 1
            AND kp.is_active = 1
            AND (ks.is_submitted = 0 OR ks.is_submitted IS NULL)
            ORDER BY kp.submission_deadline ASC, s.student_name
        ");
        $stmt->execute([$today]);
        $pendingStaffKakehashi = $stmt->fetchAll();
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>未作成タスク一覧 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #667eea;
        }

        .count-badge {
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .count-badge.success {
            background: #28a745;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e1e8ed;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.none {
            background: #dc3545;
            color: white;
        }

        .status-badge.outdated {
            background: #ffc107;
            color: #333;
        }

        .status-badge.overdue {
            background: #6c757d;
            color: white;
        }

        .status-badge.urgent {
            background: #dc3545;
            color: white;
        }

        .status-badge.warning {
            background: #ffc107;
            color: #333;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state h3 {
            color: #28a745;
            margin-bottom: 10px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .summary-card.urgent {
            border-left-color: #dc3545;
        }

        .summary-card.warning {
            border-left-color: #ffc107;
        }

        .summary-card.success {
            border-left-color: #28a745;
        }

        .summary-card-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .summary-card-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .btn-hide {
            padding: 6px 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
        }

        .btn-hide:hover {
            background: #5a6268;
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
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 未作成タスク一覧</h1>
            <div class="nav-links">
                <a href="renrakucho_activities.php">← 活動管理に戻る</a>
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
                                <?php foreach ($studentsNeedingPlan as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo $student['support_start_date'] ? date('Y年n月j日', strtotime($student['support_start_date'])) : '-'; ?></td>
                                        <td><?php echo $student['latest_plan_date'] ? date('Y年n月j日', strtotime($student['latest_plan_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $student['status'] === 'なし' ? 'none' : 'outdated'; ?>">
                                                <?php echo htmlspecialchars($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="kobetsu_plan.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                                計画書を作成
                                            </a>
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
                                    <th>最新モニタリング日</th>
                                    <th>状態</th>
                                    <th>アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsNeedingMonitoring as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo $student['support_start_date'] ? date('Y年n月j日', strtotime($student['support_start_date'])) : '-'; ?></td>
                                        <td><?php echo $student['latest_monitoring_date'] ? date('Y年n月j日', strtotime($student['latest_monitoring_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $student['status'] === 'なし' ? 'none' : 'outdated'; ?>">
                                                <?php echo htmlspecialchars($student['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="kobetsu_monitoring.php?student_id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                                モニタリング作成
                                            </a>
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
    </div>

    <script>
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
                    // 行を削除（フェードアウト効果）
                    const row = button.closest('tr');
                    row.style.opacity = '0';
                    row.style.transition = 'opacity 0.3s';

                    setTimeout(() => {
                        row.remove();

                        // テーブルが空になったら空の状態を表示
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
</body>
</html>
