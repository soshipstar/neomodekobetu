<?php
/**
 * スタッフ用 保護者入力かけはし確認ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();

// 全生徒を取得
$stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
$students = $stmt->fetchAll();

// 現在有効な期間を取得
$stmt = $pdo->query("
    SELECT * FROM kakehashi_periods
    WHERE is_active = 1
    ORDER BY submission_deadline DESC
");
$activePeriods = $stmt->fetchAll();

// 選択された生徒と期間
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedPeriodId = $_GET['period_id'] ?? ($activePeriods[0]['id'] ?? null);

// 保護者入力かけはしデータを取得
$kakehashiList = [];
if ($selectedPeriodId) {
    $sql = "
        SELECT
            kg.*,
            s.student_name,
            s.birth_date,
            u.full_name as guardian_name
        FROM kakehashi_guardian kg
        INNER JOIN students s ON kg.student_id = s.id
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE kg.period_id = ?
    ";

    if ($selectedStudentId) {
        $sql .= " AND kg.student_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedPeriodId, $selectedStudentId]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedPeriodId]);
    }

    $kakehashiList = $stmt->fetchAll();
}

// 選択された期間の情報
$selectedPeriod = null;
if ($selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$selectedPeriodId]);
    $selectedPeriod = $stmt->fetch();
}

// 提出状況の統計
$stats = [
    'total' => 0,
    'submitted' => 0,
    'draft' => 0
];

if ($selectedPeriodId) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN is_submitted = 1 THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN is_submitted = 0 THEN 1 ELSE 0 END) as draft
        FROM kakehashi_guardian
        WHERE period_id = ?
    ");
    $stmt->execute([$selectedPeriodId]);
    $statsData = $stmt->fetch();
    $stats = [
        'total' => $statsData['total'] ?? 0,
        'submitted' => $statsData['submitted'] ?? 0,
        'draft' => $statsData['draft'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保護者入力かけはし確認 - スタッフページ</title>
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

        .filter-area {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            align-items: flex-end;
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

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
        }

        .stats-area {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.submitted {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .stat-card.draft {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .kakehashi-card {
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .kakehashi-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .student-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-submitted {
            background: #28a745;
            color: white;
        }

        .status-draft {
            background: #ffc107;
            color: #856404;
        }

        .card-body {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .field-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .field-group.full-width {
            grid-column: 1 / -1;
        }

        .field-label {
            font-weight: 600;
            color: #667eea;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .field-content {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view {
            background: #007bff;
            color: white;
        }

        .btn-view:hover {
            background: #0056b3;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .header, .filter-area, .nav-links, .btn {
                display: none;
            }

            .container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 保護者入力かけはし確認</h1>
            <div class="nav-links">
                <a href="kakehashi_staff.php">✏️ スタッフ入力</a>
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
            <!-- フィルターエリア -->
            <div class="filter-area">
                <div class="form-group">
                    <label>提出期間を選択</label>
                    <select id="periodSelect" onchange="changeFilter()">
                        <?php foreach ($activePeriods as $period): ?>
                            <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($period['period_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>生徒を絞り込み（任意）</label>
                    <select id="studentSelect" onchange="changeFilter()">
                        <option value="">全ての生徒</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 統計エリア -->
            <?php if ($selectedPeriod): ?>
                <div class="stats-area">
                    <div class="stat-card total">
                        <div class="stat-number"><?= $stats['total'] ?></div>
                        <div class="stat-label">総入力数</div>
                    </div>
                    <div class="stat-card submitted">
                        <div class="stat-number"><?= $stats['submitted'] ?></div>
                        <div class="stat-label">提出済み</div>
                    </div>
                    <div class="stat-card draft">
                        <div class="stat-number"><?= $stats['draft'] ?></div>
                        <div class="stat-label">下書き</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- かけはしリスト -->
            <?php if (empty($kakehashiList)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>保護者入力のかけはしがありません</p>
                </div>
            <?php else: ?>
                <?php foreach ($kakehashiList as $kakehashi): ?>
                    <div class="kakehashi-card">
                        <div class="card-header">
                            <div>
                                <div class="student-name"><?= htmlspecialchars($kakehashi['student_name']) ?></div>
                                <small style="color: #666;">保護者: <?= htmlspecialchars($kakehashi['guardian_name'] ?? '未設定') ?></small>
                            </div>
                            <div>
                                <?php if ($kakehashi['is_submitted']): ?>
                                    <span class="status-badge status-submitted">提出済み</span>
                                    <br><small style="color: #666;"><?= date('Y/m/d H:i', strtotime($kakehashi['submitted_at'])) ?></small>
                                <?php else: ?>
                                    <span class="status-badge status-draft">下書き</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="field-group full-width">
                                <div class="field-label">🏠 家庭での課題</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['home_challenges'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🎯 短期目標（6か月）</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['short_term_goal'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🎯 長期目標（1年以上）</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['long_term_goal'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🌟 健康・生活</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['domain_health_life'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🌟 運動・感覚</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['domain_motor_sensory'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🌟 認知・行動</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['domain_cognitive_behavior'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🌟 言語・コミュニケーション</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['domain_language_communication'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group">
                                <div class="field-label">🌟 人間関係・社会性</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['domain_social_relations'] ?: '（未入力）')) ?></div>
                            </div>

                            <div class="field-group full-width">
                                <div class="field-label">📌 その他の課題</div>
                                <div class="field-content"><?= nl2br(htmlspecialchars($kakehashi['other_challenges'] ?: '（未入力）')) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeFilter() {
            const periodId = document.getElementById('periodSelect').value;
            const studentId = document.getElementById('studentSelect').value;
            let url = 'kakehashi_guardian_view.php?period_id=' + periodId;
            if (studentId) {
                url += '&student_id=' + studentId;
            }
            window.location.href = url;
        }
    </script>
</body>
</html>
