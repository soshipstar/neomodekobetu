<?php
/**
 * 個別支援計画書 PDF出力
 */
session_start();
require_once __DIR__ . '/../config/database.php';

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
    <meta charset="UTF-8">
    <title>個別支援計画書 - <?= htmlspecialchars($planData['student_name']) ?></title>
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
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
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
            padding: 8px;
            border: 1px solid #ddd;
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
            font-size: 9pt;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }

        .details-table th {
            background: #e2e8f0;
            font-weight: bold;
            text-align: center;
        }

        .details-table td {
            white-space: pre-wrap;
        }

        .category-本人支援 {
            background: #fef3c7;
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
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .signature-item {
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            width: 200px;
            margin: 10px auto;
            height: 30px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 30px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14pt;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .print-button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ PDF印刷</button>

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
                    <th style="width: 10%;">項目</th>
                    <th style="width: 12%;">支援目標</th>
                    <th style="width: 30%;">支援内容</th>
                    <th style="width: 10%;">達成時期</th>
                    <th style="width: 15%;">担当者/<br>提供機関</th>
                    <th style="width: 15%;">留意事項</th>
                    <th style="width: 8%;">優先順位</th>
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
