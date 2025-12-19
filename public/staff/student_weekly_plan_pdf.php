<?php
/**
 * 週間計画表 PDF出力（評価シート形式）
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

$studentId = $_GET['student_id'] ?? null;
$weekStartDate = $_GET['date'] ?? date('Y-m-d');

if (!$studentId) {
    $_SESSION['error'] = '生徒IDが指定されていません。';
    header('Location: student_weekly_plans.php');
    exit;
}

$pdo = getDbConnection();

// 生徒情報を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = '生徒が見つかりません。';
    header('Location: student_weekly_plans.php');
    exit;
}

// 週間計画を取得
$stmt = $pdo->prepare("
    SELECT
        id,
        weekly_goal,
        shared_goal,
        must_do,
        should_do,
        want_to_do,
        plan_data,
        created_at,
        updated_at
    FROM weekly_plans
    WHERE student_id = ? AND week_start_date = ?
");
$stmt->execute([$studentId, $weekStartDate]);
$weeklyPlan = $stmt->fetch();

$planData = $weeklyPlan ? json_decode($weeklyPlan['plan_data'], true) : [];

// 週の終了日と提出日を計算
$weekEndDate = date('Y-m-d', strtotime('+6 days', strtotime($weekStartDate)));
$submitDate = date('Y-m-d', strtotime('+7 days', strtotime($weekStartDate)));
$weekStartFormatted = date('n月j日', strtotime($weekStartDate));
$weekEndFormatted = date('n月j日', strtotime($weekEndDate));
$submitFormatted = date('n月j日', strtotime($submitDate));

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>週間計画表 - <?= htmlspecialchars($student['student_name']) ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }
            .no-print {
                display: none !important;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'MS Gothic', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            padding: 8mm;
            padding-top: 55px;
            background: #f5f5f5;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }
        }

        .control-panel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #4a90d9 0%, #357abd 100%);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .control-panel h3 {
            color: white;
            font-size: 13px;
        }

        .print-btn {
            background: #10b981;
            border: none;
            color: white;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            text-decoration: none;
        }

        .pdf-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 6mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media print {
            .pdf-container {
                box-shadow: none;
                padding: 0;
            }
        }

        .header {
            text-align: center;
            margin-bottom: 4mm;
            padding-bottom: 3mm;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 14pt;
            margin-bottom: 2mm;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
        }

        .header-info .submit-info {
            color: #c00;
            font-weight: bold;
        }

        /* 目標セクション */
        .goal-section {
            border: 1px solid #333;
            margin-bottom: 3mm;
        }

        .goal-header {
            background: #4a90d9;
            color: white;
            padding: 1.5mm 3mm;
            font-weight: bold;
            font-size: 9pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .goal-content {
            display: flex;
            border-top: 1px solid #333;
        }

        .goal-text {
            flex: 1;
            padding: 2mm 3mm;
            min-height: 10mm;
            font-size: 9pt;
            border-right: 1px solid #333;
        }

        .goal-text.empty {
            color: #999;
        }

        .goal-eval {
            width: 50mm;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2mm;
        }

        .eval-boxes {
            display: flex;
            gap: 2mm;
            align-items: center;
        }

        .eval-box {
            width: 6mm;
            height: 6mm;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7pt;
        }

        .eval-label {
            font-size: 6pt;
            text-align: center;
            margin-top: 1mm;
        }

        .eval-scale {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 曜日計画（縦並び） */
        .daily-section {
            margin-bottom: 3mm;
        }

        .daily-section h3 {
            background: #4a90d9;
            color: white;
            padding: 1.5mm 3mm;
            font-size: 9pt;
            border: 1px solid #333;
            border-bottom: none;
        }

        .daily-grid {
            border: 1px solid #333;
        }

        .daily-item {
            display: flex;
            border-bottom: 1px solid #333;
        }

        .daily-item:last-child {
            border-bottom: none;
        }

        .daily-header {
            background: #e8e8e8;
            padding: 2mm;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            width: 18mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid #333;
        }

        .daily-header .date {
            font-size: 7pt;
            font-weight: normal;
        }

        .daily-content {
            flex: 1;
            padding: 2mm;
            font-size: 8pt;
            min-height: 8mm;
            border-right: 1px solid #333;
        }

        .daily-eval {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2mm;
            gap: 1.5mm;
            width: 40mm;
        }

        .daily-eval .mini-box {
            width: 5mm;
            height: 5mm;
            border: 1px solid #333;
            font-size: 6pt;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* 保護者欄 */
        .parent-section {
            border: 2px solid #333;
            margin-top: 4mm;
        }

        .parent-header {
            background: #ffeb3b;
            padding: 2mm 3mm;
            font-weight: bold;
            font-size: 10pt;
            border-bottom: 2px solid #333;
        }

        .parent-content {
            display: flex;
        }

        .parent-comment {
            flex: 1;
            padding: 2mm 3mm;
            border-right: 2px solid #333;
        }

        .parent-comment-label {
            font-size: 8pt;
            color: #666;
            margin-bottom: 1mm;
        }

        .parent-comment-area {
            min-height: 18mm;
        }

        .parent-sign {
            width: 45mm;
            padding: 2mm 3mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .parent-sign-label {
            font-size: 8pt;
            color: #666;
        }

        .parent-sign-box {
            border: 1px solid #333;
            height: 15mm;
            margin-top: 1mm;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7pt;
            color: #999;
        }

        /* 評価凡例 */
        .legend {
            margin-top: 3mm;
            padding: 2mm 3mm;
            background: #f5f5f5;
            border: 1px solid #ccc;
            font-size: 8pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 1mm;
        }

        .legend-box {
            width: 5mm;
            height: 5mm;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7pt;
        }

        .footer {
            margin-top: 2mm;
            font-size: 7pt;
            color: #666;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="control-panel no-print">
        <a href="student_weekly_plan_detail.php?student_id=<?= $studentId ?>&date=<?= $weekStartDate ?>" class="back-btn">← 戻る</a>
        <h3>週間計画表（評価シート）</h3>
        <button class="print-btn" onclick="window.print()">印刷 / PDF保存</button>
    </div>

    <div class="pdf-container">
        <div class="header">
            <h1>週間計画表</h1>
            <div class="header-info">
                <span>名前：<?= htmlspecialchars($student['student_name']) ?></span>
                <span>期間：<?= $weekStartFormatted ?>（月）〜 <?= $weekEndFormatted ?>（日）</span>
                <span class="submit-info">提出日：<?= $submitFormatted ?>（月）</span>
            </div>
        </div>

        <!-- 今週の目標 -->
        <div class="goal-section">
            <div class="goal-header">
                <span>🎯 今週の目標</span>
                <span style="font-size: 7pt; font-weight: normal;">できたかな？</span>
            </div>
            <div class="goal-content">
                <div class="goal-text <?= empty($weeklyPlan['weekly_goal']) ? 'empty' : '' ?>"><?= !empty($weeklyPlan['weekly_goal']) ? nl2br(htmlspecialchars($weeklyPlan['weekly_goal'])) : '（目標なし）' ?></div>
                <div class="goal-eval">
                    <div class="eval-scale">
                        <div class="eval-boxes">
                            <div class="eval-box">1</div>
                            <div class="eval-box">2</div>
                            <div class="eval-box">3</div>
                            <div class="eval-box">4</div>
                            <div class="eval-box">5</div>
                        </div>
                        <div class="eval-label">できなかった ← → よくできた</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- いっしょに決めた目標 -->
        <div class="goal-section">
            <div class="goal-header">
                <span>🤝 いっしょに決めた目標</span>
                <span style="font-size: 7pt; font-weight: normal;">できたかな？</span>
            </div>
            <div class="goal-content">
                <div class="goal-text <?= empty($weeklyPlan['shared_goal']) ? 'empty' : '' ?>"><?= !empty($weeklyPlan['shared_goal']) ? nl2br(htmlspecialchars($weeklyPlan['shared_goal'])) : '（目標なし）' ?></div>
                <div class="goal-eval">
                    <div class="eval-scale">
                        <div class="eval-boxes">
                            <div class="eval-box">1</div>
                            <div class="eval-box">2</div>
                            <div class="eval-box">3</div>
                            <div class="eval-box">4</div>
                            <div class="eval-box">5</div>
                        </div>
                        <div class="eval-label">できなかった ← → よくできた</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- やるべきこと・やったほうがいいこと・やりたいこと -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2mm; margin-bottom: 3mm;">
            <div class="goal-section" style="margin-bottom: 0;">
                <div class="goal-header" style="font-size: 8pt;">✅ やるべきこと</div>
                <div style="padding: 2mm; min-height: 12mm; font-size: 8pt; border-top: 1px solid #333;"><?= !empty($weeklyPlan['must_do']) ? nl2br(htmlspecialchars($weeklyPlan['must_do'])) : '' ?></div>
                <div style="border-top: 1px solid #333; padding: 1mm; display: flex; justify-content: center; gap: 1mm;">
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">1</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">2</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">3</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">4</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">5</div>
                </div>
            </div>
            <div class="goal-section" style="margin-bottom: 0;">
                <div class="goal-header" style="font-size: 8pt;">👍 やったほうがいいこと</div>
                <div style="padding: 2mm; min-height: 12mm; font-size: 8pt; border-top: 1px solid #333;"><?= !empty($weeklyPlan['should_do']) ? nl2br(htmlspecialchars($weeklyPlan['should_do'])) : '' ?></div>
                <div style="border-top: 1px solid #333; padding: 1mm; display: flex; justify-content: center; gap: 1mm;">
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">1</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">2</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">3</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">4</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">5</div>
                </div>
            </div>
            <div class="goal-section" style="margin-bottom: 0;">
                <div class="goal-header" style="font-size: 8pt;">💡 やりたいこと</div>
                <div style="padding: 2mm; min-height: 12mm; font-size: 8pt; border-top: 1px solid #333;"><?= !empty($weeklyPlan['want_to_do']) ? nl2br(htmlspecialchars($weeklyPlan['want_to_do'])) : '' ?></div>
                <div style="border-top: 1px solid #333; padding: 1mm; display: flex; justify-content: center; gap: 1mm;">
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">1</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">2</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">3</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">4</div>
                    <div class="eval-box" style="width: 5mm; height: 5mm; font-size: 6pt;">5</div>
                </div>
            </div>
        </div>

        <!-- 各曜日の計画（縦並び） -->
        <div class="daily-section">
            <h3>📅 各曜日の計画・目標</h3>
            <div class="daily-grid">
                <?php
                $days = ['月', '火', '水', '木', '金', '土', '日'];
                foreach ($days as $index => $day):
                    $dayKey = "day_$index";
                    $date = date('n/j', strtotime("+$index days", strtotime($weekStartDate)));
                    $content = $planData[$dayKey] ?? '';
                ?>
                <div class="daily-item">
                    <div class="daily-header">
                        <?= $day ?><br><span class="date">(<?= $date ?>)</span>
                    </div>
                    <div class="daily-content"><?= !empty($content) ? nl2br(htmlspecialchars($content)) : '' ?></div>
                    <div class="daily-eval">
                        <div class="mini-box">1</div>
                        <div class="mini-box">2</div>
                        <div class="mini-box">3</div>
                        <div class="mini-box">4</div>
                        <div class="mini-box">5</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 評価凡例 -->
        <div class="legend">
            <span>【評価の書き方】できた度合いの数字に○をつけてください</span>
            <div style="display: flex; gap: 10px;">
                <div class="legend-item"><div class="legend-box">1</div>できなかった</div>
                <div class="legend-item"><div class="legend-box">3</div>まあまあ</div>
                <div class="legend-item"><div class="legend-box">5</div>よくできた</div>
            </div>
        </div>

        <!-- 保護者欄 -->
        <div class="parent-section">
            <div class="parent-header">📝 おうちの方へ（一週間後にご記入ください）</div>
            <div class="parent-content">
                <div class="parent-comment">
                    <div class="parent-comment-label">お子様の様子やコメントをご記入ください</div>
                    <div class="parent-comment-area"></div>
                </div>
                <div class="parent-sign">
                    <div class="parent-sign-label">確認印・サイン</div>
                    <div class="parent-sign-box">印</div>
                </div>
            </div>
        </div>

        <div class="footer">
            出力日：<?= date('Y年n月j日') ?>
        </div>
    </div>
</body>
</html>
