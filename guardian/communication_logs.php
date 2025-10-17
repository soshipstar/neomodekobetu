<?php
/**
 * 保護者用 - 連絡帳一覧・検索ページ
 * 検索、フィルタリング、統計機能付き
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// 保護者でない場合は適切なページへリダイレクト
if ($_SESSION['user_type'] !== 'guardian') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 検索パラメータを取得
$selectedStudentId = $_GET['student_id'] ?? '';
$searchKeyword = $_GET['keyword'] ?? '';
$searchStartDate = $_GET['start_date'] ?? '';
$searchEndDate = $_GET['end_date'] ?? '';
$searchDomain = $_GET['domain'] ?? '';

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
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
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

// 生徒フィルター
if (!empty($selectedStudentId)) {
    $sql .= " AND s.id = ?";
    $params[] = $selectedStudentId;
}

// キーワード検索
if (!empty($searchKeyword)) {
    $sql .= " AND (inote.integrated_content LIKE ? OR dr.activity_name LIKE ? OR dr.common_activity LIKE ?)";
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
    $params[] = '%' . $searchKeyword . '%';
}

// 期間検索（開始日）
if (!empty($searchStartDate)) {
    $sql .= " AND dr.record_date >= ?";
    $params[] = $searchStartDate;
}

// 期間検索（終了日）
if (!empty($searchEndDate)) {
    $sql .= " AND dr.record_date <= ?";
    $params[] = $searchEndDate;
}

// 領域フィルター
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

// 領域別カウント
foreach ($domainLabels as $key => $label) {
    $stats['domain_counts'][$key] = 0;
}

foreach ($notes as $note) {
    // 領域カウント
    if (!empty($note['domain1'])) {
        $stats['domain_counts'][$note['domain1']]++;
    }
    if (!empty($note['domain2']) && $note['domain2'] !== $note['domain1']) {
        $stats['domain_counts'][$note['domain2']]++;
    }

    // 月別カウント
    $month = date('Y-m', strtotime($note['record_date']));
    if (!isset($stats['monthly_counts'][$month])) {
        $stats['monthly_counts'][$month] = 0;
    }
    $stats['monthly_counts'][$month]++;
}

// 月別カウントをソート
krsort($stats['monthly_counts']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>連絡帳一覧 - 保護者ページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .content-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .search-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 13px;
            color: #666;
        }
        .domain-chart {
            margin-top: 20px;
        }
        .domain-bar {
            margin-bottom: 15px;
        }
        .domain-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .domain-bar-bg {
            background: #e0e0e0;
            height: 24px;
            border-radius: 12px;
            overflow: hidden;
        }
        .domain-bar-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            border-radius: 12px;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        .note-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .note-title {
            flex: 1;
        }
        .activity-name {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .student-name {
            color: #666;
            font-size: 14px;
        }
        .note-meta {
            text-align: right;
            color: #666;
            font-size: 14px;
        }
        .note-date {
            font-weight: bold;
            color: #333;
        }
        .note-badges {
            display: flex;
            gap: 5px;
            margin-top: 5px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .domain-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .note-content {
            color: #333;
            line-height: 1.8;
            white-space: pre-wrap;
            font-size: 15px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h2 {
            margin-bottom: 10px;
            color: #333;
        }
        .search-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #1976D2;
        }
        .print-btn {
            background: #28a745;
            color: white;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .header-actions, .search-form, .search-actions, .stats-grid, .domain-chart, .print-btn {
                display: none !important;
            }
            .content-box {
                box-shadow: none;
                page-break-inside: avoid;
            }
            .note-item {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 連絡帳一覧・検索</h1>
            <div class="header-actions">
                <span style="color: #666; font-size: 14px;">
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>さん（保護者）
                </span>
                <a href="dashboard.php" class="btn btn-secondary">ダッシュボードへ</a>
                <a href="/logout.php" class="btn btn-danger">ログアウト</a>
            </div>
        </div>

        <!-- 検索フォーム -->
        <div class="content-box">
            <h2 class="section-title">🔍 検索・フィルター</h2>
            <form method="GET" action="communication_logs.php">
                <div class="search-form">
                    <div class="form-group">
                        <label>お子様</label>
                        <select name="student_id">
                            <option value="">すべて</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $selectedStudentId == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['student_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>期間（開始）</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($searchStartDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>期間（終了）</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($searchEndDate); ?>">
                    </div>
                    <div class="form-group">
                        <label>領域</label>
                        <select name="domain">
                            <option value="">すべて</option>
                            <?php foreach ($domainLabels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $searchDomain === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>キーワード</label>
                        <input type="text" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>" placeholder="活動内容や様子で検索">
                    </div>
                </div>
                <div class="search-actions">
                    <a href="communication_logs.php" class="btn btn-secondary">クリア</a>
                    <button type="submit" class="btn btn-primary">検索</button>
                    <button type="button" onclick="window.print()" class="btn print-btn">印刷</button>
                </div>
            </form>
        </div>

        <!-- 統計情報 -->
        <?php if ($stats['total_count'] > 0): ?>
        <div class="content-box">
            <h2 class="section-title">📊 統計情報</h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_count']; ?></div>
                    <div class="stat-label">件の記録</div>
                </div>
                <?php if (!empty($stats['monthly_counts'])): ?>
                    <?php $latestMonth = array_key_first($stats['monthly_counts']); ?>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['monthly_counts'][$latestMonth]; ?></div>
                        <div class="stat-label">今月の記録</div>
                    </div>
                <?php endif; ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_unique(array_column($notes, 'record_date'))); ?></div>
                    <div class="stat-label">活動日数</div>
                </div>
            </div>

            <div class="domain-chart">
                <h3 style="margin-bottom: 15px; color: #333; font-size: 16px;">支援領域別の記録数</h3>
                <?php
                $maxCount = max(array_values($stats['domain_counts']));
                foreach ($domainLabels as $key => $label):
                    $count = $stats['domain_counts'][$key];
                    $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                ?>
                    <div class="domain-bar">
                        <div class="domain-bar-label">
                            <span><?php echo $label; ?></span>
                            <span><strong><?php echo $count; ?>件</strong></span>
                        </div>
                        <div class="domain-bar-bg">
                            <div class="domain-bar-fill" style="width: <?php echo $percentage; ?>%">
                                <?php if ($percentage > 15): ?><?php echo $count; ?>件<?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 検索結果 -->
        <div class="content-box">
            <h2 class="section-title">📝 連絡帳一覧</h2>

            <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($selectedStudentId) || !empty($searchDomain)): ?>
                <div class="search-info">
                    <strong>検索結果:</strong> <?php echo count($notes); ?>件の連絡帳が見つかりました
                </div>
            <?php endif; ?>

            <?php if (empty($notes)): ?>
                <div class="empty-state">
                    <?php if (!empty($searchKeyword) || !empty($searchStartDate) || !empty($searchEndDate) || !empty($selectedStudentId) || !empty($searchDomain)): ?>
                        <h2>検索条件に一致する連絡帳が見つかりませんでした</h2>
                        <p>検索条件を変更してお試しください</p>
                    <?php else: ?>
                        <h2>まだ連絡帳が送信されていません</h2>
                        <p>スタッフから連絡帳が送信されるとここに表示されます</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note-item">
                        <div class="note-header">
                            <div class="note-title">
                                <div class="activity-name"><?php echo htmlspecialchars($note['activity_name']); ?></div>
                                <div class="student-name"><?php echo htmlspecialchars($note['student_name']); ?>（<?php echo getGradeLabel($note['grade_level']); ?>）</div>
                            </div>
                            <div class="note-meta">
                                <div class="note-date">
                                    <?php echo date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($note['record_date']))] . '）', strtotime($note['record_date'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #999; margin-top: 3px;">
                                    送信: <?php echo date('m/d H:i', strtotime($note['sent_at'])); ?>
                                </div>
                                <div class="note-badges">
                                    <?php if (!empty($note['domain1'])): ?>
                                        <span class="domain-badge"><?php echo $domainLabels[$note['domain1']] ?? ''; ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($note['domain2']) && $note['domain2'] !== $note['domain1']): ?>
                                        <span class="domain-badge"><?php echo $domainLabels[$note['domain2']] ?? ''; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="note-content"><?php echo nl2br(htmlspecialchars($note['integrated_content'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
