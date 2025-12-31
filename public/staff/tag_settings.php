<?php
/**
 * タグ設定画面
 * 教室ごとにカスタムタグを設定
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

// デフォルトタグ
$defaultTags = ['動画', '食', '学習', 'イベント', 'その他'];

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $tags = $_POST['tags'] ?? [];

            // 既存のタグを削除
            $stmt = $pdo->prepare("DELETE FROM classroom_tags WHERE classroom_id = ?");
            $stmt->execute([$classroomId]);

            // 新しいタグを追加
            $sortOrder = 1;
            foreach ($tags as $tag) {
                $tagName = trim($tag);
                if (!empty($tagName)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO classroom_tags (classroom_id, tag_name, sort_order, is_active)
                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt->execute([$classroomId, $tagName, $sortOrder]);
                    $sortOrder++;
                }
            }

            $_SESSION['success'] = 'タグ設定を保存しました';
        } elseif ($action === 'reset') {
            // デフォルトタグにリセット
            $stmt = $pdo->prepare("DELETE FROM classroom_tags WHERE classroom_id = ?");
            $stmt->execute([$classroomId]);

            $sortOrder = 1;
            foreach ($defaultTags as $tag) {
                $stmt = $pdo->prepare("
                    INSERT INTO classroom_tags (classroom_id, tag_name, sort_order, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$classroomId, $tag, $sortOrder]);
                $sortOrder++;
            }

            $_SESSION['success'] = 'デフォルトタグにリセットしました';
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

    header('Location: tag_settings.php');
    exit;
}

// 既存のタグを取得
$tags = [];
try {
    $stmt = $pdo->prepare("
        SELECT tag_name FROM classroom_tags
        WHERE classroom_id = ? AND is_active = 1
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$classroomId]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // タグがない場合はデフォルトを使用
    if (empty($tags)) {
        $tags = $defaultTags;
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'does not exist') !== false) {
        $_SESSION['warning'] = 'データベースのセットアップが必要です。マイグレーションを実行してください。';
        $tags = $defaultTags;
    } else {
        throw $e;
    }
}

// ページ開始
$currentPage = 'tag_settings';
renderPageStart('staff', $currentPage, 'タグ設定');
?>

<style>
.settings-container {
    background: var(--md-bg-primary);
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

.tag-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.tag-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: var(--md-gray-6);
    border-radius: var(--radius-sm);
    border: 1px solid var(--md-gray-5);
}

.tag-item input {
    flex: 1;
    padding: var(--spacing-sm) var(--spacing-md);
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
}

.tag-item input:focus {
    outline: none;
    border-color: var(--primary-purple);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.tag-order {
    width: 28px;
    height: 28px;
    background: var(--primary-purple);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: var(--text-footnote);
    flex-shrink: 0;
}

.remove-tag-btn {
    padding: 6px 12px;
    background: var(--md-red);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: var(--text-footnote);
}

.add-tag-btn {
    padding: 10px 20px;
    background: var(--md-blue);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: var(--text-subhead);
    font-weight: 600;
    margin-bottom: 20px;
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: var(--spacing-2xl);
    flex-wrap: wrap;
}

.submit-btn {
    flex: 1;
    padding: 15px 30px;
    background: var(--md-green);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    min-width: 150px;
}

.cancel-btn {
    flex: 1;
    padding: 15px 30px;
    background: var(--md-gray);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    min-width: 150px;
}

.reset-btn {
    padding: 15px 30px;
    background: var(--md-orange);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--text-callout);
    font-weight: 600;
    cursor: pointer;
    min-width: 150px;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">タグ設定</h1>
        <p class="page-subtitle">支援案に使用するタグをカスタマイズできます</p>
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
            <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 4px;">migrations/v57_classroom_tags.sql</code>
        </div>
    </div>
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>

<div class="settings-container">
    <div class="info-box">
        支援案作成時に選択できるタグを設定します。<br>
        タグは教室ごとに設定でき、活動の分類に使用されます。
    </div>

    <form method="POST" id="tagForm">
        <input type="hidden" name="action" value="save">

        <button type="button" class="add-tag-btn" onclick="addTag()">+ タグを追加</button>

        <div class="tag-list" id="tagList">
            <?php foreach ($tags as $index => $tag): ?>
                <div class="tag-item" data-index="<?= $index ?>">
                    <div class="tag-order"><?= $index + 1 ?></div>
                    <input type="text" name="tags[]" value="<?= htmlspecialchars($tag) ?>" placeholder="タグ名を入力">
                    <button type="button" class="remove-tag-btn" onclick="removeTag(this)">削除</button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="button-group">
            <a href="support_plans.php" class="cancel-btn">キャンセル</a>
            <button type="button" class="reset-btn" onclick="resetToDefault()">デフォルトに戻す</button>
            <button type="submit" class="submit-btn">保存する</button>
        </div>
    </form>

    <form method="POST" id="resetForm" style="display: none;">
        <input type="hidden" name="action" value="reset">
    </form>
</div>

<script>
let tagIndex = <?= count($tags) ?>;

function addTag() {
    const tagList = document.getElementById('tagList');
    const newItem = document.createElement('div');
    newItem.className = 'tag-item';
    newItem.innerHTML = `
        <div class="tag-order">${tagList.children.length + 1}</div>
        <input type="text" name="tags[]" value="" placeholder="タグ名を入力">
        <button type="button" class="remove-tag-btn" onclick="removeTag(this)">削除</button>
    `;
    tagList.appendChild(newItem);
    newItem.querySelector('input').focus();
    updateTagNumbers();
}

function removeTag(btn) {
    const tagItem = btn.closest('.tag-item');
    tagItem.remove();
    updateTagNumbers();
}

function updateTagNumbers() {
    const items = document.querySelectorAll('.tag-item');
    items.forEach((item, index) => {
        item.querySelector('.tag-order').textContent = index + 1;
    });
}

function resetToDefault() {
    if (confirm('タグをデフォルトに戻しますか？現在の設定は失われます。')) {
        document.getElementById('resetForm').submit();
    }
}
</script>

<?php renderPageEnd(); ?>
