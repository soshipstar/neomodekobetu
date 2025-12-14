<?php
/**
 * 保護者用かけはし PDF出力
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

$studentId = $_GET['student_id'] ?? null;
$periodId = $_GET['period_id'] ?? null;

if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒IDまたは期間IDが指定されていません。';
    header('Location: kakehashi.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 生徒が保護者の子どもであることを確認
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND guardian_id = ?");
$stmt->execute([$studentId, $guardianId]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = '指定された生徒が見つかりません。';
    header('Location: kakehashi.php');
    exit;
}

// 期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ? AND student_id = ?");
$stmt->execute([$periodId, $studentId]);
$period = $stmt->fetch();

if (!$period) {
    $_SESSION['error'] = '指定された期間が見つかりません。';
    header('Location: kakehashi.php');
    exit;
}

// かけはしデータを取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_guardian WHERE student_id = ? AND period_id = ?");
$stmt->execute([$studentId, $periodId]);
$kakehashiData = $stmt->fetch();

// 非表示のかけはしはアクセス拒否
if ($kakehashiData && $kakehashiData['is_hidden']) {
    $_SESSION['error'] = 'このかけはしにはアクセスできません。';
    header('Location: kakehashi.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <meta charset="UTF-8">
    <title>かけはし（保護者） - <?= htmlspecialchars($student['student_name']) ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            .no-print {
                display: none;
            }
        }

        body {
            font-family: 'MS Gothic', 'MS Mincho', monospace;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: var(--spacing-lg);
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18pt;
            margin: 0 0 10px 0;
            color: #333;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 10pt;
        }

        .meta-item {
            margin-right: 15px;
        }

        .meta-label {
            font-weight: bold;
            display: inline;
        }

        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .section-content {
            padding: var(--spacing-md);
            border: 1px solid #ccc;
            min-height: 60px;
            white-space: pre-wrap;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .domains-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-top: 10px;
        }

        .domain-item {
            page-break-inside: avoid;
        }

        .domain-label {
            font-weight: bold;
            background: #e2e8f0;
            padding: 6px 10px;
            margin-bottom: 5px;
            border-radius: 4px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 30px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14pt;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .print-button:hover {
            background: #2563eb;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
        }

        .status-submitted {
            background: #10b981;
            color: white;
        }

        .status-draft {
            background: #f59e0b;
            color: white;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ PDF印刷</button>

    <div class="header">
        <h1>🌉 かけはし（保護者入力）</h1>
    </div>

    <div class="meta-info">
        <div class="meta-item">
            <span class="meta-label">児童氏名：</span>
            <span><?= htmlspecialchars($student['student_name']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">📋 個別支援計画：</span>
            <span><?= getIndividualSupportPlanStartMonth($period) ?>開始分</span>
        </div>
    </div>

    <div class="meta-info">
        <div class="meta-item">
            <span class="meta-label">対象期間：</span>
            <span><?= date('Y年m月d日', strtotime($period['start_date'])) ?> ～ <?= date('Y年m月d日', strtotime($period['end_date'])) ?></span>
        </div>
    </div>

    <!-- 本人の願い -->
    <div class="section">
        <div class="section-title">💫 本人の願い</div>
        <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['student_wish']) : '（未入力）' ?></div>
    </div>

    <!-- 家庭での願い -->
    <div class="section">
        <div class="section-title">🏠 家庭での願い</div>
        <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['home_challenges']) : '（未入力）' ?></div>
    </div>

    <!-- 目標設定 -->
    <div class="section">
        <div class="section-title">🎯 目標設定</div>
        <div class="domain-item">
            <div class="domain-label">短期目標（6か月）</div>
            <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['short_term_goal']) : '（未入力）' ?></div>
        </div>
        <br>
        <div class="domain-item">
            <div class="domain-label">長期目標（1年以上）</div>
            <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['long_term_goal']) : '（未入力）' ?></div>
        </div>
    </div>

    <!-- 五領域の課題 -->
    <div class="section">
        <div class="section-title">🌟 五領域の課題</div>
        <div class="domains-grid">
            <div class="domain-item">
                <div class="domain-label">健康・生活</div>
                <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['domain_health_life']) : '（未入力）' ?></div>
            </div>
            <div class="domain-item">
                <div class="domain-label">運動・感覚</div>
                <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['domain_motor_sensory']) : '（未入力）' ?></div>
            </div>
            <div class="domain-item">
                <div class="domain-label">認知・行動</div>
                <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['domain_cognitive_behavior']) : '（未入力）' ?></div>
            </div>
            <div class="domain-item">
                <div class="domain-label">言語・コミュニケーション</div>
                <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['domain_language_communication']) : '（未入力）' ?></div>
            </div>
            <div class="domain-item">
                <div class="domain-label">人間関係・社会性</div>
                <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['domain_social_relations']) : '（未入力）' ?></div>
            </div>
        </div>
    </div>

    <!-- その他の課題 -->
    <div class="section">
        <div class="section-title">📌 その他の課題</div>
        <div class="section-content"><?= $kakehashiData ? htmlspecialchars($kakehashiData['other_challenges']) : '（未入力）' ?></div>
    </div>

</body>
</html>
