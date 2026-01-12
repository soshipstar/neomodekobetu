<?php
/**
 * スタッフ用 面談予約回答保存処理
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: chat.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

$requestId = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? 'select';
$selectedDate = $_POST['selected_date'] ?? null;
$counterDate1 = $_POST['counter_date1'] ?: null;
$counterDate2 = $_POST['counter_date2'] ?: null;
$counterDate3 = $_POST['counter_date3'] ?: null;
$counterMessage = $_POST['counter_message'] ?? '';

if (!$requestId) {
    $_SESSION['error'] = 'リクエストIDが指定されていません。';
    header('Location: chat.php');
    exit;
}

// 面談リクエストを取得
$stmt = $pdo->prepare("
    SELECT mr.*, s.student_name
    FROM meeting_requests mr
    INNER JOIN students s ON mr.student_id = s.id
    WHERE mr.id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    $_SESSION['error'] = '指定された面談予約が見つかりません。';
    header('Location: chat.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'select' && $selectedDate) {
        // 保護者提案の日程から選択して確定
        $stmt = $pdo->prepare("
            UPDATE meeting_requests SET
                confirmed_date = ?,
                confirmed_by = 'staff',
                confirmed_at = NOW(),
                status = 'confirmed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$selectedDate, $requestId]);

        // チャットにメッセージを送信
        $dateFormat = 'Y年n月j日 H:i';
        $dateStr = date($dateFormat, strtotime($selectedDate));

        $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE student_id = ? AND guardian_id = ?");
        $stmt->execute([$request['student_id'], $request['guardian_id']]);
        $room = $stmt->fetch();

        if ($room) {
            $messageText = "【面談日時が確定しました】\n\n";
            $messageText .= "面談目的：{$request['purpose']}\n";
            $messageText .= "確定日時：{$dateStr}\n\n";
            $messageText .= "当日はよろしくお願いいたします。";

            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_type, sender_id, message, message_type, meeting_request_id, created_at)
                VALUES (?, 'staff', ?, ?, 'meeting_confirmed', ?, NOW())
            ");
            $stmt->execute([$room['id'], $staffId, $messageText, $requestId]);

            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$room['id']]);
        }

        $pdo->commit();
        $_SESSION['success'] = '面談日時が確定しました。';

    } elseif ($action === 'counter' && $counterDate1) {
        // 別日程を再提案
        $stmt = $pdo->prepare("
            UPDATE meeting_requests SET
                staff_counter_date1 = ?,
                staff_counter_date2 = ?,
                staff_counter_date3 = ?,
                staff_counter_message = ?,
                status = 'staff_counter',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$counterDate1, $counterDate2, $counterDate3, $counterMessage, $requestId]);

        // チャットにメッセージを送信
        $dateFormat = 'Y年n月j日 H:i';
        $date1Str = date($dateFormat, strtotime($counterDate1));
        $date2Str = $counterDate2 ? date($dateFormat, strtotime($counterDate2)) : '';
        $date3Str = $counterDate3 ? date($dateFormat, strtotime($counterDate3)) : '';

        $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE student_id = ? AND guardian_id = ?");
        $stmt->execute([$request['student_id'], $request['guardian_id']]);
        $room = $stmt->fetch();

        if ($room) {
            $messageText = "【面談日程の再調整】\n\n";
            $messageText .= "ご提案いただいた日程を確認いたしましたが、申し訳ございませんが都合がつきませんでした。\n";
            $messageText .= "以下の日程はいかがでしょうか。\n\n";
            $messageText .= "① {$date1Str}\n";
            if ($date2Str) {
                $messageText .= "② {$date2Str}\n";
            }
            if ($date3Str) {
                $messageText .= "③ {$date3Str}\n";
            }
            if ($counterMessage) {
                $messageText .= "\nメッセージ：{$counterMessage}";
            }

            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_type, sender_id, message, message_type, meeting_request_id, created_at)
                VALUES (?, 'staff', ?, ?, 'meeting_counter', ?, NOW())
            ");
            $stmt->execute([$room['id'], $staffId, $messageText, $requestId]);

            $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$room['id']]);
        }

        $pdo->commit();
        $_SESSION['success'] = '別日程を再提案しました。保護者からの回答をお待ちください。';

    } else {
        $pdo->rollBack();
        $_SESSION['error'] = '日程を選択するか、別日程を入力してください。';
        header("Location: meeting_response.php?request_id=$requestId");
        exit;
    }

    header("Location: meeting_response.php?request_id=$requestId");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Meeting response save error: " . $e->getMessage());
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    header("Location: meeting_response.php?request_id=$requestId");
    exit;
}
