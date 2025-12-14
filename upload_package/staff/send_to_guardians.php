<?php
/**
 * 保護者への送信処理
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

// 強力なtrim処理関数
if (!function_exists('powerTrim')) {
    function powerTrim($text) {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);
    }
}

$pdo = getDbConnection();
$currentUser = getCurrentUser();

$activityId = $_POST['activity_id'] ?? null;
$notes = $_POST['notes'] ?? [];

if (!$activityId || empty($notes)) {
    $_SESSION['error'] = 'データが不正です';
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動の日付を保存（リダイレクト用）
$recordDate = null;

// 活動情報を取得（同じ教室のスタッフが作成した活動も送信可能）
$classroomId = $_SESSION['classroom_id'] ?? null;

if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.record_date, u.classroom_id as staff_classroom_id
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        WHERE dr.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$activityId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, activity_name, record_date
        FROM daily_records
        WHERE id = ? AND staff_id = ?
    ");
    $stmt->execute([$activityId, $currentUser['id']]);
}

$activity = $stmt->fetch();

if (!$activity) {
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動の日付を保存
$recordDate = $activity['record_date'];

try {
    $pdo->beginTransaction();

    $sentCount = 0;

    foreach ($notes as $studentId => $integratedContent) {
        // 強力なtrim処理（全角スペース、特殊文字も削除）
        $integratedContent = powerTrim($integratedContent);

        if (empty($integratedContent)) {
            continue;
        }

        // 生徒情報を取得（保護者IDを含む）
        $stmt = $pdo->prepare("
            SELECT id, guardian_id
            FROM students
            WHERE id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) {
            continue;
        }

        // 既存の統合記録があるかチェック
        $stmt = $pdo->prepare("
            SELECT id, is_sent
            FROM integrated_notes
            WHERE daily_record_id = ? AND student_id = ?
        ");
        $stmt->execute([$activityId, $studentId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // 既に送信済みの場合はスキップ
            if ($existing['is_sent']) {
                continue;
            }

            // 更新して送信済みにマーク
            $stmt = $pdo->prepare("
                UPDATE integrated_notes
                SET integrated_content = ?, is_sent = 1, sent_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$integratedContent, $existing['id']]);
            $integratedNoteId = $existing['id'];
        } else {
            // 新規作成
            $stmt = $pdo->prepare("
                INSERT INTO integrated_notes
                (daily_record_id, student_id, integrated_content, is_sent, sent_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$activityId, $studentId, $integratedContent]);
            $integratedNoteId = $pdo->lastInsertId();
        }

        // 保護者がいる場合、送信履歴を記録
        if ($student['guardian_id']) {
            $stmt = $pdo->prepare("
                INSERT INTO send_history (integrated_note_id, guardian_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$integratedNoteId, $student['guardian_id']]);
        }

        $sentCount++;
    }

    $pdo->commit();

    $_SESSION['success'] = "{$sentCount}件の連絡帳を保護者に送信しました";

    // 活動の日付の活動管理ページにリダイレクト
    header('Location: renrakucho_activities.php?date=' . urlencode($recordDate));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error sending to guardians: " . $e->getMessage());

    $_SESSION['error'] = '送信中にエラーが発生しました: ' . $e->getMessage();

    // 日付が取得できている場合は、その日付のページにリダイレクト
    if ($recordDate) {
        header('Location: renrakucho_activities.php?date=' . urlencode($recordDate));
    } else {
        header('Location: renrakucho_activities.php');
    }
    exit;
}
