<?php
/**
 * 利用者一括登録 - メイン画面
 * CSVファイルをアップロードして保護者・生徒を一括登録
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// ログインチェック
requireLogin();
checkUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

$error = '';
$success = '';

// セッションからエラーメッセージを取得
if (isset($_SESSION['bulk_register_error'])) {
    $error = $_SESSION['bulk_register_error'];
    unset($_SESSION['bulk_register_error']);
}

// CSVアップロード処理（標準フォーマット）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'ファイルのアップロードに失敗しました。';
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $error = 'CSVファイルのみアップロード可能です。';
        } else {
            // CSVファイルを一時保存してセッションに保存
            $tmpPath = sys_get_temp_dir() . '/bulk_register_' . session_id() . '_' . time() . '.csv';
            if (move_uploaded_file($file['tmp_name'], $tmpPath)) {
                $_SESSION['bulk_register_csv'] = $tmpPath;
                header('Location: bulk_register_confirm.php');
                exit;
            } else {
                $error = 'ファイルの保存に失敗しました。';
            }
        }
    }
}

$role = 'staff';
$currentPage = 'bulk_register';

renderPageStart($role, $currentPage, '利用者一括登録', [
    'classroom' => $classroom
]);
?>

<style>
.upload-method-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 0;
    border-bottom: 2px solid var(--border-primary);
}
.upload-method-tab {
    padding: 12px 24px;
    background: var(--md-bg-tertiary);
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    transition: all 0.2s;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    margin-bottom: -2px;
}
.upload-method-tab.active {
    background: var(--md-bg-primary);
    color: var(--md-blue);
    border-bottom: 2px solid var(--md-blue);
}
.upload-method-tab:hover:not(.active) {
    background: var(--md-bg-secondary);
}
.upload-panel {
    display: none;
    padding: var(--spacing-lg);
}
.upload-panel.active {
    display: block;
}
.ai-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 6px;
}
</style>

<div class="page-header">
    <h1>利用者一括登録</h1>
    <p class="page-description">CSVファイルをアップロードして、保護者と生徒を一括登録できます。</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="upload-method-tabs">
        <button type="button" class="upload-method-tab active" onclick="switchTab('standard')">標準フォーマット</button>
        <button type="button" class="upload-method-tab" onclick="switchTab('ai')">AI自動解析 <span class="ai-badge">AI</span></button>
    </div>

    <!-- 標準フォーマット -->
    <div id="panel-standard" class="upload-panel active">
        <div class="info-box" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: var(--md-bg-tertiary); border-radius: var(--radius-md);">
            <h3 style="margin-bottom: var(--spacing-sm); font-size: var(--text-body);">CSV形式について</h3>
            <p style="margin-bottom: var(--spacing-sm); color: var(--text-secondary); font-size: var(--text-footnote);">
                以下の項目をCSV形式で準備してください。同じ保護者氏名の行は、同一保護者として複数の生徒を紐付けます。
            </p>
            <ul style="margin: 0; padding-left: var(--spacing-lg); color: var(--text-secondary); font-size: var(--text-footnote);">
                <li><strong>保護者氏名</strong>（必須）</li>
                <li><strong>生徒氏名</strong>（必須）</li>
                <li><strong>生年月日</strong>（必須、YYYY-MM-DD形式）</li>
                <li><strong>保護者メールアドレス</strong>（任意）</li>
                <li><strong>支援開始日</strong>（任意、YYYY-MM-DD形式）</li>
                <li><strong>学年調整</strong>（-2〜2、省略時は0）</li>
                <li><strong>通所曜日（月〜土）</strong>（1=通所、0=通所しない）</li>
            </ul>
        </div>

        <div style="margin-bottom: var(--spacing-xl);">
            <a href="bulk_register_template.php" class="btn btn-secondary">
                CSVテンプレートをダウンロード
            </a>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= csrfTokenField() ?>

            <div class="form-group">
                <label for="csv_file_standard" class="form-label">CSVファイルを選択</label>
                <input type="file" id="csv_file_standard" name="csv_file" accept=".csv" class="form-control" required>
                <p class="form-help" style="margin-top: var(--spacing-xs); color: var(--text-tertiary); font-size: var(--text-caption);">
                    UTF-8またはShift-JIS形式のCSVファイルを選択してください。
                </p>
            </div>

            <div class="form-actions" style="margin-top: var(--spacing-xl);">
                <button type="submit" class="btn btn-primary">
                    アップロードして確認
                </button>
            </div>
        </form>
    </div>

    <!-- AI自動解析 -->
    <div id="panel-ai" class="upload-panel">
        <div class="info-box" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border-radius: var(--radius-md); border: 1px solid rgba(102, 126, 234, 0.3);">
            <h3 style="margin-bottom: var(--spacing-sm); font-size: var(--text-body);">
                AI自動解析について <span class="ai-badge">AI</span>
            </h3>
            <p style="margin-bottom: var(--spacing-sm); color: var(--text-secondary); font-size: var(--text-footnote);">
                他のシステムからエクスポートしたCSVファイルをAIが自動で解析し、必要な情報を抽出します。
            </p>
            <ul style="margin: 0; padding-left: var(--spacing-lg); color: var(--text-secondary); font-size: var(--text-footnote);">
                <li>任意のCSV形式に対応（列名や順序は問いません）</li>
                <li>保護者氏名、生徒氏名、生年月日などを自動抽出</li>
                <li>日付形式を自動変換（例：2015年4月1日 → 2015-04-01）</li>
                <li>抽出結果は確認画面で編集・修正できます</li>
            </ul>
        </div>

        <form method="POST" action="bulk_register_ai_parse.php" enctype="multipart/form-data">
            <?= csrfTokenField() ?>

            <div class="form-group">
                <label for="csv_file_ai" class="form-label">CSVファイルを選択</label>
                <input type="file" id="csv_file_ai" name="csv_file" accept=".csv" class="form-control" required>
                <p class="form-help" style="margin-top: var(--spacing-xs); color: var(--text-tertiary); font-size: var(--text-caption);">
                    任意のCSVファイルを選択してください（最大100行まで）。AIが自動で解析します。
                </p>
            </div>

            <div class="form-actions" style="margin-top: var(--spacing-xl);">
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    AIで解析する
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-top: var(--spacing-xl);">
    <div class="card-header">
        <h2>注意事項</h2>
    </div>
    <div class="card-body">
        <ul style="margin: 0; padding-left: var(--spacing-lg); color: var(--text-secondary);">
            <li>ユーザー名は自動生成されます（例：guardian_001）</li>
            <li>パスワードは8文字のランダムな英数字で自動生成されます</li>
            <li>登録完了後、ID・パスワード一覧をPDFでダウンロードできます</li>
            <li>同じ保護者氏名の行は、1人の保護者に複数の生徒を紐付けます</li>
            <li>既存のユーザー名と重複しないよう、自動的に番号が振られます</li>
        </ul>
    </div>
</div>

<script>
function switchTab(tab) {
    // タブの切り替え
    document.querySelectorAll('.upload-method-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.upload-panel').forEach(p => p.classList.remove('active'));

    if (tab === 'standard') {
        document.querySelector('.upload-method-tab:first-child').classList.add('active');
        document.getElementById('panel-standard').classList.add('active');
    } else {
        document.querySelector('.upload-method-tab:last-child').classList.add('active');
        document.getElementById('panel-ai').classList.add('active');
    }
}
</script>

<?php renderPageEnd(); ?>
