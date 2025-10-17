<?php
/**
 * スタッフ用 - 休日情報の保存・削除処理
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
            // 新規休日登録
            $holidayDate = $_POST['holiday_date'];
            $holidayName = trim($_POST['holiday_name']);
            $holidayType = $_POST['holiday_type'];
            $createdBy = $_SESSION['user_id'];

            if (empty($holidayDate) || empty($holidayName) || empty($holidayType)) {
                throw new Exception('すべての項目を入力してください。');
            }

            // 日付の妥当性チェック
            if (!strtotime($holidayDate)) {
                throw new Exception('無効な日付です。');
            }

            $pdo->beginTransaction();

            try {
                if ($holidayType === 'regular') {
                    // 定期休日：年度内の該当曜日すべてに登録
                    $baseDayOfWeek = (int)date('w', strtotime($holidayDate));
                    $baseYear = (int)date('Y', strtotime($holidayDate));
                    $baseMonth = (int)date('n', strtotime($holidayDate));

                    // 年度の開始月と終了月を計算（4月始まり）
                    if ($baseMonth >= 4) {
                        // 4月以降 -> 当年4月〜翌年3月
                        $fiscalYearStart = "$baseYear-04-01";
                        $fiscalYearEnd = ($baseYear + 1) . "-03-31";
                    } else {
                        // 1〜3月 -> 前年4月〜当年3月
                        $fiscalYearStart = ($baseYear - 1) . "-04-01";
                        $fiscalYearEnd = "$baseYear-03-31";
                    }

                    $startDate = new DateTime($fiscalYearStart);
                    $endDate = new DateTime($fiscalYearEnd);
                    $insertedCount = 0;
                    $skippedCount = 0;
                    $totalMatchingDays = 0;

                    // 年度内のすべての日付をチェック
                    $currentDate = clone $startDate;
                    while ($currentDate <= $endDate) {
                        $currentDateStr = $currentDate->format('Y-m-d');
                        $currentDayOfWeek = (int)$currentDate->format('w');

                        // 曜日が一致する場合
                        if ($currentDayOfWeek === $baseDayOfWeek) {
                            $totalMatchingDays++;

                            // 重複チェック
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
                            $stmt->execute([$currentDateStr]);

                            if ($stmt->fetchColumn() == 0) {
                                // 休日を登録
                                $stmt = $pdo->prepare("
                                    INSERT INTO holidays (holiday_date, holiday_name, holiday_type, created_by, created_at)
                                    VALUES (?, ?, ?, ?, NOW())
                                ");
                                $stmt->execute([$currentDateStr, $holidayName, $holidayType, $createdBy]);
                                $insertedCount++;
                            } else {
                                $skippedCount++;
                            }
                        }

                        $currentDate->modify('+1 day');
                    }

                    $pdo->commit();

                    if ($insertedCount === 0 && $totalMatchingDays > 0) {
                        throw new Exception("該当曜日は{$totalMatchingDays}日ありますが、すべて既に登録済みです。");
                    } elseif ($totalMatchingDays === 0) {
                        throw new Exception("指定された期間内に該当する曜日が見つかりませんでした。日付: {$holidayDate}, 年度: {$fiscalYearStart} 〜 {$fiscalYearEnd}");
                    }

                    header('Location: holidays.php?success=created&count=' . $insertedCount);
                    exit;

                } else {
                    // 特別休日：指定日のみ登録
                    // 重複チェック
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE holiday_date = ?");
                    $stmt->execute([$holidayDate]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('この日付は既に休日として登録されています。');
                    }

                    // 休日を登録
                    $stmt = $pdo->prepare("
                        INSERT INTO holidays (holiday_date, holiday_name, holiday_type, created_by, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$holidayDate, $holidayName, $holidayType, $createdBy]);

                    $pdo->commit();
                    header('Location: holidays.php?success=created');
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

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
