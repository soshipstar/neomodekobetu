<?php
/**
 * 個別支援計画書 保護者確認API
 *
 * 新しいワークフロー:
 * - action: submit_comment - 変更希望コメントを送信
 * - action: confirm_review - 内容確認（変更なし）
 */
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$planId = $input['plan_id'] ?? null;
$action = $input['action'] ?? null;
$reviewComment = $input['review_comment'] ?? null;

if (!$planId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'プランIDが指定されていません']);
    exit;
}

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'アクションが指定されていません']);
    exit;
}

try {
    // この計画が保護者の生徒のものか確認
    $stmt = $pdo->prepare("
        SELECT isp.id, isp.student_id, isp.guardian_confirmed, isp.is_official, isp.guardian_review_comment
        FROM individual_support_plans isp
        INNER JOIN students s ON isp.student_id = s.id
        WHERE isp.id = ? AND s.guardian_id = ? AND isp.is_draft = 0
    ");
    $stmt->execute([$planId, $guardianId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '計画が見つかりません']);
        exit;
    }

    // 既に正式版で確認済みの場合
    if ($plan['guardian_confirmed'] && $plan['is_official']) {
        echo json_encode(['success' => false, 'message' => '既に確認・署名済みです']);
        exit;
    }

    // 既にコメントを送信済みの場合
    if ($plan['guardian_review_comment'] && $action === 'submit_comment') {
        echo json_encode(['success' => false, 'message' => '既にコメントを送信済みです']);
        exit;
    }

    if ($action === 'submit_comment') {
        // 変更希望コメントを送信
        if (empty($reviewComment)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'コメントを入力してください']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE individual_support_plans
            SET guardian_review_comment = ?,
                guardian_review_comment_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reviewComment, $planId]);

        echo json_encode(['success' => true, 'message' => 'コメントを送信しました']);

    } elseif ($action === 'confirm_review') {
        // 内容確認（変更なし）- 案を確認済みにする
        // guardian_confirmed は正式版の署名時にセットするので、ここでは別のフラグで管理
        // guardian_review_comment を空文字にセットして「確認済み（変更なし）」を表す
        $stmt = $pdo->prepare("
            UPDATE individual_support_plans
            SET guardian_review_comment = '',
                guardian_review_comment_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$planId]);

        echo json_encode(['success' => true, 'message' => '確認しました']);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無効なアクションです']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
?>
