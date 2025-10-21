<?php
/**
 * 支援案一覧ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

// 支援案一覧を取得（同じ教室のスタッフが作成した支援案を全て表示、日付順）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT sp.*, u.full_name as staff_name,
               COUNT(DISTINCT dr.id) as usage_count
        FROM support_plans sp
        INNER JOIN users u ON sp.staff_id = u.id
        LEFT JOIN daily_records dr ON sp.id = dr.support_plan_id
        WHERE sp.classroom_id = ?
        GROUP BY sp.id
        ORDER BY sp.activity_date DESC, sp.created_at DESC
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT sp.*, u.full_name as staff_name,
               COUNT(DISTINCT dr.id) as usage_count
        FROM support_plans sp
        INNER JOIN users u ON sp.staff_id = u.id
        LEFT JOIN daily_records dr ON sp.id = dr.support_plan_id
        GROUP BY sp.id
        ORDER BY sp.activity_date DESC, sp.created_at DESC
    ");
    $stmt->execute();
}
$supportPlans = $stmt->fetchAll();

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];

    try {
        // 使用中かチェック
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM daily_records WHERE support_plan_id = ?
        ");
        $stmt->execute([$deleteId]);
        $usageCount = $stmt->fetchColumn();

        if ($usageCount > 0) {
            $_SESSION['error'] = 'この支援案は既に活動で使用されているため削除できません';
        } else {
            $stmt = $pdo->prepare("DELETE FROM support_plans WHERE id = ?");
            $stmt->execute([$deleteId]);
            $_SESSION['success'] = '支援案を削除しました';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }

    header('Location: support_plans.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支援案一覧 - 個別支援連絡帳システム</title>
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            font-weight: 600;
        }

        .btn-create {
            background: #28a745;
            color: white;
        }

        .btn-create:hover {
            background: #218838;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .plan-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .plan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .plan-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .plan-meta {
            font-size: 13px;
            color: #666;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .usage-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #667eea;
            color: white;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .plan-content {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .plan-section {
            margin-bottom: 10px;
        }

        .plan-section-title {
            font-weight: 600;
            color: #667eea;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .plan-section-content {
            color: #555;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .plan-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .empty-message {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .empty-message h2 {
            margin-bottom: 10px;
            color: #666;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1>📝 支援案一覧</h1>
                    <?php if ($classroom): ?>
                        <div style="font-size: 14px; color: #666; margin-top: 5px;">
                            <?= htmlspecialchars($classroom['classroom_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <a href="support_plan_form.php" class="btn btn-create">+ 新しい支援案を作成</a>
                    <a href="renrakucho_activities.php" class="btn btn-back">← 活動管理へ</a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($supportPlans)): ?>
            <div class="empty-message">
                <h2>支援案が登録されていません</h2>
                <p>「新しい支援案を作成」ボタンから支援案を作成してください。</p>
            </div>
        <?php else: ?>
            <?php foreach ($supportPlans as $plan): ?>
                <div class="plan-card">
                    <div class="plan-header">
                        <div style="flex: 1;">
                            <div class="plan-title">
                                <?php echo htmlspecialchars($plan['activity_name']); ?>
                                <span style="font-size: 16px; color: #667eea; font-weight: normal; margin-left: 10px;">
                                    📅 <?php echo date('Y年n月j日', strtotime($plan['activity_date'])); ?>
                                </span>
                            </div>
                            <div class="plan-meta">
                                <span>作成者: <?php echo htmlspecialchars($plan['staff_name']); ?></span>
                                <span>作成日: <?php echo date('Y年n月j日', strtotime($plan['created_at'])); ?></span>
                                <?php if ($plan['usage_count'] > 0): ?>
                                    <span class="usage-badge">使用回数: <?php echo $plan['usage_count']; ?>回</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="plan-content">
                        <?php if (!empty($plan['activity_purpose'])): ?>
                            <div class="plan-section">
                                <div class="plan-section-title">活動の目的</div>
                                <div class="plan-section-content"><?php echo htmlspecialchars($plan['activity_purpose']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plan['activity_content'])): ?>
                            <div class="plan-section">
                                <div class="plan-section-title">活動の内容</div>
                                <div class="plan-section-content"><?php echo htmlspecialchars($plan['activity_content']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plan['five_domains_consideration'])): ?>
                            <div class="plan-section">
                                <div class="plan-section-title">五領域への配慮</div>
                                <div class="plan-section-content"><?php echo htmlspecialchars($plan['five_domains_consideration']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plan['other_notes'])): ?>
                            <div class="plan-section">
                                <div class="plan-section-title">その他</div>
                                <div class="plan-section-content"><?php echo htmlspecialchars($plan['other_notes']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="plan-actions">
                        <a href="support_plan_form.php?id=<?php echo $plan['id']; ?>" class="btn btn-small btn-edit">編集</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('この支援案を削除しますか？<?php echo $plan['usage_count'] > 0 ? '\n\n注意: この支援案は' . $plan['usage_count'] . '回使用されています。' : ''; ?>');">
                            <input type="hidden" name="delete_id" value="<?php echo $plan['id']; ?>">
                            <button type="submit" class="btn btn-small btn-delete">削除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
