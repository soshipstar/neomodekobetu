<?php
/**
 * 提出物データのデバッグスクリプト
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

echo "<h1>提出物データのデバッグ</h1>";

// 週間計画表の提出物テーブルの状態確認
echo "<h2>1. weekly_plan_submissions テーブルの確認</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'weekly_plan_submissions'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✓ テーブルは存在します<br>";

        // カラム構造を確認
        $stmt = $pdo->query("DESCRIBE weekly_plan_submissions");
        echo "<h3>カラム構造:</h3>";
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";

        // データ件数を確認
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM weekly_plan_submissions");
        $count = $stmt->fetch()['count'];
        echo "<p>データ件数: {$count}件</p>";

        if ($count > 0) {
            // サンプルデータを表示
            $stmt = $pdo->query("SELECT * FROM weekly_plan_submissions LIMIT 5");
            echo "<h3>サンプルデータ (最大5件):</h3>";
            echo "<pre>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
            }
            echo "</pre>";
        }
    } else {
        echo "✗ テーブルが存在しません<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// student_submissions テーブルの確認
echo "<h2>2. student_submissions テーブルの確認</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_submissions'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✓ テーブルは存在します<br>";

        // カラム構造を確認
        $stmt = $pdo->query("DESCRIBE student_submissions");
        echo "<h3>カラム構造:</h3>";
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";

        // データ件数を確認
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM student_submissions");
        $count = $stmt->fetch()['count'];
        echo "<p>データ件数: {$count}件</p>";

        if ($count > 0) {
            // サンプルデータを表示
            $stmt = $pdo->query("SELECT * FROM student_submissions LIMIT 5");
            echo "<h3>サンプルデータ (最大5件):</h3>";
            echo "<pre>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                print_r($row);
            }
            echo "</pre>";
        }
    } else {
        echo "✗ テーブルが存在しません<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// submission_requests テーブルの確認
echo "<h2>3. submission_requests テーブルの確認</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'submission_requests'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "✓ テーブルは存在します<br>";

        // データ件数を確認
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM submission_requests");
        $count = $stmt->fetch()['count'];
        echo "<p>データ件数: {$count}件</p>";
    } else {
        echo "✗ テーブルが存在しません<br>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// 週間計画表との結合確認
echo "<h2>4. 週間計画表との結合確認</h2>";
try {
    $stmt = $pdo->query("
        SELECT
            wps.*,
            wp.student_id,
            s.student_name
        FROM weekly_plan_submissions wps
        LEFT JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
        LEFT JOIN students s ON wp.student_id = s.id
        LIMIT 5
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        echo "<h3>結合結果 (最大5件):</h3>";
        echo "<pre>";
        print_r($rows);
        echo "</pre>";
    } else {
        echo "<p>結合結果がありません</p>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// 週間計画表テーブルの確認
echo "<h2>5. weekly_plans テーブルの確認</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM weekly_plans");
    $count = $stmt->fetch()['count'];
    echo "<p>週間計画表データ件数: {$count}件</p>";

    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, student_id, week_start_date, created_at FROM weekly_plans ORDER BY created_at DESC LIMIT 5");
        echo "<h3>最新の週間計画表 (最大5件):</h3>";
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

// 生徒の一覧
echo "<h2>6. 生徒一覧</h2>";
try {
    $stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 LIMIT 10");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, 名前: {$row['student_name']}\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p>テスト用生徒ID（例: 1）を入力して、その生徒の提出物を確認できます</p>";
echo '<form method="GET">';
echo '生徒ID: <input type="number" name="test_student_id" value="' . ($_GET['test_student_id'] ?? '1') . '">';
echo '<button type="submit">確認</button>';
echo '</form>';

if (isset($_GET['test_student_id'])) {
    $testStudentId = (int)$_GET['test_student_id'];

    echo "<h2>7. 生徒ID {$testStudentId} の提出物確認</h2>";

    // 週間計画表の提出物
    echo "<h3>週間計画表の提出物:</h3>";
    try {
        $stmt = $pdo->prepare("
            SELECT
                wps.id,
                wps.submission_item as title,
                wps.due_date,
                wps.is_completed,
                wp.week_start_date,
                wp.id as plan_id
            FROM weekly_plan_submissions wps
            INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
            WHERE wp.student_id = ?
            ORDER BY wps.due_date DESC
        ");
        $stmt->execute([$testStudentId]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($submissions)) {
            echo "<pre>";
            print_r($submissions);
            echo "</pre>";
        } else {
            echo "<p>この生徒の週間計画表提出物はありません</p>";
        }
    } catch (Exception $e) {
        echo "エラー: " . $e->getMessage() . "<br>";
    }

    // 生徒が登録した提出物
    echo "<h3>生徒が登録した提出物:</h3>";
    try {
        $stmt = $pdo->prepare("SELECT * FROM student_submissions WHERE student_id = ?");
        $stmt->execute([$testStudentId]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($submissions)) {
            echo "<pre>";
            print_r($submissions);
            echo "</pre>";
        } else {
            echo "<p>この生徒が登録した提出物はありません</p>";
        }
    } catch (Exception $e) {
        echo "エラー: " . $e->getMessage() . "<br>";
    }

    // カレンダー表示用クエリのテスト（今月分）
    echo "<h3>カレンダー表示用クエリのテスト（今月分）:</h3>";
    try {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        echo "<p>検索期間: {$monthStart} ～ {$monthEnd}</p>";

        // dashboard.php と同じクエリ
        $stmt = $pdo->prepare("
            SELECT
                wps.id,
                wps.submission_item,
                wps.due_date,
                wps.is_completed,
                DATEDIFF(wps.due_date, CURDATE()) as days_until_due
            FROM weekly_plan_submissions wps
            INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
            WHERE wp.student_id = ? AND wps.is_completed = 0
            ORDER BY wps.due_date ASC
        ");
        $stmt->execute([$testStudentId]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($submissions)) {
            echo "<p>未完了の提出物: " . count($submissions) . "件</p>";
            echo "<pre>";
            print_r($submissions);
            echo "</pre>";
        } else {
            echo "<p>未完了の提出物はありません</p>";
        }

        // schedule.php と同じクエリ
        $stmt = $pdo->prepare("
            SELECT
                wps.id,
                wps.submission_item,
                wps.due_date,
                wps.is_completed
            FROM weekly_plan_submissions wps
            INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
            WHERE wp.student_id = ? AND wps.due_date BETWEEN ? AND ?
            ORDER BY wps.due_date
        ");
        $stmt->execute([$testStudentId, $monthStart, $monthEnd]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($submissions)) {
            echo "<p>今月の提出物（完了含む）: " . count($submissions) . "件</p>";
            echo "<pre>";
            print_r($submissions);
            echo "</pre>";
        } else {
            echo "<p>今月の提出物はありません</p>";
        }

    } catch (Exception $e) {
        echo "エラー: " . $e->getMessage() . "<br>";
    }
}
