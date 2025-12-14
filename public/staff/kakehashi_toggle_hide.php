<?php
/**
 * かけはし非表示トグルAPI
 */

// エラー表示を有効化
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();

    // パラメータ取得
    $type = $_POST['type'] ?? ''; // 'guardian' or 'staff'
    $periodId = $_POST['period_id'] ?? 0;
    $studentId = $_POST['student_id'] ?? 0;
    $action = $_POST['action'] ?? 'toggle'; // 'toggle', 'hide', 'show'

    if (!in_array($type, ['guardian', 'staff'])) {
        throw new Exception('無効なタイプです');
    }

    if (empty($periodId) || empty($studentId)) {
        throw new Exception('必須パラメータが不足しています');
    }

    // テーブル名を決定
    $tableName = $type === 'guardian' ? 'kakehashi_guardian' : 'kakehashi_staff';

    // is_hiddenカラムが存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'is_hidden'");
    $columnExists = $stmt->rowCount() > 0;

    if (!$columnExists) {
        throw new Exception('is_hiddenカラムが存在しません。マイグレーション（run_migration_v17.php）を実行してください。');
    }

    // 現在の状態を取得
    $stmt = $pdo->prepare("SELECT is_hidden FROM {$tableName} WHERE period_id = ? AND student_id = ?");
    $stmt->execute([$periodId, $studentId]);
    $current = $stmt->fetch();

    if (!$current) {
        // レコードが存在しない場合は作成
        $stmt = $pdo->prepare("
            INSERT INTO {$tableName} (period_id, student_id, is_hidden, created_at)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$periodId, $studentId]);
        $newHiddenState = 1;
    } else {
        // 新しい状態を決定
        if ($action === 'toggle') {
            $newHiddenState = $current['is_hidden'] ? 0 : 1;
        } elseif ($action === 'hide') {
            $newHiddenState = 1;
        } else { // show
            $newHiddenState = 0;
        }

        // 状態を更新
        $stmt = $pdo->prepare("
            UPDATE {$tableName}
            SET is_hidden = ?
            WHERE period_id = ? AND student_id = ?
        ");
        $stmt->execute([$newHiddenState, $periodId, $studentId]);
    }

    echo json_encode([
        'success' => true,
        'is_hidden' => $newHiddenState,
        'message' => $newHiddenState ? '非表示にしました' : '表示に戻しました'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
