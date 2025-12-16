<?php
/**
 * 個別支援計画の根拠 PDF出力
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

$pdo = getDbConnection();

// 計画書を取得
$stmt = $pdo->prepare("
    SELECT isp.*, s.student_name as current_student_name
    FROM individual_support_plans isp
    JOIN students s ON isp.student_id = s.id
    WHERE isp.id = ?
");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = '計画書が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

$studentId = $plan['student_id'];
$studentName = $plan['student_name'] ?: $plan['current_student_name'];

// 計画の作成日に近いかけはし期間を探す
$planDate = new DateTime($plan['created_date']);
$stmt = $pdo->prepare("
    SELECT kp.*
    FROM kakehashi_periods kp
    WHERE kp.student_id = ?
    AND kp.submission_deadline <= ?
    ORDER BY kp.submission_deadline DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$period = $stmt->fetch();

// 保護者かけはしデータを取得
$guardianKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $guardianKakehashi = $stmt->fetch();
}

// スタッフかけはしデータを取得
$staffKakehashi = null;
if ($period) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ?
        ORDER BY submitted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId, $period['id']]);
    $staffKakehashi = $stmt->fetch();
}

// 直近のモニタリングを取得
$stmt = $pdo->prepare("
    SELECT mr.*, GROUP_CONCAT(
        CONCAT(
            COALESCE(ispd.category, ''), '|',
            COALESCE(ispd.sub_category, ''), '|',
            COALESCE(md.achievement_status, ''), '|',
            COALESCE(md.monitoring_comment, '')
        ) SEPARATOR '###'
    ) as monitoring_items
    FROM monitoring_records mr
    LEFT JOIN monitoring_details md ON mr.id = md.monitoring_id
    LEFT JOIN individual_support_plan_details ispd ON md.plan_detail_id = ispd.id
    WHERE mr.student_id = ?
    AND mr.monitoring_date <= ?
    GROUP BY mr.id
    ORDER BY mr.monitoring_date DESC
    LIMIT 1
");
$stmt->execute([$studentId, $planDate->format('Y-m-d')]);
$monitoring = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>個別支援計画の根拠 - <?= htmlspecialchars($studentName) ?></title>
    <style>
        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Meiryo', 'MS Gothic', sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            padding: 15px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 16pt;
            margin-bottom: 8px;
        }

        .header-meta {
            font-size: 9pt;
            color: #555;
        }

        .header-meta span {
            margin: 0 10px;
        }

        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background: #e8e8e8;
            padding: 5px 10px;
            margin-bottom: 8px;
            border-left: 3px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
            font-size: 9pt;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
            width: 33.33%;
        }

        th.guardian { background: #fce4ec; color: #880e4f; }
        th.staff { background: #e8f5e9; color: #1b5e20; }
        th.plan { background: #e3f2fd; color: #0d47a1; }

        .label-cell {
            background: #f9f9f9;
            font-weight: bold;
            width: 120px;
            font-size: 8pt;
            color: #333;
        }

        .content-cell {
            word-break: break-word;
        }

        .empty {
            color: #999;
            font-style: italic;
        }

        .overall-content {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 10px;
            line-height: 1.7;
            font-size: 9pt;
        }

        .print-button {
            position: fixed;
            top: 15px;
            right: 15px;
            background: #1976d2;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 13px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .print-button:hover {
            background: #1565c0;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ccc;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }

        .goal-label {
            font-weight: bold;
            margin: 8px 0 5px 0;
            font-size: 9pt;
        }
    </style>
</head>
<body>

<button class="print-button no-print" onclick="window.print()">PDF出力 / 印刷</button>

<div class="header">
    <h1>個別支援計画の根拠</h1>
    <div class="header-meta">
        <span>生徒名: <?= htmlspecialchars($studentName) ?></span>
        <span>計画作成日: <?= date('Y年m月d日', strtotime($plan['created_date'])) ?></span>
        <?php if ($period): ?>
            <span>根拠期間: <?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?> 期限</span>
        <?php endif; ?>
    </div>
</div>

<!-- 目標の比較 -->
<div class="section">
    <div class="section-title">目標の比較と整合性</div>

    <p class="goal-label">【短期目標】</p>
    <table>
        <tr>
            <th class="guardian">保護者の目標</th>
            <th class="staff">スタッフの目標</th>
            <th class="plan">計画書の目標</th>
        </tr>
        <tr>
            <td class="content-cell <?= empty($guardianKakehashi['short_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['short_term_goal'] ?? '（データなし）')) ?></td>
            <td class="content-cell <?= empty($staffKakehashi['short_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['short_term_goal'] ?? '（データなし）')) ?></td>
            <td class="content-cell <?= empty($plan['short_term_goal_text']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($plan['short_term_goal_text'] ?? '（未設定）')) ?></td>
        </tr>
    </table>

    <p class="goal-label">【長期目標】</p>
    <table>
        <tr>
            <th class="guardian">保護者の目標</th>
            <th class="staff">スタッフの目標</th>
            <th class="plan">計画書の目標</th>
        </tr>
        <tr>
            <td class="content-cell <?= empty($guardianKakehashi['long_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['long_term_goal'] ?? '（データなし）')) ?></td>
            <td class="content-cell <?= empty($staffKakehashi['long_term_goal']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['long_term_goal'] ?? '（データなし）')) ?></td>
            <td class="content-cell <?= empty($plan['long_term_goal_text']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($plan['long_term_goal_text'] ?? '（未設定）')) ?></td>
        </tr>
    </table>
</div>

<!-- 保護者かけはし -->
<?php if ($guardianKakehashi): ?>
<div class="section">
    <div class="section-title">保護者からのかけはし（<?= $guardianKakehashi['submitted_at'] ? date('Y年m月d日提出', strtotime($guardianKakehashi['submitted_at'])) : '未提出' ?>）</div>
    <table>
        <tr>
            <td class="label-cell">本人の願い</td>
            <td class="content-cell <?= empty($guardianKakehashi['student_wish']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['student_wish'] ?: '（未記入）')) ?></td>
            <td class="label-cell">家庭での課題</td>
            <td class="content-cell <?= empty($guardianKakehashi['home_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['home_challenges'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">健康・生活</td>
            <td class="content-cell <?= empty($guardianKakehashi['domain_health_life']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_health_life'] ?: '（未記入）')) ?></td>
            <td class="label-cell">運動・感覚</td>
            <td class="content-cell <?= empty($guardianKakehashi['domain_motor_sensory']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_motor_sensory'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">認知・行動</td>
            <td class="content-cell <?= empty($guardianKakehashi['domain_cognitive_behavior']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_cognitive_behavior'] ?: '（未記入）')) ?></td>
            <td class="label-cell">言語・コミュニケーション</td>
            <td class="content-cell <?= empty($guardianKakehashi['domain_language_communication']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_language_communication'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">人間関係・社会性</td>
            <td class="content-cell <?= empty($guardianKakehashi['domain_social_relations']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['domain_social_relations'] ?: '（未記入）')) ?></td>
            <td class="label-cell">その他</td>
            <td class="content-cell <?= empty($guardianKakehashi['other_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($guardianKakehashi['other_challenges'] ?: '（未記入）')) ?></td>
        </tr>
    </table>
</div>
<?php endif; ?>

<!-- スタッフかけはし -->
<?php if ($staffKakehashi): ?>
<div class="section">
    <div class="section-title">スタッフからのかけはし（<?= $staffKakehashi['submitted_at'] ? date('Y年m月d日提出', strtotime($staffKakehashi['submitted_at'])) : '未提出' ?>）</div>
    <table>
        <tr>
            <td class="label-cell">本人の願い</td>
            <td class="content-cell" colspan="3" style="<?= empty($staffKakehashi['student_wish']) ? 'color:#999;font-style:italic;' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['student_wish'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">健康・生活</td>
            <td class="content-cell <?= empty($staffKakehashi['domain_health_life']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_health_life'] ?: '（未記入）')) ?></td>
            <td class="label-cell">運動・感覚</td>
            <td class="content-cell <?= empty($staffKakehashi['domain_motor_sensory']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_motor_sensory'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">認知・行動</td>
            <td class="content-cell <?= empty($staffKakehashi['domain_cognitive_behavior']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_cognitive_behavior'] ?: '（未記入）')) ?></td>
            <td class="label-cell">言語・コミュニケーション</td>
            <td class="content-cell <?= empty($staffKakehashi['domain_language_communication']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_language_communication'] ?: '（未記入）')) ?></td>
        </tr>
        <tr>
            <td class="label-cell">人間関係・社会性</td>
            <td class="content-cell <?= empty($staffKakehashi['domain_social_relations']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['domain_social_relations'] ?: '（未記入）')) ?></td>
            <td class="label-cell">その他</td>
            <td class="content-cell <?= empty($staffKakehashi['other_challenges']) ? 'empty' : '' ?>"><?= htmlspecialchars(trim($staffKakehashi['other_challenges'] ?: '（未記入）')) ?></td>
        </tr>
    </table>
</div>
<?php endif; ?>

<!-- モニタリング情報 -->
<?php if ($monitoring): ?>
<div class="section">
    <div class="section-title">直近のモニタリング情報（<?= date('Y年m月d日実施', strtotime($monitoring['monitoring_date'])) ?>）</div>
    <table>
        <tr>
            <td class="label-cell">総合所見</td>
            <td class="content-cell" colspan="3" style="<?= empty($monitoring['overall_comment']) ? 'color:#999;font-style:italic;' : '' ?>"><?= htmlspecialchars(trim($monitoring['overall_comment'] ?: '（未記入）')) ?></td>
        </tr>
        <?php
        if ($monitoring['monitoring_items']) {
            $items = explode('###', $monitoring['monitoring_items']);
            $validItems = [];
            foreach ($items as $item) {
                $parts = explode('|', $item);
                if (count($parts) >= 4 && !empty($parts[0])) {
                    $validItems[] = $parts;
                }
            }
            for ($i = 0; $i < count($validItems); $i += 2) {
                echo '<tr>';
                for ($j = $i; $j < min($i + 2, count($validItems)); $j++) {
                    $parts = $validItems[$j];
                    $statusLabel = match($parts[2]) {
                        '達成' => '達成',
                        '継続' => '継続',
                        '未達成' => '未達成',
                        default => $parts[2] ?: '未評価'
                    };
                    $label = $parts[0] . '/' . $parts[1] . '（' . $statusLabel . '）';
                    echo '<td class="label-cell" style="font-size:7pt;">' . htmlspecialchars($label) . '</td>';
                    echo '<td class="content-cell' . (empty(trim($parts[3])) ? ' empty' : '') . '">' . htmlspecialchars(trim($parts[3]) ?: '（コメントなし）') . '</td>';
                }
                if (count($validItems) - $i === 1) {
                    echo '<td class="label-cell"></td><td class="content-cell"></td>';
                }
                echo '</tr>';
            }
        }
        ?>
    </table>
</div>
<?php endif; ?>

<!-- 全体所感 -->
<?php if (!empty($plan['basis_content'])): ?>
<div class="section">
    <div class="section-title">全体所感</div>
    <div class="overall-content"><?= nl2br(htmlspecialchars(trim($plan['basis_content']))) ?></div>
</div>
<?php endif; ?>

<div class="footer">
    出力日時: <?= date('Y年m月d日 H:i') ?>
</div>

</body>
</html>
