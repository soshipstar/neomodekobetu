<?php
/**
 * 面談予約リクエスト保存処理
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
$classroomId = $_POST['classroom_id'] ?? $_SESSION['classroom_id'] ?? null;

$studentId = $_POST['student_id'] ?? null;
$guardianId = $_POST['guardian_id'] ?? null;
$purpose = $_POST['purpose'] ?? null;
$purposeDetail = $_POST['purpose_detail'] ?? null;
$relatedPlanId = $_POST['related_plan_id'] ?: null;
$relatedMonitoringId = $_POST['related_monitoring_id'] ?: null;
$candidateDate1 = $_POST['candidate_date1'] ?? null;
$candidateDate2 = $_POST['candidate_date2'] ?: null;
$candidateDate3 = $_POST['candidate_date3'] ?: null;

// バリデーション
if (!$studentId || !$guardianId || !$purpose || !$candidateDate1) {
    $_SESSION['error'] = '必須項目を入力してください。';
    header("Location: meeting_request.php?student_id=$studentId");
    exit;
}

try {
    $pdo->beginTransaction();

    // 面談リクエストを保存
    $stmt = $pdo->prepare("
        INSERT INTO meeting_requests (
            classroom_id, student_id, guardian_id, staff_id,
            purpose, purpose_detail, related_plan_id, related_monitoring_id,
            candidate_date1, candidate_date2, candidate_date3,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $classroomId, $studentId, $guardianId, $staffId,
        $purpose, $purposeDetail, $relatedPlanId, $relatedMonitoringId,
        $candidateDate1, $candidateDate2, $candidateDate3
    ]);
    $meetingRequestId = $pdo->lastInsertId();

    // チャットルームを取得または作成
    $stmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE student_id = ? AND guardian_id = ?");
    $stmt->execute([$studentId, $guardianId]);
    $room = $stmt->fetch();

    if (!$room) {
        $stmt = $pdo->prepare("INSERT INTO chat_rooms (student_id, guardian_id) VALUES (?, ?)");
        $stmt->execute([$studentId, $guardianId]);
        $roomId = $pdo->lastInsertId();
    } else {
        $roomId = $room['id'];
    }

    // 候補日時のフォーマット
    $dateFormat = 'Y年n月j日 H:i';
    $date1Str = date($dateFormat, strtotime($candidateDate1));
    $date2Str = $candidateDate2 ? date($dateFormat, strtotime($candidateDate2)) : '';
    $date3Str = $candidateDate3 ? date($dateFormat, strtotime($candidateDate3)) : '';

    // チャットメッセージを作成
    $messageText = "【面談予約のご案内】\n\n";
    $messageText .= "面談目的：{$purpose}\n";
    if ($purposeDetail) {
        $messageText .= "詳細：{$purposeDetail}\n";
    }
    $messageText .= "\n以下の日程から、ご都合の良い日時をお選びください。\n\n";
    $messageText .= "① {$date1Str}\n";
    if ($date2Str) {
        $messageText .= "② {$date2Str}\n";
    }
    if ($date3Str) {
        $messageText .= "③ {$date3Str}\n";
    }
    $messageText .= "\n下記リンクから回答してください。\n";
    $messageText .= "ご都合が合わない場合は、別の希望日時を提案いただけます。";

    // チャットメッセージを保存
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (room_id, sender_type, sender_id, message, message_type, meeting_request_id, created_at)
        VALUES (?, 'staff', ?, ?, 'meeting_request', ?, NOW())
    ");
    $stmt->execute([$roomId, $staffId, $messageText, $meetingRequestId]);

    // チャットルームの最終メッセージ日時を更新
    $stmt = $pdo->prepare("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = ?");
    $stmt->execute([$roomId]);

    $pdo->commit();

    $_SESSION['success'] = '面談予約リクエストを送信しました。';
    header("Location: chat.php?student_id=$studentId");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Meeting request save error: " . $e->getMessage());
    $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    header("Location: meeting_request.php?student_id=$studentId");
    exit;
}
