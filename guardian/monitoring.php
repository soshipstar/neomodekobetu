<?php
/**
 * 保護者用 モニタリング表閲覧ページ
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

// 選択された生徒のモニタリング一覧（提出済みのみ）
$monitorings = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT mr.*, isp.created_date as plan_created_date
        FROM monitoring_records mr
        INNER JOIN individual_support_plans isp ON mr.plan_id = isp.id
        WHERE mr.student_id = ? AND mr.is_draft = 0
        ORDER BY mr.monitoring_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $monitorings = $stmt->fetchAll();
}

// 選択されたモニタリングの詳細
$selectedMonitoringId = $_GET['monitoring_id'] ?? null;
$monitoringData = null;
$planData = null;
$monitoringDetails = [];

if ($selectedMonitoringId) {
    $stmt = $pdo->prepare("
        SELECT * FROM monitoring_records
        WHERE id = ? AND student_id = ? AND is_draft = 0
    ");
    $stmt->execute([$selectedMonitoringId, $selectedStudentId]);
    $monitoringData = $stmt->fetch();

    if ($monitoringData) {
        // 計画データを取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
        $stmt->execute([$monitoringData['plan_id']]);
        $planData = $stmt->fetch();

        // 計画明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$monitoringData['plan_id']]);
        $planDetails = $stmt->fetchAll();

        // モニタリング明細を取得
        $stmt = $pdo->prepare("SELECT * FROM monitoring_details WHERE monitoring_id = ?");
        $stmt->execute([$selectedMonitoringId]);
        $monitoringDetailsRaw = $stmt->fetchAll();

        // plan_detail_idをキーにした配列に変換
        foreach ($monitoringDetailsRaw as $detail) {
            $monitoringDetails[$detail['plan_detail_id']] = $detail;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>モニタリング表 - 保護者ページ</title>
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
            max-width: 1400px;
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

        .monitoring-card {
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .monitoring-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .monitoring-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #faf0ff 100%);
        }

        .monitoring-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .monitoring-card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .monitoring-card-date {
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
            text-align: left;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .achievement-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .achievement-achieved {
            background: #d4edda;
            color: #155724;
        }

        .achievement-progressing {
            background: #fff3cd;
            color: #856404;
        }

        .achievement-not-achieved {
            background: #f8d7da;
            color: #721c24;
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

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 モニタリング表</h1>
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
                        <select onchange="location.href='monitoring.php?student_id=' + this.value">
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
                <!-- モニタリング一覧 -->
                <div class="section-title">提出済みのモニタリング表</div>

                <?php if (!empty($monitorings)): ?>
                    <?php foreach ($monitorings as $monitoring): ?>
                        <div class="monitoring-card <?= $selectedMonitoringId == $monitoring['id'] ? 'selected' : '' ?>"
                             onclick="location.href='monitoring.php?student_id=<?= $selectedStudentId ?>&monitoring_id=<?= $monitoring['id'] ?>'">
                            <div class="monitoring-card-header">
                                <div class="monitoring-card-title">
                                    モニタリング（<?= date('Y年n月j日', strtotime($monitoring['monitoring_date'])) ?>実施）
                                </div>
                                <div class="monitoring-card-date">
                                    対象計画: <?= date('Y年n月', strtotime($monitoring['plan_created_date'])) ?>作成
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- モニタリング詳細 -->
                    <?php if ($monitoringData && $planData): ?>
                        <div class="section-title">モニタリングの詳細</div>

                        <!-- 基本情報 -->
                        <div class="info-grid">
                            <div class="info-item">
                                <label>お子様のお名前</label>
                                <div class="value"><?= htmlspecialchars($monitoringData['student_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <label>実施日</label>
                                <div class="value"><?= date('Y年n月j日', strtotime($monitoringData['monitoring_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <label>対象計画書</label>
                                <div class="value"><?= date('Y年n月j日', strtotime($planData['created_date'])) ?>作成</div>
                            </div>
                        </div>

                        <!-- 達成状況詳細 -->
                        <?php if (!empty($planDetails)): ?>
                            <div class="section-title">支援目標の達成状況</div>
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">項目</th>
                                            <th style="width: 180px;">支援目標</th>
                                            <th style="width: 200px;">支援内容</th>
                                            <th style="width: 100px;">達成時期</th>
                                            <th style="width: 120px;">達成状況</th>
                                            <th>モニタリングコメント</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($planDetails as $detail): ?>
                                            <?php $monitoring = $monitoringDetails[$detail['id']] ?? null; ?>
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
                                                    <?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '' ?>
                                                </td>
                                                <td>
                                                    <?php if ($monitoring && $monitoring['achievement_status']): ?>
                                                        <?php
                                                        $statusClass = '';
                                                        $statusText = '';
                                                        switch ($monitoring['achievement_status']) {
                                                            case '達成':
                                                                $statusClass = 'achievement-achieved';
                                                                $statusText = '✓ 達成';
                                                                break;
                                                            case '一部達成':
                                                                $statusClass = 'achievement-progressing';
                                                                $statusText = '△ 一部達成';
                                                                break;
                                                            case '未達成':
                                                                $statusClass = 'achievement-not-achieved';
                                                                $statusText = '× 未達成';
                                                                break;
                                                            default:
                                                                $statusClass = 'achievement-progressing';
                                                                $statusText = htmlspecialchars($monitoring['achievement_status']);
                                                        }
                                                        ?>
                                                        <span class="achievement-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $monitoring ? nl2br(htmlspecialchars($monitoring['monitoring_comment'] ?: '')) : '' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- 総合コメント -->
                        <?php if ($monitoringData['overall_comment']): ?>
                            <div class="section-title">総合コメント</div>
                            <div class="info-item">
                                <div class="value"><?= nl2br(htmlspecialchars($monitoringData['overall_comment'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 保護者確認 -->
                        <div class="section-title">保護者確認</div>
                        <?php
                        $guardianConfirmed = $monitoringData['guardian_confirmed'] ?? 0;
                        $guardianConfirmedAt = $monitoringData['guardian_confirmed_at'] ?? null;
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
                                <p>このモニタリング表の内容を確認しました。</p>
                                <button onclick="confirmMonitoring(<?= $selectedMonitoringId ?>)" class="btn btn-primary" id="confirmBtn">
                                    ✓ 内容を確認しました
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>📊 提出済みのモニタリング表はまだありません</h3>
                        <p>スタッフがモニタリング表を作成・提出すると、ここに表示されます。</p>
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
        function confirmMonitoring(monitoringId) {
            if (!confirm('このモニタリング表の内容を確認しましたか？\n確認後は取り消せません。')) {
                return;
            }

            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.textContent = '処理中...';

            fetch('monitoring_confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ monitoring_id: monitoringId })
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
