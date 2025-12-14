<?php
/**
 * スタッフ管理の保存・編集・削除処理（管理者用）
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// 管理者チェック
requireUserType('admin');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $currentUser['classroom_id'];

// マスター管理者の場合は専用ページにリダイレクト
if (isMasterAdmin()) {
    header('Location: staff_accounts.php');
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // 新規スタッフ登録
            $username = $_POST['username'];
            $password = $_POST['password'];
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';

            // ユーザー名の重複チェック
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('このユーザー名は既に使用されています');
            }

            // パスワードハッシュ化
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // データベース登録（自分の教室に自動的に割り当て）
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, user_type, classroom_id, is_active, created_at)
                VALUES (?, ?, ?, ?, 'staff', ?, 1, NOW())
            ");
            $stmt->execute([$username, $hashedPassword, $fullName, $email, $classroomId]);

            header('Location: staff_management.php?success=' . urlencode('スタッフアカウントを登録しました'));
            exit;

        case 'edit':
            // スタッフ情報編集
            $userId = (int)$_POST['user_id'];
            $fullName = $_POST['full_name'];
            $email = $_POST['email'] ?? '';
            $isActive = (int)$_POST['is_active'];
            $password = $_POST['password'] ?? '';

            // 自分の教室のスタッフのみ編集可能
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'staff' AND classroom_id = ?");
            $stmt->execute([$userId, $classroomId]);
            if (!$stmt->fetch()) {
                throw new Exception('権限がありません');
            }

            // パスワード変更がある場合
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, password = ?, is_active = ?
                    WHERE id = ? AND user_type = 'staff' AND classroom_id = ?
                ");
                $stmt->execute([$fullName, $email, $hashedPassword, $isActive, $userId, $classroomId]);
            } else {
                // パスワード変更なし
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, is_active = ?
                    WHERE id = ? AND user_type = 'staff' AND classroom_id = ?
                ");
                $stmt->execute([$fullName, $email, $isActive, $userId, $classroomId]);
            }

            header('Location: staff_management.php?success=' . urlencode('スタッフ情報を更新しました'));
            exit;

        case 'delete':
            // スタッフ削除
            $userId = (int)$_POST['user_id'];

            // 自分の教室のスタッフのみ削除可能
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'staff' AND classroom_id = ?");
            $stmt->execute([$userId, $classroomId]);
            if (!$stmt->fetch()) {
                throw new Exception('権限がありません');
            }

            // 削除実行
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'staff' AND classroom_id = ?");
            $stmt->execute([$userId, $classroomId]);

            header('Location: staff_management.php?success=' . urlencode('スタッフアカウントを削除しました'));
            exit;

        default:
            throw new Exception('無効な操作です');
    }
} catch (Exception $e) {
    error_log("Staff management save error: " . $e->getMessage());
    header('Location: staff_management.php?error=' . urlencode($e->getMessage()));
    exit;
}
