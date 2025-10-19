<?php
/**
 * 管理者アカウントの保存・編集・削除処理（マスター管理者専用）
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // 新規管理者登録
            $username = $_POST['username'];
            $password = $_POST['password'];
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $isMaster = (int)$_POST['is_master'];
            $classroomId = (int)$_POST['classroom_id'];

            // ユーザー名の重複チェック
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('このユーザー名は既に使用されています');
            }

            // パスワードハッシュ化
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // データベース登録
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, user_type, is_master, classroom_id, is_active, created_at)
                VALUES (?, ?, ?, ?, 'admin', ?, ?, 1, NOW())
            ");
            $stmt->execute([$username, $hashedPassword, $fullName, $email, $isMaster, $classroomId]);

            header('Location: admin_accounts.php?success=' . urlencode('管理者アカウントを登録しました'));
            exit;

        case 'edit':
            // 管理者情報編集
            $userId = (int)$_POST['user_id'];
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $isMaster = (int)$_POST['is_master'];
            $classroomId = (int)$_POST['classroom_id'];
            $isActive = (int)$_POST['is_active'];
            $password = $_POST['password'] ?? '';

            // パスワード変更がある場合
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, password = ?, is_master = ?, classroom_id = ?, is_active = ?
                    WHERE id = ? AND user_type = 'admin'
                ");
                $stmt->execute([$fullName, $email, $hashedPassword, $isMaster, $classroomId, $isActive, $userId]);
            } else {
                // パスワード変更なし
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, is_master = ?, classroom_id = ?, is_active = ?
                    WHERE id = ? AND user_type = 'admin'
                ");
                $stmt->execute([$fullName, $email, $isMaster, $classroomId, $isActive, $userId]);
            }

            header('Location: admin_accounts.php?success=' . urlencode('管理者情報を更新しました'));
            exit;

        case 'delete':
            // 管理者削除
            $userId = (int)$_POST['user_id'];

            // 自分自身は削除できない
            if ($userId == $_SESSION['user_id']) {
                throw new Exception('自分自身のアカウントは削除できません');
            }

            // 削除実行
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'admin'");
            $stmt->execute([$userId]);

            header('Location: admin_accounts.php?success=' . urlencode('管理者アカウントを削除しました'));
            exit;

        default:
            throw new Exception('無効な操作です');
    }
} catch (Exception $e) {
    error_log("Admin account save error: " . $e->getMessage());
    header('Location: admin_accounts.php?error=' . urlencode($e->getMessage()));
    exit;
}
