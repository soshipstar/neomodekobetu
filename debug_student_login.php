<?php
/**
 * 生徒ログイン情報のデバッグスクリプト
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

// ログイン情報が設定されている生徒を取得
$stmt = $pdo->query("
    SELECT
        id,
        student_name,
        username,
        password_hash,
        password_plain,
        last_login
    FROM students
    WHERE username IS NOT NULL
    ORDER BY id
");

$students = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒ログイン情報デバッグ</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .hash-short {
            font-family: monospace;
            font-size: 11px;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .test-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .test-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #5568d3;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 生徒ログイン情報デバッグ</h1>

        <p>登録されている生徒ログイン情報: <strong><?php echo count($students); ?>件</strong></p>

        <table>
            <thead>
                <tr>
                    <th>生徒ID</th>
                    <th>生徒名</th>
                    <th>ユーザー名</th>
                    <th>パスワード（平文）</th>
                    <th>パスワードハッシュ</th>
                    <th>状態</th>
                    <th>最終ログイン</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                            ログイン情報が設定されている生徒はいません
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars($student['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['password_plain'] ?? '(未設定)'); ?></td>
                            <td class="hash-short" title="<?php echo htmlspecialchars($student['password_hash']); ?>">
                                <?php echo htmlspecialchars(substr($student['password_hash'], 0, 30) . '...'); ?>
                            </td>
                            <td>
                                <?php if (!empty($student['username']) && !empty($student['password_hash'])): ?>
                                    <span class="status-ok">✓ OK</span>
                                <?php else: ?>
                                    <span class="status-error">✗ 不完全</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $student['last_login'] ? date('Y/m/d H:i', strtotime($student['last_login'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="test-section">
            <h2>🔐 パスワード検証テスト</h2>
            <p>生徒のユーザー名とパスワードを入力して、ログインできるか確認できます。</p>

            <form method="POST" action="">
                <div class="test-form">
                    <div class="form-group">
                        <label>ユーザー名</label>
                        <input type="text" name="test_username" required>
                    </div>
                    <div class="form-group">
                        <label>パスワード</label>
                        <input type="password" name="test_password" required>
                    </div>
                    <button type="submit" name="test_login">ログインテスト</button>
                </div>
            </form>

            <?php
            if (isset($_POST['test_login'])) {
                $testUsername = $_POST['test_username'];
                $testPassword = $_POST['test_password'];

                $stmt = $pdo->prepare("
                    SELECT id, student_name, username, password_hash, password_plain
                    FROM students
                    WHERE username = ?
                ");
                $stmt->execute([$testUsername]);
                $testStudent = $stmt->fetch();

                if (!$testStudent) {
                    echo '<div class="result error">✗ ユーザー名「' . htmlspecialchars($testUsername) . '」が見つかりません</div>';
                } elseif (empty($testStudent['password_hash'])) {
                    echo '<div class="result error">✗ パスワードが設定されていません</div>';
                } elseif (password_verify($testPassword, $testStudent['password_hash'])) {
                    echo '<div class="result success">';
                    echo '✓ ログイン成功！<br>';
                    echo '生徒ID: ' . $testStudent['id'] . '<br>';
                    echo '生徒名: ' . htmlspecialchars($testStudent['student_name']) . '<br>';
                    echo 'ユーザー名: ' . htmlspecialchars($testStudent['username']) . '<br>';
                    echo '入力パスワード: ' . htmlspecialchars($testPassword) . '<br>';
                    echo '登録パスワード（平文）: ' . htmlspecialchars($testStudent['password_plain'] ?? '(未設定)');
                    echo '</div>';
                } else {
                    echo '<div class="result error">';
                    echo '✗ パスワードが一致しません<br>';
                    echo '入力パスワード: ' . htmlspecialchars($testPassword) . '<br>';
                    echo '登録パスワード（平文）: ' . htmlspecialchars($testStudent['password_plain'] ?? '(未設定)') . '<br>';
                    echo 'ユーザー名は正しいですが、パスワードが異なります。';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px;">
            <strong>⚠ セキュリティ警告:</strong> このデバッグページは開発用です。本番環境では削除してください。
        </div>
    </div>
</body>
</html>
