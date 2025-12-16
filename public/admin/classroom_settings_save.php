<?php
/**
 * 管理者用 - 教室情報保存処理
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: classroom_settings.php');
    exit;
}

$pdo = getDbConnection();

try {
    $classroomId = $_POST['classroom_id'] ?? null;
    $classroomName = trim($_POST['classroom_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // 対象学年を取得（チェックボックスから配列で受け取る）
    $targetGrades = isset($_POST['target_grades']) && is_array($_POST['target_grades'])
        ? implode(',', $_POST['target_grades'])
        : '';

    if (empty($classroomId) || empty($classroomName)) {
        throw new Exception('教室名は必須です。');
    }

    // ロゴアップロード処理
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadFile = $_FILES['logo'];
        $fileSize = $uploadFile['size'];
        $fileType = $uploadFile['type'];
        $tmpName = $uploadFile['tmp_name'];

        // ファイルサイズチェック（2MB以下）
        if ($fileSize > 2 * 1024 * 1024) {
            throw new Exception('ファイルサイズが大きすぎます。2MB以下のファイルを選択してください。');
        }

        // ファイル形式チェック
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('画像ファイル（JPEG、PNG、GIF）のみアップロード可能です。');
        }

        // アップロードディレクトリ作成
        $uploadDir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // ファイル名生成（一意性を保証）
        $extension = pathinfo($uploadFile['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . $classroomId . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        // ファイル移動
        if (!move_uploaded_file($tmpName, $uploadPath)) {
            throw new Exception('ファイルのアップロードに失敗しました。');
        }

        $logoPath = 'uploads/logos/' . $filename;

        // 古いロゴファイルを削除
        $stmt = $pdo->prepare("SELECT logo_path FROM classrooms WHERE id = ?");
        $stmt->execute([$classroomId]);
        $oldData = $stmt->fetch();
        if ($oldData && !empty($oldData['logo_path'])) {
            $oldFile = __DIR__ . '/../' . $oldData['logo_path'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
    }

    // データベース更新
    if ($logoPath) {
        $stmt = $pdo->prepare("
            UPDATE classrooms
            SET classroom_name = ?,
                address = ?,
                phone = ?,
                logo_path = ?,
                target_grades = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$classroomName, $address, $phone, $logoPath, $targetGrades, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE classrooms
            SET classroom_name = ?,
                address = ?,
                phone = ?,
                target_grades = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$classroomName, $address, $phone, $targetGrades, $classroomId]);
    }

    header('Location: classroom_settings.php?success=1');
    exit;

} catch (Exception $e) {
    header('Location: classroom_settings.php?error=' . urlencode($e->getMessage()));
    exit;
}
