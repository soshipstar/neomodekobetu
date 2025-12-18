<?php
/**
 * 毎日の支援設定画面
 * ルーティーン活動を最大10個まで登録・管理
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

if (!$classroomId) {
    $_SESSION['error'] = '教室が設定されていません';
    header('Location: support_plans.php');
    exit;
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            // 全ての毎日の支援を更新
            $routines = $_POST['routines'] ?? [];

            // 既存のルーティーンを削除
            $stmt = $pdo->prepare("DELETE FROM daily_routines WHERE classroom_id = ?");
            $stmt->execute([$classroomId]);

            // 新しいルーティーンを追加（最大10個）
            $sortOrder = 1;
            foreach ($routines as $routine) {
                if (!empty(trim($routine['name'])) && $sortOrder <= 10) {
                    $stmt = $pdo->prepare("
                        INSERT INTO daily_routines (classroom_id, sort_order, routine_name, routine_content, scheduled_time, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $classroomId,
                        $sortOrder,
                        trim($routine['name']),
                        trim($routine['content'] ?? ''),
                        trim($routine['time'] ?? '')
                    ]);
                    $sortOrder++;
                }
            }

            $_SESSION['success'] = '毎日の支援を保存しました';
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'does not exist') !== false) {
            $_SESSION['error'] = 'データベースのセットアップが必要です。マイグレーションを実行してください。';
        } else {
            $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    }

    header('Location: daily_routines_settings.php');
    exit;
}

// 既存の毎日の支援を取得
$routines = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM daily_routines
        WHERE classroom_id = ? AND is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$classroomId]);
    $routines = $stmt->fetchAll();
} catch (PDOException $e) {
    // テーブルが存在しない場合は空配列のまま
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'does not exist') !== false) {
        $_SESSION['warning'] = 'データベースのセットアップが必要です。マイグレーションを実行してください。';
    } else {
        throw $e;
    }
}

// 初期表示は5件（データがある場合はそのデータ数、最低5件）
$initialCount = max(5, count($routines));
while (count($routines) < $initialCount) {
    $routines[] = [
        'id' => null,
        'routine_name' => '',
        'routine_content' => '',
        'scheduled_time' => ''
    ];
}

// ページ開始
$currentPage = 'daily_routines_settings';
renderPageStart('staff', $currentPage, '毎日の支援設定');
?>

<style>
.settings-container {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.info-box {
    background: #e7f3ff;
    padding: 15px;
    border-radius: var(--radius-sm);
    border-left: 4px solid #2196F3;
    margin-bottom: 25px;
    font-size: var(--text-subhead);
    color: var(--text-primary);
}

.routine-card {
    background: var(--apple-gray-6);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: 15px;
    border: 2px solid var(--apple-gray-5);
    transition: all var(--duration-normal) var(--ease-out);
}

.routine-card:hover {
    border-color: var(--primary-purple);
}

.routine-card.filled {
    border-color: var(--apple-green);
    background: #f0fff0;
}

.routine-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.routine-number {
    width: 36px;
    height: 36px;
    background: var(--primary-purple);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: var(--text-callout);
}

.routine-card.filled .routine-number {
    background: var(--apple-green);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 150px;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: var(--text-footnote);
    color: var(--text-secondary);
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: var(--spacing-md);
    border: 1px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: var(--spacing-2xl);
}

.submit-btn {
    flex: 1;
    padding: 15px 30px;
    background: var(--apple-green);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
}

.cancel-btn {
    flex: 1;
    padding: 15px 30px;
    background: var(--apple-gray);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
}

.clear-btn {
    padding: var(--spacing-sm) 16px;
    background: var(--apple-red);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-footnote);
    cursor: pointer;
}

.add-routine-btn {
    width: 100%;
    padding: 15px;
    background: var(--apple-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 15px;
    transition: all var(--duration-fast) var(--ease-out);
}

.add-routine-btn:hover {
    background: #0056b3;
}

.add-routine-btn:disabled {
    background: var(--apple-gray-4);
    cursor: not-allowed;
}

.routine-count {
    text-align: center;
    color: var(--text-secondary);
    font-size: var(--text-footnote);
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">毎日の支援設定</h1>
        <p class="page-subtitle">ルーティーン活動を登録して支援案作成時に引用できます</p>
    </div>
    <div class="page-header-actions">
        <a href="support_plans.php" class="btn btn-secondary">← 支援案一覧へ</a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: var(--radius-sm); margin-bottom: var(--spacing-lg);">
        <strong>注意:</strong> <?= htmlspecialchars($_SESSION['warning']) ?>
        <div style="margin-top: 10px; font-size: var(--text-footnote);">
            以下のSQLをデータベースで実行してください：<br>
            <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px;">migrations/v56_daily_routines.sql</code>
        </div>
    </div>
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>

<div class="settings-container">
    <div class="info-box">
        毎日行うルーティーン活動を最大10個まで登録できます。<br>
        登録した内容は、支援案作成時に「毎日の支援を引用」から簡単に追加できます。
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="save">

        <div id="routineContainer">
            <?php foreach ($routines as $index => $routine): ?>
                <?php $num = $index + 1; ?>
                <div class="routine-card <?= !empty($routine['routine_name']) ? 'filled' : '' ?>" id="routine-<?= $num ?>" data-index="<?= $index ?>">
                    <div class="routine-header">
                        <div class="routine-number"><?= $num ?></div>
                        <div style="flex: 1; font-weight: 600; color: var(--text-primary);">
                            毎日の支援 <?= $num ?>
                        </div>
                        <button type="button" class="clear-btn" onclick="clearRoutine(<?= $num ?>)">クリア</button>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>活動名</label>
                            <input type="text"
                                   name="routines[<?= $index ?>][name]"
                                   id="routine-name-<?= $num ?>"
                                   value="<?= htmlspecialchars($routine['routine_name'] ?? '') ?>"
                                   placeholder="例: おやつの時間、帰りの会"
                                   onchange="updateCardStyle(<?= $num ?>)">
                        </div>
                        <div class="form-group">
                            <label>実施時間</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="number"
                                       name="routines[<?= $index ?>][time]"
                                       id="routine-time-<?= $num ?>"
                                       value="<?= htmlspecialchars($routine['scheduled_time'] ?? '') ?>"
                                       placeholder="30"
                                       min="1"
                                       max="480"
                                       style="width: 80px;">
                                <span style="font-weight: 600; color: var(--text-primary);">分</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>活動内容</label>
                        <textarea name="routines[<?= $index ?>][content]"
                                  id="routine-content-<?= $num ?>"
                                  placeholder="活動の具体的な内容を記入してください"><?= htmlspecialchars($routine['routine_content'] ?? '') ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="routine-count" id="routineCount">
            現在 <span id="currentCount"><?= count($routines) ?></span> / 10 件
        </div>

        <button type="button" class="add-routine-btn" id="addRoutineBtn" onclick="addRoutine()" <?= count($routines) >= 10 ? 'disabled' : '' ?>>
            + 毎日の支援を追加
        </button>

        <div class="button-group">
            <a href="support_plans.php" class="cancel-btn">キャンセル</a>
            <button type="submit" class="submit-btn">保存する</button>
        </div>
    </form>
</div>

<script>
let routineCount = <?= count($routines) ?>;
const maxRoutines = 10;

function addRoutine() {
    if (routineCount >= maxRoutines) {
        alert('最大10個まで登録できます');
        return;
    }

    routineCount++;
    const container = document.getElementById('routineContainer');
    const index = routineCount - 1;

    const cardHtml = `
        <div class="routine-card" id="routine-${routineCount}" data-index="${index}">
            <div class="routine-header">
                <div class="routine-number">${routineCount}</div>
                <div style="flex: 1; font-weight: 600; color: var(--text-primary);">
                    毎日の支援 ${routineCount}
                </div>
                <button type="button" class="clear-btn" onclick="clearRoutine(${routineCount})">クリア</button>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>活動名</label>
                    <input type="text"
                           name="routines[${index}][name]"
                           id="routine-name-${routineCount}"
                           value=""
                           placeholder="例: おやつの時間、帰りの会"
                           onchange="updateCardStyle(${routineCount})">
                </div>
                <div class="form-group">
                    <label>実施時間</label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number"
                               name="routines[${index}][time]"
                               id="routine-time-${routineCount}"
                               value=""
                               placeholder="30"
                               min="1"
                               max="480"
                               style="width: 80px;">
                        <span style="font-weight: 600; color: var(--text-primary);">分</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>活動内容</label>
                <textarea name="routines[${index}][content]"
                          id="routine-content-${routineCount}"
                          placeholder="活動の具体的な内容を記入してください"></textarea>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', cardHtml);
    updateRoutineCount();

    // 新しく追加したカードにスクロール
    document.getElementById('routine-' + routineCount).scrollIntoView({ behavior: 'smooth', block: 'center' });

    // 活動名フィールドにフォーカス
    document.getElementById('routine-name-' + routineCount).focus();
}

function clearRoutine(num) {
    if (confirm('毎日の支援 ' + num + ' をクリアしますか？')) {
        document.getElementById('routine-name-' + num).value = '';
        document.getElementById('routine-time-' + num).value = '';
        document.getElementById('routine-content-' + num).value = '';
        updateCardStyle(num);
    }
}

function updateCardStyle(num) {
    const card = document.getElementById('routine-' + num);
    const name = document.getElementById('routine-name-' + num).value;

    if (name.trim() !== '') {
        card.classList.add('filled');
    } else {
        card.classList.remove('filled');
    }
}

function updateRoutineCount() {
    document.getElementById('currentCount').textContent = routineCount;
    const addBtn = document.getElementById('addRoutineBtn');
    if (routineCount >= maxRoutines) {
        addBtn.disabled = true;
    } else {
        addBtn.disabled = false;
    }
}
</script>

<?php renderPageEnd(); ?>
