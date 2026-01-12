<?php
/**
 * 個別支援計画書 署名入力ページ
 *
 * 完成した計画書を表示し、保護者・職員の署名を取得する
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];
$staffName = $_SESSION['user_name'] ?? '';

$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    $_SESSION['error'] = '計画IDが指定されていません。';
    header('Location: kobetsu_plan.php');
    exit;
}

// 計画データを取得
$stmt = $pdo->prepare("SELECT isp.*, s.student_name as current_student_name
                       FROM individual_support_plans isp
                       LEFT JOIN students s ON isp.student_id = s.id
                       WHERE isp.id = ?");
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

// ページ開始
$currentPage = 'kobetsu_plan';
renderPageStart('staff', $currentPage, '個別支援計画書 - 署名入力');
?>

<style>
.sign-page-container {
    max-width: 1200px;
    margin: 0 auto;
}

.plan-preview {
    background: var(--md-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-xl);
    margin-bottom: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.plan-header {
    text-align: center;
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 2px solid var(--md-blue);
}

.plan-header h2 {
    font-size: var(--text-title-2);
    color: var(--md-blue);
    margin-bottom: var(--spacing-sm);
}

.plan-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
}

.plan-meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.plan-meta-label {
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.plan-meta-value {
    font-size: var(--text-callout);
    color: var(--text-primary);
    font-weight: 500;
}

.plan-section {
    margin-bottom: var(--spacing-lg);
}

.plan-section-title {
    font-size: var(--text-callout);
    color: var(--md-blue);
    font-weight: 600;
    margin-bottom: var(--spacing-sm);
    padding-left: var(--spacing-sm);
    border-left: 3px solid var(--md-blue);
}

.plan-section-content {
    background: var(--md-bg-secondary);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    white-space: pre-wrap;
    line-height: 1.6;
}

.plan-details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-subhead);
    margin-top: var(--spacing-md);
}

.plan-details-table th,
.plan-details-table td {
    padding: var(--spacing-sm);
    border: 1px solid var(--border-primary);
    vertical-align: top;
}

.plan-details-table th {
    background: var(--md-blue);
    color: white;
    font-weight: 600;
    text-align: left;
}

.plan-details-table td {
    background: var(--md-bg-primary);
}

.plan-details-table tr:nth-child(even) td {
    background: var(--md-bg-secondary);
}

/* 署名セクション */
.signature-section {
    background: var(--md-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-xl);
    box-shadow: var(--shadow-md);
}

.signature-section-title {
    font-size: var(--text-title-3);
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.signature-row {
    display: flex;
    gap: var(--spacing-xl);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-xl);
}

.signature-item {
    flex: 1;
    min-width: 300px;
}

.signature-label {
    font-weight: 600;
    color: var(--md-blue);
    margin-bottom: var(--spacing-sm);
    font-size: var(--text-callout);
}

.signature-container {
    border: 2px solid var(--md-gray-4);
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
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
    align-items: center;
}

.signature-status {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
    font-weight: 500;
}

.signature-status.pending {
    background: var(--md-gray-5);
    color: var(--text-secondary);
}

.signature-status.signed {
    background: rgba(52, 199, 89, 0.15);
    color: var(--md-green);
}

.signature-status.optional {
    background: var(--md-gray-6);
    color: var(--text-tertiary);
}

.existing-signature {
    margin-top: var(--spacing-md);
    padding: var(--spacing-md);
    background: var(--md-gray-6);
    border-radius: var(--radius-sm);
}

.existing-signature p {
    margin: 0 0 var(--spacing-sm) 0;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.signature-preview {
    max-width: 250px;
    max-height: 100px;
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    background: white;
}

/* ボタン */
.button-section {
    display: flex;
    gap: var(--spacing-md);
    justify-content: center;
    flex-wrap: wrap;
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--border-primary);
}

.btn-confirm {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: var(--spacing-md) var(--spacing-2xl);
    font-size: var(--text-callout);
    font-weight: 600;
}

.btn-confirm:disabled {
    background: var(--md-gray-4);
    cursor: not-allowed;
    opacity: 0.7;
}

