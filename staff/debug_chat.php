<?php
/**
 * チャットページデバッグ用
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();

echo "<h1>デバッグ情報</h1>";

// テーブル存在チェック
echo "<h2>1. テーブル存在チェック</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "存在するテーブル: " . implode(", ", $tables) . "<br>";

    if (in_array('chat_rooms', $tables)) {
        echo "✓ chat_rooms テーブルは存在します<br>";
    } else {
        echo "✗ chat_rooms テーブルが存在しません<br>";
    }

    if (in_array('chat_messages', $tables)) {
        echo "✓ chat_messages テーブルは存在します<br>";
    } else {
        echo "✗ chat_messages テーブルが存在しません<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// 生徒テーブルチェック
echo "<h2>2. 生徒データチェック</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE is_active = 1");
    $result = $stmt->fetch();
    echo "アクティブな生徒数: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// シンプルなクエリテスト
echo "<h2>3. シンプルなクエリテスト</h2>";
try {
    $sql = "
        SELECT
            s.id as student_id,
            s.student_name,
            s.department,
            s.guardian_id,
            u.full_name as guardian_name
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    echo "取得した生徒数: " . count($students) . "<br>";
    foreach ($students as $student) {
        echo "- " . htmlspecialchars($student['student_name']) . " (ID: " . $student['student_id'] . ")<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// chat_roomsとのJOINテスト
echo "<h2>4. chat_roomsとのJOINテスト</h2>";
try {
    $sql = "
        SELECT
            s.id as student_id,
            s.student_name,
            cr.id as room_id
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        LEFT JOIN chat_rooms cr ON s.id = cr.student_id AND s.guardian_id = cr.guardian_id
        WHERE s.is_active = 1
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    echo "取得した生徒数: " . count($students) . "<br>";
    foreach ($students as $student) {
        echo "- " . htmlspecialchars($student['student_name']) . " (room_id: " . ($student['room_id'] ?: 'なし') . ")<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// サブクエリテスト
echo "<h2>5. サブクエリ付きテスト</h2>";
try {
    $sql = "
        SELECT
            s.id as student_id,
            s.student_name,
            s.department,
            s.guardian_id,
            u.full_name as guardian_name,
            cr.id as room_id,
            cr.last_message_at,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.room_id = cr.id AND cm.sender_type = 'guardian' AND cm.is_read = 0) as unread_count
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        LEFT JOIN chat_rooms cr ON s.id = cr.student_id AND s.guardian_id = cr.guardian_id
        WHERE s.is_active = 1
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    echo "取得した生徒数: " . count($students) . "<br>";
    foreach ($students as $student) {
        echo "- " . htmlspecialchars($student['student_name']) . " (未読: " . ($student['unread_count'] ?? 0) . ")<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// フルクエリテスト
echo "<h2>6. フルクエリテスト（ORDER BY含む）</h2>";
try {
    $sql = "
        SELECT
            s.id as student_id,
            s.student_name,
            s.department,
            s.guardian_id,
            u.full_name as guardian_name,
            cr.id as room_id,
            cr.last_message_at,
            (SELECT COUNT(*) FROM chat_messages WHERE room_id = cr.id AND sender_type = 'guardian' AND is_read = 0) as unread_count
        FROM students s
        LEFT JOIN users u ON s.guardian_id = u.id
        LEFT JOIN chat_rooms cr ON s.id = cr.student_id AND s.guardian_id = cr.guardian_id
        WHERE s.is_active = 1
        ORDER BY CASE WHEN cr.last_message_at IS NULL THEN 1 ELSE 0 END, cr.last_message_at DESC, s.student_name ASC
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    echo "✓ クエリ成功！取得した生徒数: " . count($students) . "<br>";
    foreach ($students as $student) {
        echo "- " . htmlspecialchars($student['student_name']) . " (room: " . ($student['room_id'] ?: 'なし') . ", 未読: " . ($student['unread_count'] ?? 0) . ")<br>";
    }
} catch (Exception $e) {
    echo "✗ エラー: " . $e->getMessage() . "<br>";
}
?>
