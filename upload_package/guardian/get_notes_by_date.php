<?php
/**
 * 指定日の連絡帳を取得
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// 保護者でない場合はエラー
if ($_SESSION['user_type'] !== 'guardian') {
    echo json_encode(['success' => false, 'error' => '権限がありません']);
    exit;
}

// GETリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

// dateを取得
$date = $_GET['date'] ?? null;

if (!$date) {
    echo json_encode(['success' => false, 'error' => '日付が指定されていません']);
    exit;
}

// 日付のバリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'error' => '日付形式が正しくありません']);
    exit;
}

try {
    $pdo = getDbConnection();
    $guardianId = $_SESSION['user_id'];

    // 保護者の子供の指定日の連絡帳を取得
    $stmt = $pdo->prepare("
        SELECT
            inote.id,
            inote.integrated_content,
            inote.sent_at,
            inote.guardian_confirmed,
            inote.guardian_confirmed_at,
            dr.activity_name,
            dr.record_date,
            s.student_name
        FROM integrated_notes inote
        INNER JOIN daily_records dr ON inote.daily_record_id = dr.id
        INNER JOIN students s ON inote.student_id = s.id
        WHERE s.guardian_id = ?
        AND inote.is_sent = 1
        AND dr.record_date = ?
        ORDER BY s.student_name, inote.sent_at DESC
    ");
    $stmt->execute([$guardianId, $date]);
    $notes = $stmt->fetchAll();

    if (empty($notes)) {
        echo json_encode(['success' => false, 'error' => '連絡帳が見つかりません']);
        exit;
    }

    // レスポンス用にデータを整形
    $notesData = [];
    foreach ($notes as $note) {
        $notesData[] = [
            'id' => $note['id'],
            'student_name' => $note['student_name'],
            'activity_name' => $note['activity_name'],
            'integrated_content' => $note['integrated_content'],
            'sent_time' => date('H:i', strtotime($note['sent_at'])),
            'guardian_confirmed' => (bool)$note['guardian_confirmed'],
            'guardian_confirmed_at' => $note['guardian_confirmed_at'],
            'confirmed_time' => $note['guardian_confirmed_at'] ? date('Y年n月j日 H:i', strtotime($note['guardian_confirmed_at'])) : null
        ];
    }

    echo json_encode([
        'success' => true,
        'notes' => $notesData
    ]);

} catch (Exception $e) {
    error_log("Error fetching notes by date: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました']);
}
