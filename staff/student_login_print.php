<?php
/**
 * 生徒用ログイン資料の印刷ページ
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

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
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }

        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 16px;
            color: #666;
        }

        .student-name {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 5px solid #667eea;
            border-radius: 5px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-label {
            font-size: 14px;
            color: #666;
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }

        .info-value {
            font-size: 20px;
            color: #333;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 2px solid #ddd;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .url-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .url-box .label {
            font-size: 14px;
            margin-bottom: 10px;
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
            margin-bottom: 30px;
        }

        .credential-box {
            padding: 20px;
            border: 2px solid #667eea;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .credential-box .label {
            font-size: 14px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .credential-box .value {
            font-size: 24px;
            color: #333;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }

        .instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
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
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 2px dashed #ddd;
            color: #666;
            font-size: 14px;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-print {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-print:hover {
            opacity: 0.9;
        }

        .btn-close {
            padding: 12px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-close:hover {
            background: #5a6268;
        }

        /* 印刷用スタイル */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .url-box {
                background: white !important;
                color: black !important;
                border: 2px solid #667eea;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .url-box .url {
                color: black !important;
            }
        }

        .date {
            text-align: right;
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }

        .qr-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div style="max-width: 800px; margin: 0 auto; padding: 20px;">
            <button onclick="window.print()" class="btn-print">🖨️ この資料を印刷する</button>
            <button onclick="window.close()" class="btn-close">閉じる</button>
        </div>
    </div>

    <div class="container">
        <div class="icon">🎓</div>

        <div class="header">
            <h1>個別支援連絡帳システム</h1>
            <div class="subtitle">生徒用ログイン情報</div>
        </div>

        <div class="date">発行日: <?php echo $currentDate; ?></div>

        <div class="student-name">
            👤 生徒名: <?php echo htmlspecialchars($student['student_name']); ?> さん
        </div>

        <div class="url-box">
            <div class="label">📱 ログインURL（このアドレスにアクセスしてください）</div>
            <div class="url"><?php echo htmlspecialchars($loginUrl); ?></div>
        </div>

        <div class="credentials">
            <div class="credential-box">
                <div class="label">👤 ユーザー名（ID）</div>
                <div class="value"><?php echo htmlspecialchars($student['username']); ?></div>
            </div>

            <div class="credential-box">
                <div class="label">🔑 パスワード</div>
                <div class="value"><?php echo htmlspecialchars($student['password_plain']); ?></div>
            </div>
        </div>

        <div class="instructions">
            <h3>📖 ログイン手順</h3>
            <ol>
                <li>スマートフォンまたはパソコンのブラウザを開きます</li>
                <li>上記のログインURLをブラウザのアドレスバーに入力します</li>
                <li>ログイン画面が表示されたら、ユーザー名とパスワードを入力します</li>
                <li>「ログイン」ボタンをクリックします</li>
                <li>ログインが成功すると、あなた専用のページが表示されます</li>
            </ol>
        </div>

        <div class="qr-notice">
            💡 ヒント: このURLをブラウザのブックマーク（お気に入り）に保存しておくと、次回から簡単にアクセスできます。
        </div>

        <div class="footer">
            <p>⚠️ このログイン情報は他の人に教えないでください</p>
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
