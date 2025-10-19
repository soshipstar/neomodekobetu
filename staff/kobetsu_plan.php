<?php
/**
 * スタッフ用 個別支援計画書作成ページ
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

// 全生徒を取得
$stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedPlanId = $_GET['plan_id'] ?? null;

// 既存の計画を取得（plan_idが指定されている場合）
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

// 選択された生徒の情報
$selectedStudent = null;
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $selectedStudent = $stmt->fetch();
}

// 選択された生徒の計画一覧
$studentPlans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE student_id = ? ORDER BY created_date DESC");
    $stmt->execute([$selectedStudentId]);
    $studentPlans = $stmt->fetchAll();
}

// 初期明細データ（新規作成時）
$defaultDetails = [
    ['category' => '本人支援', 'sub_category' => '生活習慣（健康・生活）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => 'コミュニケーション（言語・コミュニケーション）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '社会性（人間関係・社会性）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '運動・感覚（運動・感覚）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '本人支援', 'sub_category' => '学習（認知・行動）', 'achievement_date' => '2025-09-28', 'staff_organization' => "保育士\n児童指導員"],
    ['category' => '家族支援', 'sub_category' => '保護者支援', 'achievement_date' => '2025-09-28', 'staff_organization' => "児童発達支援管理責任者\n保育士"],
    ['category' => '地域支援', 'sub_category' => '関係機関連携', 'achievement_date' => '2025-09-28', 'staff_organization' => "児童発達支援管理責任者"],
];

// 明細データの準備
if (empty($planDetails)) {
    $planDetails = $defaultDetails;
}

// かけはし分析データから明細を上書き
if (isset($_SESSION['generated_plan']) && !empty($_SESSION['generated_plan']['details'])) {
    $planDetails = $_SESSION['generated_plan']['details'];
}

// かけはし分析データの取得（セッションから）
$generatedPlan = null;
if (isset($_SESSION['generated_plan'])) {
    $generatedPlan = $_SESSION['generated_plan'];
    unset($_SESSION['generated_plan']);
}

// 選択された生徒のかけはし期間一覧を取得
$studentPeriods = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT kp.*,
               kg.is_submitted as guardian_submitted,
               ks.is_submitted as staff_submitted
        FROM kakehashi_periods kp
        LEFT JOIN kakehashi_guardian kg ON kp.id = kg.period_id AND kg.student_id = kp.student_id
        LEFT JOIN kakehashi_staff ks ON kp.id = ks.period_id AND ks.student_id = kp.student_id
        WHERE kp.student_id = ? AND kp.is_active = 1
        ORDER BY kp.submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $studentPeriods = $stmt->fetchAll();
}

// 未作成のかけはし期間をチェック
$uncreatedPeriods = [];
if ($selectedStudentId) {
    // 生徒情報を取得
    $stmt = $pdo->prepare("SELECT support_start_date FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $student = $stmt->fetch();

    if ($student && $student['support_start_date']) {
        // 作成可能なかけはし期間を計算
        $supportStartDate = new DateTime($student['support_start_date']);
        $today = new DateTime();
        $generationLimit = clone $today;
        $generationLimit->modify('+1 month');

        // 既存のかけはし期間数を取得
        $stmt = $pdo->prepare("SELECT COUNT(*) as period_count FROM kakehashi_periods WHERE student_id = ?");
        $stmt->execute([$selectedStudentId]);
        $existingCount = (int)$stmt->fetch()['period_count'];

        // 初回かけはし（支援開始日の1日前が期限）
        $firstDeadline = clone $supportStartDate;
        $firstDeadline->modify('-1 day');

        if ($existingCount === 0 && $firstDeadline <= $generationLimit) {
            $uncreatedPeriods[] = [
                'type' => '初回',
                'deadline' => $firstDeadline->format('Y/m/d')
            ];
        }

        // 2回目かけはし（初回期限の4ヶ月後が期限）
        $secondDeadline = clone $firstDeadline;
        $secondDeadline->modify('+4 months');

        if ($existingCount <= 1 && $secondDeadline <= $generationLimit) {
            $uncreatedPeriods[] = [
                'type' => '2回目',
                'deadline' => $secondDeadline->format('Y/m/d')
            ];
        }

        // 3回目以降のかけはし（6ヶ月ごと）
        if ($existingCount >= 1) {
            // 最新のかけはし期限を取得
            $stmt = $pdo->prepare("
                SELECT submission_deadline
                FROM kakehashi_periods
                WHERE student_id = ?
                ORDER BY submission_deadline DESC
                LIMIT 1
            ");
            $stmt->execute([$selectedStudentId]);
            $latestPeriod = $stmt->fetch();

            if ($latestPeriod) {
                $latestDeadline = new DateTime($latestPeriod['submission_deadline']);
                $nextDeadline = clone $latestDeadline;

                // 6ヶ月ごとに次のかけはしをチェック
                $periodNum = $existingCount + 1;
                while (true) {
                    $nextDeadline->modify('+6 months');

                    if ($nextDeadline > $generationLimit) {
                        break;
                    }

                    $uncreatedPeriods[] = [
                        'type' => "{$periodNum}回目",
                        'deadline' => $nextDeadline->format('Y/m/d')
                    ];

                    $periodNum++;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個別支援計画書作成 - スタッフページ</title>
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

        .plan-meta {
            background: #f0f7ff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .meta-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .meta-item {
            flex: 1;
        }

        .meta-label {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .meta-value {
            font-size: 16px;
            color: #333;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .goal-section {
            margin-bottom: 10px;
        }

        .goal-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .goal-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .goal-date {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
        }

        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        .support-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .support-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #5a67d8;
        }

        .support-table td {
            padding: 10px 8px;
            border: 1px solid #e1e8ed;
            vertical-align: top;
        }

        .support-table input,
        .support-table textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #e1e8ed;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .support-table textarea {
            min-height: 60px;
            resize: vertical;
        }

        .support-table input[type="date"] {
            padding: 6px 8px;
        }

        .support-table input[type="number"] {
            width: 80px;
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-export {
            background: #17a2b8;
            color: white;
        }

        .btn-export:hover {
            background: #138496;
        }

        .btn-add {
            background: #ffc107;
            color: #333;
            padding: 8px 16px;
            font-size: 14px;
            margin-top: 10px;
        }

        .btn-add:hover {
            background: #e0a800;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-warning strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .alert-warning ul {
            margin: 10px 0 0 20px;
        }

        .alert-warning li {
            margin-bottom: 5px;
        }

        .note-box {
            background: #fff9e6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
            font-size: 14px;
        }

        .plans-list {
            margin-bottom: 20px;
        }

        .plan-item {
            display: inline-block;
            padding: 8px 15px;
            margin: 5px;
            background: #e3f2fd;
            border-radius: 6px;
            text-decoration: none;
            color: #1976d2;
            transition: all 0.3s;
        }

        .plan-item:hover {
            background: #1976d2;
            color: white;
        }

        .plan-item.active {
            background: #1976d2;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📋 個別支援計画書作成</h1>
                <?php if ($planData && ($planData['guardian_confirmed'] ?? 0)): ?>
                    <div class="guardian-confirmed-badge">
                        ✓ 保護者確認済み（<?= date('Y/m/d H:i', strtotime($planData['guardian_confirmed_at'])) ?>）
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
                <div class="alert alert-info" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- 生徒選択エリア -->
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
            </div>

            <?php if ($selectedStudentId): ?>
                <!-- 未作成のかけはし期間がある場合の警告 -->
                <?php if (!empty($uncreatedPeriods)): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Caution: 作成可能なかけはしで未作成のものがあります</strong>
                        <p>以下のかけはし期間が未作成です。生徒管理ページから自動生成してください：</p>
                        <ul>
                            <?php foreach ($uncreatedPeriods as $period): ?>
                                <li><?= htmlspecialchars($period['type']) ?>かけはし（提出期限: <?= htmlspecialchars($period['deadline']) ?>）</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- かけはし分析（新規作成時のみ） -->
                <?php if (!$selectedPlanId && !empty($studentPeriods)): ?>
                    <div style="background: #f3e5f5; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px; color: #9c27b0;">📊 かけはしを分析</h3>
                        <p style="margin-bottom: 15px; color: #666;">かけはしデータとモニタリング情報を分析し、個別支援計画書案を生成します。</p>
                        <form method="POST" action="kobetsu_plan_generate.php" onsubmit="return confirmGenerate()" style="display: flex; gap: 15px; align-items: flex-end;">
                            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                            <div class="form-group" style="flex: 1;">
                                <label>かけはし期間を選択</label>
                                <select name="period_id" required>
                                    <option value="">-- かけはし期間を選択 --</option>
                                    <?php foreach ($studentPeriods as $period): ?>
                                        <option value="<?= $period['id'] ?>">
                                            <?= date('Y/m/d', strtotime($period['submission_deadline'])) ?> 期限
                                            <?php if ($period['guardian_submitted']): ?>(保護者提出済)<?php endif; ?>
                                            <?php if ($period['staff_submitted']): ?>(スタッフ提出済)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                📊 分析開始
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- 既存の計画一覧 -->
                <?php if (!empty($studentPlans)): ?>
                    <div class="plans-list">
                        <strong>既存の計画:</strong>
                        <?php foreach ($studentPlans as $plan): ?>
                            <a href="kobetsu_plan.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $plan['id'] ?>"
                               class="plan-item <?= $plan['id'] == $selectedPlanId ? 'active' : '' ?>">
                                <?= date('Y/m/d', strtotime($plan['created_date'])) ?>
                            </a>
                        <?php endforeach; ?>
                        <a href="kobetsu_plan.php?student_id=<?= $selectedStudentId ?>" class="plan-item">+ 新規作成</a>
                    </div>
                <?php endif; ?>

                <!-- 計画書入力フォーム -->
                <form method="POST" action="kobetsu_plan_save.php" id="planForm">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="plan_id" value="<?= $selectedPlanId ?? '' ?>">

                    <!-- 基本情報 -->
                    <div class="plan-meta">
                        <div class="meta-row">
                            <div class="meta-item">
                                <div class="meta-label">氏名</div>
                                <div class="meta-value">
                                    <input type="text" name="student_name" value="<?= htmlspecialchars($planData['student_name'] ?? $selectedStudent['student_name']) ?>" required>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">作成年月日</div>
                                <div class="meta-value">
                                    <input type="date" name="created_date" value="<?= $planData['created_date'] ?? ($generatedPlan['created_date'] ?? date('Y-m-d')) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 意向・方針 -->
                    <div class="section-title">利用児及び家族の生活に対する意向</div>
                    <div class="form-group">
                        <textarea name="life_intention" rows="4"><?= htmlspecialchars($planData['life_intention'] ?? $generatedPlan['life_intention'] ?? '') ?></textarea>
                    </div>

                    <div class="section-title">総合的な支援の方針</div>
                    <div class="form-group">
                        <textarea name="overall_policy" rows="4"><?= htmlspecialchars($planData['overall_policy'] ?? $generatedPlan['overall_policy'] ?? '') ?></textarea>
                    </div>

                    <!-- 目標設定 -->
                    <div class="section-title">長期目標</div>
                    <div class="goal-header">
                        <div class="goal-title">達成時期</div>
                        <input type="date" name="long_term_goal_date" class="goal-date" value="<?= $planData['long_term_goal_date'] ?? ($generatedPlan['long_term_goal_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <textarea name="long_term_goal_text" rows="4"><?= htmlspecialchars($planData['long_term_goal_text'] ?? $generatedPlan['long_term_goal_text'] ?? '') ?></textarea>
                    </div>

                    <div class="section-title">短期目標</div>
                    <div class="goal-header">
                        <div class="goal-title">達成時期</div>
                        <input type="date" name="short_term_goal_date" class="goal-date" value="<?= $planData['short_term_goal_date'] ?? ($generatedPlan['short_term_goal_date'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <textarea name="short_term_goal_text" rows="4"><?= htmlspecialchars($planData['short_term_goal_text'] ?? $generatedPlan['short_term_goal_text'] ?? '') ?></textarea>
                    </div>

                    <!-- 支援目標及び具体的な支援内容等 -->
                    <div class="section-title">○支援目標及び具体的な支援内容等</div>

                    <div class="table-wrapper">
                        <table class="support-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">項目</th>
                                    <th style="width: 200px;">支援目標<br>（具体的な到達目標）</th>
                                    <th style="width: 250px;">支援内容<br>（内容・支援の提供上のポイント・5領域（※）との関連性等）</th>
                                    <th style="width: 110px;">達成時期</th>
                                    <th style="width: 150px;">担当者／提供機関</th>
                                    <th style="width: 150px;">留意事項</th>
                                    <th style="width: 80px;">優先順位</th>
                                </tr>
                            </thead>
                            <tbody id="detailsTable">
                                <?php foreach ($planDetails as $index => $detail): ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="details[<?= $index ?>][category]" value="<?= htmlspecialchars($detail['category'] ?? '') ?>" placeholder="項目">
                                            <textarea name="details[<?= $index ?>][sub_category]" rows="2" placeholder="サブカテゴリ"><?= htmlspecialchars($detail['sub_category'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $index ?>][support_goal]" rows="3"><?= htmlspecialchars($detail['support_goal'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $index ?>][support_content]" rows="3"><?= htmlspecialchars($detail['support_content'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <input type="date" name="details[<?= $index ?>][achievement_date]" value="<?= $detail['achievement_date'] ?? '' ?>">
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $index ?>][staff_organization]" rows="3"><?= htmlspecialchars($detail['staff_organization'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <textarea name="details[<?= $index ?>][notes]" rows="3"><?= htmlspecialchars($detail['notes'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <input type="number" name="details[<?= $index ?>][priority]" value="<?= $detail['priority'] ?? '' ?>" min="1" max="10">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-add" onclick="addDetailRow()">+ 行を追加</button>

                    <div class="note-box">
                        <strong>※ 5領域の視点：</strong>
                        「健康・生活」「運動・感覚」「認知・行動」「言語・コミュニケーション」「人間関係・社会性」
                    </div>

                    <!-- 同意欄 -->
                    <div class="section-title">同意</div>
                    <div class="meta-row">
                        <div class="meta-item">
                            <div class="meta-label">管理責任者氏名</div>
                            <input type="text" name="manager_name" value="<?= htmlspecialchars($planData['manager_name'] ?? '') ?>">
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">同意日</div>
                            <input type="date" name="consent_date" value="<?= $planData['consent_date'] ?? ($generatedPlan['consent_date'] ?? '') ?>">
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">保護者署名</div>
                            <input type="text" name="guardian_signature" value="<?= htmlspecialchars($planData['guardian_signature'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="button-group">
                        <button type="submit" name="save_draft" class="btn btn-secondary">📝 下書き保存（保護者非公開）</button>
                        <button type="submit" name="action" value="save" class="btn btn-success">✅ 作成・提出（保護者に公開）</button>
                        <?php if ($selectedPlanId): ?>
                            <a href="kobetsu_plan_export.php?plan_id=<?= $selectedPlanId ?>" class="btn btn-export">📥 CSV出力</a>
                            <a href="kobetsu_plan_pdf.php?plan_id=<?= $selectedPlanId ?>" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">📄 PDF出力</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    生徒を選択してください。
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = `kobetsu_plan.php?student_id=${studentId}`;
            }
        }

        function confirmGenerate() {
            return confirm('選択したかけはし期間のデータを分析し、個別支援計画書案を生成します。\n既に入力されている内容は上書きされます。\nよろしいですか？');
        }

        let rowIndex = <?= count($planDetails) ?>;

        function addDetailRow() {
            const table = document.getElementById('detailsTable');
            const row = table.insertRow();

            row.innerHTML = `
                <td>
                    <input type="text" name="details[${rowIndex}][category]" placeholder="項目">
                    <textarea name="details[${rowIndex}][sub_category]" rows="2" placeholder="サブカテゴリ"></textarea>
                </td>
                <td>
                    <textarea name="details[${rowIndex}][support_goal]" rows="3"></textarea>
                </td>
                <td>
                    <textarea name="details[${rowIndex}][support_content]" rows="3"></textarea>
                </td>
                <td>
                    <input type="date" name="details[${rowIndex}][achievement_date]">
                </td>
                <td>
                    <textarea name="details[${rowIndex}][staff_organization]" rows="3"></textarea>
                </td>
                <td>
                    <textarea name="details[${rowIndex}][notes]" rows="3"></textarea>
                </td>
                <td>
                    <input type="number" name="details[${rowIndex}][priority]" min="1" max="10">
                </td>
            `;

            rowIndex++;
        }
    </script>
</body>
</html>
