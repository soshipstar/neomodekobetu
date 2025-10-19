<?php
/**
 * 全かけはしデータ削除スクリプト
 * 注意: このスクリプトは全てのかけはし期間とその関連データを削除します
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=UTF-8');

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'DELETE_ALL') {
    try {
        $pdo->beginTransaction();

        // 1. kakehashi_guardian のデータを削除
        $stmt = $pdo->query("DELETE FROM kakehashi_guardian");
        $guardianCount = $stmt->rowCount();

        // 2. kakehashi_staff のデータを削除
        $stmt = $pdo->query("DELETE FROM kakehashi_staff");
        $staffCount = $stmt->rowCount();

        // 3. kakehashi_periods のデータを削除
        $stmt = $pdo->query("DELETE FROM kakehashi_periods");
        $periodCount = $stmt->rowCount();

        $pdo->commit();

        echo "<!DOCTYPE html>";
        echo "<html lang='ja'><head><meta charset='UTF-8'><title>削除完了</title></head><body>";
        echo "<h1 style='color: green;'>✅ 削除完了</h1>";
        echo "<p>以下のデータを削除しました：</p>";
        echo "<ul>";
        echo "<li>かけはし期間: {$periodCount} 件</li>";
        echo "<li>保護者かけはし: {$guardianCount} 件</li>";
        echo "<li>スタッフかけはし: {$staffCount} 件</li>";
        echo "</ul>";
        echo "<p><a href='check_student_data.php'>データ確認ページへ</a></p>";
        echo "</body></html>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<!DOCTYPE html>";
        echo "<html lang='ja'><head><meta charset='UTF-8'><title>エラー</title></head><body>";
        echo "<h1 style='color: red;'>❌ エラー発生</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='javascript:history.back()'>戻る</a></p>";
        echo "</body></html>";
        exit;
    }
}

// 確認画面
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>かけはしデータ全削除</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .danger {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        button {
            padding: 15px 30px;
            font-size: 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            margin: 10px 5px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 16px;
            width: 300px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <h1>⚠️ かけはしデータ全削除</h1>

    <div class="danger">
        <h2>⚠️ 警告</h2>
        <p><strong>この操作は元に戻せません！</strong></p>
        <p>以下の全てのデータが完全に削除されます：</p>
        <ul>
            <li>全てのかけはし期間データ</li>
            <li>全ての保護者かけはし入力データ</li>
            <li>全てのスタッフかけはし入力データ</li>
        </ul>
    </div>

    <?php
    // 現在のデータ件数を表示
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kakehashi_periods");
    $periodCount = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kakehashi_guardian");
    $guardianCount = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM kakehashi_staff");
    $staffCount = $stmt->fetch()['count'];
    ?>

    <div class="info">
        <h3>現在のデータ件数</h3>
        <table>
            <tr>
                <th>データ種別</th>
                <th>件数</th>
            </tr>
            <tr>
                <td>かけはし期間</td>
                <td><?= $periodCount ?> 件</td>
            </tr>
            <tr>
                <td>保護者かけはし</td>
                <td><?= $guardianCount ?> 件</td>
            </tr>
            <tr>
                <td>スタッフかけはし</td>
                <td><?= $staffCount ?> 件</td>
            </tr>
        </table>
    </div>

    <div class="warning">
        <h3>📝 削除実行方法</h3>
        <p>本当に削除する場合は、下のテキストボックスに <code>DELETE_ALL</code> と入力して「削除実行」ボタンを押してください。</p>

        <form method="POST" onsubmit="return confirm('本当に全てのかけはしデータを削除しますか？この操作は元に戻せません！');">
            <p>
                <label>確認テキスト: </label>
                <input type="text" name="confirm" placeholder="DELETE_ALL と入力" required>
            </p>
            <p>
                <button type="submit" class="btn-danger">🗑️ 削除実行</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='check_student_data.php'">キャンセル</button>
            </p>
        </form>
    </div>

    <p><a href="check_student_data.php">← データ確認ページに戻る</a></p>
</body>
</html>
