<?php
/**
 * スタッフ用 - 保護者情報の保存・更新処理
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // 新規保護者登録
            $fullName = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $passwordConfirm = $_POST['password_confirm'];
            $email = trim($_POST['email']) ?: null;

            // バリデーション
            if (empty($fullName) || empty($username) || empty($password)) {
                throw new Exception('氏名、ユーザー名、パスワードは必須です。');
            }

            if ($password !== $passwordConfirm) {
                throw new Exception('パスワードが一致しません。');
            }

            if (strlen($password) < 8) {
                throw new Exception('パスワードは8文字以上で設定してください。');
            }

            // ユーザー名の重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このユーザー名は既に使用されています。');
            }

            // パスワードをハッシュ化
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // ログインユーザーの教室IDを取得
            $classroomId = $_SESSION['classroom_id'] ?? null;

            // 保護者を登録（スタッフと同じ教室に所属）
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, user_type, classroom_id, is_active, created_at)
                VALUES (?, ?, ?, ?, 'guardian', ?, 1, NOW())
            ");
            $stmt->execute([$username, $hashedPassword, $fullName, $email, $classroomId]);

            header('Location: guardians.php?success=created');
            exit;

        case 'update':
            // 保護者情報更新
            $guardianId = (int)$_POST['guardian_id'];
            $fullName = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']) ?: null;
            $password = $_POST['password'] ?? '';

            if (empty($guardianId) || empty($fullName) || empty($username)) {
                throw new Exception('必須項目が入力されていません。');
            }

            // ユーザー名の重複チェック（自分以外）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $guardianId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('このユーザー名は既に使用されています。');
            }

            // パスワードが入力されている場合
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    throw new Exception('パスワードは8文字以上で設定してください。');
                }
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?,
                        username = ?,
                        email = ?,
                        password = ?
                    WHERE id = ? AND user_type = 'guardian'
                ");
                $stmt->execute([$fullName, $username, $email, $hashedPassword, $guardianId]);
            } else {
                // パスワード変更なし
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?,
                        username = ?,
                        email = ?
                    WHERE id = ? AND user_type = 'guardian'
                ");
                $stmt->execute([$fullName, $username, $email, $guardianId]);
            }

            header('Location: guardians.php?success=updated');
            exit;

        case 'delete':
            // 保護者削除
            $guardianId = (int)$_POST['guardian_id'];

            if (empty($guardianId)) {
                throw new Exception('保護者IDが指定されていません。');
            }

            // 生徒との紐付けを解除
            $stmt = $pdo->prepare("UPDATE students SET guardian_id = NULL WHERE guardian_id = ?");
            $stmt->execute([$guardianId]);

            // 保護者を削除
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'guardian'");
            $stmt->execute([$guardianId]);

            header('Location: guardians.php?success=deleted');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: guardians.php?error=' . urlencode($e->getMessage()));
    exit;
}
