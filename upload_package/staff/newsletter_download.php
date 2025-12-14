<?php
/**
 * 施設通信ダウンロード
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

$id = $_GET['id'] ?? null;

if (!$id) {
    die('通信IDが指定されていません');
}

// 通信を取得
$stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
$stmt->execute([$id]);
$newsletter = $stmt->fetch();

if (!$newsletter) {
    die('通信が見つかりません');
}

// ファイル名を生成
$filename = sprintf(
    "%d年%d月通信_%s.doc",
    $newsletter['year'],
    $newsletter['month'],
    date('Ymd')
);

// ヘッダーを設定（Word文書としてダウンロード）
header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// HTML出力（Wordで開ける形式）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            font-family: 'メイリオ', 'Meiryo', sans-serif;
            line-height: 1.8;
            padding: var(--spacing-2xl);
            max-width: 800px;
            margin: 0 auto;
        }

        h1 {
            font-size: 24pt;
            text-align: center;
            margin-bottom: var(--spacing-2xl);
            border-bottom: 3px solid var(--text-primary);
            padding-bottom: 10px;
        }

        h2 {
            font-size: 18pt;
            margin-top: var(--spacing-2xl);
            margin-bottom: 15px;
            padding: var(--spacing-md);
            background-color: var(--apple-bg-secondary);
            border-left: 5px solid var(--primary-purple);
        }

        p {
            margin: var(--spacing-md) 0;
            text-indent: 1em;
        }

        .metadata {
            text-align: right;
            color: var(--text-secondary);
            font-size: 10pt;
            margin-bottom: var(--spacing-lg);
        }

        .greeting {
            margin: var(--spacing-lg) 0;
            text-align: justify;
        }

        .content-section {
            margin: 25px 0;
            page-break-inside: avoid;
        }

        .event-item {
            margin: var(--spacing-md) 0 10px 20px;
        }

        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'メイリオ', 'Meiryo', sans-serif;
            line-height: 1.8;
            margin: var(--spacing-md) 0;
        }
    </style>
</head>
<body>
    <h1><?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?></h1>

    <div class="metadata">
        報告期間: <?php echo date('Y年m月d日', strtotime($newsletter['report_start_date'])); ?>
        ～ <?php echo date('Y年m月d日', strtotime($newsletter['report_end_date'])); ?><br>
        予定期間: <?php echo date('Y年m月d日', strtotime($newsletter['schedule_start_date'])); ?>
        ～ <?php echo date('Y年m月d日', strtotime($newsletter['schedule_end_date'])); ?>
    </div>

    <?php if (!empty($newsletter['greeting'])): ?>
    <div class="content-section greeting">
        <?php echo nl2br(htmlspecialchars($newsletter['greeting'], ENT_QUOTES, 'UTF-8')); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['event_calendar'])): ?>
    <div class="content-section">
        <h2>📅 今月の予定</h2>
        <pre><?php echo htmlspecialchars($newsletter['event_calendar'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['event_details'])): ?>
    <div class="content-section">
        <h2>📝 イベント詳細</h2>
        <pre><?php echo htmlspecialchars($newsletter['event_details'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['weekly_reports'])): ?>
    <div class="content-section">
        <h2>📖 各曜日の活動報告</h2>
        <pre><?php echo htmlspecialchars($newsletter['weekly_reports'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['event_results'])): ?>
    <div class="content-section">
        <h2>🎉 イベント結果報告</h2>
        <pre><?php echo htmlspecialchars($newsletter['event_results'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['requests'])): ?>
    <div class="content-section">
        <h2>🙏 施設からのお願い</h2>
        <pre><?php echo htmlspecialchars($newsletter['requests'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <?php if (!empty($newsletter['others'])): ?>
    <div class="content-section">
        <h2>📌 その他</h2>
        <pre><?php echo htmlspecialchars($newsletter['others'], ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
    <?php endif; ?>

    <div class="metadata" style="margin-top: 40px; border-top: 1px solid var(--apple-gray-4); padding-top: 10px;">
        発行日: <?php echo $newsletter['published_at'] ? date('Y年m月d日', strtotime($newsletter['published_at'])) : '未発行'; ?>
    </div>
</body>
</html>
