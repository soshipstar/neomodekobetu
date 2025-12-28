<?php
/**
 * スタッフ用 - 保護者情報の保存・更新処理
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';
$classroomId = $_SESSION['classroom_id'] ?? null;

/**
 * ランダムなパスワードを生成（8文字の英数字）
 */
function generateRandomPasswordForGuardian($length = 8) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * ユニークなユーザー名を生成（guardian_XXX形式）
 */
function generateUniqueUsername($pdo) {
    // 既存のguardian_XXX形式のユーザー名の最大番号を取得
    $stmt = $pdo->prepare("SELECT username FROM users WHERE username LIKE 'guardian_%' ORDER BY username DESC LIMIT 1");
    $stmt->execute();
    $lastUsername = $stmt->fetchColumn();
    $nextNumber = 1;
    if ($lastUsername && preg_match('/guardian_(\d+)/', $lastUsername, $matches)) {
        $nextNumber = (int)$matches[1] + 1;
    }

    $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

    // 重複確認
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    while ($stmt->fetchColumn() > 0) {
        $nextNumber++;
        $username = 'guardian_' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        $stmt->execute([$username]);
    }

    return $username;
}

try {
    switch ($action) {
        case 'create':
            // 新規保護者登録
            $fullName = trim($_POST['full_name']);
            $email = trim($_POST['email']) ?: null;

            // バリデーション
            if (empty($fullName)) {
                throw new Exception('氏名は必須です。');
            }

            // ユーザー名とパスワードを自動生成
            $username = generateUniqueUsername($pdo);
            $password = generateRandomPasswordForGuardian();

            // パスワードをハッシュ化
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // ログインユーザーの教室IDを取得
            $classroomId = $_SESSION['classroom_id'] ?? null;

            // 保護者を登録（スタッフと同じ教室に所属）
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, password_plain, full_name, email, user_type, classroom_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 'guardian', ?, 1, NOW())
            ");
            $stmt->execute([$username, $hashedPassword, $password, $fullName, $email, $classroomId]);

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
                        password = ?,
                        password_plain = ?
                    WHERE id = ? AND user_type = 'guardian'
                ");
                $stmt->execute([$fullName, $username, $email, $hashedPassword, $password, $guardianId]);
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
            // 保護者削除（自分の教室のみ）
            $guardianId = (int)$_POST['guardian_id'];

            if (empty($guardianId)) {
                throw new Exception('保護者IDが指定されていません。');
            }

            // 生徒との紐付けを解除（自分の教室の生徒のみ）
            if ($classroomId) {
                $stmt = $pdo->prepare("UPDATE students SET guardian_id = NULL WHERE guardian_id = ? AND classroom_id = ?");
                $stmt->execute([$guardianId, $classroomId]);
            } else {
                $stmt = $pdo->prepare("UPDATE students SET guardian_id = NULL WHERE guardian_id = ?");
                $stmt->execute([$guardianId]);
            }

            // 保護者を削除（自分の教室のみ）
            if ($classroomId) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'guardian' AND classroom_id = ?");
                $stmt->execute([$guardianId, $classroomId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = 'guardian'");
                $stmt->execute([$guardianId]);
            }

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
