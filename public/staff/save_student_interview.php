<?php
/**
 * スタッフ用 - 生徒面談記録の保存処理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$sessionClassroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_POST['student_id'] ?? null;
$classroomId = $_POST['classroom_id'] ?? null;
$interviewId = $_POST['interview_id'] ?? null;

if (!$studentId || !$classroomId) {
    header('Location: student_interviews.php?error=' . urlencode('必須項目が不足しています'));
    exit;
}

// アクセス権限チェック（生徒のclassroom_idでフィルタ）
if ($sessionClassroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id
        FROM students s
        WHERE s.id = ? AND s.classroom_id = ?
    ");
    $stmt->execute([$studentId, $sessionClassroomId]);
} else {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
}

if (!$stmt->fetch()) {
    header('Location: student_interviews.php?error=' . urlencode('アクセス権限がありません'));
    exit;
}

// フィールドデータを収集
$interviewDate = $_POST['interview_date'] ?? null;
$interviewerId = $_POST['interviewer_id'] ?? null;
$interviewContent = trim($_POST['interview_content'] ?? '');
$childWish = trim($_POST['child_wish'] ?? '');
$checkSchool = isset($_POST['check_school']) ? 1 : 0;
$checkSchoolNote = trim($_POST['check_school_note'] ?? '');
$checkHome = isset($_POST['check_home']) ? 1 : 0;
$checkHomeNote = trim($_POST['check_home_note'] ?? '');
$checkTroubles = isset($_POST['check_troubles']) ? 1 : 0;
$checkTroublesNote = trim($_POST['check_troubles_note'] ?? '');
$otherNotes = trim($_POST['other_notes'] ?? '');

if (!$interviewDate || !$interviewerId) {
    header("Location: student_interview_detail.php?student_id=$studentId&error=" . urlencode('面談日と面談者は必須です'));
    exit;
}

try {
    if ($interviewId) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE student_interviews
            SET interview_date = ?,
                interviewer_id = ?,
                interview_content = ?,
                child_wish = ?,
                check_school = ?,
                check_school_note = ?,
                check_home = ?,
                check_home_note = ?,
                check_troubles = ?,
                check_troubles_note = ?,
                other_notes = ?,
                updated_at = NOW()
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([
            $interviewDate,
            $interviewerId,
            $interviewContent,
            $childWish,
            $checkSchool,
            $checkSchoolNote,
            $checkHome,
            $checkHomeNote,
            $checkTroubles,
            $checkTroublesNote,
            $otherNotes,
            $interviewId,
            $studentId
        ]);

        header("Location: student_interview_detail.php?student_id=$studentId&interview_id=$interviewId&success=1");
        exit;
    } else {
        // 新規作成
        $stmt = $pdo->prepare("
            INSERT INTO student_interviews (
                student_id,
                classroom_id,
                interview_date,
                interviewer_id,
                interview_content,
                child_wish,
                check_school,
                check_school_note,
                check_home,
                check_home_note,
                check_troubles,
                check_troubles_note,
                other_notes,
                created_by,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $studentId,
            $classroomId,
            $interviewDate,
            $interviewerId,
            $interviewContent,
            $childWish,
            $checkSchool,
            $checkSchoolNote,
            $checkHome,
            $checkHomeNote,
            $checkTroubles,
            $checkTroublesNote,
            $otherNotes,
            $_SESSION['user_id']
        ]);

        $newInterviewId = $pdo->lastInsertId();

        header("Location: student_interview_detail.php?student_id=$studentId&interview_id=$newInterviewId&success=1");
        exit;
    }
} catch (Exception $e) {
    header("Location: student_interview_detail.php?student_id=$studentId&error=" . urlencode($e->getMessage()));
    exit;
}
