<?php
/**
 * 保護者用かけはし履歴詳細表示・印刷ページ
 * 保護者用とスタッフ用を別々の書類として表示・印刷
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
$type = $_GET['type'] ?? 'guardian'; // 'guardian' or 'staff'

if (!$studentId || !$periodId) {
    $_SESSION['error'] = '生徒IDまたは期間IDが指定されていません。';
    header('Location: kakehashi_history.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 生徒情報を取得（この保護者の子どもかチェック）
$stmt = $pdo->prepare("
    SELECT s.*, c.classroom_name, u.full_name as guardian_name
    FROM students s
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN users u ON s.guardian_id = u.id
    WHERE s.id = ? AND s.guardian_id = ? AND s.is_active = 1
");
$stmt->execute([$studentId, $guardianId]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = '指定された生徒が見つからないか、アクセス権がありません。';
    header('Location: kakehashi_history.php');
    exit;
}

// 期間情報を取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ? AND student_id = ?");
$stmt->execute([$periodId, $studentId]);
$period = $stmt->fetch();

if (!$period) {
    $_SESSION['error'] = '指定された期間が見つかりません。';
    header('Location: kakehashi_history.php');
    exit;
}

// スタッフ用かけはしデータを取得（作成者情報含む）
$stmt = $pdo->prepare("
    SELECT ks.*, u.full_name as staff_name
    FROM kakehashi_staff ks
    LEFT JOIN users u ON ks.staff_id = u.id
    WHERE ks.student_id = ? AND ks.period_id = ?
");
$stmt->execute([$studentId, $periodId]);
$staffData = $stmt->fetch();

// 保護者用かけはしデータを取得
$stmt = $pdo->prepare("SELECT * FROM kakehashi_guardian WHERE student_id = ? AND period_id = ?");
$stmt->execute([$studentId, $periodId]);
$guardianData = $stmt->fetch();

// 表示するデータを選択
$displayData = null;
$documentTitle = '';
$documentSubtitle = '';
$authorLabel = '';
$classroomName = $student['classroom_name'] ?? '事業所';

if ($type === 'staff') {
    if (!$staffData || !$staffData['is_submitted']) {
        $_SESSION['error'] = 'スタッフ提出のかけはしがありません。';
        header('Location: kakehashi_history.php?student_id=' . $studentId);
        exit;
    }
    $displayData = $staffData;
    $documentTitle = 'かけはし（' . $classroomName . '）';
    $documentSubtitle = '事業所入力';
    $authorLabel = $staffData['staff_name'] ?? '担当者';
    $wishLabel = '事業所での願い';
} else {
    if (!$guardianData || !$guardianData['is_submitted']) {
        $_SESSION['error'] = '保護者提出のかけはしがありません。';
        header('Location: kakehashi_history.php?student_id=' . $studentId);
        exit;
    }
    $displayData = $guardianData;
    $documentTitle = 'かけはし（保護者）';
    $documentSubtitle = '保護者入力';
    $authorLabel = $student['guardian_name'] ?? '保護者';
    $wishLabel = '家庭での願い';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($documentTitle) ?> - <?= htmlspecialchars($student['student_name']) ?></title>
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
            background: linear-gradient(135deg, #34c759 0%, #30d158 100%);
            color: white;
        }

        .status-draft {
            background: linear-gradient(135deg, #ff9500 0%, #ff9f0a 100%);
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
    <div class="print-buttons no-print">
        <button class="print-button" onclick="window.print()">印刷 / PDF保存</button>
        <a href="kakehashi_history.php?student_id=<?= $studentId ?>" class="print-button secondary">戻る</a>
    </div>

    <div class="container">
        <!-- ヘッダー -->
        <div class="header">
            <h1><?= htmlspecialchars($documentTitle) ?></h1>
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
                    <span class="meta-label">作成者</span>
                    <span class="meta-value"><?= htmlspecialchars($authorLabel) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">状態</span>
                    <span class="status-badge status-submitted">提出済み</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">対象期間</span>
                    <span class="meta-value"><?= date('Y/m/d', strtotime($period['start_date'])) ?> 〜 <?= date('Y/m/d', strtotime($period['end_date'])) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">提出期限</span>
                    <span class="meta-value"><?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">提出日</span>
                    <span class="meta-value"><?= date('Y/m/d H:i', strtotime($displayData['submitted_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- 本人の願い -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-wish">✨</div>
                    <div class="section-title">本人の願い</div>
                </div>
                <div class="section-content"><?= $displayData['student_wish'] ? htmlspecialchars($displayData['student_wish']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>

            <!-- 家庭での願い / 事業所での願い -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-home"><?= $type === 'staff' ? '🏢' : '🏠' ?></div>
                    <div class="section-title"><?= $wishLabel ?></div>
                </div>
                <div class="section-content"><?= $displayData['home_challenges'] ? htmlspecialchars($displayData['home_challenges']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>

            <!-- 目標設定 -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-goal">🎯</div>
                    <div class="section-title">目標設定</div>
                </div>
                <div class="goals-container">
                    <div class="goal-card">
                        <div class="goal-label">📌 短期目標（6か月）</div>
                        <div class="goal-content"><?= $displayData['short_term_goal'] ? htmlspecialchars($displayData['short_term_goal']) : '<span class="empty-content">（未入力）</span>' ?></div>
                    </div>
                    <div class="goal-card">
                        <div class="goal-label">🚀 長期目標（1年以上）</div>
                        <div class="goal-content"><?= $displayData['long_term_goal'] ? htmlspecialchars($displayData['long_term_goal']) : '<span class="empty-content">（未入力）</span>' ?></div>
                    </div>
                </div>
            </div>

            <!-- 五領域の課題 -->
            <div class="section">
                <div class="domains-section">
                    <div class="domains-header">
                        <div class="section-icon icon-domain">🌟</div>
                        <div class="section-title">五領域の課題</div>
                    </div>
                    <div class="domains-grid">
                        <div class="domain-item">
                            <div class="domain-label">健康・生活</div>
                            <div class="domain-content"><?= $displayData['domain_health_life'] ? htmlspecialchars($displayData['domain_health_life']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">運動・感覚</div>
                            <div class="domain-content"><?= $displayData['domain_motor_sensory'] ? htmlspecialchars($displayData['domain_motor_sensory']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">認知・行動</div>
                            <div class="domain-content"><?= $displayData['domain_cognitive_behavior'] ? htmlspecialchars($displayData['domain_cognitive_behavior']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">言語・コミュニケーション</div>
                            <div class="domain-content"><?= $displayData['domain_language_communication'] ? htmlspecialchars($displayData['domain_language_communication']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                        <div class="domain-item">
                            <div class="domain-label">人間関係・社会性</div>
                            <div class="domain-content"><?= $displayData['domain_social_relations'] ? htmlspecialchars($displayData['domain_social_relations']) : '<span class="empty-content">（未入力）</span>' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- その他の課題 -->
            <div class="section">
                <div class="section-header">
                    <div class="section-icon icon-other">📝</div>
                    <div class="section-title">その他の課題</div>
                </div>
                <div class="section-content"><?= $displayData['other_challenges'] ? htmlspecialchars($displayData['other_challenges']) : '<span class="empty-content">（未入力）</span>' ?></div>
            </div>
        </div>
    </div>
</body>
</html>
