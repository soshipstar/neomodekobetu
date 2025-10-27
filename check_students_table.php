<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "=== Students Table Structure ===\n";
$stmt = $pdo->query("DESCRIBE students");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']} - Null:{$row['Null']} - Key:{$row['Key']}\n";
}

echo "\n=== Chat Rooms Table Structure ===\n";
$stmt = $pdo->query("DESCRIBE chat_rooms");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']} - Null:{$row['Null']} - Key:{$row['Key']}\n";
}

echo "\n=== Sample Student Data ===\n";
$stmt = $pdo->query("SELECT id, student_name, guardian_id FROM students LIMIT 3");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, Name: {$row['student_name']}, Guardian: {$row['guardian_id']}\n";
}
