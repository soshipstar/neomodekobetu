<?php
/**
 * 教室データの保存・編集・削除処理（マスター管理者専用）
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// マスター管理者チェック
requireMasterAdmin();

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // 新規教室登録
            $classroomName = $_POST['classroom_name'];
            $address = $_POST['address'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $logoPath = '';

            // ロゴアップロード処理
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileSize = $_FILES['logo']['size'];
                $fileType = $_FILES['logo']['type'];
                $tmpName = $_FILES['logo']['tmp_name'];

                // ファイルサイズチェック（2MB以内）
                if ($fileSize > 2 * 1024 * 1024) {
                    throw new Exception('ファイルサイズが大きすぎます（2MB以内）');
                }

                // ファイルタイプチェック
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('画像ファイル（JPEG, PNG, GIF）のみアップロード可能です');
                }

                // ファイル名生成
                $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'classroom_logo_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

                // ファイル移動
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $logoPath = 'uploads/' . $filename;
                }
            }

            // データベース登録
            $stmt = $pdo->prepare("
                INSERT INTO classrooms (classroom_name, address, phone, logo_path)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$classroomName, $address, $phone, $logoPath]);

            header('Location: classrooms.php?success=' . urlencode('教室を登録しました'));
            exit;

        case 'edit':
            // 教室情報編集
            $classroomId = (int)$_POST['classroom_id'];
            $classroomName = $_POST['classroom_name'];
            $address = $_POST['address'] ?? '';
            $phone = $_POST['phone'] ?? '';

            // 既存のロゴパスを取得
            $stmt = $pdo->prepare("SELECT logo_path FROM classrooms WHERE id = ?");
            $stmt->execute([$classroomId]);
            $existingClassroom = $stmt->fetch();
            $logoPath = $existingClassroom['logo_path'];

            // 新しいロゴがアップロードされた場合
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileSize = $_FILES['logo']['size'];
                $fileType = $_FILES['logo']['type'];
                $tmpName = $_FILES['logo']['tmp_name'];

                // ファイルサイズチェック（2MB以内）
                if ($fileSize > 2 * 1024 * 1024) {
                    throw new Exception('ファイルサイズが大きすぎます（2MB以内）');
                }

                // ファイルタイプチェック
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('画像ファイル（JPEG, PNG, GIF）のみアップロード可能です');
                }

                // 古いロゴを削除
                if ($logoPath && file_exists(__DIR__ . '/../' . $logoPath)) {
                    unlink(__DIR__ . '/../' . $logoPath);
                }

                // 新しいファイル名生成
                $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'classroom_logo_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

                // ファイル移動
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $logoPath = 'uploads/' . $filename;
                }
            }

            // データベース更新
            $stmt = $pdo->prepare("
                UPDATE classrooms
                SET classroom_name = ?, address = ?, phone = ?, logo_path = ?
                WHERE id = ?
            ");
            $stmt->execute([$classroomName, $address, $phone, $logoPath, $classroomId]);

            header('Location: classrooms.php?success=' . urlencode('教室情報を更新しました'));
            exit;

        case 'delete':
            // 教室削除（カスケード削除）
            $classroomId = (int)$_POST['classroom_id'];

            $pdo->beginTransaction();
            try {
                // 教室に関連する生徒を取得してカスケード削除
                $stmt = $pdo->prepare("SELECT id FROM students WHERE classroom_id = ?");
                $stmt->execute([$classroomId]);
                $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($students as $studentId) {
                    // 生徒に関連するデータを削除
                    $stmt = $pdo->prepare("DELETE FROM daily_records WHERE student_id = ?");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM kakehashi_guardian WHERE student_id = ?");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM kakehashi_staff WHERE student_id = ?");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM individual_support_plan_details WHERE plan_id IN (SELECT id FROM individual_support_plans WHERE student_id = ?)");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM monitoring_details WHERE monitoring_id IN (SELECT id FROM monitoring_records WHERE student_id = ?)");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM monitoring_records WHERE student_id = ?");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM individual_support_plans WHERE student_id = ?");
                    $stmt->execute([$studentId]);

                    $stmt = $pdo->prepare("DELETE FROM integrated_notes WHERE student_id = ?");
                    $stmt->execute([$studentId]);
                }

                // 生徒を削除
                $stmt = $pdo->prepare("DELETE FROM students WHERE classroom_id = ?");
                $stmt->execute([$classroomId]);

                // この教室のユーザーを削除
                $stmt = $pdo->prepare("DELETE FROM users WHERE classroom_id = ?");
                $stmt->execute([$classroomId]);

                // 教室のロゴを削除
                $stmt = $pdo->prepare("SELECT logo_path FROM classrooms WHERE id = ?");
                $stmt->execute([$classroomId]);
                $classroom = $stmt->fetch();
                if ($classroom && $classroom['logo_path'] && file_exists(__DIR__ . '/../' . $classroom['logo_path'])) {
                    unlink(__DIR__ . '/../' . $classroom['logo_path']);
                }

                // 教室を削除
                $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
                $stmt->execute([$classroomId]);

                $pdo->commit();
                header('Location: classrooms.php?success=' . urlencode('教室を削除しました'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        default:
            throw new Exception('無効な操作です');
    }
} catch (Exception $e) {
    error_log("Classroom save error: " . $e->getMessage());
    header('Location: classrooms.php?error=' . urlencode($e->getMessage()));
    exit;
}
