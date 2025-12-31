<?php
/**
 * 個別支援計画書 PDF出力
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

// 計画データを取得
$stmt = $pdo->prepare("SELECT * FROM individual_support_plans WHERE id = ?");
$stmt->execute([$planId]);
$planData = $stmt->fetch();

if (!$planData) {
    $_SESSION['error'] = '指定された計画が見つかりません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 明細を取得
$stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
$stmt->execute([$planId]);
$planDetails = $stmt->fetchAll();

// TCPDF/FPDFを使わず、HTMLをPDFに変換する方法として、DomPDFを使用します
// ここではシンプルにHTML出力してブラウザのPDF印刷機能を利用する方法を採用します

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <meta charset="UTF-8">
    <title>個別支援計画書 - <?= htmlspecialchars($planData['student_name']) ?></title>
    <style>
        :root {
            --table-font-size: 9pt;
            --cell-padding: 6px 8px;
            --cell-min-height: 60px;
        }

        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: 'MS Gothic', 'MS Mincho', monospace;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0;
            padding: 8px;
            padding-top: 70px;
        }

        @media print {
            body {
                padding-top: 8px;
            }
        }

        .control-panel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .control-group label {
            font-size: 12px;
            white-space: nowrap;
        }

        .control-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .control-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .size-display {
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            min-width: 50px;
            text-align: center;
        }

        .print-btn {
            background: #10b981;
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-left: auto;
        }

        .print-btn:hover {
            background: #059669;
        }

        .header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            border-bottom: 2px solid var(--text-primary);
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18pt;
            margin: 0 0 10px 0;
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
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .section-title {
            background: #4a5568;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 8px;
        }

        .section-content {
            padding: var(--spacing-sm);
            border: 1px solid var(--md-gray-5);
            min-height: 50px;
            white-space: pre-wrap;
        }

        .goal-section {
            margin-bottom: 15px;
        }

        .goal-header {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .goal-title {
            font-weight: bold;
            margin-right: 10px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: var(--table-font-size);
        }

        .details-table th,
        .details-table td {
            border: 1px solid var(--text-primary);
            padding: var(--cell-padding);
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            background: #e2e8f0;
            font-weight: bold;
            text-align: center;
            font-size: var(--table-font-size);
        }

        .details-table td {
            white-space: pre-wrap;
            min-height: var(--cell-min-height);
            line-height: 1.5;
        }

        .category-本人支援 {
            background: var(--md-bg-secondary);
        }

        .category-家族支援 {
            background: #dbeafe;
        }

        .category-地域支援 {
            background: #d1fae5;
        }

        .signature-section {
            display: flex;
            justify-content: space-around;
            margin-top: var(--spacing-2xl);
            padding-top: 20px;
            border-top: 1px solid var(--md-gray-5);
        }

        .signature-item {
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid var(--text-primary);
            width: 200px;
            margin: var(--spacing-md) auto;
            height: 30px;
        }
    </style>
</head>
<body>
    <!-- サイズ調整コントロールパネル -->
    <div class="control-panel no-print">
        <div class="control-group">
            <label>文字サイズ:</label>
            <button class="control-btn" onclick="adjustFontSize(-1)">−</button>
            <span class="size-display" id="fontSizeDisplay">9pt</span>
            <button class="control-btn" onclick="adjustFontSize(1)">+</button>
        </div>
        <div class="control-group">
            <label>セル余白:</label>
            <button class="control-btn" onclick="adjustPadding(-2)">−</button>
            <span class="size-display" id="paddingDisplay">6px</span>
            <button class="control-btn" onclick="adjustPadding(2)">+</button>
        </div>
        <div class="control-group">
            <label>セル高さ:</label>
            <button class="control-btn" onclick="adjustHeight(-10)">−</button>
            <span class="size-display" id="heightDisplay">60px</span>
            <button class="control-btn" onclick="adjustHeight(10)">+</button>
        </div>
        <button class="print-btn" onclick="window.print()"><span class="material-symbols-outlined">print</span> PDF印刷</button>
    </div>

    <script>
        let fontSize = 9;
        let cellPadding = 6;
        let cellHeight = 60;

        function adjustFontSize(delta) {
            fontSize = Math.max(6, Math.min(14, fontSize + delta));
            document.documentElement.style.setProperty('--table-font-size', fontSize + 'pt');
            document.getElementById('fontSizeDisplay').textContent = fontSize + 'pt';
        }

        function adjustPadding(delta) {
            cellPadding = Math.max(2, Math.min(16, cellPadding + delta));
            document.documentElement.style.setProperty('--cell-padding', cellPadding + 'px ' + (cellPadding + 2) + 'px');
            document.getElementById('paddingDisplay').textContent = cellPadding + 'px';
        }

        function adjustHeight(delta) {
            cellHeight = Math.max(30, Math.min(150, cellHeight + delta));
            document.documentElement.style.setProperty('--cell-min-height', cellHeight + 'px');
            document.getElementById('heightDisplay').textContent = cellHeight + 'px';
        }
    </script>

    <div class="header">
        <h1>個別支援計画書</h1>
    </div>

    <div class="meta-info">
        <div class="meta-item">
            <span class="meta-label">児童氏名：</span>
            <span><?= htmlspecialchars($planData['student_name']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">作成年月日：</span>
            <span><?= $planData['created_date'] ? date('Y年m月d日', strtotime($planData['created_date'])) : '' ?></span>
        </div>
    </div>

    <!-- 利用児及び家族の生活に対する意向 -->
    <div class="section">
        <div class="section-title">利用児及び家族の生活に対する意向</div>
        <div class="section-content"><?= htmlspecialchars($planData['life_intention']) ?></div>
    </div>

    <!-- 総合的な支援の方針 -->
    <div class="section">
        <div class="section-title">総合的な支援の方針</div>
        <div class="section-content"><?= htmlspecialchars($planData['overall_policy']) ?></div>
    </div>

    <!-- 長期目標 -->
    <div class="goal-section">
        <div class="section-title">長期目標</div>
        <div class="goal-header">
            <span class="goal-title">達成時期：</span>
            <span><?= $planData['long_term_goal_date'] ? date('Y年m月d日', strtotime($planData['long_term_goal_date'])) : '' ?></span>
        </div>
        <div class="section-content"><?= htmlspecialchars($planData['long_term_goal_text']) ?></div>
    </div>

    <!-- 短期目標 -->
    <div class="goal-section">
        <div class="section-title">短期目標</div>
        <div class="goal-header">
            <span class="goal-title">達成時期：</span>
            <span><?= $planData['short_term_goal_date'] ? date('Y年m月d日', strtotime($planData['short_term_goal_date'])) : '' ?></span>
        </div>
        <div class="section-content"><?= htmlspecialchars($planData['short_term_goal_text']) ?></div>
    </div>

    <!-- 支援内容明細 -->
    <div class="section">
        <div class="section-title">支援内容</div>
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 8%;">項目</th>
                    <th style="width: 18%;">支援目標</th>
                    <th style="width: 32%;">支援内容</th>
                    <th style="width: 8%;">達成時期</th>
                    <th style="width: 12%;">担当者/<br>提供機関</th>
                    <th style="width: 16%;">留意事項</th>
                    <th style="width: 6%;">優先順位</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planDetails as $detail): ?>
                <tr class="category-<?= htmlspecialchars($detail['category']) ?>">
                    <td><?= nl2br(htmlspecialchars($detail['sub_category'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($detail['support_goal'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($detail['support_content'])) ?></td>
                    <td><?= $detail['achievement_date'] ? date('Y/m/d', strtotime($detail['achievement_date'])) : '' ?></td>
                    <td><?= nl2br(htmlspecialchars($detail['staff_organization'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($detail['notes'])) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($detail['priority']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 署名欄 -->
    <div class="signature-section">
        <div class="signature-item">
            <div class="meta-label">児童発達支援管理責任者</div>
            <div class="signature-line"><?= htmlspecialchars($planData['manager_name']) ?></div>
        </div>
        <div class="signature-item">
            <div class="meta-label">同意日</div>
            <div class="signature-line"><?= $planData['consent_date'] ? date('Y年m月d日', strtotime($planData['consent_date'])) : '' ?></div>
        </div>
        <div class="signature-item">
            <div class="meta-label">保護者署名</div>
            <div class="signature-line"><?= htmlspecialchars($planData['guardian_signature']) ?></div>
        </div>
    </div>
</body>
</html>
