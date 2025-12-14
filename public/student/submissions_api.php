<?php
/**
 * 生徒用提出物API
 */

require_once __DIR__ . '/../../includes/student_auth.php';
require_once __DIR__ . '/../../config/database.php';

requireStudentLogin();

header('Content-Type: application/json');

$pdo = getDbConnection();
$student = getCurrentStudent();
$studentId = $student['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '不正なリクエストです']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // 提出物の追加・編集（生徒が登録したもののみ）
    if (empty($action)) {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';

        if (empty($title) || empty($dueDate)) {
            echo json_encode(['success' => false, 'error' => '必須項目が入力されていません']);
            exit;
        }

        if ($id) {
            // 編集
            $stmt = $pdo->prepare("
                UPDATE student_submissions
                SET title = ?, description = ?, due_date = ?, updated_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$title, $description, $dueDate, $id, $studentId]);
        } else {
            // 新規追加
            $stmt = $pdo->prepare("
                INSERT INTO student_submissions (student_id, title, description, due_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$studentId, $title, $description, $dueDate]);
        }

        echo json_encode(['success' => true]);
    }

    // 完了にする
    elseif ($action === 'complete') {
        $source = $_POST['source'] ?? '';
        $id = $_POST['id'] ?? null;

        if (!$id || !$source) {
            echo json_encode(['success' => false, 'error' => 'パラメータが不足しています']);
            exit;
        }

        if ($source === 'weekly_plan') {
            // 週間計画表の提出物
            $stmt = $pdo->prepare("
                UPDATE weekly_plan_submissions wps
                INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
                SET wps.is_completed = 1,
                    wps.completed_at = NOW(),
                    wps.completed_by_type = 'student',
                    wps.completed_by_id = ?
                WHERE wps.id = ? AND wp.student_id = ?
            ");
            $stmt->execute([$studentId, $id, $studentId]);
        } elseif ($source === 'guardian_chat') {
            // 保護者チャット経由の提出物（生徒は完了フラグのみ変更可能）
            $stmt = $pdo->prepare("
                UPDATE submission_requests sr
                INNER JOIN chat_rooms cr ON sr.room_id = cr.id
                SET sr.is_completed = 1, sr.completed_at = NOW()
                WHERE sr.id = ? AND cr.student_id = ?
            ");
            $stmt->execute([$id, $studentId]);
        } elseif ($source === 'student') {
            // 生徒自身が登録した提出物
            $stmt = $pdo->prepare("
                UPDATE student_submissions
                SET is_completed = 1, completed_at = NOW()
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$id, $studentId]);
        } else {
            echo json_encode(['success' => false, 'error' => '不正なソースです']);
            exit;
        }

        echo json_encode(['success' => true]);
    }

    // 未完了に戻す
    elseif ($action === 'uncomplete') {
        $source = $_POST['source'] ?? '';
        $id = $_POST['id'] ?? null;

        if (!$id || !$source) {
            echo json_encode(['success' => false, 'error' => 'パラメータが不足しています']);
            exit;
        }

        if ($source === 'weekly_plan') {
            $stmt = $pdo->prepare("
                UPDATE weekly_plan_submissions wps
                INNER JOIN weekly_plans wp ON wps.weekly_plan_id = wp.id
                SET wps.is_completed = 0,
                    wps.completed_at = NULL,
                    wps.completed_by_type = NULL,
                    wps.completed_by_id = NULL
                WHERE wps.id = ? AND wp.student_id = ?
            ");
            $stmt->execute([$id, $studentId]);
        } elseif ($source === 'guardian_chat') {
            $stmt = $pdo->prepare("
                UPDATE submission_requests sr
                INNER JOIN chat_rooms cr ON sr.room_id = cr.id
                SET sr.is_completed = 0, sr.completed_at = NULL
                WHERE sr.id = ? AND cr.student_id = ?
            ");
            $stmt->execute([$id, $studentId]);
        } elseif ($source === 'student') {
            $stmt = $pdo->prepare("
                UPDATE student_submissions
                SET is_completed = 0, completed_at = NULL
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$id, $studentId]);
        } else {
            echo json_encode(['success' => false, 'error' => '不正なソースです']);
            exit;
        }

        echo json_encode(['success' => true]);
    }

    // 削除（生徒が登録したもののみ）
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'IDが指定されていません']);
            exit;
        }

        $stmt = $pdo->prepare("
            DELETE FROM student_submissions
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$id, $studentId]);

        echo json_encode(['success' => true]);
    }

    else {
        echo json_encode(['success' => false, 'error' => '不正なアクションです']);
    }

} catch (Exception $e) {
    error_log("Submissions API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'エラーが発生しました']);
}
