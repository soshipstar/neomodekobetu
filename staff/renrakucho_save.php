<?php
/**
 * 連絡帳保存処理（複数活動対応）
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// POSTデータを取得
$action = $_POST['action'] ?? '';
$activityName = trim($_POST['activity_name'] ?? '');
$commonActivity = trim($_POST['common_activity'] ?? '');
$recordDate = $_POST['record_date'] ?? date('Y-m-d');
$students = $_POST['students'] ?? [];
$activityId = $_POST['activity_id'] ?? null;

// バリデーション
if (empty($activityName)) {
    $_SESSION['error'] = '活動名を入力してください';
    header('Location: renrakucho.php');
    exit;
}

if (empty($commonActivity)) {
    $_SESSION['error'] = '本日の活動（共通）を入力してください';
    header('Location: renrakucho.php');
    exit;
}

if (empty($students)) {
    $_SESSION['error'] = '参加者を選択してください';
    header('Location: renrakucho.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($activityId) {
        // 既存の活動を更新（同じ教室のスタッフが作成した活動も更新可能）
        // まず、この活動が自分の教室のものか確認
        $classroomId = $_SESSION['classroom_id'] ?? null;
        if ($classroomId) {
            $checkStmt = $pdo->prepare("
                SELECT dr.id FROM daily_records dr
                INNER JOIN users u ON dr.staff_id = u.id
                WHERE dr.id = ? AND u.classroom_id = ?
            ");
            $checkStmt->execute([$activityId, $classroomId]);
            if (!$checkStmt->fetch()) {
                throw new Exception('この活動を更新する権限がありません');
            }
        }

        $stmt = $pdo->prepare("
            UPDATE daily_records
            SET activity_name = ?, common_activity = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$activityName, $commonActivity, $activityId]);

        // 既存の生徒記録を削除（後で再挿入）
        $stmt = $pdo->prepare("
            DELETE FROM student_records
            WHERE daily_record_id = ?
        ");
        $stmt->execute([$activityId]);

        $recordId = $activityId;
    } else {
        // 新規活動を作成
        $stmt = $pdo->prepare("
            INSERT INTO daily_records (record_date, staff_id, activity_name, common_activity)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$recordDate, $currentUser['id'], $activityName, $commonActivity]);
        $recordId = $pdo->lastInsertId();
    }

    // 生徒記録を保存
    $stmt = $pdo->prepare("
        INSERT INTO student_records
        (daily_record_id, student_id, daily_note, domain1, domain1_content, domain2, domain2_content)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($students as $studentData) {
        $studentId = $studentData['id'];
        $dailyNote = trim($studentData['daily_note'] ?? '');
        $domain1 = $studentData['domain1'];
        $domain1Content = trim($studentData['domain1_content']);
        $domain2 = $studentData['domain2'] ?? null;
        $domain2Content = trim($studentData['domain2_content'] ?? '');

        // バリデーション
        if (empty($domain1) || empty($domain1Content)) {
            throw new Exception('全ての必須項目を入力してください');
        }

        if ($domain1 === $domain2 && !empty($domain2)) {
            throw new Exception('同じ領域を2回選択することはできません');
        }

        $stmt->execute([
            $recordId,
            $studentId,
            $dailyNote,
            $domain1,
            $domain1Content,
            $domain2,
            $domain2Content
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = $activityId ? '活動を更新しました' : '活動を保存しました';
    header('Location: renrakucho_activities.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error saving renrakucho: " . $e->getMessage());

    $_SESSION['error'] = '保存中にエラーが発生しました: ' . $e->getMessage();
    header('Location: renrakucho.php');
    exit;
}
