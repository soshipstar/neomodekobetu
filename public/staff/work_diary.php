<?php
/**
 * 業務日誌作成・編集ページ
 * 放課後等デイサービスの日々の業務記録を作成・編集
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error'] = '教室が設定されていません。';
    header('Location: renrakucho_activities.php');
    exit;
}

// 日付を取得（デフォルトは今日）
$diaryDate = $_GET['date'] ?? date('Y-m-d');

// 既存の業務日誌を取得
$stmt = $pdo->prepare("
    SELECT wd.*, u1.full_name as creator_name, u2.full_name as updater_name
    FROM work_diaries wd
    LEFT JOIN users u1 ON wd.created_by = u1.id
    LEFT JOIN users u2 ON wd.updated_by = u2.id
    WHERE wd.classroom_id = ? AND wd.diary_date = ?
");
$stmt->execute([$classroomId, $diaryDate]);
$diary = $stmt->fetch();

// 前日の業務日誌を取得（振り返り参照用）
$prevDate = date('Y-m-d', strtotime('-1 day', strtotime($diaryDate)));
$stmt = $pdo->prepare("
    SELECT * FROM work_diaries
    WHERE classroom_id = ? AND diary_date = ?
");
$stmt->execute([$classroomId, $prevDate]);
$prevDiary = $stmt->fetch();

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $previousDayReview = $_POST['previous_day_review'] ?? '';
        $dailyCommunication = $_POST['daily_communication'] ?? '';
        $dailyRoles = $_POST['daily_roles'] ?? '';
        $prevDayChildrenStatus = $_POST['prev_day_children_status'] ?? '';
        $childrenSpecialNotes = $_POST['children_special_notes'] ?? '';
        $otherNotes = $_POST['other_notes'] ?? '';

        if ($diary) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE work_diaries SET
                    previous_day_review = ?,
                    daily_communication = ?,
                    daily_roles = ?,
                    prev_day_children_status = ?,
                    children_special_notes = ?,
                    other_notes = ?,
                    updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $previousDayReview,
                $dailyCommunication,
                $dailyRoles,
                $prevDayChildrenStatus,
                $childrenSpecialNotes,
                $otherNotes,
                $currentUser['id'],
                $diary['id']
            ]);
            $_SESSION['success'] = '業務日誌を更新しました。';
        } else {
            // 新規作成
            $stmt = $pdo->prepare("
                INSERT INTO work_diaries (
                    classroom_id, diary_date, previous_day_review, daily_communication,
                    daily_roles, prev_day_children_status, children_special_notes, other_notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $classroomId,
                $diaryDate,
                $previousDayReview,
                $dailyCommunication,
                $dailyRoles,
                $prevDayChildrenStatus,
                $childrenSpecialNotes,
                $otherNotes,
                $currentUser['id']
            ]);
            $_SESSION['success'] = '業務日誌を作成しました。';
        }

        header("Location: work_diary.php?date=$diaryDate");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// 日付フォーマット
$dateObj = new DateTime($diaryDate);
$formattedDate = $dateObj->format('Y年n月j日');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][(int)$dateObj->format('w')];

// 前日・翌日の日付
$prevDateNav = date('Y-m-d', strtotime('-1 day', strtotime($diaryDate)));
$nextDateNav = date('Y-m-d', strtotime('+1 day', strtotime($diaryDate)));

$currentPage = 'work_diary';
renderPageStart('staff', $currentPage, '業務日誌');
?>

<style>
.diary-container {
    max-width: 900px;
    margin: 0 auto;
}

.diary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-lg);
    padding: var(--spacing-lg);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-md);
}

.diary-date-nav {
    display: flex;
    align-items: center;
    gap: 15px;
}

.diary-date {
    font-size: 20px;
    font-weight: bold;
    color: var(--text-primary);
}

.nav-btn {
    padding: 8px 16px;
    background: var(--md-gray-5);
    color: var(--text-primary);
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    transition: background var(--duration-fast);
}

.nav-btn:hover {
    background: var(--md-gray-4);
}

.diary-actions {
    display: flex;
    gap: 10px;
}

.diary-section {
    background: var(--md-bg-primary);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.diary-section h3 {
    color: var(--md-blue);
    font-size: var(--text-body);
    margin-bottom: var(--spacing-md);
    display: flex;
    align-items: center;
    gap: 8px;
}

.diary-section textarea {
    width: 100%;
    min-height: 120px;
    padding: var(--spacing-md);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    font-family: inherit;
    resize: vertical;
    transition: border-color var(--duration-fast);
}

.diary-section textarea:focus {
    outline: none;
    border-color: var(--md-blue);
}

.prev-diary-reference {
    background: var(--md-gray-6);
    padding: var(--spacing-md);
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-md);
    font-size: var(--text-footnote);
    color: var(--text-secondary);
    border-left: 3px solid var(--md-orange);
}

.prev-diary-reference h4 {
    font-size: var(--text-footnote);
    color: var(--md-orange);
    margin-bottom: 5px;
}

.submit-btn {
    padding: 12px 30px;
    background: var(--md-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-body);
    font-weight: bold;
    cursor: pointer;
    transition: background var(--duration-fast);
}

.submit-btn:hover {
    background: var(--md-blue-dark);
}

.btn-calendar {
    padding: 10px 20px;
    background: var(--md-green);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm);
    font-weight: 500;
    transition: background var(--duration-fast);
}

.btn-calendar:hover {
    opacity: 0.9;
}

.meta-info {
    font-size: var(--text-footnote);
    color: var(--text-tertiary);
    margin-top: var(--spacing-lg);
    padding-top: var(--spacing-md);
    border-top: 1px solid var(--md-gray-5);
}

.success-message {
    background: rgba(36, 161, 72, 0.15);
    color: var(--cds-support-success);
    padding: var(--spacing-md);
    border-radius: 0;
    margin-bottom: var(--spacing-lg);
}

.error-message {
    background: rgba(218, 30, 40, 0.15);
    color: var(--cds-support-error);
    padding: var(--spacing-md);
    border-radius: 0;
    margin-bottom: var(--spacing-lg);
}
</style>

<div class="diary-container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="diary-header">
        <div class="diary-date-nav">
            <a href="?date=<?php echo $prevDateNav; ?>" class="nav-btn">← 前日</a>
            <span class="diary-date"><?php echo $formattedDate; ?>（<?php echo $dayOfWeek; ?>）</span>
            <a href="?date=<?php echo $nextDateNav; ?>" class="nav-btn">翌日 →</a>
        </div>
        <div class="diary-actions">
            <a href="work_diary_calendar.php" class="btn-calendar">カレンダー表示</a>
        </div>
    </div>

    <form method="POST">
        <!-- 前日の振り返り -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">edit_note</span> 前日の振り返り</h3>
            <?php if ($prevDiary && !empty($prevDiary['children_special_notes'])): ?>
                <div class="prev-diary-reference">
                    <h4>参考：前日の児童の状況</h4>
                    <?php echo nl2br(htmlspecialchars($prevDiary['children_special_notes'])); ?>
                </div>
            <?php endif; ?>
            <textarea name="previous_day_review" placeholder="昨日の活動の振り返り、反省点、良かった点などを記入してください"><?php echo htmlspecialchars($diary['previous_day_review'] ?? ''); ?></textarea>
        </div>

        <!-- 本日の伝達事項 -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">campaign</span> 本日の伝達事項</h3>
            <textarea name="daily_communication" placeholder="スタッフ間で共有すべき情報、保護者からの連絡、注意事項などを記入してください"><?php echo htmlspecialchars($diary['daily_communication'] ?? ''); ?></textarea>
        </div>

        <!-- 本日の役割分担 -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">group</span> 本日の役割分担</h3>
            <textarea name="daily_roles" placeholder="各スタッフの担当業務、配置、送迎担当などを記入してください"><?php echo htmlspecialchars($diary['daily_roles'] ?? ''); ?></textarea>
        </div>

        <!-- 前日の児童の状況 -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">face</span> 前日の児童の状況</h3>
            <?php if ($prevDiary && !empty($prevDiary['children_special_notes'])): ?>
                <div class="prev-diary-reference">
                    <h4>参考：前日の特記事項</h4>
                    <?php echo nl2br(htmlspecialchars($prevDiary['children_special_notes'])); ?>
                </div>
            <?php endif; ?>
            <textarea name="prev_day_children_status" placeholder="前日の児童の体調、出席状況、気になった様子などを記入してください"><?php echo htmlspecialchars($diary['prev_day_children_status'] ?? ''); ?></textarea>
        </div>

        <!-- 児童に関する特記事項 -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">push_pin</span> 児童に関する特記事項</h3>
            <textarea name="children_special_notes" placeholder="本日注意すべき児童の情報、トラブル、成長の記録、保護者からの連絡などを記入してください"><?php echo htmlspecialchars($diary['children_special_notes'] ?? ''); ?></textarea>
        </div>

        <!-- その他メモ -->
        <div class="diary-section">
            <h3><span class="material-symbols-outlined">description</span> その他メモ</h3>
            <textarea name="other_notes" placeholder="備品の補充、施設の修繕、その他共有事項などを記入してください"><?php echo htmlspecialchars($diary['other_notes'] ?? ''); ?></textarea>
        </div>

        <div style="text-align: center; margin-bottom: var(--spacing-xl);">
            <button type="submit" class="submit-btn"><?php echo $diary ? '更新する' : '保存する'; ?></button>
        </div>

        <?php if ($diary): ?>
            <div class="meta-info">
                作成者: <?php echo htmlspecialchars($diary['creator_name']); ?>
                （<?php echo date('Y/m/d H:i', strtotime($diary['created_at'])); ?>）
                <?php if ($diary['updated_by']): ?>
                    / 最終更新: <?php echo htmlspecialchars($diary['updater_name']); ?>
                    （<?php echo date('Y/m/d H:i', strtotime($diary['updated_at'])); ?>）
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php renderPageEnd(); ?>
