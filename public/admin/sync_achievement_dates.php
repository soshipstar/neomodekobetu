<?php
/**
 * 個別支援計画書の達成時期一括同期スクリプト
 *
 * individual_support_plan_details.achievement_date を
 * 対応する individual_support_plans.short_term_goal_date に統一します。
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 管理者のみ実行可能
requireLogin();
checkUserType('admin');

$pdo = getDbConnection();
$message = '';
$results = [];

// 実行前の状態を確認
$stmt = $pdo->query("
    SELECT
        isp.id as plan_id,
        isp.student_id,
        s.student_name,
        isp.short_term_goal_date,
        isp.created_date as plan_created_date,
        COUNT(ispd.id) as detail_count,
        GROUP_CONCAT(DISTINCT ispd.achievement_date ORDER BY ispd.achievement_date SEPARATOR ', ') as current_achievement_dates
    FROM individual_support_plans isp
    LEFT JOIN individual_support_plan_details ispd ON isp.id = ispd.plan_id
    LEFT JOIN students s ON isp.student_id = s.id
    WHERE isp.short_term_goal_date IS NOT NULL
    GROUP BY isp.id
    ORDER BY isp.created_date DESC
");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// POST時に実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("
            UPDATE individual_support_plan_details ispd
            INNER JOIN individual_support_plans isp ON ispd.plan_id = isp.id
            SET ispd.achievement_date = isp.short_term_goal_date
            WHERE isp.short_term_goal_date IS NOT NULL
        ");
        $updateStmt->execute();
        $affectedRows = $updateStmt->rowCount();

        $pdo->commit();
        $message = "成功: {$affectedRows}件の達成時期を更新しました。";

        // 更新後の状態を再取得
        $stmt = $pdo->query("
            SELECT
                isp.id as plan_id,
                isp.student_id,
                s.student_name,
                isp.short_term_goal_date,
                isp.created_date as plan_created_date,
                COUNT(ispd.id) as detail_count,
                GROUP_CONCAT(DISTINCT ispd.achievement_date ORDER BY ispd.achievement_date SEPARATOR ', ') as current_achievement_dates
            FROM individual_support_plans isp
            LEFT JOIN individual_support_plan_details ispd ON isp.id = ispd.plan_id
            LEFT JOIN students s ON isp.student_id = s.id
            WHERE isp.short_term_goal_date IS NOT NULL
            GROUP BY isp.id
            ORDER BY isp.created_date DESC
        ");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>達成時期一括同期 - 管理者ツール</title>
    <link rel="stylesheet" href="/assets/css/apple-design.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--apple-bg-secondary);
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--apple-bg-primary);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .description {
            color: var(--text-secondary);
            margin-bottom: 20px;
            padding: 15px;
            background: var(--apple-bg-tertiary);
            border-radius: 8px;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success {
            background: rgba(52, 199, 89, 0.2);
            color: #34c759;
            border: 1px solid #34c759;
        }
        .message.error {
            background: rgba(255, 59, 48, 0.2);
            color: #ff3b30;
            border: 1px solid #ff3b30;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }
        th {
            background: var(--apple-bg-tertiary);
            color: var(--text-primary);
            font-weight: 600;
        }
        td {
            color: var(--text-secondary);
        }
        .mismatch {
            background: rgba(255, 149, 0, 0.1);
        }
        .match {
            background: rgba(52, 199, 89, 0.1);
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-primary {
            background: #007aff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: var(--apple-bg-tertiary);
            color: var(--text-primary);
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .warning {
            color: #ff9500;
            font-weight: bold;
        }
        .ok {
            color: #34c759;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>達成時期一括同期ツール</h1>

        <div class="description">
            <p><strong>このツールについて:</strong></p>
            <p>個別支援計画書の「支援目標及び具体的な支援内容等」の各行にある達成時期を、
            その計画書の「短期目標の達成時期」に統一します。</p>
            <p>処理内容: <code>individual_support_plan_details.achievement_date</code> = <code>individual_support_plans.short_term_goal_date</code></p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'エラー') !== false ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <h2>現在の状態</h2>
        <table>
            <thead>
                <tr>
                    <th>計画ID</th>
                    <th>生徒名</th>
                    <th>作成日</th>
                    <th>短期目標達成時期</th>
                    <th>明細件数</th>
                    <th>現在の達成時期</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan):
                    $shortTermDate = $plan['short_term_goal_date'];
                    $currentDates = $plan['current_achievement_dates'];
                    $isMatch = ($currentDates === $shortTermDate || $currentDates === null);
                ?>
                    <tr class="<?= $isMatch ? 'match' : 'mismatch' ?>">
                        <td><?= $plan['plan_id'] ?></td>
                        <td><?= htmlspecialchars($plan['student_name'] ?? '不明') ?></td>
                        <td><?= $plan['plan_created_date'] ?></td>
                        <td><?= $shortTermDate ?></td>
                        <td><?= $plan['detail_count'] ?></td>
                        <td><?= $currentDates ?: '(なし)' ?></td>
                        <td>
                            <?php if ($isMatch): ?>
                                <span class="ok">OK</span>
                            <?php else: ?>
                                <span class="warning">要同期</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" onsubmit="return confirm('全ての個別支援計画書の達成時期を短期目標の達成時期に統一します。\nこの操作は元に戻せません。\n実行しますか？');">
            <div class="actions">
                <button type="submit" name="execute" value="1" class="btn btn-primary">
                    一括同期を実行
                </button>
                <a href="index.php" class="btn btn-secondary">管理画面に戻る</a>
            </div>
        </form>
    </div>
</body>
</html>
