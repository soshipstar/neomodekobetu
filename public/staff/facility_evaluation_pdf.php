<?php
/**
 * 事業所評価PDF出力ページ
 * 印刷用に最適化されたHTMLを出力（ブラウザのPDF印刷機能を使用）
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();

$periodId = $_GET['period_id'] ?? null;
if (!$periodId) {
    die('評価期間が指定されていません。');
}

// 評価期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM facility_evaluation_periods WHERE id = ?");
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period) {
    die('評価期間が見つかりません。');
}

// 質問と集計結果を取得
$stmt = $pdo->prepare("
    SELECT q.*, s.yes_count, s.neutral_count, s.no_count, s.unknown_count, s.yes_percentage, s.comment_summary, s.facility_comment
    FROM facility_evaluation_questions q
    LEFT JOIN facility_evaluation_summaries s ON q.id = s.question_id AND s.period_id = ?
    WHERE q.is_active = 1
    ORDER BY q.question_type, q.sort_order, q.question_number
");
$stmt->execute([$periodId]);
$allQuestions = $stmt->fetchAll();

// タイプ・カテゴリ別にグループ化
$guardianQuestions = [];
$staffQuestions = [];
foreach ($allQuestions as $q) {
    if ($q['question_type'] === 'guardian') {
        $guardianQuestions[$q['category']][] = $q;
    } else {
        $staffQuestions[$q['category']][] = $q;
    }
}

// 回答数を取得
$guardianCount = $pdo->prepare("SELECT COUNT(*) FROM facility_guardian_evaluations WHERE period_id = ? AND is_submitted = 1");
$guardianCount->execute([$periodId]);
$guardianRespondents = $guardianCount->fetchColumn();

$staffCount = $pdo->prepare("SELECT COUNT(*) FROM facility_staff_evaluations WHERE period_id = ? AND is_submitted = 1");
$staffCount->execute([$periodId]);
$staffRespondents = $staffCount->fetchColumn();

// 教室情報を取得
$classroomId = $_SESSION['classroom_id'] ?? null;
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($period['title']); ?> - 事業所評価結果</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #5C6ACF;
        }

        .header h1 {
            font-size: 18pt;
            color: #5C6ACF;
            margin-bottom: 5px;
        }

        .header .subtitle {
            font-size: 11pt;
            color: #666;
        }

        .header .facility {
            font-size: 10pt;
            color: #666;
            margin-top: 8px;
        }

        .summary-box {
            background: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .summary-box h2 {
            font-size: 12pt;
            margin-bottom: 10px;
            color: #5C6ACF;
        }

        .summary-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 20pt;
            font-weight: bold;
            color: #5C6ACF;
        }

        .stat-label {
            font-size: 9pt;
            color: #666;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #fff;
            background: #5C6ACF;
            padding: 8px 15px;
            margin-bottom: 0;
        }

        .category-title {
            font-size: 11pt;
            font-weight: bold;
            color: #5C6ACF;
            background: #e8e8ff;
            padding: 6px 15px;
            border-left: 4px solid #5C6ACF;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5f5f5;
            font-weight: 600;
            font-size: 9pt;
        }

        .question-num {
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: #5C6ACF;
        }

        .question-text {
            width: 40%;
        }

        .result-cell {
            width: 15%;
            text-align: center;
        }

        .comment-cell {
            width: 30%;
            font-size: 9pt;
        }

        .bar-container {
            height: 16px;
            display: flex;
            border-radius: 2px;
            overflow: hidden;
            background: #eee;
            margin-bottom: 4px;
        }

        .bar-yes { background: #34c759; }
        .bar-neutral { background: #ff9500; }
        .bar-no { background: #ff3b30; }

        .result-text {
            font-size: 8pt;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }

            .section {
                page-break-inside: avoid;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #5C6ACF;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .print-button:hover {
            background: #4a59bd;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        PDF印刷
    </button>

    <div class="header">
        <h1><?php echo htmlspecialchars($period['title']); ?></h1>
        <div class="subtitle">事業所における自己評価結果（公表）</div>
        <?php if ($classroom): ?>
            <div class="facility"><?php echo htmlspecialchars($classroom['name']); ?></div>
        <?php endif; ?>
    </div>

    <div class="summary-box">
        <h2>回答状況</h2>
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $guardianRespondents; ?></div>
                <div class="stat-label">保護者回答数</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $staffRespondents; ?></div>
                <div class="stat-label">職員回答数</div>
            </div>
        </div>
    </div>

    <!-- 保護者評価結果 -->
    <div class="section">
        <div class="section-title">保護者等からの事業所評価の集計結果（公表）</div>

        <?php foreach ($guardianQuestions as $category => $questions): ?>
            <div class="category-title"><?php echo htmlspecialchars($category); ?></div>
            <table>
                <thead>
                    <tr>
                        <th class="question-num">No</th>
                        <th class="question-text">チェック項目</th>
                        <th class="result-cell">はい</th>
                        <th class="result-cell">どちらとも</th>
                        <th class="result-cell">いいえ</th>
                        <th class="comment-cell">ご意見・事業所コメント</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q):
                        $total = ($q['yes_count'] ?? 0) + ($q['neutral_count'] ?? 0) + ($q['no_count'] ?? 0);
                        $yesPercent = $total > 0 ? round(($q['yes_count'] / $total) * 100) : 0;
                        $neutralPercent = $total > 0 ? round(($q['neutral_count'] / $total) * 100) : 0;
                        $noPercent = $total > 0 ? round(($q['no_count'] / $total) * 100) : 0;
                    ?>
                        <tr>
                            <td class="question-num"><?php echo $q['question_number']; ?></td>
                            <td class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></td>
                            <td class="result-cell">
                                <?php echo $q['yes_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $yesPercent; ?>%)</div>
                            </td>
                            <td class="result-cell">
                                <?php echo $q['neutral_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $neutralPercent; ?>%)</div>
                            </td>
                            <td class="result-cell">
                                <?php echo $q['no_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $noPercent; ?>%)</div>
                            </td>
                            <td class="comment-cell">
                                <?php if ($q['comment_summary']): ?>
                                    <strong>意見:</strong> <?php echo nl2br(htmlspecialchars($q['comment_summary'])); ?><br>
                                <?php endif; ?>
                                <?php if ($q['facility_comment']): ?>
                                    <strong>事業所:</strong> <?php echo nl2br(htmlspecialchars($q['facility_comment'])); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>

    <!-- スタッフ自己評価結果 -->
    <div class="section">
        <div class="section-title">事業所における自己評価結果（公表）</div>

        <?php foreach ($staffQuestions as $category => $questions): ?>
            <div class="category-title"><?php echo htmlspecialchars($category); ?></div>
            <table>
                <thead>
                    <tr>
                        <th class="question-num">No</th>
                        <th class="question-text">チェック項目</th>
                        <th class="result-cell">はい</th>
                        <th class="result-cell">どちらとも</th>
                        <th class="result-cell">いいえ</th>
                        <th class="comment-cell">工夫・改善計画</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q):
                        $total = ($q['yes_count'] ?? 0) + ($q['neutral_count'] ?? 0) + ($q['no_count'] ?? 0);
                        $yesPercent = $total > 0 ? round(($q['yes_count'] / $total) * 100) : 0;
                        $neutralPercent = $total > 0 ? round(($q['neutral_count'] / $total) * 100) : 0;
                        $noPercent = $total > 0 ? round(($q['no_count'] / $total) * 100) : 0;
                    ?>
                        <tr>
                            <td class="question-num"><?php echo $q['question_number']; ?></td>
                            <td class="question-text"><?php echo htmlspecialchars($q['question_text']); ?></td>
                            <td class="result-cell">
                                <?php echo $q['yes_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $yesPercent; ?>%)</div>
                            </td>
                            <td class="result-cell">
                                <?php echo $q['neutral_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $neutralPercent; ?>%)</div>
                            </td>
                            <td class="result-cell">
                                <?php echo $q['no_count'] ?? 0; ?>
                                <div class="result-text">(<?php echo $noPercent; ?>%)</div>
                            </td>
                            <td class="comment-cell">
                                <?php if ($q['facility_comment']): ?>
                                    <?php echo nl2br(htmlspecialchars($q['facility_comment'])); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        <p>公表日: <?php echo date('Y年n月j日'); ?></p>
        <?php if ($classroom): ?>
            <p><?php echo htmlspecialchars($classroom['name']); ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
