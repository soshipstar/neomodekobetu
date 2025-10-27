<?php
/**
 * 保護者向け施設通信閲覧ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// 保護者のみアクセス可能
requireUserType(['guardian']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 発行済み通信を取得（新しい順）
$stmt = $pdo->prepare("
    SELECT * FROM newsletters
    WHERE status = 'published'
    ORDER BY year DESC, month DESC
");
$stmt->execute();
$newsletters = $stmt->fetchAll();

// 詳細表示用の通信
$selectedNewsletter = null;
if (isset($_GET['id'])) {
    $newsletterId = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT * FROM newsletters
        WHERE id = ? AND status = 'published'
    ");
    $stmt->execute([$newsletterId]);
    $selectedNewsletter = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設通信 - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .back-btn {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .newsletter-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .newsletter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .newsletter-card h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .newsletter-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .newsletter-date {
            font-size: 14px;
            color: #999;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .newsletter-detail {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            line-height: 1.8;
        }

        .newsletter-detail h2 {
            color: #333;
            font-size: 28px;
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .detail-metadata {
            text-align: right;
            color: #666;
            font-size: 13px;
            margin-bottom: 30px;
        }

        .detail-section {
            margin: 30px 0;
        }

        .detail-section h3 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-left: 5px solid #667eea;
            border-radius: 3px;
        }

        .detail-section-content {
            padding: 15px 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .greeting-section {
            background: #fff9e6;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }

        .back-to-list {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 30px;
        }

        .back-to-list:hover {
            background: #5568d3;
        }

        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state-text {
            color: #666;
            font-size: 16px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .header, .back-to-list, .back-btn {
                display: none;
            }

            .newsletter-detail {
                box-shadow: none;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .newsletters-grid {
                grid-template-columns: 1fr;
            }

            .newsletter-detail {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($selectedNewsletter): ?>
            <!-- 通信詳細表示 -->
            <div class="header">
                <h1>📰 施設通信</h1>
                <button onclick="window.print()" class="back-btn" style="cursor: pointer;">🖨️ 印刷</button>
            </div>

            <div class="newsletter-detail">
                <h2><?php echo htmlspecialchars($selectedNewsletter['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

                <div class="detail-metadata">
                    報告期間: <?php echo date('Y年m月d日', strtotime($selectedNewsletter['report_start_date'])); ?>
                    ～ <?php echo date('Y年m月d日', strtotime($selectedNewsletter['report_end_date'])); ?><br>
                    予定期間: <?php echo date('Y年m月d日', strtotime($selectedNewsletter['schedule_start_date'])); ?>
                    ～ <?php echo date('Y年m月d日', strtotime($selectedNewsletter['schedule_end_date'])); ?><br>
                    発行日: <?php echo date('Y年m月d日', strtotime($selectedNewsletter['published_at'])); ?>
                </div>

                <?php if (!empty($selectedNewsletter['greeting'])): ?>
                <div class="greeting-section">
                    <?php echo nl2br(htmlspecialchars($selectedNewsletter['greeting'], ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['event_calendar'])): ?>
                <div class="detail-section">
                    <h3>📅 今月の予定</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['event_calendar'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['event_details'])): ?>
                <div class="detail-section">
                    <h3>📝 イベント詳細</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['event_details'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['weekly_reports'])): ?>
                <div class="detail-section">
                    <h3>📖 各曜日の活動報告</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['weekly_reports'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['event_results'])): ?>
                <div class="detail-section">
                    <h3>🎉 イベント結果報告</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['event_results'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['requests'])): ?>
                <div class="detail-section">
                    <h3>🙏 施設からのお願い</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['requests'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($selectedNewsletter['others'])): ?>
                <div class="detail-section">
                    <h3>📌 その他</h3>
                    <div class="detail-section-content">
                        <?php echo htmlspecialchars($selectedNewsletter['others'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <a href="newsletters.php" class="back-to-list">← 一覧に戻る</a>
            </div>

        <?php else: ?>
            <!-- 通信一覧表示 -->
            <div class="header">
                <h1>📰 施設通信</h1>
                <a href="dashboard.php" class="back-btn">← ダッシュボードへ戻る</a>
            </div>

            <?php if (empty($newsletters)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-text">まだ通信が発行されていません</div>
                </div>
            <?php else: ?>
                <div class="newsletters-grid">
                    <?php foreach ($newsletters as $newsletter): ?>
                        <a href="newsletters.php?id=<?php echo $newsletter['id']; ?>" class="newsletter-card">
                            <h3><?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="newsletter-meta">
                                報告: <?php echo date('Y/m/d', strtotime($newsletter['report_start_date'])); ?>
                                ～ <?php echo date('Y/m/d', strtotime($newsletter['report_end_date'])); ?>
                            </div>
                            <div class="newsletter-meta">
                                予定: <?php echo date('Y/m/d', strtotime($newsletter['schedule_start_date'])); ?>
                                ～ <?php echo date('Y/m/d', strtotime($newsletter['schedule_end_date'])); ?>
                            </div>
                            <div class="newsletter-date">
                                発行日: <?php echo date('Y年m月d日', strtotime($newsletter['published_at'])); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
