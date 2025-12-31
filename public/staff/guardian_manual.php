<?php
/**
 * 保護者向けマニュアル生成ページ
 * アカウント情報とログイン方法、使い方を印刷可能な形式で出力
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// 保護者一覧を取得（自分の教室のみ）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT u.*,
               GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
        FROM users u
        LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
        WHERE u.user_type = 'guardian' AND u.is_active = 1 AND u.classroom_id = ?
        GROUP BY u.id
        ORDER BY u.full_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("
        SELECT u.*,
               GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
        FROM users u
        LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
        WHERE u.user_type = 'guardian' AND u.is_active = 1
        GROUP BY u.id
        ORDER BY u.full_name
    ");
}
$guardians = $stmt->fetchAll();

// 選択された保護者（自分の教室のみ）
$selectedGuardianId = $_GET['guardian_id'] ?? null;
$guardianData = null;

if ($selectedGuardianId) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
            FROM users u
            LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
            WHERE u.id = ? AND u.user_type = 'guardian' AND u.classroom_id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$selectedGuardianId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*,
                   GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
            FROM users u
            LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
            WHERE u.id = ? AND u.user_type = 'guardian'
            GROUP BY u.id
        ");
        $stmt->execute([$selectedGuardianId]);
    }
    $guardianData = $stmt->fetch();
}

// サーバーURLを取得
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$serverUrl = $protocol . $_SERVER['HTTP_HOST'];
$loginUrl = $serverUrl . '/login.php';

// ページ開始
$currentPage = 'guardian_manual';
renderPageStart('staff', $currentPage, '保護者向けマニュアル生成');
?>

<style>
.selector-section {
    background: var(--md-gray-6);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
}

.manual {
    background: var(--md-bg-primary);
    padding: var(--spacing-2xl);
    margin-top: var(--spacing-lg);
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-md);
}

.manual-header {
    text-align: center;
    margin-bottom: var(--spacing-2xl);
    padding-bottom: 20px;
    border-bottom: 3px solid var(--md-blue);
}

.manual-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: var(--spacing-md);
}

.manual-subtitle {
    font-size: var(--text-callout);
    color: var(--text-secondary);
}

.manual-section {
    margin-bottom: var(--spacing-2xl);
}

.manual-section-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--md-blue);
    margin-bottom: 15px;
    padding-left: 10px;
    border-left: 4px solid var(--md-blue);
}

.info-box {
    background: var(--md-bg-secondary);
    border: 2px solid var(--md-blue);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-bottom: 15px;
}

.info-row {
    display: flex;
    margin-bottom: 12px;
    font-size: var(--text-callout);
}

.info-label {
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 150px;
}

.info-value {
    color: var(--text-primary);
    font-weight: 700;
}

.step-list {
    list-style: none;
    counter-reset: step-counter;
    padding: 0;
}

.step-list li {
    counter-increment: step-counter;
    margin-bottom: var(--spacing-lg);
    padding-left: 50px;
    position: relative;
}

.step-list li:before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    background: var(--md-blue);
    color: white;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
}

.step-title {
    font-weight: 700;
    font-size: 18px;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.step-description {
    color: var(--text-secondary);
    line-height: 1.6;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.feature-card {
    background: var(--md-bg-primary);
    border: 2px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    padding: 15px;
    text-align: center;
}

.feature-icon {
    font-size: 32px;
    margin-bottom: var(--spacing-md);
}

.feature-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.feature-desc {
    font-size: var(--text-subhead);
    color: var(--text-secondary);
}

.contact-info {
    background: rgba(255, 149, 0, 0.1);
    border: 2px solid var(--md-orange);
    border-radius: var(--radius-md);
    padding: var(--spacing-lg);
    margin-top: var(--spacing-2xl);
}

.contact-title {
    font-weight: 700;
    font-size: 18px;
    color: var(--md-orange);
    margin-bottom: var(--spacing-md);
}

.contact-text {
    color: var(--text-secondary);
    line-height: 1.6;
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
    display: inline-block;
    margin-bottom: var(--spacing-lg);
}
.quick-link:hover { background: var(--md-gray-5); }

@media print {
    .sidebar, .mobile-header, .page-header, .selector-section, .quick-link { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .manual { border: none; padding: var(--spacing-lg); }
    .step-list li { page-break-inside: avoid; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">保護者向けマニュアル生成</h1>
        <p class="page-subtitle">保護者に配布するログイン情報と使い方ガイドを生成</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動管理へ戻る</a>
            <!-- 保護者選択 -->
            <div class="selector-section">
                <div class="form-group">
                    <label>保護者を選択</label>
                    <select onchange="location.href='guardian_manual.php?guardian_id=' + this.value">
                        <option value="">-- 保護者を選択してください --</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?= $guardian['id'] ?>" <?= $selectedGuardianId == $guardian['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($guardian['full_name']) ?>
                                <?php if ($guardian['student_names']): ?>
                                    （<?= htmlspecialchars($guardian['student_names']) ?>）
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($guardianData): ?>
                    <button onclick="window.print()" class="btn btn-primary"><span class="material-symbols-outlined">print</span> 印刷する</button>
                <?php endif; ?>
            </div>

            <!-- マニュアル本体 -->
            <?php if ($guardianData): ?>
                <div class="manual">
                    <!-- ヘッダー -->
                    <div class="manual-header">
                        <div class="manual-title">個別支援連絡帳システム</div>
                        <div class="manual-subtitle">保護者向けご利用ガイド</div>
                    </div>

                    <!-- アカウント情報 -->
                    <div class="manual-section">
                        <div class="manual-section-title">1. あなたのアカウント情報</div>
                        <div class="info-box">
                            <div class="info-row">
                                <div class="info-label">保護者氏名：</div>
                                <div class="info-value"><?= htmlspecialchars($guardianData['full_name']) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">お子様：</div>
                                <div class="info-value"><?= htmlspecialchars($guardianData['student_names'] ?: '未設定') ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">ログインID：</div>
                                <div class="info-value"><?= htmlspecialchars($guardianData['username']) ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">初期パスワード：</div>
                                <div class="info-value"><?= $guardianData['password_plain'] ? htmlspecialchars($guardianData['password_plain']) : '（未設定 - スタッフにお問い合わせください）' ?></div>
                            </div>
                        </div>
                        <p style="color: var(--md-red); font-weight: 600; margin-top: 10px;">
                            <span class="material-symbols-outlined" style="vertical-align: middle;">warning</span> このアカウント情報は大切に保管してください。
                        </p>
                    </div>

                    <!-- ログイン方法 -->
                    <div class="manual-section">
                        <div class="manual-section-title">2. ログイン方法</div>
                        <ol class="step-list">
                            <li>
                                <div class="step-title">ログインページにアクセス</div>
                                <div class="step-description">
                                    スマートフォンまたはパソコンのブラウザで以下のURLにアクセスしてください。<br>
                                    <strong style="color: var(--primary-purple); font-size: 18px; display: block; margin-top: 10px;">
                                        <?= htmlspecialchars($loginUrl) ?>
                                    </strong>
                                    <div style="margin-top: 10px; padding: var(--spacing-md); background: var(--md-gray-6); border-radius: var(--radius-sm);">
                                        <span class="material-symbols-outlined" style="vertical-align: middle;">lightbulb</span> ヒント：このページをブックマーク（お気に入り）に登録すると便利です
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="step-title">ログインIDとパスワードを入力</div>
                                <div class="step-description">
                                    上記のアカウント情報に記載されているログインIDとパスワードを入力します。
                                </div>
                            </li>
                            <li>
                                <div class="step-title">「ログイン」ボタンをクリック</div>
                                <div class="step-description">
                                    入力が完了したら、ログインボタンをクリックしてください。<br>
                                    ダッシュボード画面が表示されれば、ログイン成功です。
                                </div>
                            </li>
                        </ol>
                    </div>

                    <!-- 主な機能 -->
                    <div class="manual-section">
                        <div class="manual-section-title">3. 主な機能</div>
                        <div class="feature-grid">
                            <div class="feature-card">
                                <div class="feature-icon"><span class="material-symbols-outlined">assignment</span></div>
                                <div class="feature-name">個別支援計画書</div>
                                <div class="feature-desc">お子様の支援計画を確認できます</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><span class="material-symbols-outlined">monitoring</span></div>
                                <div class="feature-name">モニタリング表</div>
                                <div class="feature-desc">支援の達成状況を確認できます</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><span class="material-symbols-outlined">handshake</span></div>
                                <div class="feature-name">かけはし入力</div>
                                <div class="feature-desc">5領域の成長記録を入力します</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><span class="material-symbols-outlined">menu_book</span></div>
                                <div class="feature-name">連絡帳一覧</div>
                                <div class="feature-desc">日々の連絡帳を確認できます</div>
                            </div>
                        </div>
                    </div>

                    <!-- 使い方の詳細 -->
                    <div class="manual-section">
                        <div class="manual-section-title">4. 各機能の使い方</div>

                        <h3 style="color: var(--text-primary); margin: var(--spacing-lg) 0 10px 0; font-size: 18px;"><span class="material-symbols-outlined" style="vertical-align: middle;">assignment</span> 個別支援計画書の確認</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「個別支援計画書」ボタンをクリック
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    お子様を選択すると、提出済みの計画書が一覧表示されます
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    見たい計画書をクリックすると、詳細が表示されます
                                </div>
                            </li>
                        </ol>

                        <h3 style="color: var(--text-primary); margin: var(--spacing-lg) 0 10px 0; font-size: 18px;"><span class="material-symbols-outlined" style="vertical-align: middle;">monitoring</span> モニタリング表の確認</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「モニタリング表」ボタンをクリック
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    お子様を選択すると、提出済みのモニタリング表が一覧表示されます
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    見たいモニタリング表をクリックすると、達成状況やコメントが確認できます
                                </div>
                            </li>
                        </ol>

                        <h3 style="color: var(--text-primary); margin: var(--spacing-lg) 0 10px 0; font-size: 18px;"><span class="material-symbols-outlined" style="vertical-align: middle;">handshake</span> かけはしの入力</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「かけはし入力」ボタンをクリック
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    お子様と対象期間を選択します
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    5つの領域（健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性）について、お子様の様子を記入します
                                </div>
                            </li>
                            <li>
                                <div class="step-description">
                                    入力が完了したら「保存」ボタンをクリックします
                                </div>
                            </li>
                        </ol>
                    </div>

                    <!-- よくある質問 -->
                    <div class="manual-section">
                        <div class="manual-section-title">5. よくある質問</div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--text-primary);">Q. パスワードを忘れてしまいました</strong>
                            <p style="margin-top: 5px; color: var(--text-secondary);">
                                A. スタッフまでお問い合わせください。パスワードをリセットいたします。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--text-primary);">Q. スマートフォンでも利用できますか？</strong>
                            <p style="margin-top: 5px; color: var(--text-secondary);">
                                A. はい、スマートフォン、タブレット、パソコンのいずれでもご利用いただけます。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--text-primary);">Q. いつでも見られますか？</strong>
                            <p style="margin-top: 5px; color: var(--text-secondary);">
                                A. はい、インターネット接続があれば24時間いつでもアクセスできます。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: var(--text-primary);">Q. 計画書やモニタリング表が表示されません</strong>
                            <p style="margin-top: 5px; color: var(--text-secondary);">
                                A. スタッフが作成・提出すると表示されます。まだ作成されていない可能性があります。
                            </p>
                        </div>
                    </div>

                    <!-- お問い合わせ -->
                    <div class="contact-info">
                        <div class="contact-title"><span class="material-symbols-outlined" style="vertical-align: middle;">call</span> お問い合わせ</div>
                        <div class="contact-text">
                            ご不明な点がございましたら、スタッフまでお気軽にお問い合わせください。<br>
                            ログインに関するトラブルやシステムの使い方について、サポートいたします。
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--md-gray-5); color: var(--text-secondary); font-size: var(--text-subhead);">
                        発行日：<?= date('Y年n月j日') ?>
                    </div>
                </div>
            <?php endif; ?>

<?php renderPageEnd(); ?>
