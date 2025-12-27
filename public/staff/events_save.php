<?php
/**
 * スタッフ用 - イベント情報の保存・削除処理
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

try {
    switch ($action) {
        case 'create':
            // 新規イベント登録
            $eventDate = $_POST['event_date'];
            $eventName = trim($_POST['event_name']);
            $eventDescription = trim($_POST['event_description'] ?? '');
            $staffComment = trim($_POST['staff_comment'] ?? '');
            $guardianMessage = trim($_POST['guardian_message'] ?? '');
            $eventColor = $_POST['event_color'] ?? '#28a745';
            $targetAudience = $_POST['target_audience'] ?? 'all';
            $createdBy = $_SESSION['user_id'];
            $classroomId = $_SESSION['classroom_id'] ?? null;

            if (empty($eventDate) || empty($eventName)) {
                throw new Exception('日付とイベント名は必須です。');
            }

            if (empty($classroomId)) {
                throw new Exception('教室IDが設定されていません。');
            }

            // 日付の妥当性チェック
            if (!strtotime($eventDate)) {
                throw new Exception('無効な日付です。');
            }

            // 色コードの検証（#から始まる6桁の16進数）
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $eventColor)) {
                $eventColor = '#28a745'; // デフォルト色
            }

            // 対象者の妥当性チェック（複数選択対応）
            $validTargetAudiences = ['all', 'preschool', 'elementary', 'junior_high', 'high_school', 'guardian', 'other'];
            if (is_array($targetAudience)) {
                $targetAudience = array_filter($targetAudience, fn($t) => in_array($t, $validTargetAudiences));
                $targetAudience = !empty($targetAudience) ? implode(',', $targetAudience) : 'all';
            } elseif (!in_array($targetAudience, $validTargetAudiences)) {
                $targetAudience = 'all';
            }

            // イベントを登録
            $stmt = $pdo->prepare("
                INSERT INTO events (event_date, event_name, event_description, staff_comment, guardian_message, event_color, target_audience, created_by, classroom_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$eventDate, $eventName, $eventDescription, $staffComment, $guardianMessage, $eventColor, $targetAudience, $createdBy, $classroomId]);

            header('Location: events.php?success=created');
            exit;

        case 'edit':
            // イベント編集
            $eventId = (int)$_POST['event_id'];
            $eventDate = $_POST['event_date'];
            $eventName = trim($_POST['event_name']);
            $eventDescription = trim($_POST['event_description'] ?? '');
            $staffComment = trim($_POST['staff_comment'] ?? '');
            $guardianMessage = trim($_POST['guardian_message'] ?? '');
            $eventColor = $_POST['event_color'] ?? '#28a745';
            $targetAudience = $_POST['target_audience'] ?? 'all';

            if (empty($eventId)) {
                throw new Exception('イベントIDが指定されていません。');
            }

            if (empty($eventDate) || empty($eventName)) {
                throw new Exception('日付とイベント名は必須です。');
            }

            // 日付の妥当性チェック
            if (!strtotime($eventDate)) {
                throw new Exception('無効な日付です。');
            }

            // 色コードの検証（#から始まる6桁の16進数）
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $eventColor)) {
                $eventColor = '#28a745'; // デフォルト色
            }

            // 対象者の妥当性チェック（複数選択対応）
            $validTargetAudiences = ['all', 'preschool', 'elementary', 'junior_high', 'high_school', 'guardian', 'other'];
            if (is_array($targetAudience)) {
                $targetAudience = array_filter($targetAudience, fn($t) => in_array($t, $validTargetAudiences));
                $targetAudience = !empty($targetAudience) ? implode(',', $targetAudience) : 'all';
            } elseif (!in_array($targetAudience, $validTargetAudiences)) {
                $targetAudience = 'all';
            }

            // イベントを更新（自分の教室のみ）
            if ($classroomId) {
                $stmt = $pdo->prepare("
                    UPDATE events
                    SET event_date = ?,
                        event_name = ?,
                        event_description = ?,
                        staff_comment = ?,
                        guardian_message = ?,
                        event_color = ?,
                        target_audience = ?
                    WHERE id = ? AND classroom_id = ?
                ");
                $stmt->execute([
                    $eventDate,
                    $eventName,
                    $eventDescription,
                    $staffComment,
                    $guardianMessage,
                    $eventColor,
                    $targetAudience,
                    $eventId,
                    $classroomId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE events
                    SET event_date = ?,
                        event_name = ?,
                        event_description = ?,
                        staff_comment = ?,
                        guardian_message = ?,
                        event_color = ?,
                        target_audience = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $eventDate,
                    $eventName,
                    $eventDescription,
                    $staffComment,
                    $guardianMessage,
                    $eventColor,
                    $targetAudience,
                    $eventId
                ]);
            }

            header('Location: events.php?success=updated');
            exit;

        case 'delete':
            // イベント削除（自分の教室のみ）
            $eventId = (int)$_POST['event_id'];

            if (empty($eventId)) {
                throw new Exception('イベントIDが指定されていません。');
            }

            if ($classroomId) {
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND classroom_id = ?");
                $stmt->execute([$eventId, $classroomId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$eventId]);
            }

            header('Location: events.php?success=deleted');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: events.php?error=' . urlencode($e->getMessage()));
    exit;
}