.confirm-note {
    text-align: center;
    color: var(--text-secondary);
    font-size: var(--text-subhead);
    margin-bottom: var(--spacing-lg);
}

@media (max-width: 768px) {
    .signature-row {
        flex-direction: column;
    }

    .signature-item {
        min-width: 100%;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><span class="material-symbols-outlined">draw</span> 個別支援計画書 - 署名入力</h1>
        <p class="page-subtitle"><?= htmlspecialchars($planData['student_name'] ?? $planData['current_student_name']) ?>さんの計画書</p>
    </div>
</div>

<div class="sign-page-container">
    <!-- 計画書プレビュー -->
    <div class="plan-preview">
        <div class="plan-header">
            <h2>個別支援計画書</h2>
            <p style="color: var(--text-secondary);">以下の内容で署名を取得します</p>
        </div>

        <!-- 基本情報 -->
        <div class="plan-meta">
            <div class="plan-meta-item">
                <span class="plan-meta-label">利用者氏名</span>
                <span class="plan-meta-value"><?= htmlspecialchars($planData['student_name'] ?? '') ?></span>
            </div>
            <div class="plan-meta-item">
                <span class="plan-meta-label">計画作成日</span>
                <span class="plan-meta-value"><?= $planData['created_date'] ? date('Y年n月j日', strtotime($planData['created_date'])) : '' ?></span>
            </div>
            <div class="plan-meta-item">
                <span class="plan-meta-label">計画作成担当者</span>
                <span class="plan-meta-value"><?= htmlspecialchars($planData['manager_name'] ?? '') ?></span>
            </div>
            <div class="plan-meta-item">
                <span class="plan-meta-label">同意日</span>
                <span class="plan-meta-value"><?= $planData['consent_date'] ? date('Y年n月j日', strtotime($planData['consent_date'])) : '（署名時に記録）' ?></span>
            </div>
        </div>

        <!-- 本人の意向 -->
        <?php if (!empty($planData['life_intention'])): ?>
        <div class="plan-section">
            <div class="plan-section-title">本人・保護者の意向</div>
            <div class="plan-section-content"><?= nl2br(htmlspecialchars($planData['life_intention'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- 総合的な支援の方針 -->
        <?php if (!empty($planData['overall_policy'])): ?>
        <div class="plan-section">
            <div class="plan-section-title">総合的な支援の方針</div>
            <div class="plan-section-content"><?= nl2br(htmlspecialchars($planData['overall_policy'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- 長期目標 -->
        <?php if (!empty($planData['long_term_goal_text'])): ?>
        <div class="plan-section">
            <div class="plan-section-title">長期目標（<?= $planData['long_term_goal_date'] ? date('Y年n月', strtotime($planData['long_term_goal_date'])) : '' ?>）</div>
            <div class="plan-section-content"><?= nl2br(htmlspecialchars($planData['long_term_goal_text'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- 短期目標 -->
        <?php if (!empty($planData['short_term_goal_text'])): ?>
        <div class="plan-section">
            <div class="plan-section-title">短期目標（<?= $planData['short_term_goal_date'] ? date('Y年n月', strtotime($planData['short_term_goal_date'])) : '' ?>）</div>
            <div class="plan-section-content"><?= nl2br(htmlspecialchars($planData['short_term_goal_text'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- 支援内容明細 -->
        <?php if (!empty($planDetails)): ?>
        <div class="plan-section">
            <div class="plan-section-title">支援内容</div>
            <div style="overflow-x: auto;">
                <table class="plan-details-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">項目</th>
                            <th style="width: 25%;">支援目標</th>
                            <th style="width: 30%;">支援内容</th>
                            <th style="width: 10%;">達成時期</th>
                            <th style="width: 20%;">備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($planDetails as $detail): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($detail['category'] ?? '') ?></strong><br>
                                <span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($detail['sub_category'] ?? '') ?></span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($detail['support_goal'] ?? '')) ?></td>
                            <td><?= nl2br(htmlspecialchars($detail['support_content'] ?? '')) ?></td>
                            <td><?= $detail['achievement_date'] ? date('Y/n/j', strtotime($detail['achievement_date'])) : '' ?></td>
                            <td><?= nl2br(htmlspecialchars($detail['notes'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 署名セクション -->
    <div class="signature-section">
        <div class="signature-section-title">
            <span class="material-symbols-outlined">draw</span>
            電子署名
        </div>

        <p class="confirm-note">
            <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">info</span>
            職員の署名が入力されると「確定して保存」ボタンが有効になります。保護者署名は任意です。
        </p>

        <form id="signatureForm" action="kobetsu_plan_sign_save.php" method="POST">
            <input type="hidden" name="plan_id" value="<?= $planId ?>">
            <input type="hidden" name="staff_signer_name" value="<?= htmlspecialchars($staffName) ?>">

            <div class="signature-row">
                <!-- 保護者署名 -->
                <div class="signature-item">
                    <div class="signature-label">保護者署名（任意）</div>
                    <div class="signature-container">
                        <canvas id="guardianSignatureCanvas" class="signature-canvas"></canvas>
                    </div>
                    <input type="hidden" name="guardian_signature_image" id="guardianSignatureData">
                    <div class="signature-controls">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearGuardianSignature()">
                            <span class="material-symbols-outlined">refresh</span> クリア
                        </button>
                        <div id="guardianSignatureStatus" class="signature-status optional">
                            <span class="material-symbols-outlined" style="font-size: 16px;">remove</span>
                            未署名（任意）
                        </div>
                    </div>
                    <?php if (!empty($planData['guardian_signature_image'])): ?>
                    <div class="existing-signature">
                        <p>保存済みの保護者署名（<?= $planData['guardian_signature_date'] ?? '' ?>）:</p>
                        <img src="<?= htmlspecialchars($planData['guardian_signature_image']) ?>" alt="保護者署名" class="signature-preview">
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 職員署名 -->
                <div class="signature-item">
                    <div class="signature-label">職員署名（<?= htmlspecialchars($staffName) ?>）</div>
                    <div class="signature-container">
                        <canvas id="staffSignatureCanvas" class="signature-canvas"></canvas>
                    </div>
                    <input type="hidden" name="staff_signature_image" id="staffSignatureData">
                    <div class="signature-controls">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearStaffSignature()">
                            <span class="material-symbols-outlined">refresh</span> クリア
                        </button>
                        <div id="staffSignatureStatus" class="signature-status pending">
                            <span class="material-symbols-outlined" style="font-size: 16px;">edit</span>
                            未署名
                        </div>
                    </div>
                    <?php if (!empty($planData['staff_signature_image'])): ?>
                    <div class="existing-signature">
                        <p>保存済みの職員署名（<?= htmlspecialchars($planData['staff_signer_name'] ?? '') ?> / <?= $planData['staff_signature_date'] ?? '' ?>）:</p>
                        <img src="<?= htmlspecialchars($planData['staff_signature_image']) ?>" alt="職員署名" class="signature-preview">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-section">
                <a href="kobetsu_plan.php?student_id=<?= $planData['student_id'] ?>&plan_id=<?= $planId ?>" class="btn btn-secondary">
                    <span class="material-symbols-outlined">arrow_back</span> 計画書編集に戻る
                </a>
                <button type="submit" id="confirmButton" class="btn btn-confirm" disabled>
                    <span class="material-symbols-outlined">check_circle</span> 確定して保存（正式版として提出）
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$inlineJs = <<<JS
// 署名パッドクラス
class SignaturePad {
    constructor(canvasId, onChangeCallback) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext('2d');
        this.drawing = false;
        this.lastX = 0;
        this.lastY = 0;
        this.hasDrawn = false;
        this.onChangeCallback = onChangeCallback;

        this.init();
    }

    init() {
        // キャンバスサイズを設定
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width;
        this.canvas.height = rect.height;

        this.clear();
        this.bindEvents();
    }

    bindEvents() {
        // マウスイベント
        this.canvas.addEventListener('mousedown', (e) => this.startDrawing(e));
        this.canvas.addEventListener('mousemove', (e) => this.draw(e));
        this.canvas.addEventListener('mouseup', () => this.stopDrawing());
        this.canvas.addEventListener('mouseleave', () => this.stopDrawing());

        // タッチイベント
        this.canvas.addEventListener('touchstart', (e) => this.startDrawing(e));
        this.canvas.addEventListener('touchmove', (e) => this.draw(e));
        this.canvas.addEventListener('touchend', () => this.stopDrawing());
    }

    getCoordinates(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;

        if (e.touches) {
            return {
                x: (e.touches[0].clientX - rect.left) * scaleX,
                y: (e.touches[0].clientY - rect.top) * scaleY
            };
        }
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    startDrawing(e) {
        e.preventDefault();
        this.drawing = true;
        const coords = this.getCoordinates(e);
        this.lastX = coords.x;
        this.lastY = coords.y;
    }

    draw(e) {
        if (!this.drawing) return;
        e.preventDefault();

        const coords = this.getCoordinates(e);

        this.ctx.beginPath();
        this.ctx.strokeStyle = '#000000';
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.moveTo(this.lastX, this.lastY);
        this.ctx.lineTo(coords.x, coords.y);
        this.ctx.stroke();

        this.lastX = coords.x;
        this.lastY = coords.y;

        if (!this.hasDrawn) {
            this.hasDrawn = true;
            if (this.onChangeCallback) this.onChangeCallback();
        }
    }

    stopDrawing() {
        this.drawing = false;
    }

    clear() {
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        this.hasDrawn = false;
        if (this.onChangeCallback) this.onChangeCallback();
    }

    isEmpty() {
        return !this.hasDrawn;
    }

    toDataURL() {
        return this.canvas.toDataURL('image/png');
    }
}

let staffSignaturePad = null;
let guardianSignaturePad = null;

function updateSignatureStatus() {
    const guardianStatus = document.getElementById('guardianSignatureStatus');
    const staffStatus = document.getElementById('staffSignatureStatus');
    const confirmButton = document.getElementById('confirmButton');

    const guardianSigned = guardianSignaturePad && !guardianSignaturePad.isEmpty();
    const staffSigned = staffSignaturePad && !staffSignaturePad.isEmpty();

    // 保護者署名ステータス更新（任意）
    if (guardianSigned) {
        guardianStatus.className = 'signature-status signed';
        guardianStatus.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span> 署名済み';
    } else {
        guardianStatus.className = 'signature-status optional';
        guardianStatus.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">remove</span> 未署名（任意）';
    }

    // 職員署名ステータス更新
    if (staffSigned) {
        staffStatus.className = 'signature-status signed';
        staffStatus.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span> 署名済み';
    } else {
        staffStatus.className = 'signature-status pending';
        staffStatus.innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">edit</span> 未署名';
    }

    // 職員署名があれば確定ボタンを有効化（保護者署名はオプション）
    confirmButton.disabled = !staffSigned;
}

function clearStaffSignature() {
    if (staffSignaturePad) {
        staffSignaturePad.clear();
        document.getElementById('staffSignatureData').value = '';
    }
}

function clearGuardianSignature() {
    if (guardianSignaturePad) {
        guardianSignaturePad.clear();
        document.getElementById('guardianSignatureData').value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 署名パッド初期化
    guardianSignaturePad = new SignaturePad('guardianSignatureCanvas', updateSignatureStatus);
    staffSignaturePad = new SignaturePad('staffSignatureCanvas', updateSignatureStatus);

    // フォーム送信時に署名データを保存
    const signatureForm = document.getElementById('signatureForm');
    if (signatureForm) {
        signatureForm.addEventListener('submit', function(e) {
            // 署名データを設定
            if (guardianSignaturePad && !guardianSignaturePad.isEmpty()) {
                document.getElementById('guardianSignatureData').value = guardianSignaturePad.toDataURL();
            }
            if (staffSignaturePad && !staffSignaturePad.isEmpty()) {
                document.getElementById('staffSignatureData').value = staffSignaturePad.toDataURL();
            }

            // 職員署名があるか確認（保護者署名はオプション）
            if (!document.getElementById('staffSignatureData').value) {
                e.preventDefault();
                alert('職員の署名が必要です。');
                return false;
            }
        });
    }
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
