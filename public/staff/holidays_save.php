<?php
/**
 * スタッフ用 - 休日情報の保存・削除処理
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

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
        case 'create_regular':
            // 定期休日登録（曜日チェックボックスから）
            $holidayName = trim($_POST['holiday_name'] ?? '');
            $daysOfWeek = $_POST['days_of_week'] ?? [];
            $startDateStr = $_POST['start_date'] ?? date('Y-m-d');
            $createdBy = $_SESSION['user_id'];
            $classroomId = $_SESSION['classroom_id'] ?? null;

            if (empty($holidayName)) {
                throw new Exception('休日名を入力してください。');
            }

            if (empty($daysOfWeek)) {
                throw new Exception('少なくとも1つの曜日を選択してください。');
            }

            if (empty($classroomId)) {
                throw new Exception('教室IDが設定されていません。');
            }

            // 日付の妥当性チェック
            if (!strtotime($startDateStr)) {
                throw new Exception('無効な開始日です。');
            }

            $pdo->beginTransaction();

            try {
                $baseYear = (int)date('Y', strtotime($startDateStr));
                $baseMonth = (int)date('n', strtotime($startDateStr));

                // 年度の終了月を計算（4月始まり）
                if ($baseMonth >= 4) {
                    $fiscalYearEnd = ($baseYear + 1) . "-03-31";
                } else {
                    $fiscalYearEnd = "$baseYear-03-31";
                }

                $startDate = new DateTime($startDateStr);
                $endDate = new DateTime($fiscalYearEnd);
                $insertedCount = 0;
                $skippedCount = 0;

                // 選択された曜日を配列として保持
                $selectedDays = array_map('intval', $daysOfWeek);

                // 年度内のすべての日付をチェック
                $currentDate = clone $startDate;
                while ($currentDate <= $endDate) {
                    $currentDateStr = $currentDate->format('Y-m-d');
                    $currentDayOfWeek = (int)$currentDate->format('w');

                    // 選択された曜日に一致する場合
                    if (in_array($currentDayOfWeek, $selectedDays)) {
                        // 重複チェック（同じ教室で同じ日付）
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
                        $stmt->execute([$currentDateStr, $classroomId]);

                        if ($stmt->fetchColumn() == 0) {
                            // 休日を登録
                            $stmt = $pdo->prepare("
                                INSERT INTO holidays (holiday_date, holiday_name, holiday_type, classroom_id, created_by, created_at)
                                VALUES (?, ?, 'regular', ?, ?, NOW())
                            ");
                            $stmt->execute([$currentDateStr, $holidayName, $classroomId, $createdBy]);
                            $insertedCount++;
                        } else {
                            $skippedCount++;
                        }
                    }

                    $currentDate->modify('+1 day');
                }

                $pdo->commit();

                if ($insertedCount === 0) {
                    throw new Exception("選択された曜日はすべて既に登録済みか、該当する日付がありませんでした。");
                }

                header('Location: holidays.php?success=created&count=' . $insertedCount);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        case 'create_special':
            // 特別休日登録（日付指定）
            $holidayDate = $_POST['holiday_date'] ?? '';
            $holidayName = trim($_POST['holiday_name'] ?? '');
            $createdBy = $_SESSION['user_id'];
            $classroomId = $_SESSION['classroom_id'] ?? null;

            if (empty($holidayDate) || empty($holidayName)) {
                throw new Exception('日付と休日名を入力してください。');
            }

            if (empty($classroomId)) {
                throw new Exception('教室IDが設定されていません。');
            }

            // 日付の妥当性チェック
            if (!strtotime($holidayDate)) {
                throw new Exception('無効な日付です。');
            }

            // 重複チェック（同じ教室で同じ日付）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
            $stmt->execute([$holidayDate, $classroomId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('この日付は既に休日として登録されています。');
            }

            // 休日を登録
            $stmt = $pdo->prepare("
                INSERT INTO holidays (holiday_date, holiday_name, holiday_type, classroom_id, created_by, created_at)
                VALUES (?, ?, 'special', ?, ?, NOW())
            ");
            $stmt->execute([$holidayDate, $holidayName, $classroomId, $createdBy]);

            header('Location: holidays.php?success=created');
            exit;

        case 'create':
            // 後方互換性のため残す（旧フォーム対応）
            $holidayDate = $_POST['holiday_date'];
            $holidayName = trim($_POST['holiday_name']);
            $holidayType = $_POST['holiday_type'];
            $createdBy = $_SESSION['user_id'];
            $classroomId = $_SESSION['classroom_id'] ?? null;

            if (empty($holidayDate) || empty($holidayName) || empty($holidayType)) {
                throw new Exception('すべての項目を入力してください。');
            }

            if (empty($classroomId)) {
                throw new Exception('教室IDが設定されていません。');
            }

            // 日付の妥当性チェック
            if (!strtotime($holidayDate)) {
                throw new Exception('無効な日付です。');
            }

            // 重複チェック
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ? AND classroom_id = ?");
            $stmt->execute([$holidayDate, $classroomId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('この日付は既に休日として登録されています。');
            }

            $stmt = $pdo->prepare("
                INSERT INTO holidays (holiday_date, holiday_name, holiday_type, classroom_id, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$holidayDate, $holidayName, $holidayType, $classroomId, $createdBy]);

            header('Location: holidays.php?success=created');
            exit;

        case 'delete':
            // 休日削除
            $holidayId = (int)$_POST['holiday_id'];

            if (empty($holidayId)) {
                throw new Exception('休日IDが指定されていません。');
            }

            $stmt = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->execute([$holidayId]);

            header('Location: holidays.php?success=deleted');
            exit;

        default:
            throw new Exception('無効な操作です。');
    }
} catch (Exception $e) {
    // エラーが発生した場合
    header('Location: holidays.php?error=' . urlencode($e->getMessage()));
    exit;
}
