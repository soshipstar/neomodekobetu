<?php
/**
 * スタッフ用 モニタリング表作成ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_monitoring_id'])) {
    $deleteId = $_POST['delete_monitoring_id'];

    try {
        // モニタリング明細も削除
        $stmt = $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$deleteId]);

        // モニタリング本体を削除
        $stmt = $pdo->prepare("DELETE FROM monitoring_records WHERE id = ?");
        $stmt->execute([$deleteId]);

        $_SESSION['success'] = 'モニタリング表を削除しました。';

        // リダイレクト先を決定
        $studentId = $_POST['student_id'] ?? null;
        $planId = $_POST['plan_id'] ?? null;

        if ($studentId && $planId) {
            header("Location: kobetsu_monitoring.php?student_id=$studentId&plan_id=$planId");
        } else {
            header("Location: kobetsu_monitoring.php");
        }
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }
}

// 自分の教室の生徒を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
}
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedPlanId = $_GET['plan_id'] ?? null;
$selectedMonitoringId = $_GET['monitoring_id'] ?? null;

// 選択された生徒の個別支援計画一覧
$studentPlans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE student_id = ? ORDER BY created_date DESC");
    $stmt->execute([$selectedStudentId]);
    $studentPlans = $stmt->fetchAll();
}

// 選択された計画の詳細
$planData = null;
$planDetails = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
    $stmt->execute([$selectedPlanId]);
    $planData = $stmt->fetch();

    if ($planData) {
        $selectedStudentId = $planData['student_id'];

        // 明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 既存のモニタリングデータを取得
$monitoringData = null;
$monitoringDetails = [];
if ($selectedMonitoringId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE id = ?");
    $stmt->execute([$selectedMonitoringId]);
    $monitoringData = $stmt->fetch();

    if ($monitoringData) {
        $selectedPlanId = $monitoringData['plan_id'];
        $selectedStudentId = $monitoringData['student_id'];

        // モニタリング明細を取得
        $stmt = $pdo->prepare("SELECT * FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$selectedMonitoringId]);
        $monitoringDetailsRaw = $stmt->fetchAll();

        // plan_detail_idをキーにした配列に変換
        foreach ($monitoringDetailsRaw as $detail) {
            $monitoringDetails[$detail['plan_detail_id']] = $detail;
        }

        // 計画データも取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
        $stmt->execute([$selectedPlanId]);
        $planData = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// 選択された計画の既存モニタリング一覧
$existingMonitorings = [];
if ($selectedPlanId) {
    $stmt = $pdo->prepare("SELECT * FROM monitoring_records WHERE plan_id = ? ORDER BY monitoring_date DESC");
    $stmt->execute([$selectedPlanId]);
    $existingMonitorings = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>モニタリング表作成 - スタッフページ</title>
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
            max-width: 1600px;
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
            margin-bottom: 8px;
        }

        .guardian-confirmed-badge {
            display: inline-block;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
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

        .selection-area {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }

        .plan-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }

        .info-item {
            flex: 1;
        }

        .info-label {
            font-weight: 600;
            color: #1976d2;
            font-size: 14px;
        }

        .info-value {
            color: #333;
            margin-top: 5px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        .monitoring-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .monitoring-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #5a67d8;
        }

        .monitoring-table td {
            padding: 10px 8px;
            border: 1px solid #e1e8ed;
            vertical-align: top;
            text-align: left;
        }

        .monitoring-table .plan-content {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            font-size: 14px;
            color: #555;
            white-space: normal;
            text-align: left;
        }

        .monitoring-table input,
        .monitoring-table textarea,
        .monitoring-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #e1e8ed;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .monitoring-table textarea {
            min-height: 80px;
            resize: vertical;
        }

        .monitoring-table select {
            padding: 6px 8px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .monitoring-list {
            margin-bottom: 20px;
        }

        .monitoring-item {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            background: #fff3cd;
            border-radius: 6px;
            text-decoration: none;
            color: #856404;
            transition: all 0.3s;
        }

        .monitoring-item:hover {
            background: #ffc107;
            color: white;
        }

        .monitoring-item.active {
            background: #ffc107;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📊 モニタリング表作成</h1>
                <?php if ($monitoringData && ($monitoringData['guardian_confirmed'] ?? 0)): ?>
                    <div class="guardian-confirmed-badge">
                        ✓ 保護者確認済み（<?= date('Y/m/d H:i', strtotime($monitoringData['guardian_confirmed_at'])) ?>）
                    </div>
                <?php endif; ?>
            </div>
            <div class="nav-links">
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- 生徒・計画選択エリア -->
            <div class="selection-area">
                <div class="form-group">
                    <label>生徒を選択 *</label>
                    <select id="studentSelect" onchange="changeStudent()">
                        <option value="">-- 生徒を選択してください --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($studentPlans)): ?>
                    <div class="form-group">
                        <label>個別支援計画書を選択 *</label>
                        <select id="planSelect" onchange="changePlan()">
                            <option value="">-- 計画書を選択してください --</option>
                            <?php foreach ($studentPlans as $plan): ?>
                                <option value="<?= $plan['id'] ?>" <?= $plan['id'] == $selectedPlanId ? 'selected' : '' ?>>
                                    <?= date('Y年m月d日', strtotime($plan['created_date'])) ?> 作成
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selectedPlanId && $planData): ?>
                <!-- 既存のモニタリング一覧 -->
                <?php if (!empty($existingMonitorings)): ?>
                    <div class="monitoring-list">
                        <strong>既存のモニタリング:</strong>
                        <?php foreach ($existingMonitorings as $monitoring): ?>
                            <div style="display: inline-flex; align-items: center; gap: 5px;">
                                <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>&monitoring_id=<?= $monitoring['id'] ?>"
                                   class="monitoring-item <?= $monitoring['id'] == $selectedMonitoringId ? 'active' : '' ?>">
                                    <?= date('Y/m/d', strtotime($monitoring['monitoring_date'])) ?>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('このモニタリング表を削除してもよろしいですか？');">
                                    <input type="hidden" name="delete_monitoring_id" value="<?= $monitoring['id'] ?>">
                                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                                    <button type="submit" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">🗑️</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <a href="kobetsu_monitoring.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $selectedPlanId ?>" class="monitoring-item">+ 新規作成</a>
                    </div>
                <?php endif; ?>

                <!-- 計画情報表示 -->
                <div class="plan-info">
                    <h3 style="margin-bottom: 15px; color: #1976d2;">対象の個別支援計画書</h3>
                    <div class="info-row">
                        <div class="info-item">
                            <div class="info-label">生徒氏名</div>
                            <div class="info-value"><?= htmlspecialchars($planData['student_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">作成年月日</div>
                            <div class="info-value"><?= date('Y年m月d日', strtotime($planData['created_date'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">長期目標達成時期</div>
                            <div class="info-value"><?= $planData['long_term_goal_date'] ? date('Y年m月d日', strtotime($planData['long_term_goal_date'])) : '未設定' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">短期目標達成時期</div>
                            <div class="info-value"><?= $planData['short_term_goal_date'] ? date('Y年m月d日', strtotime($planData['short_term_goal_date'])) : '未設定' ?></div>
                        </div>
                    </div>
                </div>

                <!-- モニタリング入力フォーム -->
                <form method="POST" action="kobetsu_monitoring_save.php" id="monitoringForm">
                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?>">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="monitoring_id" value="<?= $selectedMonitoringId ?? '' ?>">

                    <!-- モニタリング実施日 -->
                    <div class="selection-area">
                        <div class="form-group">
                            <label>モニタリング実施日 *</label>
                            <input type="date" name="monitoring_date" value="<?= $monitoringData['monitoring_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- モニタリング表 -->
                    <div class="section-title">支援目標の達成状況</div>

                    <div class="table-wrapper">
                        <table class="monitoring-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">項目</th>
                                    <th style="width: 180px;">支援目標</th>
                                    <th style="width: 200px;">支援内容</th>
                                    <th style="width: 100px;">達成時期</th>
                                    <th style="width: 120px;">達成状況</th>
                                    <th style="width: 300px;">モニタリングコメント</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planDetails as $detail): ?>
                                    <?php
                                    $monitoringDetail = $monitoringDetails[$detail['id']] ?? null;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="plan-content">
                                                <?= htmlspecialchars($detail['category']) ?>
                                                <?php if ($detail['sub_category']): ?>
                                                    <br><?= htmlspecialchars($detail['sub_category']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= nl2br(htmlspecialchars($detail['support_goal'] ?: '（未設定）')) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= nl2br(htmlspecialchars($detail['support_content'] ?: '（未設定）')) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="plan-content">
                                                <?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '（未設定）' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="hidden" name="details[<?= $detail['id'] ?>][plan_detail_id]" value="<?= $detail['id'] ?>">
                                            <select name="details[<?= $detail['id'] ?>][achievement_status]">
                                                <option value="">-- 選択 --</option>
                                                <option value="未着手" <?= ($monitoringDetail['achievement_status'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                                <option value="進行中" <?= ($monitoringDetail['achievement_status'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                                <option value="達成" <?= ($monitoringDetail['achievement_status'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                                <option value="継続中" <?= ($monitoringDetail['achievement_status'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                                <option value="見直し必要" <?= ($monitoringDetail['achievement_status'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                                            </select>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $detail['id'] ?>][monitoring_comment]" rows="3"><?= htmlspecialchars($monitoringDetail['monitoring_comment'] ?? '') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- 短期目標・長期目標の振り返り -->
                    <div class="section-title" style="margin-top: 30px;">目標の達成状況</div>

                    <!-- 長期目標 -->
                    <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                        <h4 style="color: #667eea; margin-bottom: 12px; font-size: 16px;">🎯 長期目標</h4>
                        <?php if (!empty($planData['long_term_goal_text'])): ?>
                            <div style="padding: 12px; background: white; border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 12px; background: white; border-radius: 6px; margin-bottom: 15px; color: #999; font-style: italic;">
                                長期目標が設定されていません
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">達成状況</label>
                            <select name="long_term_goal_achievement" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- 選択してください --</option>
                                <option value="未着手" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                <option value="進行中" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                <option value="達成" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                <option value="継続中" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                <option value="見直し必要" <?= ($monitoringData['long_term_goal_achievement'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">コメント</label>
                            <textarea name="long_term_goal_comment" rows="4" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical;" placeholder="長期目標に対する振り返りや所見を記入してください"><?= htmlspecialchars($monitoringData['long_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 短期目標 -->
                    <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                        <h4 style="color: #28a745; margin-bottom: 12px; font-size: 16px;">📌 短期目標</h4>
                        <?php if (!empty($planData['short_term_goal_text'])): ?>
                            <div style="padding: 12px; background: white; border-radius: 6px; margin-bottom: 15px; line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 12px; background: white; border-radius: 6px; margin-bottom: 15px; color: #999; font-style: italic;">
                                短期目標が設定されていません
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">達成状況</label>
                            <select name="short_term_goal_achievement" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                <option value="">-- 選択してください --</option>
                                <option value="未着手" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '未着手' ? 'selected' : '' ?>>未着手</option>
                                <option value="進行中" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '進行中' ? 'selected' : '' ?>>進行中</option>
                                <option value="達成" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '達成' ? 'selected' : '' ?>>達成</option>
                                <option value="継続中" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '継続中' ? 'selected' : '' ?>>継続中</option>
                                <option value="見直し必要" <?= ($monitoringData['short_term_goal_achievement'] ?? '') == '見直し必要' ? 'selected' : '' ?>>見直し必要</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #555;">コメント</label>
                            <textarea name="short_term_goal_comment" rows="4" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical;" placeholder="短期目標に対する振り返りや所見を記入してください"><?= htmlspecialchars($monitoringData['short_term_goal_comment'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- 総合所見 -->
                    <div class="section-title">総合所見</div>
                    <div class="form-group">
                        <textarea name="overall_comment" rows="6"><?= htmlspecialchars($monitoringData['overall_comment'] ?? '') ?></textarea>
                    </div>

                    <!-- ボタン -->
                    <div class="button-group">
                        <button type="submit" name="save_draft" class="btn btn-secondary">📝 下書き保存（保護者非公開）</button>
                        <button type="submit" class="btn btn-success">✅ 作成・提出（保護者に公開）</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    生徒を選択し、個別支援計画書を選択してください。
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = `kobetsu_monitoring.php?student_id=${studentId}`;
            }
        }

        function changePlan() {
            const studentId = document.getElementById('studentSelect').value;
            const planId = document.getElementById('planSelect').value;
            if (studentId && planId) {
                window.location.href = `kobetsu_monitoring.php?student_id=${studentId}&plan_id=${planId}`;
            }
        }
    </script>
</body>
</html>
