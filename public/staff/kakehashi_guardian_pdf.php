<?php
/**
 * スタッフ用 保護者かけはし PDF出力
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';

// 認証チェック（スタッフのみ）
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$studentId = $_GET['student_id'] ?? null;
$periodId = $_GET['period_id'] ?? null;

if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒IDまたは期間IDが指定されていません。';
    header('Location: kakehashi_guardian_view.php');
    exit;
}

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 生徒情報を取得（自分の教室のみ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.id = ? AND s.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
}
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = '指定された生徒が見つかりません、またはアクセス権限がありません。';
    header('Location: kakehashi_guardian_view.php');
    exit;
}

// 期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ? AND student_id = ?");
$stmt->execute([$periodId, $studentId]);
$period = $stmt->fetch();

if (!$period) {
    $_SESSION['error'] = '指定された期間が見つかりません。';
    header('Location: kakehashi_guardian_view.php');
    exit;
}

// かけはしデータを取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_guardian WHERE student_id = ? AND period_id = ?");
$stmt->execute([$studentId, $periodId]);
$kakehashiData = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>かけはし（保護者） - <?= htmlspecialchars($student['student_name']) ?></title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                background: white;
            }
            .container {
                box-shadow: none;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            background: #f5f5f7;
            color: #1d1d1f;
            padding: 20px;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            border-radius: 18px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* ヘッダー */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            text-align: center;
        }

        .header h1 {
            font-size: 18pt;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .header .subtitle {
            font-size: 9pt;
            opacity: 0.9;
            margin-top: 4px;
        }

        /* メタ情報 */
        .meta-card {
            background: #fafafa;
            margin: 16px;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid #e5e5e7;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .meta-label {
            font-size: 8pt;
            color: #86868b;
            font-weight: 500;
        }

        .meta-value {
            font-size: 10pt;
            color: #1d1d1f;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 600;
        }

        .status-submitted {
            background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%);
            color: white;
        }

        .status-draft {
            background: linear-gradient(135deg, #FF9800 0%, #ff9f0a 100%);
            color: white;
        }

        /* コンテンツエリア */
        .content {
            padding: 0 16px 16px;
        }

        /* セクション */
        .section {
            margin-bottom: 12px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .section-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .section-title {
            font-size: 11pt;
            font-weight: 600;
            color: #1d1d1f;
        }

        .section-content {
            background: #f5f5f7;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 9pt;
            line-height: 1.6;
            color: #1d1d1f;
            white-space: pre-wrap;
            min-height: 40px;
        }

        .empty-content {
            color: #86868b;
            font-style: italic;
        }

        /* 目標セクション */
        .goals-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .goal-card {
            background: #f5f5f7;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .goal-label {
            font-size: 8pt;
            color: #86868b;
            font-weight: 600;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .goal-content {
            font-size: 9pt;
            line-height: 1.5;
            color: #1d1d1f;
            white-space: pre-wrap;
        }

        /* 五領域 */
        .domains-section {
            background: #fafafa;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid #e5e5e7;
        }

        .domains-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e7;
        }

        .domains-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .domain-item {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            align-items: start;
        }

        .domain-label {
            font-size: 9pt;
            font-weight: 600;
            color: #1d1d1f;
            padding: 8px 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e5e7;
            text-align: center;
        }

        .domain-content {
            font-size: 9pt;
            line-height: 1.5;
            color: #1d1d1f;
            background: white;
            border-radius: 8px;
            padding: 8px 10px;
            white-space: pre-wrap;
            min-height: 32px;
        }

        /* カラーテーマ */
        .icon-wish { background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%); }
        .icon-home { background: linear-gradient(135deg, #4ecdc4 0%, #6ee7de 100%); }
        .icon-goal { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .icon-domain { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .icon-other { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        /* 印刷ボタン */
        .print-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .print-button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .print-button.secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        .print-button.secondary:hover {
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.4);
        }
    </style>
</head>
<body>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <div class="print-buttons no-print">
        <button class="print-button" onclick="window.print()"><span class="material-symbols-outlined">print</span> 印刷 / PDF保存</button>
        <a href="kakehashi_guardian_view.php?student_id=<?= $studentId ?>&period_id=<?= $periodId ?>" class="print-button secondary">戻る</a>
    </div>

    <div class="container">
        <!-- ヘッダー -->
        <div class="header">
            <h1>かけはし（保護者入力）</h1>
            <div class="subtitle"><?= getIndividualSupportPlanStartMonth($period) ?>開始 個別支援計画用</div>
        </div>

        <!-- メタ情報 -->
        <div class="meta-card">
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">児童氏名</span>
                    <span class="meta-value"><?= htmlspecialchars($student['student_name']) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">保護者</span>
                    <span class="meta-value"><?= htmlspecialchars($student['guardian_name'] ?? '未設定') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">状態</span>
                    <?php if ($kakehashiData && $kakehashiData['is_submitted']): ?>
                        <span class="status-badge status-submitted">提出済み</span>
                    <?php else: ?>
                        <span class="status-badge status-draft">下書き</span>
                    <?php endif; ?>
                </div>
                <div class="meta-item">
                    <span class="meta-label">対象期間</span>
                    <span class="meta-value"><?= date('Y/m/d', strtotime($period['start_date'])) ?> 〜 <?= date('Y/m/d', strtotime($period['end_date'])) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">提出期限</span>
                    <span class="meta-value"><?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?></span>
                </div>
                <?php if ($kakehashiData && $kakehashiData['is_submitted']): ?>
                <div class="meta-item">
                    <span class="meta-label">提出日</span>
                    <span class="meta-value"><?= date('Y/m/d H:i', strtotime($kakehashiData['submitted_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content">
            <!-- 本人の願い -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-wish"><span class="material-symbols-outlined">auto_awesome</span></div>
                    <div class="section-title">本人の願い</div>
                </div>
                <div class="section-content"><?= $kakehashiData && $kakehashiData['student_wish'] ? htmlspecialchars($kakehashiData['student_wish']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>

            <!-- 家庭での願い -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-home"><span class="material-symbols-outlined">home</span></div>
                    <div class="section-title">家庭での願い</div>
                </div>
                <div class="section-content"><?= $kakehashiData && $kakehashiData['home_challenges'] ? htmlspecialchars($kakehashiData['home_challenges']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>

            <!-- 目標設定 -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-goal"><span class="material-symbols-outlined">flag</span></div>
                    <div class="section-title">目標設定</div>
                </div>
                <div class="goals-container">
                    <div class="goal-card">
                        <div class="goal-label"><span class="material-symbols-outlined">push_pin</span> 短期目標（6か月）</div>
                        <div class="goal-content"><?= $kakehashiData && $kakehashiData['short_term_goal'] ? htmlspecialchars($kakehashiData['short_term_goal']) : '<span class="empty-content">（未入力）</span>' ?></div>
                    </div>
                    <div class="goal-card">
                        <div class="goal-label"><span class="material-symbols-outlined">rocket_launch</span> 長期目標（1年以上）</div>
                        <div class="goal-content"><?= $kakehashiData && $kakehashiData['long_term_goal'] ? htmlspecialchars($kakehashiData['long_term_goal']) : '<span class="empty-content">（未入力）</span>' ?></div>
                    </div>
                </div>
            </div>

            <!-- 五領域の課題 -->
            <div class="section">
                <div class="domains-section">
                    <div class="domains-header">
                        <div class="section-icon icon-domain"><span class="material-symbols-outlined">star</span></div>
                        <div class="section-title">五領域の課題</div>
                    </div>
                    <div class="domains-grid">
                        <div class="domain-item">
                            <div class="domain-label">健康・生活</div>
                            <div class="domain-content"><?= $kakehashiData && $kakehashiData['domain_health_life'] ? htmlspecialchars($kakehashiData['domain_health_life']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">運動・感覚</div>
                            <div class="domain-content"><?= $kakehashiData && $kakehashiData['domain_motor_sensory'] ? htmlspecialchars($kakehashiData['domain_motor_sensory']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">認知・行動</div>
                            <div class="domain-content"><?= $kakehashiData && $kakehashiData['domain_cognitive_behavior'] ? htmlspecialchars($kakehashiData['domain_cognitive_behavior']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">言語・コミュニケーション</div>
                            <div class="domain-content"><?= $kakehashiData && $kakehashiData['domain_language_communication'] ? htmlspecialchars($kakehashiData['domain_language_communication']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">人間関係・社会性</div>
                            <div class="domain-content"><?= $kakehashiData && $kakehashiData['domain_social_relations'] ? htmlspecialchars($kakehashiData['domain_social_relations']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- その他の課題 -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-other"><span class="material-symbols-outlined">edit_note</span></div>
                    <div class="section-title">その他の課題</div>
                </div>
                <div class="section-content"><?= $kakehashiData && $kakehashiData['other_challenges'] ? htmlspecialchars($kakehashiData['other_challenges']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>
        </div>
    </div>
</body>
</html>
