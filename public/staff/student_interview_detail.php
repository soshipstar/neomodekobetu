<?php
/**
 * スタッフ用 - 生徒面談記録詳細・新規作成・編集
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$studentId = $_GET['student_id'] ?? null;
$interviewId = $_GET['interview_id'] ?? null;
$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';
$isNewMode = isset($_GET['new']) && $_GET['new'] === '1';

if (!$studentId) {
    header('Location: student_interviews.php');
    exit;
}

// 生徒情報を取得（アクセス権限チェック含む）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.classroom_id
        FROM students s
        WHERE s.id = ? AND s.classroom_id = ?
    ");
    $stmt->execute([$studentId, $classroomId]);
} else {
    $stmt = $pdo->prepare("SELECT id, student_name, classroom_id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
}

$student = $stmt->fetch();

if (!$student) {
    header('Location: student_interviews.php');
    exit;
}

// 面談記録を取得（特定のIDまたは一覧）
$interview = null;
$interviews = [];

if ($interviewId) {
    // 特定の面談記録を取得
    $stmt = $pdo->prepare("
        SELECT si.*, u.full_name as interviewer_name
        FROM student_interviews si
        LEFT JOIN users u ON si.interviewer_id = u.id
        WHERE si.id = ? AND si.student_id = ?
    ");
    $stmt->execute([$interviewId, $studentId]);
    $interview = $stmt->fetch();
}

// この生徒の全ての面談記録を取得
$stmt = $pdo->prepare("
    SELECT si.*, u.full_name as interviewer_name
    FROM student_interviews si
    LEFT JOIN users u ON si.interviewer_id = u.id
    WHERE si.student_id = ?
    ORDER BY si.interview_date DESC
");
$stmt->execute([$studentId]);
$interviews = $stmt->fetchAll();

// スタッフ一覧を取得（面談者選択用）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT id, full_name
        FROM users
        WHERE user_type IN ('staff', 'admin') AND classroom_id = ?
        ORDER BY full_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT id, full_name
        FROM users
        WHERE user_type IN ('staff', 'admin')
        ORDER BY full_name
    ");
}
$staffMembers = $stmt->fetchAll();

// ページ開始
$currentPage = 'student_interview_detail';
$pageTitle = htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') . 'の面談記録';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
    .interview-container {
        background: var(--md-bg-primary);
        border-radius: var(--radius-md);
        padding: 25px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: var(--spacing-lg);
    }

    .interview-section {
        margin-bottom: 25px;
    }

    .interview-section h3 {
        color: var(--md-teal);
        font-size: var(--text-callout);
        margin-bottom: var(--spacing-md);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .interview-section textarea,
    .interview-section input[type="text"],
    .interview-section input[type="date"],
    .interview-section select {
        width: 100%;
        padding: var(--spacing-md);
        border: 1px solid var(--md-gray-5);
        border-radius: var(--radius-sm);
        font-size: var(--text-subhead);
        font-family: inherit;
    }

    .interview-section textarea {
        min-height: 80px;
        resize: vertical;
    }

    .interview-section .view-content {
        padding: var(--spacing-md);
        background: var(--md-gray-6);
        border-left: 4px solid var(--md-teal);
        border-radius: 4px;
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .interview-section .view-content.empty {
        color: var(--text-secondary);
        font-style: italic;
    }

    .check-section {
        background: var(--md-bg-secondary);
        padding: var(--spacing-md);
        border-radius: var(--radius-sm);
        margin-bottom: var(--spacing-md);
    }

    .check-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: var(--spacing-sm);
    }

    .check-header input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .check-header label {
        font-weight: 600;
        color: var(--text-primary);
        cursor: pointer;
    }

    .check-note {
        margin-top: var(--spacing-sm);
    }

    .check-note textarea {
        min-height: 60px;
    }

    .check-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        font-size: var(--text-footnote);
        font-weight: 600;
    }

    .check-badge.checked {
        background: rgba(36, 161, 72, 0.15);
        color: var(--cds-support-success);
    }

    .check-badge.unchecked {
        background: var(--md-gray-6);
        color: var(--text-secondary);
    }

    .btn {
        padding: var(--spacing-md) 20px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: var(--text-subhead);
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        background: var(--md-green);
        color: white;
    }

    .btn-secondary {
        background: var(--md-gray);
        color: white;
    }

    .btn-edit {
        background: var(--md-teal);
        color: white;
    }

    .btn-danger {
        background: var(--md-red);
        color: white;
    }

    .btn-new {
        background: var(--md-blue);
        color: white;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: var(--spacing-lg);
        flex-wrap: wrap;
    }

    .interview-list {
        margin-top: var(--spacing-lg);
    }

    .interview-list h3 {
        color: var(--text-primary);
        margin-bottom: var(--spacing-md);
    }

    .interview-item {
        background: var(--md-bg-primary);
        border-radius: var(--radius-sm);
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-md);
        border-left: 4px solid var(--md-teal);
        cursor: pointer;
        transition: all var(--duration-fast);
    }

    .interview-item:hover {
        background: var(--md-gray-6);
    }

    .interview-item.active {
        border-left-color: var(--md-blue);
        background: rgba(0, 125, 184, 0.15);
    }

    .interview-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .interview-item-date {
        font-weight: 600;
        color: var(--md-teal);
    }

    .interview-item-interviewer {
        font-size: var(--text-footnote);
        color: var(--text-secondary);
    }

    .interview-item-preview {
        font-size: var(--text-footnote);
        color: var(--text-secondary);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .message {
        padding: var(--spacing-md) 20px;
        border-radius: var(--radius-sm);
        margin-bottom: var(--spacing-lg);
        font-size: var(--text-subhead);
    }

    .message.success {
        background: rgba(36, 161, 72, 0.15);
        color: var(--cds-support-success);
        border-left: 4px solid var(--md-green);
    }

    .message.error {
        background: var(--md-bg-secondary);
        color: var(--cds-support-error);
        border-left: 4px solid var(--md-red);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--spacing-md);
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .btn {
            width: 100%;
            text-align: center;
        }
    }
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8'); ?>さんの面談記録</h1>
        <p class="page-subtitle">面談記録の確認・作成</p>
    </div>
    <div class="page-header-actions">
        <a href="student_interviews.php" class="btn btn-secondary">
            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">arrow_back</span>
            一覧に戻る
        </a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="message success">
        <?php if ($_GET['success'] == '1'): ?>
            面談記録を保存しました
        <?php elseif ($_GET['success'] == '2'): ?>
            面談記録を削除しました
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="message error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--spacing-lg);">
    <!-- メインコンテンツ -->
    <div>
        <?php if ($isNewMode || ($isEditMode && $interview)): ?>
            <!-- 新規作成・編集モード -->
            <form method="POST" action="save_student_interview.php">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <input type="hidden" name="classroom_id" value="<?php echo $student['classroom_id']; ?>">
                <?php if ($interview): ?>
                    <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
                <?php endif; ?>

                <div class="interview-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h2 style="color: var(--text-primary); font-size: 20px;">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">edit_note</span>
                            <?php echo $isNewMode ? '新規面談記録' : '面談記録を編集'; ?>
                        </h2>
                        <div style="display: flex; gap: 10px;">
                            <a href="?student_id=<?php echo $studentId; ?><?php echo $interview ? '&interview_id=' . $interview['id'] : ''; ?>" class="btn btn-secondary">キャンセル</a>
                            <button type="submit" class="btn btn-primary">保存する</button>
                        </div>
                    </div>

                    <!-- 基本情報 -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">info</span> 基本情報</h3>
                        <div class="form-row">
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">面談実施日 <span style="color: var(--md-red);">*</span></label>
                                <input type="date" name="interview_date" value="<?php echo htmlspecialchars($interview['interview_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">面談者 <span style="color: var(--md-red);">*</span></label>
                                <select name="interviewer_id" required>
                                    <option value="">選択してください</option>
                                    <?php foreach ($staffMembers as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>" <?php echo (($interview['interviewer_id'] ?? $_SESSION['user_id']) == $staff['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 面談内容 -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">description</span> 面談内容</h3>
                        <textarea name="interview_content" placeholder="面談の内容を自由に記述してください"><?php echo htmlspecialchars($interview['interview_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 児童の願い -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">favorite</span> 児童の願い</h3>
                        <textarea name="child_wish" placeholder="児童が願っていること、望んでいることを記述してください"><?php echo htmlspecialchars($interview['child_wish'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- 学校での様子 -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">school</span> 学校での様子</h3>
                        <div class="check-section">
                            <div class="check-header">
                                <input type="checkbox" name="check_school" id="check_school" value="1" <?php echo (!empty($interview['check_school'])) ? 'checked' : ''; ?>>
                                <label for="check_school">学校での様子について話があった</label>
                            </div>
                            <div class="check-note">
                                <textarea name="check_school_note" placeholder="学校での様子についてのメモ"><?php echo htmlspecialchars($interview['check_school_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 家庭での様子 -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">home</span> 家庭での様子</h3>
                        <div class="check-section">
                            <div class="check-header">
                                <input type="checkbox" name="check_home" id="check_home" value="1" <?php echo (!empty($interview['check_home'])) ? 'checked' : ''; ?>>
                                <label for="check_home">家庭での様子について話があった</label>
                            </div>
                            <div class="check-note">
                                <textarea name="check_home_note" placeholder="家庭での様子についてのメモ"><?php echo htmlspecialchars($interview['check_home_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 困りごと・悩み -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">help</span> 困りごと・悩み</h3>
                        <div class="check-section">
                            <div class="check-header">
                                <input type="checkbox" name="check_troubles" id="check_troubles" value="1" <?php echo (!empty($interview['check_troubles'])) ? 'checked' : ''; ?>>
                                <label for="check_troubles">困りごとや悩みについて話があった</label>
                            </div>
                            <div class="check-note">
                                <textarea name="check_troubles_note" placeholder="困りごとや悩みについてのメモ"><?php echo htmlspecialchars($interview['check_troubles_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- その他備考 -->
                    <div class="interview-section">
                        <h3><span class="material-symbols-outlined">note</span> その他備考</h3>
                        <textarea name="other_notes" placeholder="その他の備考を記述してください"><?php echo htmlspecialchars($interview['other_notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </form>

        <?php elseif ($interview): ?>
            <!-- 表示モード -->
            <div class="interview-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="color: var(--text-primary); font-size: 20px;">
                        <span class="material-symbols-outlined" style="vertical-align: middle;">description</span>
                        面談記録詳細
                    </h2>
                    <div style="display: flex; gap: 10px;">
                        <a href="?student_id=<?php echo $studentId; ?>&interview_id=<?php echo $interview['id']; ?>&edit=1" class="btn btn-edit">
                            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">edit</span>
                            編集
                        </a>
                        <button type="button" onclick="confirmDelete(<?php echo $interview['id']; ?>)" class="btn btn-danger">
                            <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">delete</span>
                            削除
                        </button>
                    </div>
                </div>

                <!-- 基本情報 -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">info</span> 基本情報</h3>
                    <div style="display: flex; gap: var(--spacing-lg); flex-wrap: wrap;">
                        <div>
                            <strong>面談実施日:</strong>
                            <?php echo date('Y年m月d日', strtotime($interview['interview_date'])); ?>
                        </div>
                        <div>
                            <strong>面談者:</strong>
                            <?php echo htmlspecialchars($interview['interviewer_name'] ?? '不明', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                </div>

                <!-- 面談内容 -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">description</span> 面談内容</h3>
                    <div class="view-content <?php echo empty($interview['interview_content']) ? 'empty' : ''; ?>">
                        <?php echo !empty($interview['interview_content']) ? nl2br(htmlspecialchars($interview['interview_content'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- 児童の願い -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">favorite</span> 児童の願い</h3>
                    <div class="view-content <?php echo empty($interview['child_wish']) ? 'empty' : ''; ?>">
                        <?php echo !empty($interview['child_wish']) ? nl2br(htmlspecialchars($interview['child_wish'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <!-- 学校での様子 -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">school</span> 学校での様子</h3>
                    <div class="check-section">
                        <span class="check-badge <?php echo $interview['check_school'] ? 'checked' : 'unchecked'; ?>">
                            <span class="material-symbols-outlined" style="font-size: 14px;"><?php echo $interview['check_school'] ? 'check_circle' : 'cancel'; ?></span>
                            <?php echo $interview['check_school'] ? '話があった' : '話なし'; ?>
                        </span>
                        <?php if (!empty($interview['check_school_note'])): ?>
                            <div class="view-content" style="margin-top: var(--spacing-sm);">
                                <?php echo nl2br(htmlspecialchars($interview['check_school_note'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 家庭での様子 -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">home</span> 家庭での様子</h3>
                    <div class="check-section">
                        <span class="check-badge <?php echo $interview['check_home'] ? 'checked' : 'unchecked'; ?>">
                            <span class="material-symbols-outlined" style="font-size: 14px;"><?php echo $interview['check_home'] ? 'check_circle' : 'cancel'; ?></span>
                            <?php echo $interview['check_home'] ? '話があった' : '話なし'; ?>
                        </span>
                        <?php if (!empty($interview['check_home_note'])): ?>
                            <div class="view-content" style="margin-top: var(--spacing-sm);">
                                <?php echo nl2br(htmlspecialchars($interview['check_home_note'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 困りごと・悩み -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">help</span> 困りごと・悩み</h3>
                    <div class="check-section">
                        <span class="check-badge <?php echo $interview['check_troubles'] ? 'checked' : 'unchecked'; ?>">
                            <span class="material-symbols-outlined" style="font-size: 14px;"><?php echo $interview['check_troubles'] ? 'check_circle' : 'cancel'; ?></span>
                            <?php echo $interview['check_troubles'] ? '話があった' : '話なし'; ?>
                        </span>
                        <?php if (!empty($interview['check_troubles_note'])): ?>
                            <div class="view-content" style="margin-top: var(--spacing-sm);">
                                <?php echo nl2br(htmlspecialchars($interview['check_troubles_note'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- その他備考 -->
                <div class="interview-section">
                    <h3><span class="material-symbols-outlined">note</span> その他備考</h3>
                    <div class="view-content <?php echo empty($interview['other_notes']) ? 'empty' : ''; ?>">
                        <?php echo !empty($interview['other_notes']) ? nl2br(htmlspecialchars($interview['other_notes'], ENT_QUOTES, 'UTF-8')) : '未記入'; ?>
                    </div>
                </div>

                <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: var(--spacing-lg); text-align: right;">
                    作成日時: <?php echo date('Y/m/d H:i', strtotime($interview['created_at'])); ?>
                    <?php if ($interview['updated_at'] != $interview['created_at']): ?>
                        / 更新日時: <?php echo date('Y/m/d H:i', strtotime($interview['updated_at'])); ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- 面談記録選択画面 -->
            <div class="interview-container">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <h2 style="color: var(--text-primary); font-size: 20px;">
                        <span class="material-symbols-outlined" style="vertical-align: middle;">folder</span>
                        面談記録
                    </h2>
                    <a href="?student_id=<?php echo $studentId; ?>&new=1" class="btn btn-new">
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">add</span>
                        新規作成
                    </a>
                </div>

                <?php if (empty($interviews)): ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined" style="font-size: 48px; color: var(--md-gray-4);">folder_open</span>
                        <p style="margin-top: var(--spacing-md);">まだ面談記録がありません</p>
                        <p style="font-size: var(--text-footnote);">「新規作成」ボタンから面談記録を作成してください</p>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">左のリストから面談記録を選択してください</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- サイドバー：面談記録一覧 -->
    <div>
        <div class="interview-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-md);">
                <h3 style="color: var(--text-primary); margin: 0;">
                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">list</span>
                    記録一覧
                </h3>
                <a href="?student_id=<?php echo $studentId; ?>&new=1" class="btn btn-new" style="padding: 8px 12px; font-size: var(--text-footnote);">
                    <span class="material-symbols-outlined" style="font-size: 14px; vertical-align: middle;">add</span>
                    新規
                </a>
            </div>

            <?php if (empty($interviews)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <span class="material-symbols-outlined" style="font-size: 32px; color: var(--md-gray-4);">folder_open</span>
                    <p style="margin-top: var(--spacing-sm); font-size: var(--text-footnote);">記録なし</p>
                </div>
            <?php else: ?>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php foreach ($interviews as $item): ?>
                        <a href="?student_id=<?php echo $studentId; ?>&interview_id=<?php echo $item['id']; ?>" class="interview-item <?php echo ($interviewId == $item['id']) ? 'active' : ''; ?>" style="display: block; text-decoration: none; color: inherit;">
                            <div class="interview-item-header">
                                <span class="interview-item-date">
                                    <?php echo date('Y/m/d', strtotime($item['interview_date'])); ?>
                                </span>
                            </div>
                            <div class="interview-item-interviewer">
                                面談者: <?php echo htmlspecialchars($item['interviewer_name'] ?? '不明', ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <?php if (!empty($item['interview_content'])): ?>
                                <div class="interview-item-preview">
                                    <?php echo mb_substr(htmlspecialchars($item['interview_content'], ENT_QUOTES, 'UTF-8'), 0, 30); ?>...
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(interviewId) {
    if (confirm('この面談記録を削除してもよろしいですか？\nこの操作は取り消せません。')) {
        window.location.href = 'delete_student_interview.php?interview_id=' + interviewId + '&student_id=<?php echo $studentId; ?>';
    }
}
</script>

<?php renderPageEnd(); ?>
