<?php
/**
 * 利用日変更API
 * 追加利用の登録/削除、通常利用日のキャンセル/復活を処理
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_id']) || !isset($input['changes'])) {
    echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています']);
    exit;
}

$studentId = (int)$input['student_id'];
$changes = $input['changes'];

// 生徒情報を取得
$stmt = $pdo->prepare("
    SELECT s.id, s.student_name, s.guardian_id
    FROM students s
    WHERE s.id = ? AND s.is_active = 1
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'message' => '生徒が見つかりません']);
    exit;
}

// チャットルームを取得
$stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE student_id = ? LIMIT 1");
$stmt->execute([$studentId]);
$chatRoom = $stmt->fetch();

try {
    $pdo->beginTransaction();

    $notifications = []; // チャット通知用

    foreach ($changes as $date => $change) {
        // 日付形式を検証
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }

        $action = is_array($change) ? ($change['action'] ?? '') : ($change ? 'add' : 'remove');
        $type = is_array($change) ? ($change['type'] ?? 'additional') : 'additional';

        switch ($action) {
            case 'add':
                // 追加利用を登録
                $stmt = $pdo->prepare("
                    INSERT INTO additional_usages (student_id, usage_date, created_by)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$studentId, $date, $currentUser['id']]);
                break;

            case 'remove':
                // 追加利用を削除
                $stmt = $pdo->prepare("
                    DELETE FROM additional_usages
                    WHERE student_id = ? AND usage_date = ?
                ");
                $stmt->execute([$studentId, $date]);
                break;

            case 'cancel':
                // 通常利用日をキャンセル（absence_notificationsに追加）
                $stmt = $pdo->prepare("
                    INSERT INTO absence_notifications (student_id, absence_date, reason, message_id, created_at)
                    VALUES (?, ?, 'スタッフによるキャンセル', NULL, NOW())
                    ON DUPLICATE KEY UPDATE reason = 'スタッフによるキャンセル', created_at = NOW()
                ");
                $stmt->execute([$studentId, $date]);

                // 通知用に記録
                $notifications[] = ['date' => $date, 'action' => 'cancel'];
                break;

            case 'restore':
                // キャンセルを取り消し（absence_notificationsから削除）
                $stmt = $pdo->prepare("
                    DELETE FROM absence_notifications
                    WHERE student_id = ? AND absence_date = ?
                ");
                $stmt->execute([$studentId, $date]);
                break;
        }
    }

    // キャンセル通知をチャットに送信
    if ($chatRoom && !empty($notifications)) {
        foreach ($notifications as $notification) {
            $dateObj = new DateTime($notification['date']);
            $dateStr = $dateObj->format('n月j日');
            $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$dateObj->format('w')];

            $message = "【利用日変更】{$student['student_name']}さんの{$dateStr}({$dayOfWeek})の利用がキャンセルされました。";

            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, created_at)
                VALUES (?, ?, 'staff', ?, NOW())
            ");
            $stmt->execute([$chatRoom['id'], $currentUser['id'], $message]);

            // ルームの最終メッセージ時刻を更新
            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$chatRoom['id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '保存しました']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Additional usage API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
