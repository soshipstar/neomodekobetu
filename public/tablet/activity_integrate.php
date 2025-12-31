<?php
/**
 * タブレットユーザー用統合連絡帳作成ページ
 * 音声入力機能付き
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

// タブレットユーザーのみアクセス可能
requireUserType('tablet_user');

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = $_GET['id'] ?? null;
$recordDate = $_GET['date'] ?? date('Y-m-d');

if (!$activityId) {
    header('Location: index.php');
    exit;
}

// 活動情報を取得
$stmt = $pdo->prepare("
    SELECT dr.*
    FROM daily_records dr
    INNER JOIN users u ON dr.staff_id = u.id
    WHERE dr.id = ? AND u.classroom_id = ?
");
$stmt->execute([$activityId, $classroomId]);
$activity = $stmt->fetch();

if (!$activity) {
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: index.php');
    exit;
}

// 参加者と記録を取得
$stmt = $pdo->prepare("
    SELECT s.id, s.student_name, sr.daily_note,
           inote.id as integrated_id, inote.integrated_content, inote.is_sent
    FROM students s
    INNER JOIN student_records sr ON s.id = sr.student_id
    LEFT JOIN integrated_notes inote ON s.id = inote.student_id AND sr.daily_record_id = inote.daily_record_id
    WHERE sr.daily_record_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$activityId]);
$participants = $stmt->fetchAll();

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $integratedContents = $_POST['integrated_content'] ?? [];

    try {
        $pdo->beginTransaction();

        foreach ($integratedContents as $studentId => $content) {
            $content = trim($content);
            if (empty($content)) {
                continue;
            }

            // 既存の統合連絡帳を確認
            $stmt = $pdo->prepare("
                SELECT id FROM integrated_notes
                WHERE daily_record_id = ? AND student_id = ?
            ");
            $stmt->execute([$activityId, $studentId]);
            $existing = $stmt->fetch();

            if ($existing) {
                // 更新
                $stmt = $pdo->prepare("
                    UPDATE integrated_notes
                    SET integrated_content = ?, is_sent = 0
                    WHERE id = ?
                ");
                $stmt->execute([$content, $existing['id']]);
            } else {
                // 新規作成
                $stmt = $pdo->prepare("
                    INSERT INTO integrated_notes (daily_record_id, student_id, integrated_content, is_sent)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt->execute([$activityId, $studentId, $content]);
            }
        }

        $pdo->commit();
        header('Location: index.php?date=' . $recordDate);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = '保存中にエラーが発生しました: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <style>@media(prefers-color-scheme:dark){html,body{background:#1E1E1E;color:rgba(255,255,255,0.87)}}</style>
    <link rel="stylesheet" href="/assets/css/google-design.css">
    <title>統合連絡帳作成 - タブレット</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            font-size: var(--text-title-2);
        }

        .header {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
        }

        .header h1 {
            font-size: 36px;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .activity-info {
            font-size: 26px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .back-link {
            display: inline-block;
            color: var(--md-blue);
            text-decoration: none;
            font-size: var(--text-title-2);
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .student-card {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-md);
        }

        .student-name {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .daily-note {
            background: var(--md-gray-6);
            padding: var(--spacing-lg);
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            border-left: 5px solid var(--md-blue);
        }

        .daily-note-label {
            font-size: 22px;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-md);
        }

        .daily-note-content {
            font-size: var(--text-title-2);
            color: var(--text-primary);
            line-height: 1.6;
        }

        label {
            display: block;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .textarea-with-voice {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        textarea {
            width: 100%;
            min-height: 200px;
            padding: var(--spacing-lg);
            font-size: 26px;
            border: 3px solid var(--md-gray-5);
            border-radius: var(--radius-md);
            resize: vertical;
            font-family: inherit;
        }

        textarea:focus {
            outline: none;
            border-color: var(--md-blue);
        }

        .voice-btn {
            background: var(--md-blue);
            color: white;
            border: none;
            padding: 25px 40px;
            font-size: 28px;
            border-radius: var(--radius-md);
            cursor: pointer;
            align-self: flex-start;
        }

        .voice-btn:hover {
            background: #1565C0;
        }

        .voice-btn.listening {
            background: var(--md-red);
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .button-group {
            display: flex;
            gap: 20px;
            margin-top: 40px;
        }

        .submit-btn {
            flex: 1;
            padding: var(--spacing-2xl);
            font-size: 32px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: bold;
        }

        .btn-save {
            background: var(--md-green);
            color: white;
        }

        .btn-save:hover {
            background: var(--md-green);
        }

        .btn-cancel {
            background: var(--md-gray);
            color: white;
        }

        .btn-cancel:hover {
            background: var(--md-gray);
        }

        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 統合連絡帳作成</h1>
        <div class="activity-info">
            活動: <?php echo htmlspecialchars($activity['activity_name']); ?><br>
            日付: <?php echo date('Y年n月j日', strtotime($activity['record_date'])); ?>
        </div>
        <a href="index.php?date=<?php echo $recordDate; ?>" class="back-link">← 戻る</a>
    </div>

    <form method="POST" id="integrateForm">
        <?php foreach ($participants as $participant): ?>
            <div class="student-card">
                <div class="student-name">
                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">person</span> <?php echo htmlspecialchars($participant['student_name']); ?>
                </div>

                <?php if (!empty($participant['daily_note'])): ?>
                    <div class="daily-note">
                        <div class="daily-note-label"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">description</span> 活動記録:</div>
                        <div class="daily-note-content">
                            <?php echo nl2br(htmlspecialchars($participant['daily_note'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <label for="content_<?php echo $participant['id']; ?>">
                    統合連絡帳の内容
                </label>
                <div class="textarea-with-voice">
                    <textarea
                        id="content_<?php echo $participant['id']; ?>"
                        name="integrated_content[<?php echo $participant['id']; ?>]"
                        placeholder="保護者に送る内容を入力してください"
                    ><?php echo htmlspecialchars($participant['integrated_content'] ?? ''); ?></textarea>
                    <button
                        type="button"
                        class="voice-btn"
                        onclick="startVoiceInput('content_<?php echo $participant['id']; ?>')"
                    >
                        🎤 声で入力
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="button-group">
            <button type="submit" class="submit-btn btn-save"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">save</span> 保存する</button>
            <button type="button" class="submit-btn btn-cancel" onclick="location.href='index.php?date=<?php echo $recordDate; ?>'">
                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">cancel</span> キャンセル
            </button>
        </div>
    </form>

    <script>
        // Web Speech APIによる音声入力
        let recognition = null;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.lang = 'ja-JP';
            recognition.continuous = true;  // 連続モード
            recognition.interimResults = true;  // 途中結果も取得
        }

        function startVoiceInput(fieldId) {
            if (!recognition) {
                alert('お使いのブラウザは音声入力に対応していません。Chrome、Edge、Safariをご利用ください。');
                return;
            }

            const textarea = document.getElementById(fieldId);
            const voiceBtn = event.currentTarget;
            let finalTranscript = textarea.value;  // 既存の内容を保持

            voiceBtn.classList.add('listening');
            voiceBtn.textContent = '🎤 聞いています... (クリックで終了)';

            // 再度クリックで停止
            const stopListening = () => {
                recognition.stop();
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';
                voiceBtn.onclick = () => startVoiceInput(fieldId);
            };

            voiceBtn.onclick = stopListening;

            recognition.onresult = (event) => {
                let interimTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;

                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }

                textarea.value = finalTranscript + interimTranscript;
            };

            recognition.onerror = (event) => {
                console.error('音声認識エラー:', event.error);
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';
                voiceBtn.onclick = () => startVoiceInput(fieldId);

                if (event.error === 'no-speech') {
                    alert('音声が認識されませんでした。もう一度お試しください。');
                } else if (event.error !== 'aborted') {
                    alert('音声入力エラー: ' + event.error);
                }
            };

            recognition.onend = () => {
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';
                voiceBtn.onclick = () => startVoiceInput(fieldId);
            };

            recognition.start();
        }
    </script>
</body>
</html>
