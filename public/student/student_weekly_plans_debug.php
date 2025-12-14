<?php
/**
 * スタッフ用 - 生徒週間計画表一覧（デバッグ版）
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<!-- デバッグ開始 -->\n";

try {
    echo "<!-- auth.php を読み込み -->\n";
    require_once __DIR__ . '/../../includes/auth.php';
    echo "<!-- auth.php 読み込み成功 -->\n";
} catch (Throwable $e) {
    die("auth.php エラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    echo "<!-- database.php を読み込み -->\n";
    require_once __DIR__ . '/../../config/database.php';
    echo "<!-- database.php 読み込み成功 -->\n";
} catch (Throwable $e) {
    die("database.php エラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    echo "<!-- requireUserType を呼び出し -->\n";
    requireUserType(['staff', 'admin']);
    echo "<!-- requireUserType 成功 -->\n";
} catch (Throwable $e) {
    die("requireUserType エラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    echo "<!-- データベース接続 -->\n";
    $pdo = getDbConnection();
    echo "<!-- データベース接続成功 -->\n";
} catch (Throwable $e) {
    die("DB接続エラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    echo "<!-- getCurrentUser を呼び出し -->\n";
    $currentUser = getCurrentUser();
    echo "<!-- getCurrentUser 成功 -->\n";
} catch (Throwable $e) {
    die("getCurrentUser エラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

$classroomId = $_SESSION['classroom_id'] ?? null;
echo "<!-- classroom_id: " . ($classroomId ?? 'NULL') . " -->\n";

// 生徒一覧を取得
try {
    echo "<!-- 生徒一覧クエリ実行 -->\n";
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.student_name
            FROM students s
            INNER JOIN users g ON s.guardian_id = g.id
            WHERE g.classroom_id = ?
            ORDER BY s.student_name
        ");
        $stmt->execute([$classroomId]);
    } else {
        $stmt = $pdo->query("
            SELECT id, student_name
            FROM students
            ORDER BY student_name
        ");
    }
    $students = $stmt->fetchAll();
    echo "<!-- 生徒数: " . count($students) . " -->\n";
} catch (Throwable $e) {
    die("生徒一覧クエリエラー: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>デバッグ成功</title></head><body>";
echo "<h1>デバッグ成功！</h1>";
echo "<p>生徒数: " . count($students) . "</p>";
echo "<pre>";
print_r($students);
echo "</pre>";
echo "</body></html>";
