<?php
/**
 * 既存の保護者のclassroom_idを更新するスクリプト
 *
 * 実行方法: php update_guardian_classroom.php
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

try {
    echo "=== 保護者のclassroom_id更新処理を開始します ===\n\n";

    // 現在の状況を確認
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM users
        WHERE user_type = 'guardian' AND classroom_id IS NULL
    ");
    $nullCount = $stmt->fetch()['count'];
    echo "classroom_idがNULLの保護者: {$nullCount}名\n\n";

    if ($nullCount == 0) {
        echo "更新対象の保護者はいません。\n";
        exit(0);
    }

    // トランザクション開始
    $pdo->beginTransaction();

    // スタッフのclassroom_idを取得（最初のスタッフの教室を使用）
    $stmt = $pdo->query("
        SELECT classroom_id
        FROM users
        WHERE user_type IN ('staff', 'admin') AND classroom_id IS NOT NULL
        LIMIT 1
    ");
    $defaultClassroom = $stmt->fetch();

    if (!$defaultClassroom) {
        // スタッフがいない場合は、最初の教室を使用
        $stmt = $pdo->query("SELECT MIN(id) as id FROM classrooms");
        $classroomRow = $stmt->fetch();
        $classroomId = $classroomRow['id'];
        echo "スタッフが見つからないため、教室ID={$classroomId}を使用します。\n";
    } else {
        $classroomId = $defaultClassroom['classroom_id'];
        echo "スタッフの教室ID={$classroomId}を使用します。\n";
    }

    // classroom_idがNULLの保護者を更新
    $stmt = $pdo->prepare("
        UPDATE users
        SET classroom_id = ?
        WHERE user_type = 'guardian' AND classroom_id IS NULL
    ");
    $stmt->execute([$classroomId]);
    $updatedCount = $stmt->rowCount();

    // コミット
    $pdo->commit();

    echo "\n更新完了: {$updatedCount}名の保護者のclassroom_idを更新しました。\n\n";

    // 更新後の確認
    echo "=== 更新後の保護者一覧 ===\n";
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username,
            u.full_name,
            u.classroom_id,
            c.classroom_name
        FROM users u
        LEFT JOIN classrooms c ON u.classroom_id = c.id
        WHERE u.user_type = 'guardian'
        ORDER BY u.id
    ");
    $guardians = $stmt->fetchAll();

    foreach ($guardians as $guardian) {
        printf(
            "ID:%d %s (%s) - 教室: %s (ID:%s)\n",
            $guardian['id'],
            $guardian['full_name'],
            $guardian['username'],
            $guardian['classroom_name'] ?? '未設定',
            $guardian['classroom_id'] ?? 'NULL'
        );
    }

    echo "\n処理が正常に完了しました。\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}
