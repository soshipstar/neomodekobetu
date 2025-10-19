<?php
/**
 * 管理者用 - 教室情報設定ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];

// ログインユーザーの教室IDを取得
$stmt = $pdo->prepare("SELECT classroom_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$classroomId = $user['classroom_id'];

// 教室情報を取得
$classroomData = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroomData = $stmt->fetch();
}

// デフォルト教室がない場合は作成
if (!$classroomData) {
    $stmt = $pdo->prepare("INSERT INTO classrooms (classroom_name, address, phone) VALUES ('新規教室', '', '')");
    $stmt->execute();
    $classroomId = $pdo->lastInsertId();

    // ユーザーに教室IDを設定
    $stmt = $pdo->prepare("UPDATE users SET classroom_id = ? WHERE id = ?");
    $stmt->execute([$classroomId, $userId]);

    // 再取得
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroomData = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教室情報設定 - 管理者</title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .logo-preview {
            margin-top: 10px;
            max-width: 300px;
        }
        .logo-preview img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background: white;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏫 教室情報設定</h1>
            <a href="index.php" class="btn btn-secondary">管理画面に戻る</a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                教室情報を更新しました。
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="content-box">
            <h2 class="section-title">教室基本情報</h2>
            <form action="classroom_settings_save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="classroom_id" value="<?= $classroomId ?>">

                <div class="form-group">
                    <label>教室名 *</label>
                    <input type="text" name="classroom_name" value="<?= htmlspecialchars($classroomData['classroom_name'] ?? '') ?>" required>
                    <div class="form-help">教室・施設の名称を入力してください</div>
                </div>

                <div class="form-group">
                    <label>住所</label>
                    <textarea name="address"><?= htmlspecialchars($classroomData['address'] ?? '') ?></textarea>
                    <div class="form-help">教室の所在地を入力してください</div>
                </div>

                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($classroomData['phone'] ?? '') ?>">
                    <div class="form-help">例: 03-1234-5678</div>
                </div>

                <div class="form-group">
                    <label>教室ロゴ</label>
                    <input type="file" name="logo" accept="image/*">
                    <div class="form-help">PNG、JPG、GIF形式の画像ファイルをアップロードできます（最大2MB）</div>

                    <?php if (!empty($classroomData['logo_path']) && file_exists(__DIR__ . '/../' . $classroomData['logo_path'])): ?>
                        <div class="logo-preview">
                            <p style="font-weight: bold; margin-top: 15px; margin-bottom: 10px;">現在のロゴ:</p>
                            <img src="../<?= htmlspecialchars($classroomData['logo_path']) ?>" alt="教室ロゴ">
                        </div>
                    <?php endif; ?>
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">💾 保存する</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
