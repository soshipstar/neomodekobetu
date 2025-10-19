<?php
/**
 * 保護者用 個別支援計画書閲覧ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 保護者に紐づく生徒を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? AND is_active = 1 ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// 選択された生徒の個別支援計画一覧（提出済みのみ）
$plans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT * FROM individual_support_plans
        WHERE student_id = ? AND is_draft = 0
        ORDER BY created_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $plans = $stmt->fetchAll();
}

// 選択された計画の詳細
$selectedPlanId = $_GET['plan_id'] ?? null;
$planData = null;
$planDetails = [];

if ($selectedPlanId) {
    $stmt = $pdo->prepare("
        SELECT * FROM individual_support_plans
        WHERE id = ? AND student_id = ? AND is_draft = 0
    ");
    $stmt->execute([$selectedPlanId, $selectedStudentId]);
    $planData = $stmt->fetch();

    if ($planData) {
        // 明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個別支援計画書 - 保護者ページ</title>
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

        .selector-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .selector-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
        }

        .form-group select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 15px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .plan-card {
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .plan-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .plan-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #faf0ff 100%);
        }

        .plan-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .plan-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .plan-card-date {
            color: #666;
            font-size: 14px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .info-item label {
            display: block;
            font-weight: 600;
            color: #666;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-item .value {
            color: #333;
            font-size: 16px;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            color: #999;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .confirmation-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }

        .confirmation-box p {
            margin-bottom: 20px;
            font-size: 16px;
            color: #333;
        }

        .confirmation-box.confirmed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: left;
        }

        .confirmation-icon {
            width: 60px;
            height: 60px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .confirmation-content {
            flex-grow: 1;
        }

        .confirmation-title {
            font-size: 20px;
            font-weight: bold;
            color: #155724;
            margin-bottom: 5px;
        }

        .confirmation-date {
            font-size: 14px;
            color: #155724;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 個別支援計画書</h1>
            <div class="nav-links">
                <a href="dashboard.php">← ダッシュボード</a>
            </div>
        </div>

        <div class="content">
            <!-- 生徒選択 -->
            <div class="selector-section">
                <div class="selector-group">
                    <div class="form-group">
                        <label>👤 お子様を選択</label>
                        <select onchange="location.href='support_plans.php?student_id=' + this.value">
                            <option value="">-- 選択してください --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $selectedStudentId == $student['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['student_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($selectedStudentId): ?>
                <!-- 計画一覧 -->
                <div class="section-title">提出済みの個別支援計画書</div>

                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <div class="plan-card <?= $selectedPlanId == $plan['id'] ? 'selected' : '' ?>"
                             onclick="location.href='support_plans.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $plan['id'] ?>'">
                            <div class="plan-card-header">
                                <div class="plan-card-title">
                                    <?= htmlspecialchars($plan['student_name']) ?>さんの個別支援計画書
                                </div>
                                <div class="plan-card-date">
                                    作成日: <?= date('Y年n月j日', strtotime($plan['created_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- 計画詳細 -->
                    <?php if ($planData): ?>
                        <div class="section-title">計画書の詳細</div>

                        <!-- 基本情報 -->
                        <div class="info-grid">
                            <div class="info-item">
                                <label>お子様のお名前</label>
                                <div class="value"><?= htmlspecialchars($planData['student_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <label>作成日</label>
                                <div class="value"><?= date('Y年n月j日', strtotime($planData['created_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <label>担当者</label>
                                <div class="value"><?= htmlspecialchars($planData['manager_name'] ?: '未設定') ?></div>
                            </div>
                        </div>

                        <!-- 本人・家族の意向 -->
                        <?php if ($planData['life_intention']): ?>
                            <div class="section-title">本人・家族の意向</div>
                            <div class="info-item">
                                <div class="value"><?= nl2br(htmlspecialchars($planData['life_intention'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 総合的な支援方針 -->
                        <?php if ($planData['overall_policy']): ?>
                            <div class="section-title">総合的な支援方針</div>
                            <div class="info-item">
                                <div class="value"><?= nl2br(htmlspecialchars($planData['overall_policy'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 長期目標 -->
                        <?php if ($planData['long_term_goal_text']): ?>
                            <div class="section-title">長期目標</div>
                            <div class="info-grid">
                                <?php if ($planData['long_term_goal_date']): ?>
                                    <div class="info-item">
                                        <label>達成時期</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['long_term_goal_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>目標内容</label>
                                    <div class="value"><?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 短期目標 -->
                        <?php if ($planData['short_term_goal_text']): ?>
                            <div class="section-title">短期目標</div>
                            <div class="info-grid">
                                <?php if ($planData['short_term_goal_date']): ?>
                                    <div class="info-item">
                                        <label>達成時期</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['short_term_goal_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>目標内容</label>
                                    <div class="value"><?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 支援内容詳細 -->
                        <?php if (!empty($planDetails)): ?>
                            <div class="section-title">支援内容の詳細</div>
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">項目</th>
                                            <th style="width: 200px;">支援目標</th>
                                            <th style="width: 250px;">支援内容</th>
                                            <th style="width: 120px;">達成時期</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($planDetails as $detail): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($detail['main_category'] ?: '') ?>
                                                    <?php if ($detail['sub_category']): ?>
                                                        <br><small><?= htmlspecialchars($detail['sub_category']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= nl2br(htmlspecialchars($detail['support_goal'] ?: '')) ?></td>
                                                <td><?= nl2br(htmlspecialchars($detail['support_content'] ?: '')) ?></td>
                                                <td>
                                                    <?= $detail['achievement_date'] ? date('Y年n月j日', strtotime($detail['achievement_date'])) : '' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- 同意情報 -->
                        <?php if ($planData['consent_date'] || $planData['guardian_signature']): ?>
                            <div class="section-title">同意情報</div>
                            <div class="info-grid">
                                <?php if ($planData['consent_date']): ?>
                                    <div class="info-item">
                                        <label>同意日</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['consent_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($planData['guardian_signature']): ?>
                                    <div class="info-item">
                                        <label>保護者署名</label>
                                        <div class="value"><?= htmlspecialchars($planData['guardian_signature']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- 保護者確認 -->
                        <div class="section-title">保護者確認</div>
                        <?php
                        $guardianConfirmed = $planData['guardian_confirmed'] ?? 0;
                        $guardianConfirmedAt = $planData['guardian_confirmed_at'] ?? null;
                        ?>
                        <?php if ($guardianConfirmed): ?>
                            <div class="confirmation-box confirmed">
                                <div class="confirmation-icon">✓</div>
                                <div class="confirmation-content">
                                    <div class="confirmation-title">確認済み</div>
                                    <div class="confirmation-date">
                                        確認日時: <?= date('Y年n月j日 H:i', strtotime($guardianConfirmedAt)) ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="confirmation-box">
                                <p>この個別支援計画書の内容を確認しました。</p>
                                <button onclick="confirmPlan(<?= $selectedPlanId ?>)" class="btn btn-primary" id="confirmBtn">
                                    ✓ 内容を確認しました
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>📋 提出済みの個別支援計画書はまだありません</h3>
                        <p>スタッフが計画書を作成・提出すると、ここに表示されます。</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>👤 お子様を選択してください</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmPlan(planId) {
            if (!confirm('この個別支援計画書の内容を確認しましたか？\n確認後は取り消せません。')) {
                return;
            }

            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.textContent = '処理中...';

            fetch('support_plan_confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ plan_id: planId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('確認しました。ありがとうございます。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = '✓ 内容を確認しました';
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
                btn.disabled = false;
                btn.textContent = '✓ 内容を確認しました';
            });
        }
    </script>
</body>
</html>
