<?php
/**
 * 保護者用 個別支援計画書閲覧ページ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType('guardian');

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 教室情報を取得
$classroom = null;
$stmt = $pdo->prepare("
    SELECT c.* FROM classrooms c
    INNER JOIN users u ON c.id = u.classroom_id
    WHERE u.id = ?
");
$stmt->execute([$guardianId]);
$classroom = $stmt->fetch();

// 現在のページ設定
$currentPage = 'support_plans';
$pageTitle = '個別支援計画書';

// 保護者に紐づく生徒を取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? AND is_active = 1 ORDER BY student_name");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// 選択された生徒の個別支援計画一覧（提出済みのみ）
$plans = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT * FROM individual_support_plans
        WHERE student_id = ? AND is_draft = 0
        ORDER BY created_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $plans = $stmt->fetchAll();
}

// 選択された計画の詳細
$selectedPlanId = $_GET['plan_id'] ?? null;
$planData = null;
$planDetails = [];

if ($selectedPlanId) {
    $stmt = $pdo->prepare("
        SELECT * FROM individual_support_plans
        WHERE id = ? AND student_id = ? AND is_draft = 0
    ");
    $stmt->execute([$selectedPlanId, $selectedStudentId]);
    $planData = $stmt->fetch();

    if ($planData) {
        // 明細を取得
        $stmt = $pdo->prepare("SELECT * FROM individual_support_plan_details WHERE plan_id = ? ORDER BY row_order");
        $stmt->execute([$selectedPlanId]);
        $planDetails = $stmt->fetchAll();
    }
}

// ページ開始
renderPageStart('guardian', $currentPage, $pageTitle);
?>

<style>
        .content-box {
            background: var(--md-bg-primary);
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .page-header {
            background: var(--md-bg-secondary);
            color: var(--text-primary);
            padding: var(--spacing-2xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: var(--spacing-md) 20px;
            border-radius: var(--radius-sm);
            background: var(--md-gray-5);
            transition: all var(--duration-normal) var(--ease-out);
        }

        .nav-links a:hover {
            background: var(--md-gray-5);
        }

        .content {
            padding: var(--spacing-2xl);
        }

        .selector-section {
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
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
            color: var(--text-primary);
        }

        .form-group select {
            padding: var(--spacing-md);
            border: 2px solid #e1e8ed;
            border-radius: var(--radius-sm);
            font-size: 15px;
            background: var(--md-bg-primary);
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--primary-purple);
        }

        .plan-card {
            background: var(--md-bg-primary);
            border: 2px solid #e1e8ed;
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: 15px;
            cursor: pointer;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .plan-card:hover {
            border-color: var(--primary-purple);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .plan-card.selected {
            border-color: var(--primary-purple);
            background: linear-gradient(135deg, #f0f4ff 0%, #faf0ff 100%);
        }

        .plan-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }

        .plan-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .plan-card-date {
            color: var(--text-secondary);
            font-size: var(--text-subhead);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-purple);
            margin: var(--spacing-2xl) 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-purple);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: var(--spacing-lg);
        }

        .info-item {
            background: var(--md-gray-6);
            padding: 15px;
            border-radius: var(--radius-sm);
        }

        .info-item label {
            display: block;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-size: var(--text-subhead);
        }

        .info-item .value {
            color: var(--text-primary);
            font-size: var(--text-callout);
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: var(--radius-md);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: var(--spacing-lg);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--md-bg-primary);
        }

        th {
            background: var(--md-bg-secondary);
            color: var(--text-primary);
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: var(--text-subhead);
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: var(--md-gray-6);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .btn {
            display: inline-block;
            padding: var(--spacing-md) 24px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--duration-normal) var(--ease-out);
        }

        .btn-primary {
            background: var(--md-bg-secondary);
            color: var(--text-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .confirmation-box {
            background: var(--md-gray-6);
            padding: 25px;
            border-radius: var(--radius-md);
            text-align: center;
        }

        .confirmation-box p {
            margin-bottom: var(--spacing-lg);
            font-size: var(--text-callout);
            color: var(--text-primary);
        }

        .confirmation-box.confirmed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid var(--md-green);
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: left;
        }

        .confirmation-icon {
            width: 60px;
            height: 60px;
            background: var(--md-green);
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
            font-size: var(--text-subhead);
            color: #155724;
        }

        .alert {
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: var(--md-bg-secondary);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* スマートフォン対応 */
        @media (max-width: 768px) {
            body {
                padding: var(--spacing-sm);
            }
            .container {
                border-radius: var(--radius-md);
            }
            .header {
                padding: var(--spacing-md);
                flex-direction: column;
                gap: 12px;
                text-align: left;
            }
            .header h1 {
                font-size: 18px;
                line-height: 1.3;
                margin: 0;
            }
            .nav-links a {
                display: inline-block;
                padding: 8px 14px;
                font-size: 13px;
            }
            .content {
                padding: var(--spacing-md);
            }
            .selector-section {
                flex-direction: column;
            }
            .selector-group {
                flex-direction: column;
                gap: 15px;
            }
            .form-group select {
                font-size: 16px;
            }
            .plan-card {
                padding: var(--spacing-md);
            }
            .plan-table {
                display: block;
                overflow-x: auto;
            }
            .plan-table th,
            .plan-table td {
                padding: var(--spacing-sm);
                font-size: var(--text-footnote);
            }
            .confirm-btn {
                width: 100%;
                padding: var(--spacing-md);
            }
        }

        /* 電子署名スタイル */
        .signature-section {
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-top: var(--spacing-md);
        }

        .signature-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            font-size: var(--text-subhead);
        }

        .signature-container {
            border: 2px solid #e1e8ed;
            border-radius: var(--radius-sm);
            background: white;
            overflow: hidden;
        }

        .signature-canvas {
            display: block;
            width: 100%;
            height: 150px;
            cursor: crosshair;
            touch-action: none;
        }

        .signature-controls {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
            align-items: center;
            flex-wrap: wrap;
        }

        .signature-preview {
            max-width: 250px;
            max-height: 100px;
            border: 1px solid #e1e8ed;
            border-radius: var(--radius-sm);
        }

        .signature-display {
            margin-top: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--md-bg-primary);
            border-radius: var(--radius-sm);
        }

        .staff-signature-section {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid #e1e8ed;
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">個別支援計画書</h1>
        <p class="page-subtitle">お子様の支援計画を確認</p>
    </div>
</div>

                <div class="content-box">
                    <div class="content">
            <!-- 生徒選択 -->
            <div class="selector-section">
                <div class="selector-group">
                    <div class="form-group">
                        <label><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> お子様を選択</label>
                        <select onchange="location.href='support_plans.php?student_id=' + this.value">
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
                <!-- 計画一覧 -->
                <div class="section-title">提出済みの個別支援計画書</div>

                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <div class="plan-card <?= $selectedPlanId == $plan['id'] ? 'selected' : '' ?>"
                             onclick="location.href='support_plans.php?student_id=<?= $selectedStudentId ?>&plan_id=<?= $plan['id'] ?>'">
                            <div class="plan-card-header">
                                <div class="plan-card-title">
                                    <?= htmlspecialchars($plan['student_name']) ?>さんの個別支援計画書
                                </div>
                                <div class="plan-card-date">
                                    作成日: <?= date('Y年n月j日', strtotime($plan['created_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- 計画詳細 -->
                    <?php if ($planData): ?>
                        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span>計画書の詳細</span>
                            <div style="display: flex; gap: 10px;">
                                <?php if ($planData['is_official'] ?? 0): ?>
                                    <a href="support_plan_pdf.php?plan_id=<?= $planData['id'] ?>" class="btn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 8px 16px; font-size: 14px;" target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">verified</span> 正式版PDF
                                    </a>
                                <?php endif; ?>
                                <?php if (!($planData['is_draft'] ?? true)): ?>
                                    <a href="support_plan_draft_pdf.php?plan_id=<?= $planData['id'] ?>" class="btn" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 8px 16px; font-size: 14px;" target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span> 計画案PDF
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 基本情報 -->
                        <div class="info-grid">
                            <div class="info-item">
                                <label>お子様のお名前</label>
                                <div class="value"><?= htmlspecialchars($planData['student_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <label>作成日</label>
                                <div class="value"><?= date('Y年n月j日', strtotime($planData['created_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <label>担当者</label>
                                <div class="value"><?= htmlspecialchars($planData['manager_name'] ?: '未設定') ?></div>
                            </div>
                        </div>

                        <!-- 本人・家族の意向 -->
                        <?php if ($planData['life_intention']): ?>
                            <div class="section-title">本人・家族の意向</div>
                            <div class="info-item">
                                <div class="value"><?= nl2br(htmlspecialchars($planData['life_intention'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 総合的な支援方針 -->
                        <?php if ($planData['overall_policy']): ?>
                            <div class="section-title">総合的な支援方針</div>
                            <div class="info-item">
                                <div class="value"><?= nl2br(htmlspecialchars($planData['overall_policy'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- 長期目標 -->
                        <?php if ($planData['long_term_goal_text']): ?>
                            <div class="section-title">長期目標</div>
                            <div class="info-grid">
                                <?php if ($planData['long_term_goal_date']): ?>
                                    <div class="info-item">
                                        <label>達成時期</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['long_term_goal_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>目標内容</label>
                                    <div class="value"><?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 短期目標 -->
                        <?php if ($planData['short_term_goal_text']): ?>
                            <div class="section-title">短期目標</div>
                            <div class="info-grid">
                                <?php if ($planData['short_term_goal_date']): ?>
                                    <div class="info-item">
                                        <label>達成時期</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['short_term_goal_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <label>目標内容</label>
                                    <div class="value"><?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 支援内容詳細 -->
                        <?php if (!empty($planDetails)): ?>
                            <div class="section-title">支援内容の詳細</div>
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">項目</th>
                                            <th style="width: 200px;">支援目標</th>
                                            <th style="width: 250px;">支援内容</th>
                                            <th style="width: 120px;">達成時期</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($planDetails as $detail): ?>
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
                                                    <?= $detail['achievement_date'] ? date('Y年n月j日', strtotime($detail['achievement_date'])) : '' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- 同意情報 -->
                        <?php if ($planData['consent_date'] || $planData['guardian_signature']): ?>
                            <div class="section-title">同意情報</div>
                            <div class="info-grid">
                                <?php if ($planData['consent_date']): ?>
                                    <div class="info-item">
                                        <label>同意日</label>
                                        <div class="value"><?= date('Y年n月j日', strtotime($planData['consent_date'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($planData['guardian_signature']): ?>
                                    <div class="info-item">
                                        <label>保護者署名</label>
                                        <div class="value"><?= htmlspecialchars($planData['guardian_signature']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- 確認・署名状況 -->
                        <div class="section-title">確認状況</div>
                        <?php
                        $guardianConfirmed = $planData['guardian_confirmed'] ?? 0;
                        $guardianConfirmedAt = $planData['guardian_confirmed_at'] ?? null;
                        $isOfficial = $planData['is_official'] ?? 0;
                        $guardianReviewComment = $planData['guardian_review_comment'] ?? null;
                        $guardianReviewCommentAt = $planData['guardian_review_comment_at'] ?? null;
                        ?>

                        <?php if ($guardianConfirmed && $isOfficial): ?>
                            <!-- 正式版で署名済み -->
                            <div class="confirmation-box confirmed">
                                <div class="confirmation-icon">✓</div>
                                <div class="confirmation-content">
                                    <div class="confirmation-title">署名・確認済み（正式版）</div>
                                    <div class="confirmation-date">
                                        確認日時: <?= date('Y年n月j日 H:i', strtotime($guardianConfirmedAt)) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 電子署名の表示 -->
                            <?php if (!empty($planData['guardian_signature_image']) || !empty($planData['staff_signature_image'])): ?>
                                <div class="section-title">電子署名</div>
                                <div class="info-grid">
                                    <?php if (!empty($planData['staff_signature_image'])): ?>
                                        <div class="info-item">
                                            <label>職員署名</label>
                                            <p style="margin: 0 0 8px 0; font-size: var(--text-footnote); color: var(--text-secondary);">
                                                署名者: <?= htmlspecialchars($planData['staff_signer_name'] ?? '') ?>
                                            </p>
                                            <img src="<?= htmlspecialchars($planData['staff_signature_image']) ?>" alt="職員署名" class="signature-preview">
                                            <?php if ($planData['staff_signature_date']): ?>
                                                <p style="margin: 8px 0 0 0; font-size: var(--text-footnote); color: var(--text-secondary);">
                                                    署名日: <?= date('Y年n月j日', strtotime($planData['staff_signature_date'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($planData['guardian_signature_image'])): ?>
                                        <div class="info-item">
                                            <label>保護者署名</label>
                                            <img src="<?= htmlspecialchars($planData['guardian_signature_image']) ?>" alt="保護者署名" class="signature-preview">
                                            <?php if ($planData['guardian_signature_date']): ?>
                                                <p style="margin: 8px 0 0 0; font-size: var(--text-footnote); color: var(--text-secondary);">
                                                    署名日: <?= date('Y年n月j日', strtotime($planData['guardian_signature_date'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($isOfficial && !$guardianConfirmed): ?>
                            <!-- 正式版だが署名待ち -->
                            <div class="confirmation-box" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 2px solid #ffc107;">
                                <p style="color: #856404; margin: 0;">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">schedule</span>
                                    正式版の個別支援計画書です。次回の面談時に署名をお願いいたします。
                                </p>
                            </div>

                        <?php elseif ($guardianReviewComment): ?>
                            <!-- コメント送信済み -->
                            <div class="confirmation-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 2px solid #17a2b8;">
                                <div class="confirmation-icon" style="background: #17a2b8;">!</div>
                                <div class="confirmation-content">
                                    <div class="confirmation-title" style="color: #0c5460;">変更希望コメント送信済み</div>
                                    <div class="confirmation-date" style="color: #0c5460;">
                                        送信日時: <?= date('Y年n月j日 H:i', strtotime($guardianReviewCommentAt)) ?>
                                    </div>
                                    <div style="margin-top: var(--spacing-md); padding: var(--spacing-md); background: rgba(255,255,255,0.7); border-radius: var(--radius-sm);">
                                        <p style="margin: 0; font-size: var(--text-footnote); color: #0c5460; font-weight: 600;">送信したコメント:</p>
                                        <p style="margin: 8px 0 0 0; color: #0c5460;"><?= nl2br(htmlspecialchars($guardianReviewComment)) ?></p>
                                    </div>
                                    <p style="margin: var(--spacing-md) 0 0 0; font-size: var(--text-footnote); color: #0c5460;">
                                        スタッフがコメントを確認し、正式版を作成いたします。
                                    </p>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- 計画書案の確認待ち -->
                            <div class="review-section" style="background: var(--md-gray-6); padding: var(--spacing-lg); border-radius: var(--radius-md);">
                                <p style="margin-bottom: var(--spacing-lg); color: var(--text-primary); font-size: var(--text-callout);">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span>
                                    この個別支援計画書（案）の内容をご確認ください。
                                </p>

                                <div style="background: #fff3cd; padding: var(--spacing-md); border-radius: var(--radius-sm); margin-bottom: var(--spacing-lg); border-left: 4px solid #ffc107;">
                                    <p style="margin: 0; color: #856404; font-size: var(--text-subhead);">
                                        <strong>変更をご希望の場合：</strong>下記のコメント欄にご記入ください。<br>
                                        <strong>内容に問題がない場合：</strong>「内容を確認しました」ボタンを押してください。
                                    </p>
                                </div>

                                <div class="form-group" style="margin-bottom: var(--spacing-lg);">
                                    <label style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-sm); display: block;">
                                        変更希望コメント（任意）
                                    </label>
                                    <textarea id="reviewComment" rows="4" style="width: 100%; padding: var(--spacing-md); border: 2px solid #e1e8ed; border-radius: var(--radius-sm); font-size: 15px; resize: vertical;" placeholder="変更をご希望の場合はこちらにコメントをご記入ください..."></textarea>
                                </div>

                                <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                                    <button onclick="submitReviewComment(<?= $selectedPlanId ?>)" class="btn" id="commentBtn" style="flex: 1; min-width: 200px; background: #17a2b8; color: white;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">send</span> コメントを送信
                                    </button>
                                    <button onclick="confirmPlanReview(<?= $selectedPlanId ?>)" class="btn btn-primary" id="confirmBtn" style="flex: 1; min-width: 200px; background: linear-gradient(135deg, var(--md-green) 0%, #20c997 100%); color: white;">
                                        <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> 内容を確認しました
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 提出済みの個別支援計画書はまだありません</h3>
                        <p>スタッフが計画書を作成・提出すると、ここに表示されます。</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> お子様を選択してください</h3>
                </div>
            <?php endif; ?>
                    </div><!-- /.content -->
                </div><!-- /.content-box -->

    <script>
        // 変更希望コメントを送信
        function submitReviewComment(planId) {
            const comment = document.getElementById('reviewComment').value.trim();
            if (!comment) {
                alert('コメントを入力してください。');
                return;
            }

            if (!confirm('このコメントを送信しますか？')) {
                return;
            }

            const btn = document.getElementById('commentBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">hourglass_empty</span> 送信中...';

            fetch('support_plan_confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    plan_id: planId,
                    action: 'submit_comment',
                    review_comment: comment
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('コメントを送信しました。スタッフが確認いたします。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">send</span> コメントを送信';
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">send</span> コメントを送信';
            });
        }

        // 内容確認（変更なし）
        function confirmPlanReview(planId) {
            if (!confirm('この個別支援計画書（案）の内容を確認しましたか？\n確認後、スタッフが正式版を作成します。')) {
                return;
            }

            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">hourglass_empty</span> 処理中...';

            fetch('support_plan_confirm.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    plan_id: planId,
                    action: 'confirm_review'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('確認しました。ありがとうございます。\n正式版作成後、面談時に署名をお願いいたします。');
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> 内容を確認しました';
                }
            })
            .catch(error => {
                alert('エラーが発生しました: ' + error);
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">check_circle</span> 内容を確認しました';
            });
        }
    </script>

<?php renderPageEnd(); ?>
