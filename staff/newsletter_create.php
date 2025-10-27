<?php
/**
 * 施設通信作成開始ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 現在の年月を取得
$currentYear = date('Y');
$currentMonth = date('m');

// 既存の通信を取得
$stmt = $pdo->prepare("
    SELECT * FROM newsletters
    ORDER BY year DESC, month DESC
    LIMIT 10
");
$stmt->execute();
$existingNewsletters = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設通信作成 - 個別支援連絡帳システム</title>
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
            max-width: 900px;
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

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-section h2 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .date-range {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 10px;
            align-items: center;
        }

        .date-range span {
            text-align: center;
            color: #666;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .existing-newsletters {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .existing-newsletters h2 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .newsletter-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .newsletter-item:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .newsletter-info {
            flex: 1;
        }

        .newsletter-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .newsletter-meta {
            font-size: 12px;
            color: #666;
        }

        .newsletter-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .status-published {
            background: #d4edda;
            color: #155724;
        }

        .newsletter-actions {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-edit {
            background: #667eea;
            color: white;
        }

        .btn-edit:hover {
            background: #5568d3;
        }

        .btn-view {
            background: #28a745;
            color: white;
        }

        .btn-view:hover {
            background: #218838;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #004085;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .date-range {
                grid-template-columns: 1fr;
            }

            .date-range span {
                display: none;
            }

            .newsletter-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .newsletter-actions {
                margin-left: 0;
                width: 100%;
            }

            .action-btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📰 施設通信作成</h1>
            <a href="renrakucho_activities.php" class="back-btn">← 活動管理へ戻る</a>
        </div>

        <div class="form-section">
            <h2>新しい通信を作成</h2>

            <div class="info-box">
                💡 通信を作成すると、AIが該当期間の連絡帳データを参照して通信の下書きを自動生成します。生成後、内容を確認・編集してから発行してください。
            </div>

            <form method="POST" action="newsletter_edit.php" id="createForm">
                <div class="form-group">
                    <label>通信の年月 *</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <input type="number" name="year" value="<?php echo $currentYear; ?>" min="2020" max="2100" required>
                        </div>
                        <div>
                            <select name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                        <?php echo $m; ?>月
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>報告事項の期間 *</label>
                    <div class="date-range">
                        <input type="date" name="report_start_date" required>
                        <span>～</span>
                        <input type="date" name="report_end_date" required>
                    </div>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        過去の活動記録やイベント結果を報告する期間を指定してください
                    </small>
                </div>

                <div class="form-group">
                    <label>予定連絡の期間 *</label>
                    <div class="date-range">
                        <input type="date" name="schedule_start_date" required>
                        <span>～</span>
                        <input type="date" name="schedule_end_date" required>
                    </div>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        今後の予定イベントを掲載する期間を指定してください
                    </small>
                </div>

                <button type="submit" class="submit-btn">📝 通信を制作する</button>
            </form>
        </div>

        <?php if (!empty($existingNewsletters)): ?>
        <div class="existing-newsletters">
            <h2>既存の通信一覧</h2>

            <?php foreach ($existingNewsletters as $newsletter): ?>
                <div class="newsletter-item">
                    <div class="newsletter-info">
                        <div class="newsletter-title">
                            <?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="newsletter-meta">
                            報告: <?php echo date('Y/m/d', strtotime($newsletter['report_start_date'])); ?>
                            ～ <?php echo date('Y/m/d', strtotime($newsletter['report_end_date'])); ?>
                            | 予定: <?php echo date('Y/m/d', strtotime($newsletter['schedule_start_date'])); ?>
                            ～ <?php echo date('Y/m/d', strtotime($newsletter['schedule_end_date'])); ?>
                        </div>
                    </div>
                    <span class="newsletter-status status-<?php echo $newsletter['status']; ?>">
                        <?php echo $newsletter['status'] === 'published' ? '発行済み' : '下書き'; ?>
                    </span>
                    <div class="newsletter-actions">
                        <a href="newsletter_edit.php?id=<?php echo $newsletter['id']; ?>" class="action-btn btn-edit">
                            編集
                        </a>
                        <?php if ($newsletter['status'] === 'published'): ?>
                        <a href="newsletter_view.php?id=<?php echo $newsletter['id']; ?>" class="action-btn btn-view">
                            表示
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // フォームのバリデーション
        document.getElementById('createForm').addEventListener('submit', function(e) {
            const reportStart = new Date(document.querySelector('input[name="report_start_date"]').value);
            const reportEnd = new Date(document.querySelector('input[name="report_end_date"]').value);
            const scheduleStart = new Date(document.querySelector('input[name="schedule_start_date"]').value);
            const scheduleEnd = new Date(document.querySelector('input[name="schedule_end_date"]').value);

            if (reportStart > reportEnd) {
                alert('報告事項の期間が不正です。開始日は終了日より前である必要があります。');
                e.preventDefault();
                return false;
            }

            if (scheduleStart > scheduleEnd) {
                alert('予定連絡の期間が不正です。開始日は終了日より前である必要があります。');
                e.preventDefault();
                return false;
            }

            return true;
        });

        // 今月の日付を自動設定
        window.addEventListener('DOMContentLoaded', function() {
            const year = document.querySelector('input[name="year"]').value;
            const month = document.querySelector('select[name="month"]').value.padStart(2, '0');

            // 報告期間: 前月1日～前月末日
            const lastMonth = new Date(year, month - 2, 1);
            const lastMonthEnd = new Date(year, month - 1, 0);
            document.querySelector('input[name="report_start_date"]').value =
                lastMonth.toISOString().split('T')[0];
            document.querySelector('input[name="report_end_date"]').value =
                lastMonthEnd.toISOString().split('T')[0];

            // 予定期間: 今月1日～今月末日
            const thisMonth = new Date(year, month - 1, 1);
            const thisMonthEnd = new Date(year, month, 0);
            document.querySelector('input[name="schedule_start_date"]').value =
                thisMonth.toISOString().split('T')[0];
            document.querySelector('input[name="schedule_end_date"]').value =
                thisMonthEnd.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
