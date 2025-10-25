<?php
/**
 * 生徒用 - パスワード変更ページ
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/student_auth.php';

requireStudentLogin();

$pdo = getDbConnection();
$studentId = $_SESSION['student_id'];
$error = '';
$success = '';

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // バリデーション
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '全ての項目を入力してください。';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '新しいパスワードが一致しません。';
    } elseif (strlen($newPassword) < 6) {
        $error = 'パスワードは6文字以上で設定してください。';
    } else {
        // 現在のパスワードを確認
        $stmt = $pdo->prepare("SELECT password_hash FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student || !password_verify($currentPassword, $student['password_hash'])) {
            $error = '現在のパスワードが正しくありません。';
        } else {
            // パスワードを更新
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE students
                SET password_hash = ?, password_plain = ?
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $newPassword, $studentId]);
            $success = 'パスワードを変更しました。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード変更 - 生徒用</title>
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
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #28a745;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 パスワード変更</h1>
        <p class="subtitle">新しいパスワードを設定してください</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">現在のパスワード *</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">新しいパスワード *</label>
                <input type="password" id="new_password" name="new_password" required>
                <div class="help-text">6文字以上で設定してください</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">新しいパスワード（確認） *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="button-group">
                <a href="dashboard.php" class="btn btn-secondary">キャンセル</a>
                <button type="submit" class="btn btn-primary">変更する</button>
            </div>
        </form>
    </div>
</body>
</html>
