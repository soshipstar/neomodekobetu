<?php
/**
 * 保護者向けマニュアル生成ページ
 * アカウント情報とログイン方法、使い方を印刷可能な形式で出力
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();

// 保護者一覧を取得
$stmt = $pdo->query("
    SELECT u.*,
           GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
    FROM users u
    LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
    WHERE u.user_type = 'guardian' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY u.full_name
");
$guardians = $stmt->fetchAll();

// 選択された保護者
$selectedGuardianId = $_GET['guardian_id'] ?? null;
$guardianData = null;

if ($selectedGuardianId) {
    $stmt = $pdo->prepare("
        SELECT u.*,
               GROUP_CONCAT(s.student_name SEPARATOR '、') as student_names
        FROM users u
        LEFT JOIN students s ON u.id = s.guardian_id AND s.is_active = 1
        WHERE u.id = ? AND u.user_type = 'guardian'
        GROUP BY u.id
    ");
    $stmt->execute([$selectedGuardianId]);
    $guardianData = $stmt->fetch();
}

// サーバーURLを取得
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$serverUrl = $protocol . $_SERVER['HTTP_HOST'];
$loginUrl = $serverUrl . '/login.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保護者向けマニュアル生成</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hiragino Sans", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .selector-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
        }

        .form-group select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* 印刷用マニュアルスタイル */
        .manual {
            background: white;
            padding: 40px;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .manual-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }

        .manual-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .manual-subtitle {
            font-size: 16px;
            color: #666;
        }

        .manual-section {
            margin-bottom: 30px;
        }

        .manual-section-title {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #667eea;
        }

        .info-box {
            background: #f0f4ff;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            min-width: 150px;
        }

        .info-value {
            color: #333;
            font-weight: 700;
        }

        .step-list {
            list-style: none;
            counter-reset: step-counter;
        }

        .step-list li {
            counter-increment: step-counter;
            margin-bottom: 20px;
            padding-left: 50px;
            position: relative;
        }

        .step-list li:before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #333;
            margin-bottom: 8px;
        }

        .step-description {
            color: #666;
            line-height: 1.6;
        }

        .screenshot-placeholder {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            color: #999;
            margin: 15px 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .feature-card {
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }

        .feature-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .feature-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .feature-desc {
            font-size: 14px;
            color: #666;
        }

        .contact-info {
            background: #fff9e6;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }

        .contact-title {
            font-weight: 700;
            font-size: 18px;
            color: #856404;
            margin-bottom: 10px;
        }

        .contact-text {
            color: #666;
            line-height: 1.6;
        }

        /* 印刷用スタイル */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                border-radius: 0;
            }

            .header,
            .selector-section,
            .nav-links,
            .btn {
                display: none;
            }

            .content {
                padding: 0;
            }

            .manual {
                border: none;
                padding: 20px;
            }

            .step-list li {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📖 保護者向けマニュアル生成</h1>
            <div class="nav-links">
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
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
                    <button onclick="window.print()" class="btn btn-primary">🖨️ 印刷する</button>
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
                                <div class="info-value">（初回ログイン時にお伝えします）</div>
                            </div>
                        </div>
                        <p style="color: #dc3545; font-weight: 600; margin-top: 10px;">
                            ⚠️ このアカウント情報は大切に保管してください。
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
                                    <strong style="color: #667eea; font-size: 18px; display: block; margin-top: 10px;">
                                        <?= htmlspecialchars($loginUrl) ?>
                                    </strong>
                                    <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                        💡 ヒント：このページをブックマーク（お気に入り）に登録すると便利です
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
                                <div class="feature-icon">📋</div>
                                <div class="feature-name">個別支援計画書</div>
                                <div class="feature-desc">お子様の支援計画を確認できます</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📊</div>
                                <div class="feature-name">モニタリング表</div>
                                <div class="feature-desc">支援の達成状況を確認できます</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">🌉</div>
                                <div class="feature-name">かけはし入力</div>
                                <div class="feature-desc">5領域の成長記録を入力します</div>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📚</div>
                                <div class="feature-name">連絡帳一覧</div>
                                <div class="feature-desc">日々の連絡帳を確認できます</div>
                            </div>
                        </div>
                    </div>

                    <!-- 使い方の詳細 -->
                    <div class="manual-section">
                        <div class="manual-section-title">4. 各機能の使い方</div>

                        <h3 style="color: #333; margin: 20px 0 10px 0; font-size: 18px;">📋 個別支援計画書の確認</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「📋 個別支援計画書」ボタンをクリック
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

                        <h3 style="color: #333; margin: 20px 0 10px 0; font-size: 18px;">📊 モニタリング表の確認</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「📊 モニタリング表」ボタンをクリック
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

                        <h3 style="color: #333; margin: 20px 0 10px 0; font-size: 18px;">🌉 かけはしの入力</h3>
                        <ol class="step-list">
                            <li>
                                <div class="step-description">
                                    ダッシュボード上部の「🌉 かけはし入力」ボタンをクリック
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
                            <strong style="color: #333;">Q. パスワードを忘れてしまいました</strong>
                            <p style="margin-top: 5px; color: #666;">
                                A. スタッフまでお問い合わせください。パスワードをリセットいたします。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: #333;">Q. スマートフォンでも利用できますか？</strong>
                            <p style="margin-top: 5px; color: #666;">
                                A. はい、スマートフォン、タブレット、パソコンのいずれでもご利用いただけます。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: #333;">Q. いつでも見られますか？</strong>
                            <p style="margin-top: 5px; color: #666;">
                                A. はい、インターネット接続があれば24時間いつでもアクセスできます。
                            </p>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <strong style="color: #333;">Q. 計画書やモニタリング表が表示されません</strong>
                            <p style="margin-top: 5px; color: #666;">
                                A. スタッフが作成・提出すると表示されます。まだ作成されていない可能性があります。
                            </p>
                        </div>
                    </div>

                    <!-- お問い合わせ -->
                    <div class="contact-info">
                        <div class="contact-title">📞 お問い合わせ</div>
                        <div class="contact-text">
                            ご不明な点がございましたら、スタッフまでお気軽にお問い合わせください。<br>
                            ログインに関するトラブルやシステムの使い方について、サポートいたします。
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #e1e8ed; color: #999; font-size: 14px;">
                        発行日：<?= date('Y年n月j日') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
