<?php
/**
 * 振替依頼API
 * 承認・却下・メモ追加処理
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// JSONデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$requestId = $input['request_id'] ?? null;

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'リクエストIDが指定されていません']);
    exit;
}

try {
    // 振替依頼を取得
    $stmt = $pdo->prepare("
        SELECT
            an.*,
            s.student_name,
            s.classroom_id
        FROM absence_notifications an
        INNER JOIN students s ON an.student_id = s.id
        WHERE an.id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => '振替依頼が見つかりません']);
        exit;
    }

    // 承認処理
    if ($action === 'approve') {
        if ($request['makeup_status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'この依頼は既に処理済みです']);
            exit;
        }

        if (!$request['makeup_request_date']) {
            echo json_encode(['success' => false, 'message' => '振替希望日が設定されていません']);
            exit;
        }

        $pdo->beginTransaction();

        // 振替依頼を承認
        $stmt = $pdo->prepare("
            UPDATE absence_notifications
            SET
                makeup_status = 'approved',
                makeup_approved_by = ?,
                makeup_approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$staffId, $requestId]);

        // 振替希望日の出席予定者に生徒を追加
        // まず、その日の活動記録があるか確認
        $stmt = $pdo->prepare("
            SELECT id FROM renrakucho
            WHERE classroom_id = ? AND activity_date = ?
        ");
        $stmt->execute([$request['classroom_id'], $request['makeup_request_date']]);
        $renrakucho = $stmt->fetch();

        if ($renrakucho) {
            // 活動記録が存在する場合、既に登録されているか確認
            $stmt = $pdo->prepare("
                SELECT id FROM renrakucho_students
                WHERE renrakucho_id = ? AND student_id = ?
            ");
            $stmt->execute([$renrakucho['id'], $request['student_id']]);

            if (!$stmt->fetch()) {
                // 未登録の場合のみ追加
                $stmt = $pdo->prepare("
                    INSERT INTO renrakucho_students (renrakucho_id, student_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$renrakucho['id'], $request['student_id']]);
            }
        } else {
            // 活動記録が存在しない場合は新規作成
            $stmt = $pdo->prepare("
                INSERT INTO renrakucho (classroom_id, activity_date, created_by, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$request['classroom_id'], $request['makeup_request_date'], $staffId]);
            $renrakuchoId = $pdo->lastInsertId();

            // 生徒を追加
            $stmt = $pdo->prepare("
                INSERT INTO renrakucho_students (renrakucho_id, student_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$renrakuchoId, $request['student_id']]);
        }

        // 保護者にチャットで通知メッセージを送信
        $makeupDate = new DateTime($request['makeup_request_date']);
        $dateStr = $makeupDate->format('n月j日');
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$makeupDate->format('w')];

        $notificationMessage = "【振替承認】{$request['student_name']}さんの振替依頼を承認しました。\n振替日: {$dateStr}({$dayOfWeek})";

        // チャットルームを取得
        $stmt = $pdo->prepare("
            SELECT cr.id
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$request['student_id']]);
        $chatRoom = $stmt->fetch();

        if ($chatRoom) {
            // チャットメッセージを送信
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, created_at)
                VALUES (?, ?, 'staff', ?, NOW())
            ");
            $stmt->execute([$chatRoom['id'], $staffId, $notificationMessage]);

            // ルームの最終メッセージ時刻を更新
            $stmt = $pdo->prepare("
                UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$chatRoom['id']]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => '承認しました']);
    }

    // 却下処理
    elseif ($action === 'reject') {
        if ($request['makeup_status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'この依頼は既に処理済みです']);
            exit;
        }

        $pdo->beginTransaction();

        // 振替依頼を却下
        $stmt = $pdo->prepare("
            UPDATE absence_notifications
            SET
                makeup_status = 'rejected',
                makeup_approved_by = ?,
                makeup_approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$staffId, $requestId]);

        // 保護者にチャットで通知メッセージを送信
        $makeupDate = new DateTime($request['makeup_request_date']);
        $dateStr = $makeupDate->format('n月j日');
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$makeupDate->format('w')];

        $notificationMessage = "【振替却下】{$request['student_name']}さんの振替依頼を却下しました。\n希望日: {$dateStr}({$dayOfWeek})";

        // チャットルームを取得
        $stmt = $pdo->prepare("
            SELECT cr.id
            FROM chat_rooms cr
            INNER JOIN students s ON cr.student_id = s.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$request['student_id']]);
        $chatRoom = $stmt->fetch();

        if ($chatRoom) {
            // チャットメッセージを送信
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (room_id, sender_id, sender_type, message, created_at)
                VALUES (?, ?, 'staff', ?, NOW())
            ");
            $stmt->execute([$chatRoom['id'], $staffId, $notificationMessage]);

            // ルームの最終メッセージ時刻を更新
            $stmt = $pdo->prepare("
                UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$chatRoom['id']]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => '却下しました']);
    }

    // メモ追加処理
    elseif ($action === 'add_note') {
        $note = trim($input['note'] ?? '');

        if (!$note) {
            echo json_encode(['success' => false, 'message' => 'メモが入力されていません']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE absence_notifications
            SET makeup_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$note, $requestId]);

        echo json_encode(['success' => true, 'message' => 'メモを保存しました']);
    }

    else {
        echo json_encode(['success' => false, 'message' => '不明なアクションです']);
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
