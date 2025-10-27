<?php
/**
 * 施設通信編集ページ
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// 教室名を取得
$classroomName = "教室";
$classroomId = $_SESSION['classroom_id'] ?? null;
if ($classroomId) {
    $stmt = $pdo->prepare("SELECT classroom_name FROM classrooms WHERE id = ?");
    $stmt->execute([$classroomId]);
    $classroom = $stmt->fetch();
    if ($classroom) {
        $classroomName = $classroom['classroom_name'];
    }
}

$isNewNewsletter = false;
$newsletter = null;
$needsGeneration = false;

// 既存の通信を編集
if (isset($_GET['id'])) {
    $newsletterId = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$newsletterId]);
    $newsletter = $stmt->fetch();

    if (!$newsletter) {
        $_SESSION['error'] = '通信が見つかりません';
        header('Location: newsletter_create.php');
        exit;
    }
}
// 新規作成
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = $_POST['year'];
    $month = $_POST['month'];
    $reportStartDate = $_POST['report_start_date'];
    $reportEndDate = $_POST['report_end_date'];
    $scheduleStartDate = $_POST['schedule_start_date'];
    $scheduleEndDate = $_POST['schedule_end_date'];

    // 新規通信レコードを作成（下書き状態）
    $stmt = $pdo->prepare("
        INSERT INTO newsletters
        (year, month, title, report_start_date, report_end_date,
         schedule_start_date, schedule_end_date, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");

    $title = sprintf("%d年%d月「%s」通信", $year, $month, $classroomName);

    $stmt->execute([
        $year, $month, $title,
        $reportStartDate, $reportEndDate,
        $scheduleStartDate, $scheduleEndDate,
        $currentUser['id']
    ]);

    $newsletterId = $pdo->lastInsertId();
    $isNewNewsletter = true;
    $needsGeneration = true;

    // 作成したレコードを取得
    $stmt = $pdo->prepare("SELECT * FROM newsletters WHERE id = ?");
    $stmt->execute([$newsletterId]);
    $newsletter = $stmt->fetch();
} else {
    header('Location: newsletter_create.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>施設通信編集 - <?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title {
            flex: 1;
            min-width: 200px;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header-meta {
            font-size: 13px;
            color: #666;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .btn-save {
            background: #28a745;
            color: white;
        }

        .btn-save:hover {
            background: #218838;
        }

        .btn-publish {
            background: #007bff;
            color: white;
        }

        .btn-publish:hover {
            background: #0069d9;
        }

        .btn-download {
            background: #17a2b8;
            color: white;
        }

        .btn-download:hover {
            background: #138496;
        }

        .btn-generate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
        }

        .content-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .section-header h2 {
            color: #667eea;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        .status-published {
            background: #d4edda;
            color: #155724;
        }

        .auto-save-indicator {
            font-size: 12px;
            color: #666;
            padding: 5px 10px;
            border-radius: 3px;
            background: #f8f9fa;
        }

        .auto-save-indicator.saving {
            color: #856404;
            background: #fff3cd;
        }

        .auto-save-indicator.saved {
            color: #155724;
            background: #d4edda;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #004085;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                <h1>
                    <?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="status-badge status-<?php echo $newsletter['status']; ?>">
                        <?php echo $newsletter['status'] === 'published' ? '発行済み' : '下書き'; ?>
                    </span>
                </h1>
                <div class="header-meta">
                    報告: <?php echo date('Y/m/d', strtotime($newsletter['report_start_date'])); ?>
                    ～ <?php echo date('Y/m/d', strtotime($newsletter['report_end_date'])); ?>
                    | 予定: <?php echo date('Y/m/d', strtotime($newsletter['schedule_start_date'])); ?>
                    ～ <?php echo date('Y/m/d', strtotime($newsletter['schedule_end_date'])); ?>
                </div>
            </div>
            <div class="header-actions">
                <span class="auto-save-indicator" id="autoSaveIndicator">保存済み</span>
                <a href="newsletter_create.php" class="btn btn-back">← 一覧へ戻る</a>
                <?php if ($needsGeneration): ?>
                <button type="button" class="btn btn-generate" id="generateBtn">
                    🤖 AIで通信を生成
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-save" id="saveBtn">
                    💾 下書き保存
                </button>
                <?php if ($newsletter['status'] === 'draft'): ?>
                <button type="button" class="btn btn-publish" id="publishBtn">
                    📤 発行する
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-download" id="downloadBtn">
                    📥 ダウンロード
                </button>
            </div>
        </div>

        <?php if ($isNewNewsletter): ?>
        <div class="info-box">
            💡 新しい通信を作成しました。「AIで通信を生成」ボタンをクリックすると、指定期間の連絡帳データを基に通信の下書きが自動生成されます。生成後、内容を確認・編集してください。
        </div>
        <?php endif; ?>

        <form id="newsletterForm">
            <input type="hidden" name="id" value="<?php echo $newsletter['id']; ?>">

            <!-- タイトル -->
            <div class="content-section">
                <div class="section-header">
                    <h2>📋 タイトル</h2>
                </div>
                <div class="form-group">
                    <input type="text" name="title" class="form-control"
                           value="<?php echo htmlspecialchars($newsletter['title'], ENT_QUOTES, 'UTF-8'); ?>"
                           required>
                </div>
            </div>

            <!-- あいさつ文 -->
            <div class="content-section">
                <div class="section-header">
                    <h2>👋 あいさつ文</h2>
                </div>
                <div class="form-group">
                    <textarea name="greeting" class="form-control" rows="6"><?php echo htmlspecialchars($newsletter['greeting'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- イベントカレンダー -->
            <div class="content-section">
                <div class="section-header">
                    <h2>📅 予定イベントカレンダー</h2>
                </div>
                <div class="form-group">
                    <label>カレンダー形式で表示する予定</label>
                    <textarea name="event_calendar" class="form-control" rows="8" placeholder="例：&#10;5日(月) 運動会&#10;12日(月) 遠足&#10;19日(月) 避難訓練"><?php echo htmlspecialchars($newsletter['event_calendar'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- イベント詳細 -->
            <div class="content-section">
                <div class="section-header">
                    <h2>📝 イベント詳細</h2>
                </div>
                <div class="form-group">
                    <label>各イベントの詳細説明（各100字程度）</label>
                    <textarea name="event_details" class="form-control" rows="12"><?php echo htmlspecialchars($newsletter['event_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- 各曜日の報告 -->
            <div class="content-section">
                <div class="section-header">
                    <h2>📖 各曜日の活動報告</h2>
                </div>
                <div class="form-group">
                    <label>曜日ごとの活動内容と子どもたちの様子</label>
                    <textarea name="weekly_reports" class="form-control" rows="15"><?php echo htmlspecialchars($newsletter['weekly_reports'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- イベント結果報告 -->
            <div class="content-section">
                <div class="section-header">
                    <h2>🎉 イベント結果報告</h2>
                </div>
                <div class="form-group">
                    <label>実施したイベントの結果と様子</label>
                    <textarea name="event_results" class="form-control" rows="10"><?php echo htmlspecialchars($newsletter['event_results'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- 施設からのお願い -->
            <div class="content-section">
                <div class="section-header">
                    <h2>🙏 施設からのお願い</h2>
                </div>
                <div class="form-group">
                    <textarea name="requests" class="form-control" rows="8"><?php echo htmlspecialchars($newsletter['requests'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <!-- その他 -->
            <div class="content-section">
                <div class="section-header">
                    <h2>📌 その他</h2>
                </div>
                <div class="form-group">
                    <textarea name="others" class="form-control" rows="6"><?php echo htmlspecialchars($newsletter['others'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        </form>
    </div>

    <!-- ローディングオーバーレイ -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p id="loadingMessage">処理中...</p>
        </div>
    </div>

    <script>
        const newsletterId = <?php echo $newsletter['id']; ?>;
        let autoSaveTimer = null;
        let isGenerating = false;

        // 自動保存
        function autoSave() {
            if (isGenerating) return;

            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveNewsletter(false);
            }, 5000); // 5秒後に自動保存
        }

        // 入力変更時に自動保存タイマーをセット
        document.querySelectorAll('input, textarea').forEach(element => {
            element.addEventListener('input', autoSave);
        });

        // 保存処理
        async function saveNewsletter(showMessage = true) {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.textContent = '保存中...';
            indicator.className = 'auto-save-indicator saving';

            const formData = new FormData(document.getElementById('newsletterForm'));
            formData.append('action', 'save');

            try {
                const response = await fetch('newsletter_save.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    indicator.textContent = '保存済み';
                    indicator.className = 'auto-save-indicator saved';

                    if (showMessage) {
                        alert('下書きを保存しました');
                    }

                    setTimeout(() => {
                        indicator.className = 'auto-save-indicator';
                    }, 2000);
                } else {
                    throw new Error(result.error || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Save error:', error);
                indicator.textContent = '保存失敗';
                indicator.className = 'auto-save-indicator';

                if (showMessage) {
                    alert('保存に失敗しました: ' + error.message);
                }
            }
        }

        // 下書き保存ボタン
        document.getElementById('saveBtn').addEventListener('click', () => {
            saveNewsletter(true);
        });

        // 発行ボタン
        const publishBtn = document.getElementById('publishBtn');
        if (publishBtn) {
            publishBtn.addEventListener('click', async () => {
                if (!confirm('通信を発行しますか？発行すると保護者が閲覧できるようになります。')) {
                    return;
                }

                const formData = new FormData(document.getElementById('newsletterForm'));
                formData.append('action', 'publish');

                try {
                    const response = await fetch('newsletter_save.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('通信を発行しました');
                        window.location.reload();
                    } else {
                        throw new Error(result.error || '発行に失敗しました');
                    }
                } catch (error) {
                    console.error('Publish error:', error);
                    alert('発行に失敗しました: ' + error.message);
                }
            });
        }

        // ダウンロードボタン
        document.getElementById('downloadBtn').addEventListener('click', () => {
            window.location.href = 'newsletter_download.php?id=' + newsletterId;
        });

        // AI生成ボタン
        const generateBtn = document.getElementById('generateBtn');
        if (generateBtn) {
            generateBtn.addEventListener('click', async () => {
                if (!confirm('AIで通信の内容を生成しますか？現在の入力内容は上書きされます。')) {
                    return;
                }

                isGenerating = true;
                const overlay = document.getElementById('loadingOverlay');
                const message = document.getElementById('loadingMessage');

                overlay.classList.add('active');
                message.textContent = '通信を生成中...（1〜2分かかる場合があります）';

                try {
                    const response = await fetch('newsletter_generate_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ newsletter_id: newsletterId })
                    });

                    // レスポンスのテキストを取得してログ出力
                    const responseText = await response.text();
                    console.log('Response status:', response.status);
                    console.log('Response text:', responseText);

                    // JSONとしてパース
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        throw new Error('サーバーからの応答が不正です。レスポンス: ' + responseText.substring(0, 200));
                    }

                    if (result.success) {
                        // 生成されたコンテンツをフォームに反映
                        document.querySelector('textarea[name="greeting"]').value = result.data.greeting || '';
                        document.querySelector('textarea[name="event_calendar"]').value = result.data.event_calendar || '';
                        document.querySelector('textarea[name="event_details"]').value = result.data.event_details || '';
                        document.querySelector('textarea[name="weekly_reports"]').value = result.data.weekly_reports || '';
                        document.querySelector('textarea[name="event_results"]').value = result.data.event_results || '';
                        document.querySelector('textarea[name="requests"]').value = result.data.requests || '';
                        document.querySelector('textarea[name="others"]').value = result.data.others || '';

                        // 自動保存
                        await saveNewsletter(false);

                        alert('通信の生成が完了しました！内容を確認して、必要に応じて編集してください。');

                        // 生成ボタンを非表示
                        generateBtn.style.display = 'none';
                    } else {
                        throw new Error(result.error || '生成に失敗しました');
                    }
                } catch (error) {
                    console.error('Generate error:', error);
                    alert('生成に失敗しました: ' + error.message);
                } finally {
                    overlay.classList.remove('active');
                    isGenerating = false;
                }
            });
        }

        // Ctrl+S で保存
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveNewsletter(true);
            }
        });

        // ページ離脱時の警告（未保存の変更がある場合）
        let hasUnsavedChanges = false;
        document.querySelectorAll('input, textarea').forEach(element => {
            element.addEventListener('input', () => {
                hasUnsavedChanges = true;
            });
        });

        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // 保存後は未保存フラグをクリア
        const originalSave = saveNewsletter;
        saveNewsletter = async function(...args) {
            await originalSave(...args);
            hasUnsavedChanges = false;
        };
    </script>
</body>
</html>
