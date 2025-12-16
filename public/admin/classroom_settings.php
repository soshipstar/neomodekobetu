<?php
/**
 * 管理者用 - 教室情報設定ページ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// 管理者のみアクセス可能
requireUserType('admin');

$pdo = getDbConnection();
$userId = $_SESSION['user_id'];

// ログインユーザーの教室IDを取得
$stmt = $pdo->prepare("SELECT classroom_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$classroomId = $user['classroom_id'];

// target_gradesカラムが存在するかチェックして追加
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM classrooms LIKE 'target_grades'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE classrooms ADD COLUMN target_grades VARCHAR(255) DEFAULT 'preschool,elementary,junior_high,high_school'");
    }
} catch (Exception $e) {
    // カラム追加失敗時は継続
}

// 教室情報を取得
$classroomData = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroomData = $stmt->fetch();
}

// デフォルト教室がない場合は作成
if (!$classroomData) {
    $stmt = $pdo->prepare("INSERT INTO classrooms (classroom_name, address, phone) VALUES ('新規教室', '', '')");
    $stmt->execute();
    $classroomId = $pdo->lastInsertId();

    // ユーザーに教室IDを設定
    $stmt = $pdo->prepare("UPDATE users SET classroom_id = ? WHERE id = ?");
    $stmt->execute([$classroomId, $userId]);

    // 再取得
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroomData = $stmt->fetch();
}

// ページ開始
$currentPage = 'classroom_settings';
renderPageStart('admin', $currentPage, '教室情報設定');
?>

<style>
.content-box {
    background: var(--apple-bg-primary);
    padding: var(--spacing-2xl);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-md);
}

.section-title {
    font-size: 20px;
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-purple);
}

.form-help {
    font-size: var(--text-caption-1);
    color: var(--text-secondary);
    margin-top: 5px;
}

.logo-preview {
    margin-top: 10px;
    max-width: 300px;
}

.logo-preview img {
    max-width: 100%;
    height: auto;
    border: 1px solid var(--apple-gray-5);
    border-radius: var(--radius-sm);
    padding: var(--spacing-md);
    background: var(--apple-bg-primary);
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--apple-gray-5); }

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-md);
    margin-top: var(--spacing-sm);
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all var(--duration-fast);
}

.checkbox-item:hover {
    background: var(--apple-gray-5);
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--apple-purple);
}

.checkbox-item label {
    cursor: pointer;
    font-weight: 500;
}

.section-divider {
    margin: var(--spacing-2xl) 0;
    border-top: 1px solid var(--apple-gray-5);
    padding-top: var(--spacing-2xl);
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">教室情報設定</h1>
        <p class="page-subtitle">教室の基本情報を設定</p>
    </div>
</div>

<a href="index.php" class="quick-link">← 管理画面に戻る</a>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                教室情報を更新しました。
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                エラー: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="content-box">
            <h2 class="section-title">教室基本情報</h2>
            <form action="classroom_settings_save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="classroom_id" value="<?= $classroomId ?>">

                <div class="form-group">
                    <label>教室名 *</label>
                    <input type="text" name="classroom_name" value="<?= htmlspecialchars($classroomData['classroom_name'] ?? '') ?>" required>
                    <div class="form-help">教室・施設の名称を入力してください</div>
                </div>

                <div class="form-group">
                    <label>住所</label>
                    <textarea name="address"><?= htmlspecialchars($classroomData['address'] ?? '') ?></textarea>
                    <div class="form-help">教室の所在地を入力してください</div>
                </div>

                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($classroomData['phone'] ?? '') ?>">
                    <div class="form-help">例: 03-1234-5678</div>
                </div>

                <div class="form-group">
                    <label>教室ロゴ</label>
                    <input type="file" name="logo" accept="image/*">
                    <div class="form-help">PNG、JPG、GIF形式の画像ファイルをアップロードできます（最大2MB）</div>

                    <?php if (!empty($classroomData['logo_path']) && file_exists(__DIR__ . '/../' . $classroomData['logo_path'])): ?>
                        <div class="logo-preview">
                            <p style="font-weight: bold; margin-top: 15px; margin-bottom: var(--spacing-md);">現在のロゴ:</p>
                            <img src="../<?= htmlspecialchars($classroomData['logo_path']) ?>" alt="教室ロゴ">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-divider">
                    <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary);">対象学年設定</h3>
                    <div class="form-help" style="margin-bottom: var(--spacing-md);">この教室で対象とする学年を選択してください。スタッフ画面の生徒一覧に反映されます。</div>
                    <?php
                    $targetGrades = explode(',', $classroomData['target_grades'] ?? 'preschool,elementary,junior_high,high_school');
                    ?>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_grades[]" value="preschool" id="grade_preschool"
                                <?= in_array('preschool', $targetGrades) ? 'checked' : '' ?>>
                            <label for="grade_preschool">未就学児</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_grades[]" value="elementary" id="grade_elementary"
                                <?= in_array('elementary', $targetGrades) ? 'checked' : '' ?>>
                            <label for="grade_elementary">小学生</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_grades[]" value="junior_high" id="grade_junior_high"
                                <?= in_array('junior_high', $targetGrades) ? 'checked' : '' ?>>
                            <label for="grade_junior_high">中学生</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="target_grades[]" value="high_school" id="grade_high_school"
                                <?= in_array('high_school', $targetGrades) ? 'checked' : '' ?>>
                            <label for="grade_high_school">高校生</label>
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: var(--spacing-2xl);">
                    <button type="submit" class="btn btn-primary">💾 保存する</button>
                </div>
            </form>
        </div>

<?php renderPageEnd(); ?>
