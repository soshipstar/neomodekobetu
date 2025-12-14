<?php
/**
 * タブレットユーザーアカウント保存処理
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// 管理者のみアクセス可能
requireUserType('admin');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$isMaster = isMasterAdmin();
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tablet_accounts.php');
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_POST['user_id'] ?? null;
$username = trim($_POST['username'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');
$selectedClassroomId = $_POST['classroom_id'] ?? null;
$password = $_POST['password'] ?? '';

// バリデーション
$errors = [];

if (empty($username)) {
    $errors[] = 'ユーザー名を入力してください';
}

if (empty($fullName)) {
    $errors[] = '氏名を入力してください';
}

if (empty($selectedClassroomId)) {
    $errors[] = '教室を選択してください';
}

// 通常管理者は自分の教室のユーザーのみ作成可能
if (!$isMaster && $selectedClassroomId != $classroomId) {
    $errors[] = '他の教室のユーザーは作成できません';
}

if ($action === 'create' && empty($password)) {
    $errors[] = 'パスワードを入力してください';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: tablet_accounts.php');
    exit;
}

try {
    if ($action === 'create') {
        // ユーザー名の重複チェック
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'このユーザー名は既に使用されています';
            header('Location: tablet_accounts.php');
            exit;
        }

        // 新規作成
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, user_type, classroom_id, is_active)
            VALUES (?, ?, ?, 'tablet_user', ?, 1)
        ");
        $stmt->execute([$username, $hashedPassword, $fullName, $selectedClassroomId]);

        header('Location: tablet_accounts.php?success=' . urlencode('タブレットユーザーを作成しました'));
        exit;

    } elseif ($action === 'edit') {
        if (!$userId) {
            $_SESSION['error'] = 'ユーザーIDが指定されていません';
            header('Location: tablet_accounts.php');
            exit;
        }

        // 編集権限チェック
        if ($isMaster) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'tablet_user'");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND user_type = 'tablet_user' AND classroom_id = ?");
            $stmt->execute([$userId, $classroomId]);
        }

        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'このユーザーを編集する権限がありません';
            header('Location: tablet_accounts.php');
            exit;
        }

        // ユーザー名の重複チェック（自分以外）
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'このユーザー名は既に使用されています';
            header('Location: tablet_accounts.php');
            exit;
        }

        // 更新
        if (!empty($password)) {
            // パスワードも更新
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users
                SET username = ?, password = ?, full_name = ?, classroom_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $hashedPassword, $fullName, $selectedClassroomId, $userId]);
        } else {
            // パスワード以外を更新
            $stmt = $pdo->prepare("
                UPDATE users
                SET username = ?, full_name = ?, classroom_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullName, $selectedClassroomId, $userId]);
        }

        header('Location: tablet_accounts.php?success=' . urlencode('タブレットユーザーを更新しました'));
        exit;
    }

} catch (PDOException $e) {
    error_log("Tablet user save error: " . $e->getMessage());
    $_SESSION['error'] = '保存中にエラーが発生しました';
    header('Location: tablet_accounts.php');
    exit;
}
