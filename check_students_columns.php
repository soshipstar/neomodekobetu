<?php
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<h1>studentsテーブルのカラム確認</h1>";

try {
    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll();

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>カラム名</th><th>型</th><th>NULL許可</th><th>キー</th><th>デフォルト</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // departmentカラムの存在確認
    $hasDepartment = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'department') {
            $hasDepartment = true;
            break;
        }
    }

    echo "<br><br>";
    if ($hasDepartment) {
        echo "✓ departmentカラムは存在します";
    } else {
        echo "✗ departmentカラムが存在しません";

        // classroomsテーブルを確認
        echo "<h2>classroomsテーブルの確認</h2>";
        $stmt = $pdo->query("DESCRIBE classrooms");
        $classroomCols = $stmt->fetchAll();

        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>カラム名</th><th>型</th></tr>";
        foreach ($classroomCols as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
?>
