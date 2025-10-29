<?php
/**
 * 生徒用ダッシュボード
 */

require_once __DIR__ . '/../includes/student_auth.php';
require_once __DIR__ . '/../config/database.php';

requireStudentLogin();

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

// 提出物の未提出数を取得（保護者チャット経由）
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.is_completed = 0 AND sr.due_date >= CURDATE()
");
$stmt->execute([$studentId]);
$pendingSubmissions = $stmt->fetchColumn();

// すべての提出物を取得（統合）
$allSubmissions = [];

// 1. 週間計画表の提出物
$stmt = $pdo->prepare("
    SELECT
        wps.id,
        wps.submission_item as title,
        wps.due_date,
        wps.is_completed,
        DATEDIFF(wps.due_date, CURDATE()) as days_until_due
    FROM weekly_plan_submissions wps
    INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
    WHERE wp.student_id = ? AND wps.is_completed = 0
    ORDER BY wps.due_date ASC
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 2. 保護者チャット経由の提出物
$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.title,
        sr.due_date,
        sr.is_completed,
        DATEDIFF(sr.due_date, CURDATE()) as days_until_due
    FROM submission_requests sr
    INNER JOIN chat_rooms cr ON sr.room_id = cr.id
    WHERE cr.student_id = ? AND sr.is_completed = 0
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 3. 生徒自身が登録した提出物
$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        due_date,
        is_completed,
        DATEDIFF(due_date, CURDATE()) as days_until_due
    FROM student_submissions
    WHERE student_id = ? AND is_completed = 0
");
$stmt->execute([$studentId]);
while ($row = $stmt->fetch()) {
    $allSubmissions[] = $row;
}

// 提出物をカテゴリ分け（1週間以内のもののみ表示）
$overdueSubmissions = [];
$urgentSubmissions = [];
$normalSubmissions = [];

foreach ($allSubmissions as $sub) {
    if ($sub['days_until_due'] < 0) {
        $overdueSubmissions[] = $sub;
    } elseif ($sub['days_until_due'] <= 3) {
        $urgentSubmissions[] = $sub;
    } elseif ($sub['days_until_due'] <= 7) {
        $normalSubmissions[] = $sub;
    }
    // 8日以上先の提出物は表示しない
}

// 新着メッセージ数を取得（生徒用チャット）
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM student_chat_rooms scr
    LEFT JOIN student_chat_messages scm ON scr.id = scm.room_id
    WHERE scr.student_id = ? AND scm.sender_type = 'staff'
      AND scm.created_at > COALESCE(
          (SELECT MAX(created_at) FROM student_chat_messages WHERE room_id = scr.id AND sender_type = 'student'),
          '1970-01-01'
      )
");
$stmt->execute([$studentId]);
$newMessages = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ - 個別支援連絡帳システム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .logout-btn {
            float: right;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .menu-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .menu-item-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .menu-item h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #667eea;
        }

        .menu-item p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e74c3c;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .alerts-section {
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .alert-overdue {
            background: linear-gradient(135deg, #721c24 0%, #c82333 100%);
            color: white;
            font-weight: 600;
        }

        .alert-urgent {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
        }

        .alert-normal {
            background: linear-gradient(135deg, #3498db 0%, #667eea 100%);
            color: white;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .alert-date {
            font-size: 13px;
            opacity: 0.9;
        }

        .alert-action {
            margin-left: 20px;
        }

        .alert-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            white-space: nowrap;
            transition: background 0.2s;
        }

        .alert-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar .today {
            background: #fff3cd;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 0;
            }

            .header {
                padding: 15px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header p {
                font-size: 14px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .menu-item {
                padding: 20px;
            }

            .alert {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .alert-action {
                margin-left: 0;
                width: 100%;
            }

            .alert-btn {
                display: block;
                text-align: center;
                width: 100%;
            }

            .summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="logout.php" class="logout-btn">ログアウト</a>
            <h1>🎓 マイページ</h1>
            <p>ようこそ、<?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さん</p>
        </div>

        <div class="menu-grid">
            <a href="schedule.php" class="menu-item">
                <div class="menu-item-icon">📅</div>
                <h2>スケジュール</h2>
                <p>出席日、イベント、休日を確認</p>
            </a>

            <a href="chat.php" class="menu-item">
                <?php if ($newMessages > 0): ?>
                    <span class="badge"><?php echo $newMessages; ?></span>
                <?php endif; ?>
                <div class="menu-item-icon">💬</div>
                <h2>チャット</h2>
                <p>スタッフとメッセージをやり取り</p>
            </a>

            <a href="weekly_plan.php" class="menu-item">
                <div class="menu-item-icon">📝</div>
                <h2>週間計画表</h2>
                <p>今週の計画を立てる・確認する</p>
            </a>

            <a href="submissions.php" class="menu-item">
                <?php if ($pendingSubmissions > 0): ?>
                    <span class="badge"><?php echo $pendingSubmissions; ?></span>
                <?php endif; ?>
                <div class="menu-item-icon">📤</div>
                <h2>提出物</h2>
                <p>提出物の確認と管理</p>
            </a>

            <a href="change_password.php" class="menu-item">
                <div class="menu-item-icon">🔐</div>
                <h2>パスワード変更</h2>
                <p>ログインパスワードを変更する</p>
            </a>
        </div>

        <!-- 提出物アラート -->
        <?php if (!empty($overdueSubmissions) || !empty($urgentSubmissions) || !empty($normalSubmissions)): ?>
            <div class="alerts-section">
                <!-- 期限超過 -->
                <?php foreach ($overdueSubmissions as $sub): ?>
                    <div class="alert alert-overdue">
                        <div class="alert-content">
                            <div class="alert-title">⚠️ 【期限超過】<?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="alert-date">
                                期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                                （<?php echo abs($sub['days_until_due']); ?>日超過）
                            </div>
                        </div>
                        <div class="alert-action">
                            <a href="weekly_plan.php" class="alert-btn">確認する</a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- 緊急（3日以内） -->
                <?php foreach ($urgentSubmissions as $sub): ?>
                    <div class="alert alert-urgent">
                        <div class="alert-content">
                            <div class="alert-title">🔥 【緊急】<?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="alert-date">
                                期限: <?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>
                                <?php if ($sub['days_until_due'] == 0): ?>
                                    （今日が期限）
                                <?php else: ?>
                                    （あと<?php echo $sub['days_until_due']; ?>日）
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="alert-action">
                            <a href="weekly_plan.php" class="alert-btn">確認する</a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- 1週間以内 -->
                <?php foreach ($normalSubmissions as $sub): ?>
                    <div class="alert alert-normal">
                        <div class="alert-content">
                            <div class="alert-title">📋 <?php echo htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="alert-date">
                                提出期限まであと<?php echo $sub['days_until_due']; ?>日です（<?php echo date('Y年m月d日', strtotime($sub['due_date'])); ?>）
                            </div>
                        </div>
                        <div class="alert-action">
                            <a href="submissions.php" class="alert-btn">確認する</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
