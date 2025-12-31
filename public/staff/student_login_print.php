<?php
/**
 * 生徒用ログイン資料の印刷ページ
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// ログインチェック
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$studentId = $_GET['student_id'] ?? null;

if (!$studentId) {
    die('生徒IDが指定されていません。');
}

// 生徒情報を取得
$stmt = $pdo->prepare("
    SELECT
        id,
        student_name,
        username,
        password_plain,
        birth_date
    FROM students
    WHERE id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die('指定された生徒が見つかりません。');
}

if (empty($student['username']) || empty($student['password_plain'])) {
    die('この生徒にはログイン情報が設定されていません。');
}

// ログインURLを固定値で設定
$loginUrl = 'https://kobetu.narze.xyz/student/login.php';

// 現在の日付
$currentDate = date('Y年m月d日');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生徒用ログイン情報 - <?php echo htmlspecialchars($student['student_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            padding: var(--spacing-lg);
            background: var(--md-gray-6);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary-purple);
        }

        .header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .header .subtitle {
            font-size: var(--text-callout);
            color: var(--text-secondary);
        }

        .student-name {
            font-size: var(--text-title-2);
            font-weight: bold;
            color: var(--primary-purple);
            margin-bottom: var(--spacing-2xl);
            padding: 15px;
            background: var(--md-gray-6);
            border-left: 5px solid var(--primary-purple);
            border-radius: var(--radius-sm);
        }

        .info-section {
            margin-bottom: var(--spacing-2xl);
        }

        .info-label {
            font-size: var(--text-subhead);
            color: var(--text-secondary);
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }

        .info-value {
            font-size: 20px;
            color: var(--text-primary);
            padding: 15px;
            background: var(--md-gray-6);
            border-radius: var(--radius-sm);
            border: 2px solid var(--md-gray-5);
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .url-box {
            background: var(--md-bg-secondary);
            color: var(--text-primary);
            padding: var(--spacing-lg);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-2xl);
        }

        .url-box .label {
            font-size: var(--text-subhead);
            margin-bottom: var(--spacing-md);
            opacity: 0.9;
        }

        .url-box .url {
            font-size: 18px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .credentials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: var(--spacing-2xl);
        }

        .credential-box {
            padding: var(--spacing-lg);
            border: 2px solid var(--primary-purple);
            border-radius: var(--radius-sm);
            background: var(--md-gray-6);
        }

        .credential-box .label {
            font-size: var(--text-subhead);
            color: var(--primary-purple);
            font-weight: bold;
            margin-bottom: var(--spacing-md);
        }

        .credential-box .value {
            font-size: var(--text-title-2);
            color: var(--text-primary);
            font-weight: bold;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .instructions {
            background: var(--md-bg-secondary);
            border-left: 4px solid var(--md-orange);
            padding: var(--spacing-lg);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-2xl);
        }

        .instructions h3 {
            font-size: 18px;
            color: #856404;
            margin-bottom: 15px;
        }

        .instructions ol {
            margin-left: 20px;
            color: #856404;
        }

        .instructions li {
            margin-bottom: var(--spacing-md);
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 2px dashed var(--md-gray-5);
            color: var(--text-secondary);
            font-size: var(--text-subhead);
        }

        .no-print {
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }

        .btn-print {
            padding: var(--spacing-md) 30px;
            background: var(--md-bg-secondary);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: bold;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-print:hover {
            opacity: 0.9;
        }

        .btn-close {
            padding: var(--spacing-md) 30px;
            background: var(--md-gray);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: bold;
            cursor: pointer;
        }

        .btn-close:hover {
            background: var(--md-gray);
        }

        /* 印刷用スタイル */
        @media print {
            body {
                background: var(--md-bg-primary);
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: var(--spacing-lg);
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .url-box {
                background: white !important;
                color: black !important;
                border: 2px solid var(--primary-purple);
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .url-box .url {
                color: black !important;
            }
        }

        .date {
            text-align: right;
            color: var(--text-secondary);
            font-size: var(--text-subhead);
            margin-bottom: var(--spacing-lg);
        }

        .icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }

        .qr-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-top: var(--spacing-lg);
            text-align: center;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div style="max-width: 800px; margin: 0 auto; padding: var(--spacing-lg);">
            <button onclick="window.print()" class="btn-print"><span class="material-symbols-outlined">print</span> この資料を印刷する</button>
            <button onclick="window.close()" class="btn-close">閉じる</button>
        </div>
    </div>

    <div class="container">
        <div class="icon"><span class="material-symbols-outlined" style="font-size: inherit;">school</span></div>

        <div class="header">
            <h1>個別支援連絡帳システム</h1>
            <div class="subtitle">生徒用ログイン情報</div>
        </div>

        <div class="date">発行日: <?php echo $currentDate; ?></div>

        <div class="student-name">
            <span class="material-symbols-outlined">person</span> 生徒名: <?php echo htmlspecialchars($student['student_name']); ?> さん
        </div>

        <div class="url-box">
            <div class="label"><span class="material-symbols-outlined">smartphone</span> ログインURL（このアドレスにアクセスしてください）</div>
            <div class="url"><?php echo htmlspecialchars($loginUrl); ?></div>
        </div>

        <div class="credentials">
            <div class="credential-box">
                <div class="label"><span class="material-symbols-outlined">person</span> ユーザー名（ID）</div>
                <div class="value"><?php echo htmlspecialchars($student['username']); ?></div>
            </div>

            <div class="credential-box">
                <div class="label"><span class="material-symbols-outlined">key</span> パスワード</div>
                <div class="value"><?php echo htmlspecialchars($student['password_plain']); ?></div>
            </div>
        </div>

        <div class="instructions">
            <h3><span class="material-symbols-outlined">menu_book</span> ログイン手順</h3>
            <ol>
                <li>スマートフォンまたはパソコンのブラウザを開きます</li>
                <li>上記のログインURLをブラウザのアドレスバーに入力します</li>
                <li>ログイン画面が表示されたら、ユーザー名とパスワードを入力します</li>
                <li>「ログイン」ボタンをクリックします</li>
                <li>ログインが成功すると、あなた専用のページが表示されます</li>
            </ol>
        </div>

        <div class="qr-notice">
            <span class="material-symbols-outlined">lightbulb</span> ヒント: このURLをブラウザのブックマーク（お気に入り）に保存しておくと、次回から簡単にアクセスできます。
        </div>

        <div class="footer">
            <p><span class="material-symbols-outlined">warning</span> このログイン情報は他の人に教えないでください</p>
            <p style="margin-top: 10px;">ログインできない場合や、パスワードを忘れた場合は、スタッフにお知らせください。</p>
        </div>
    </div>

    <script>
        // ページ読み込み時に自動的に印刷ダイアログを表示（オプション）
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
