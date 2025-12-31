<?php
/**
 * 利用者一括登録 - PDF出力ページ
 * 登録した保護者のID/パスワード一覧をPDFで出力
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$classroomId = $_SESSION['classroom_id'] ?? null;

// セッションから登録結果を取得
if (!isset($_SESSION['bulk_register_result'])) {
    header('Location: bulk_register.php');
    exit;
}

$result = $_SESSION['bulk_register_result'];

// 教室情報を取得
$classroom = null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
}

$classroomName = $classroom['classroom_name'] ?? '教室';
$registeredAt = $result['registered_at'] ?? date('Y-m-d H:i:s');
$guardians = $result['guardians'] ?? [];
$students = $result['students'] ?? [];

// PDFファイル名
$pdfFilename = '利用者登録情報_' . date('Ymd_His');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>利用者登録情報 - PDF出力</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Yu Gothic", "YuGothic", "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Meiryo", sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1976D2;
            color: white;
        }

        .btn-primary:hover {
            background: #1565C0;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-success:hover {
            background: #388E3C;
        }

        /* PDF用コンテンツ */
        #pdf-content {
            background: white;
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .header .meta {
            font-size: 12px;
            color: #666;
        }

        .section {
            margin-bottom: 25px;
        }

        .section h2 {
            font-size: 14px;
            background: #f0f0f0;
            padding: 8px 12px;
            margin-bottom: 10px;
            border-left: 4px solid #1976D2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .credential-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .credential-box .guardian-name {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .credential-box .credential-row {
            display: flex;
            gap: 30px;
            font-size: 12px;
        }

        .credential-box .credential-row span {
            display: inline-block;
        }

        .credential-box .label {
            color: #666;
            min-width: 80px;
        }

        .credential-box .value {
            font-family: "Consolas", "Monaco", monospace;
            font-weight: bold;
        }

        .students-list {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 10px;
            color: #666;
            text-align: center;
        }

        .notice {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 11px;
            color: #721c24;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .controls {
                display: none;
            }

            #pdf-content {
                box-shadow: none;
                padding: 10mm;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <button class="btn btn-primary" onclick="downloadPdf()">PDFダウンロード</button>
        <button class="btn btn-secondary" onclick="window.print()">印刷</button>
        <a href="bulk_register.php" class="btn btn-success">続けて登録</a>
    </div>

    <div id="pdf-content">
        <div class="header">
            <h1>利用者登録情報</h1>
            <div class="meta">
                <?= htmlspecialchars($classroomName) ?> ｜ 登録日時: <?= htmlspecialchars($registeredAt) ?>
            </div>
        </div>

        <div class="notice">
            <strong>重要:</strong> この書類には保護者のログイン情報（ユーザー名・パスワード）が含まれています。
            取り扱いには十分ご注意ください。保護者への配布後は適切に管理してください。
        </div>

        <div class="section">
            <h2>登録サマリー</h2>
            <table>
                <tr>
                    <th>登録保護者数</th>
                    <td><?= count($guardians) ?>名</td>
                    <th>登録生徒数</th>
                    <td><?= count($students) ?>名</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>保護者ログイン情報</h2>
            <?php foreach ($guardians as $guardian): ?>
            <div class="credential-box">
                <div class="guardian-name"><?= htmlspecialchars($guardian['name']) ?></div>
                <div class="credential-row">
                    <span><span class="label">ユーザー名:</span> <span class="value"><?= htmlspecialchars($guardian['username']) ?></span></span>
                    <span><span class="label">パスワード:</span> <span class="value"><?= htmlspecialchars($guardian['password']) ?></span></span>
                </div>
                <?php if (!empty($guardian['email'])): ?>
                <div style="font-size: 11px; color: #666; margin-top: 3px;">
                    メール: <?= htmlspecialchars($guardian['email']) ?>
                </div>
                <?php endif; ?>
                <div class="students-list">
                    紐付け生徒: <?= htmlspecialchars(implode('、', $guardian['students'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="section">
            <h2>登録生徒一覧</h2>
            <table>
                <thead>
                    <tr>
                        <th>生徒氏名</th>
                        <th>保護者</th>
                        <th>生年月日</th>
                        <th>支援開始日</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['name']) ?></td>
                        <td><?= htmlspecialchars($student['guardian_name']) ?></td>
                        <td><?= htmlspecialchars($student['birth_date']) ?></td>
                        <td><?= htmlspecialchars($student['support_start_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            この書類は <?= htmlspecialchars($classroomName) ?> の利用者登録システムにより自動生成されました。
        </div>
    </div>

    <script>
        async function downloadPdf() {
            const element = document.getElementById('pdf-content');
            const filename = '<?= $pdfFilename ?>';

            const opt = {
                margin: [5, 5, 5, 5],
                filename: filename + '.pdf',
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                },
                pagebreak: {
                    mode: ['avoid-all', 'css', 'legacy'],
                    avoid: '.credential-box, table'
                }
            };

            try {
                await html2pdf().set(opt).from(element).save();
            } catch (error) {
                console.error('PDF生成エラー:', error);
                alert('PDF生成に失敗しました。印刷機能をご利用ください。');
            }
        }
    </script>
</body>
</html>
