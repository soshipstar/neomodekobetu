<?php
/**
 * スタッフ用 - イベント情報の保存・削除処理
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireLogin();

// スタッフまたは管理者のみ
if ($_SESSION['user_type'] !== 'staff' && $_SESSION['user_type'] !== 'admin') {
    header('Location: /index.php');
    exit;
}

$pdo = getDbConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // 新規イベント登録
            $eventDate = $_POST['event_date'];
            $eventName = trim($_POST['event_name']);
            $eventDescription = trim($_POST['event_description'] ?? '');
            $eventColor = $_POST['event_color'] ?? '#28a745';
            $targetAudience = $_POST['target_audience'] ?? 'all';
            $createdBy = $_SESSION['user_id'];

            if (empty($eventDate) || empty($eventName)) {
                throw new Exception('日付とイベント名は必須です。');
            }

            // 日付の妥当性チェック
            if (!strtotime($eventDate)) {
                throw new Exception('無効な日付です。');
            }

            // 色コードの検証（#から始まる6桁の16進数）
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $eventColor)) {
                $eventColor = '#28a745'; // デフォルト色
            }

            // 対象者の妥当性チェック
            $validTargetAudiences = ['elementary', 'junior_high_school', 'all', 'guardian', 'other'];
            if (!in_array($targetAudience, $validTargetAudiences)) {
                $targetAudience = 'all';
            }

            // イベントを登録
            $stmt = $pdo->prepare("
                INSERT INTO events (event_date, event_name, event_description, event_color, target_audience, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$eventDate, $eventName, $eventDescription, $eventColor, $targetAudience, $createdBy]);

            header('Location: events.php?success=created');
            exit;

        case 'delete':
            // イベント削除
            $eventId = (int)$_POST['event_id'];

            if (empty($eventId)) {
                throw new Exception('イベントIDが指定されていません。');
            }

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$eventId]);

            header('Location: events.php?success=deleted');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: events.php?error=' . urlencode($e->getMessage()));
    exit;
}
