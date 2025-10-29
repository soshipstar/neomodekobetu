<?php
/**
 * 特定の生徒のチャットメッセージをすべて削除
 */

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDbConnection();

    // 削除対象の生徒名
    $studentName = '平元　俊翔';

    echo "生徒チャットメッセージ削除スクリプト\n";
    echo str_repeat("=", 60) . "\n";
    echo "対象生徒: {$studentName}\n\n";

    // 生徒IDを取得
    $stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE student_name = ?");
    $stmt->execute([$studentName]);
    $student = $stmt->fetch();

    if (!$student) {
        echo "エラー: 生徒「{$studentName}」が見つかりませんでした。\n";
        echo "\n登録されている生徒一覧:\n";
        $stmt = $pdo->query("SELECT id, student_name FROM students ORDER BY student_name");
        $students = $stmt->fetchAll();
        foreach ($students as $s) {
            echo "  ID: {$s['id']} - {$s['student_name']}\n";
        }
        exit(1);
    }

    $studentId = $student['id'];
    echo "生徒ID: {$studentId}\n";

    // チャットルームを取得
    $stmt = $pdo->prepare("SELECT id FROM student_chat_rooms WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $room = $stmt->fetch();

    if (!$room) {
        echo "この生徒のチャットルームは存在しません。\n";
        echo "削除するメッセージはありません。\n";
        exit(0);
    }

    $roomId = $room['id'];
    echo "チャットルームID: {$roomId}\n\n";

    // メッセージ数を確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM student_chat_messages
        WHERE room_id = ?
    ");
    $stmt->execute([$roomId]);
    $messageCount = $stmt->fetchColumn();

    echo "削除対象メッセージ数: {$messageCount}件\n";

    if ($messageCount == 0) {
        echo "削除するメッセージはありません。\n";
        exit(0);
    }

    // メッセージの詳細を表示
    echo "\nメッセージ詳細:\n";
    echo str_repeat("-", 60) . "\n";
    $stmt = $pdo->prepare("
        SELECT
            scm.id,
            scm.sender_type,
            scm.message,
            scm.created_at,
            CASE
                WHEN scm.sender_type = 'student' THEN s.student_name
                WHEN scm.sender_type = 'staff' THEN u.full_name
            END as sender_name
        FROM student_chat_messages scm
        LEFT JOIN students s ON scm.sender_type = 'student' AND scm.sender_id = s.id
        LEFT JOIN users u ON scm.sender_type = 'staff' AND scm.sender_id = u.id
        WHERE scm.room_id = ?
        ORDER BY scm.created_at ASC
    ");
    $stmt->execute([$roomId]);
    $messages = $stmt->fetchAll();

    foreach ($messages as $msg) {
        $preview = mb_substr($msg['message'], 0, 50);
        if (mb_strlen($msg['message']) > 50) {
            $preview .= '...';
        }
        echo sprintf("[%s] %s (%s): %s\n",
            $msg['created_at'],
            $msg['sender_name'],
            $msg['sender_type'],
            $preview
        );
    }

    echo "\n" . str_repeat("-", 60) . "\n";
    echo "これらのメッセージを削除します。よろしいですか？ (yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirmation = trim(strtolower($line));
    fclose($handle);

    if ($confirmation !== 'yes' && $confirmation !== 'y') {
        echo "削除をキャンセルしました。\n";
        exit(0);
    }

    // トランザクション開始
    $pdo->beginTransaction();

    // 添付ファイルがある場合は削除
    $stmt = $pdo->prepare("
        SELECT attachment_path
        FROM student_chat_messages
        WHERE room_id = ? AND attachment_path IS NOT NULL
    ");
    $stmt->execute([$roomId]);
    $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($attachments)) {
        echo "\n添付ファイルを削除中...\n";
        foreach ($attachments as $path) {
            $fullPath = __DIR__ . '/' . $path;
            if (file_exists($fullPath)) {
                if (unlink($fullPath)) {
                    echo "  削除: {$path}\n";
                } else {
                    echo "  削除失敗: {$path}\n";
                }
            }
        }
    }

    // メッセージを削除
    echo "\nメッセージをデータベースから削除中...\n";
    $stmt = $pdo->prepare("DELETE FROM student_chat_messages WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $deletedCount = $stmt->rowCount();

    // コミット
    $pdo->commit();

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "削除完了\n";
    echo "削除されたメッセージ数: {$deletedCount}件\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\nエラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
