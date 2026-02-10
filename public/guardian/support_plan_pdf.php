<?php
/**
 * 個別支援計画書 PDF出力（保護者用）
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

$guardianId = $_SESSION['user_id'];
$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: support_plans.php');
    exit;
}

$pdo = getDbConnection();

// 計画データを取得（保護者の子供の計画のみ）
$stmt = $pdo->prepare("
    SELECT isp.*,
           s.classroom_id,
           c.classroom_name,
           c.address as classroom_address,
           c.phone as classroom_phone
    FROM individual_support_plans isp
    INNER JOIN students s ON isp.student_id = s.id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    WHERE isp.id = ? AND s.guardian_id = ?
");
$stmt->execute([$planId, $guardianId]);
$planData = $stmt->fetch();

if (!$planData) {
    $_SESSION['error'] = '指定された計画が見つかりません。';
    header('Location: support_plans.php');
    exit;
}

// 明細を取得
$stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
$stmt->execute([$planId]);
$planDetails = $stmt->fetchAll();

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
            --cell-padding: 4px 6px;
            --cell-min-height: 40px;
        }

        @media print {
            @page {
                size: A3 landscape;
                margin: 8mm;
            }
            .no-print {
                display: none !important;
            }
            html, body {
                width: 420mm;
                height: 297mm;
                overflow: hidden;
            }
            /* 空白ページを防ぐ */
            html, body, .section, .goal-section, table {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            height: auto;
        }

        body {
            font-family: 'MS Gothic', 'MS Mincho', monospace;
            font-size: 10pt;
            line-height: 1.3;
            margin: 0;
            padding: 8px;
            padding-top: 70px;
            padding-bottom: 30px;
        }

        @media print {
            html, body {
                overflow: hidden;
                height: 100%;
            }
            body {
                padding: 0;
            }
        }

        .control-panel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--cds-purple-60);
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
            background: var(--cds-support-success);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-left: auto;
        }

        .print-btn:hover {
            background: var(--cds-support-success);
            opacity: 0.8;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 0;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid var(--text-primary);
            padding-bottom: 8px;
        }

        .header h1 {
            font-size: 18pt;
            margin: 0;
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
            background: var(--cds-text-primary);
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
            background: rgba(0,0,0,0.05);
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
            background: rgba(59, 130, 246, 0.15);
        }

        .category-地域支援 {
            background: rgba(36, 161, 72, 0.15);
        }

        /* 署名フッター */
        .signature-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid var(--cds-text-primary);
        }

        .signature-center {
            display: flex;
            gap: 30px;
            flex: 1;
            justify-content: center;
            align-items: center;
        }

        .signature-footer .signature-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .signature-footer .signature-label {
            font-weight: bold;
            font-size: 10pt;
            white-space: nowrap;
        }

        .signature-footer .signature-content {
            display: flex;
            align-items: center;
            gap: 5px;
            border-bottom: 1px solid var(--cds-text-primary);
            min-width: 150px;
            padding: 3px 5px;
        }

        .signature-footer .signature-image {
            max-height: 40px;
            max-width: 120px;
        }

        .signature-footer .signature-name {
            font-size: 9pt;
        }

        .footer-issuer {
            text-align: right;
            min-width: 200px;
        }

        .footer-issuer .issuer-name {
            font-size: 11pt;
            font-weight: bold;
        }

        .footer-issuer .issuer-details {
            font-size: 9pt;
            color: var(--cds-text-primary);
        }

        /* A3横向きレイアウト用 */
        .two-column {
            display: flex;
            gap: 15px;
        }

        .two-column > .section {
            flex: 1;
            margin-bottom: 10px;
        }

        .goal-row {
            display: flex;
            gap: 15px;
        }

        .goal-row > .goal-section {
            flex: 1;
            margin-bottom: 10px;
        }

        @media print {
            .section {
                margin-bottom: 8px;
            }
            .section-content {
                min-height: 30px;
            }
            .goal-section {
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- コントロールパネル -->
    <div class="control-panel no-print">
        <a href="support_plans.php" class="back-btn">
            <span class="material-symbols-outlined">arrow_back</span> 戻る
        </a>
        <div class="control-group">
            <span class="size-display" style="background: rgba(245, 158, 11, 0.15); color: var(--cds-text-primary);">A3 横向き</span>
        </div>
        <button class="print-btn" onclick="window.print()"><span class="material-symbols-outlined">print</span> PDF印刷</button>
    </div>

    <div class="header">
        <h1>個別支援計画書</h1>
    </div>

    <div class="meta-info">
        <div class="meta-item">
            <span class="meta-label">児童氏名：</span>
            <span><?= htmlspecialchars($planData['student_name']) ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">同意日：</span>
            <span><?= $planData['consent_date'] ? date('Y年m月d日', strtotime($planData['consent_date'])) : '' ?></span>
        </div>
    </div>

    <!-- 意向と方針を2列で表示 -->
    <div class="two-column">
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
    </div>

    <!-- 長期目標と短期目標を2列で表示 -->
    <div class="goal-row">
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

    <!-- 署名欄フッター -->
    <div class="signature-footer">
        <div class="signature-center">
            <div class="signature-item">
                <div class="signature-label">児童発達支援管理責任者</div>
                <div class="signature-content">
                    <?php if (!empty($planData['staff_signature_image'])): ?>
                    <img src="<?= $planData['staff_signature_image'] ?>" alt="職員署名" class="signature-image">
                    <?php
                    $signerName = $planData['staff_signer_name'] ?? $planData['manager_name'] ?? '';
                    if ($signerName): ?>
                    <div class="signature-name">(<?= htmlspecialchars($signerName) ?>)</div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="signature-name"><?= htmlspecialchars($planData['manager_name'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="signature-item">
                <div class="signature-label">保護者署名</div>
                <div class="signature-content">
                    <?php if (!empty($planData['guardian_signature_image'])): ?>
                    <img src="<?= $planData['guardian_signature_image'] ?>" alt="保護者署名" class="signature-image">
                    <?php else: ?>
                    <div class="signature-name"><?= htmlspecialchars($planData['guardian_signature'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-issuer">
            <div class="issuer-name"><?= htmlspecialchars($planData['classroom_name'] ?? '') ?></div>
            <div class="issuer-details">
                <?php if (!empty($planData['classroom_address'])): ?>
                〒<?= htmlspecialchars($planData['classroom_address']) ?>
                <?php endif; ?>
                <?php if (!empty($planData['classroom_phone'])): ?>
                <br>TEL: <?= htmlspecialchars($planData['classroom_phone']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
