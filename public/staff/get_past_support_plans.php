<?php
/**
 * 過去の支援案一覧を取得するAPI
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

header('Content-Type: application/json');

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 日付範囲パラメータを取得
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// 期間パラメータを取得（デフォルトは30日）
$period = $_GET['period'] ?? '30';

// 日付範囲が指定されている場合
if ($startDate && $endDate) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                   five_domains_consideration, other_notes
            FROM support_plans
            WHERE classroom_id = ? AND activity_date BETWEEN ? AND ?
            ORDER BY activity_date DESC, created_at DESC
        ");
        $stmt->execute([$classroomId, $startDate, $endDate]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                   five_domains_consideration, other_notes
            FROM support_plans
            WHERE activity_date BETWEEN ? AND ?
            ORDER BY activity_date DESC, created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
    }
} else {
    // 期間に応じた日数を設定
    $days = match($period) {
        '7' => 7,
        '30' => 30,
        '90' => 90,
        'all' => null,
        default => 30
    };

    // 同じ教室の支援案を取得
    if ($classroomId) {
        if ($days === null) {
            // すべての期間
            $stmt = $pdo->prepare("
                SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                       five_domains_consideration, other_notes
                FROM support_plans
                WHERE classroom_id = ?
                ORDER BY activity_date DESC, created_at DESC
            ");
            $stmt->execute([$classroomId]);
        } else {
            // 指定期間
            $stmt = $pdo->prepare("
                SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                       five_domains_consideration, other_notes
                FROM support_plans
                WHERE classroom_id = ? AND activity_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY activity_date DESC, created_at DESC
            ");
            $stmt->execute([$classroomId, $days]);
        }
    } else {
        if ($days === null) {
            // すべての期間
            $stmt = $pdo->prepare("
                SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                       five_domains_consideration, other_notes
                FROM support_plans
                ORDER BY activity_date DESC, created_at DESC
            ");
            $stmt->execute();
        } else {
            // 指定期間
            $stmt = $pdo->prepare("
                SELECT id, activity_date, activity_name, activity_purpose, activity_content,
                       five_domains_consideration, other_notes
                FROM support_plans
                WHERE activity_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY activity_date DESC, created_at DESC
            ");
            $stmt->execute([$days]);
        }
    }
}

$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($plans);
