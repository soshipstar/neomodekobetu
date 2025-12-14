<?php
/**
 * スタッフ用 - 生徒情報の保存・更新処理（デバッグ版）
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>デバッグモード</h1>";
echo "<pre>";

try {
    echo "ステップ1: ファイル読み込み開始\n";

    require_once __DIR__ . '/../../config/database.php';
    echo "✓ database.php 読み込み成功\n";

    require_once __DIR__ . '/../../includes/auth.php';
    echo "✓ auth.php 読み込み成功\n";

    require_once __DIR__ . '/../../includes/student_helper.php';
    echo "✓ student_helper.php 読み込み成功\n";

    require_once __DIR__ . '/../../includes/kakehashi_helper.php';
    echo "✓ kakehashi_helper.php 読み込み成功\n";

    echo "\nステップ2: ログインチェック\n";
    requireLogin();
    echo "✓ ログイン確認成功\n";

    checkUserType('staff');
    echo "✓ 権限確認成功\n";

    echo "\nステップ3: データベース接続\n";
    $pdo = getDbConnection();
    echo "✓ データベース接続成功\n";

    $action = $_POST['action'] ?? '';
    echo "\nアクション: " . htmlspecialchars($action) . "\n";

    echo "\nPOSTデータ:\n";
    print_r($_POST);

    if ($action === 'create') {
        echo "\n=== 新規生徒登録処理 ===\n";

        $studentName = trim($_POST['student_name']);
        $birthDate = $_POST['birth_date'] ?? null;
        $kakehashiInitialDate = $_POST['kakehashi_initial_date'] ?? null;
        $guardianId = !empty($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : null;

        echo "生徒名: " . htmlspecialchars($studentName) . "\n";
        echo "生年月日: " . htmlspecialchars($birthDate) . "\n";
        echo "初回かけはし作成日: " . htmlspecialchars($kakehashiInitialDate) . "\n";
        echo "保護者ID: " . htmlspecialchars($guardianId) . "\n";

        // 参加予定曜日
        $scheduledMonday = isset($_POST['scheduled_monday']) ? 1 : 0;
        $scheduledTuesday = isset($_POST['scheduled_tuesday']) ? 1 : 0;
        $scheduledWednesday = isset($_POST['scheduled_wednesday']) ? 1 : 0;
        $scheduledThursday = isset($_POST['scheduled_thursday']) ? 1 : 0;
        $scheduledFriday = isset($_POST['scheduled_friday']) ? 1 : 0;
        $scheduledSaturday = isset($_POST['scheduled_saturday']) ? 1 : 0;
        $scheduledSunday = isset($_POST['scheduled_sunday']) ? 1 : 0;

        if (empty($studentName) || empty($birthDate)) {
            throw new Exception('生徒名と生年月日は必須です。');
        }

        echo "\n学年計算中...\n";
        $gradeLevel = calculateGradeLevel($birthDate);
        echo "学年: " . htmlspecialchars($gradeLevel) . "\n";

        echo "\nINSERT実行中...\n";
        $stmt = $pdo->prepare("
            INSERT INTO students (
                student_name, birth_date, kakehashi_initial_date, grade_level, guardian_id, is_active, created_at,
                scheduled_monday, scheduled_tuesday, scheduled_wednesday, scheduled_thursday,
                scheduled_friday, scheduled_saturday, scheduled_sunday
            )
            VALUES (?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $studentName, $birthDate, $kakehashiInitialDate, $gradeLevel, $guardianId,
            $scheduledMonday, $scheduledTuesday, $scheduledWednesday, $scheduledThursday,
            $scheduledFriday, $scheduledSaturday, $scheduledSunday
        ]);

        $studentId = $pdo->lastInsertId();
        echo "✓ 生徒登録成功 ID: " . $studentId . "\n";

        // かけはし期間の自動生成
        if (!empty($kakehashiInitialDate)) {
            echo "\nかけはし期間生成開始...\n";
            echo "生徒ID: " . $studentId . "\n";
            echo "初回日: " . htmlspecialchars($kakehashiInitialDate) . "\n";

            try {
                $result = generateKakehashiPeriods($pdo, $studentId, $kakehashiInitialDate);
                echo "✓ かけはし期間生成成功\n";
                echo "生成された期間:\n";
                print_r($result);
            } catch (Exception $e) {
                echo "⚠ かけはし期間生成エラー: " . $e->getMessage() . "\n";
                echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
            }
        }

        echo "\n=== 処理完了 ===\n";
        echo "通常版では students.php?success=created にリダイレクトします\n";
    }

} catch (Exception $e) {
    echo "\n❌ エラーが発生しました\n";
    echo "エラーメッセージ: " . $e->getMessage() . "\n";
    echo "\nスタックトレース:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
