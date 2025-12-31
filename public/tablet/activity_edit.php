<?php
/**
 * タブレットユーザー用活動編集ページ
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
$error = '';
$success = '';

// 既存の活動を編集する場合
$activity = null;
$participants = [];

if ($activityId) {
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

    $recordDate = $activity['record_date'];

    // 参加者を取得
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM student_records sr
        INNER JOIN students s ON sr.student_id = s.id
        WHERE sr.daily_record_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$activityId]);
    $participants = $stmt->fetchAll();
}

// 教室の全生徒を取得
$stmt = $pdo->prepare("
    SELECT s.id, s.student_name
    FROM students s
    INNER JOIN users u ON s.guardian_id = u.id
    WHERE s.is_active = 1 AND u.classroom_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$classroomId]);
$allStudents = $stmt->fetchAll();

// その日の支援案を取得（同じ教室のスタッフが作成した支援案）
$stmt = $pdo->prepare("
    SELECT sp.*, u.full_name as staff_name
    FROM support_plans sp
    INNER JOIN users u ON sp.staff_id = u.id
    WHERE sp.classroom_id = ? AND sp.activity_date = ?
    ORDER BY sp.created_at DESC
");
$stmt->execute([$classroomId, $recordDate]);
$supportPlans = $stmt->fetchAll();

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityName = trim($_POST['activity_name'] ?? '');
    $studentIds = $_POST['student_ids'] ?? [];

    if (empty($activityName)) {
        $error = '活動名を入力してください';
    } elseif (empty($studentIds)) {
        $error = '参加者を選択してください';
    } else {
        try {
            $pdo->beginTransaction();

            if ($activityId) {
                // 既存の活動を更新
                $stmt = $pdo->prepare("
                    UPDATE daily_records
                    SET activity_name = ?, common_activity = ?
                    WHERE id = ?
                ");
                $stmt->execute([$activityName, $activityName, $activityId]);

                // 既存の参加者を削除
                $stmt = $pdo->prepare("DELETE FROM student_records WHERE daily_record_id = ?");
                $stmt->execute([$activityId]);
            } else {
                // 新しい活動を作成
                $stmt = $pdo->prepare("
                    INSERT INTO daily_records (staff_id, activity_name, common_activity, record_date)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$currentUser['id'], $activityName, $activityName, $recordDate]);
                $activityId = $pdo->lastInsertId();
            }

            // 参加者を追加
            $stmt = $pdo->prepare("
                INSERT INTO student_records (daily_record_id, student_id, daily_note)
                VALUES (?, ?, '')
            ");
            foreach ($studentIds as $studentId) {
                $stmt->execute([$activityId, $studentId]);
            }

            $pdo->commit();
            $success = '活動を保存しました';

            // 成功後、一覧ページにリダイレクト
            header('Location: index.php?date=' . $recordDate);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = '保存中にエラーが発生しました: ' . $e->getMessage();
        }
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
    <title><?php echo $activityId ? '活動編集' : '新しい活動'; ?> - タブレット</title>
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
        }

        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: var(--md-blue);
            text-decoration: none;
            font-size: var(--text-title-2);
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .form-container {
            background: var(--md-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .message {
            padding: 25px;
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-2xl);
            font-size: var(--text-title-2);
        }

        .error {
            background: var(--md-bg-secondary);
            color: #721c24;
            border-left: 5px solid #f5c6cb;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #c3e6cb;
        }

        .form-group {
            margin-bottom: 40px;
        }

        label {
            display: block;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .input-with-voice {
            position: relative;
            display: flex;
            gap: 15px;
        }

        input[type="text"] {
            flex: 1;
            padding: 25px;
            font-size: 28px;
            border: 3px solid var(--md-gray-5);
            border-radius: var(--radius-md);
        }

        input[type="text"]:focus {
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
            white-space: nowrap;
            min-width: 200px;
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

        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .student-checkbox {
            display: none;
        }

        .student-label {
            display: block;
            padding: 25px;
            background: var(--md-gray-6);
            border: 3px solid var(--md-gray-5);
            border-radius: var(--radius-md);
            cursor: pointer;
            text-align: center;
            font-size: 26px;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .student-checkbox:checked + .student-label {
            background: #d4edda;
            border-color: var(--md-green);
            font-weight: bold;
        }

        .student-label:hover {
            background: #e9ecef;
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
            .student-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo $activityId ? '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit</span> 活動編集' : '<span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">add</span> 新しい活動'; ?></h1>
        <a href="index.php?date=<?php echo $recordDate; ?>" class="back-link">← 戻る</a>
    </div>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" id="activityForm">
            <!-- 支援案選択 -->
            <?php if (!empty($supportPlans)): ?>
            <div class="form-group">
                <label for="support_plan"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">assignment</span> 支援案を選択 (任意)</label>
                <select id="support_plan" style="width: 100%; padding: 25px; font-size: 28px; border: 3px solid var(--md-gray-5); border-radius: var(--radius-md);">
                    <option value="">支援案を選択しない（手動入力）</option>
                    <?php foreach ($supportPlans as $plan): ?>
                        <option value="<?php echo $plan['id']; ?>"
                                data-activity-name="<?php echo htmlspecialchars($plan['activity_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-purpose="<?php echo htmlspecialchars($plan['activity_purpose'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-content="<?php echo htmlspecialchars($plan['activity_content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-domains="<?php echo htmlspecialchars($plan['five_domains_consideration'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                data-other="<?php echo htmlspecialchars($plan['other_notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($plan['activity_name']); ?>
                            (作成者: <?php echo htmlspecialchars($plan['staff_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 支援案の内容表示 -->
            <div id="support_plan_details" style="display: none; background: var(--md-gray-6); padding: var(--spacing-2xl); border-radius: var(--radius-md); margin-bottom: var(--spacing-2xl); border-left: 5px solid var(--primary-purple);">
                <h3 style="color: var(--primary-purple); font-size: 28px; margin-bottom: var(--spacing-lg);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 選択した支援案の内容</h3>
                <div id="plan_purpose" style="margin-bottom: 15px; font-size: var(--text-title-2); line-height: 1.6;"></div>
                <div id="plan_content" style="margin-bottom: 15px; font-size: var(--text-title-2); line-height: 1.6;"></div>
                <div id="plan_domains" style="margin-bottom: 15px; font-size: var(--text-title-2); line-height: 1.6;"></div>
                <div id="plan_other" style="font-size: var(--text-title-2); line-height: 1.6;"></div>
            </div>
            <?php else: ?>
            <div style="background: var(--md-bg-secondary); padding: 25px; border-radius: var(--radius-md); margin-bottom: var(--spacing-2xl); border-left: 5px solid var(--md-orange); font-size: var(--text-title-2);">
                <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">lightbulb</span> この日（<?php echo date('Y年m月d日', strtotime($recordDate)); ?>）の支援案がまだ作成されていません。
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="activity_name"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 活動名</label>
                <div class="input-with-voice">
                    <input
                        type="text"
                        id="activity_name"
                        name="activity_name"
                        value="<?php echo htmlspecialchars($activity['activity_name'] ?? ''); ?>"
                        required
                    >
                    <button type="button" class="voice-btn" onclick="startVoiceInput('activity_name')">
                        🎤 声で入力
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>👥 参加者を選択してください</label>
                <div class="student-grid">
                    <?php
                    $participantIds = array_column($participants, 'id');
                    foreach ($allStudents as $student):
                        $checked = in_array($student['id'], $participantIds) ? 'checked' : '';
                    ?>
                        <div>
                            <input
                                type="checkbox"
                                class="student-checkbox"
                                id="student_<?php echo $student['id']; ?>"
                                name="student_ids[]"
                                value="<?php echo $student['id']; ?>"
                                <?php echo $checked; ?>
                            >
                            <label class="student-label" for="student_<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['student_name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="submit-btn btn-save"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">save</span> 保存する</button>
                <button type="button" class="submit-btn btn-cancel" onclick="location.href='index.php?date=<?php echo $recordDate; ?>'">
                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">cancel</span> キャンセル
                </button>
            </div>
        </form>
    </div>

    <script>
        // Web Speech APIによる音声入力
        let recognition = null;

        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.lang = 'ja-JP';
            recognition.continuous = false;
            recognition.interimResults = false;
        }

        function startVoiceInput(fieldId) {
            if (!recognition) {
                alert('お使いのブラウザは音声入力に対応していません。Chrome、Edge、Safariをご利用ください。');
                return;
            }

            const inputField = document.getElementById(fieldId);
            const voiceBtn = event.currentTarget;

            voiceBtn.classList.add('listening');
            voiceBtn.textContent = '🎤 聞いています...';

            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                inputField.value = transcript;
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';
            };

            recognition.onerror = (event) => {
                console.error('音声認識エラー:', event.error);
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';

                if (event.error === 'no-speech') {
                    alert('音声が認識されませんでした。もう一度お試しください。');
                } else {
                    alert('音声入力エラー: ' + event.error);
                }
            };

            recognition.onend = () => {
                voiceBtn.classList.remove('listening');
                voiceBtn.textContent = '🎤 声で入力';
            };

            recognition.start();
        }

        // 支援案選択時の処理
        const supportPlanSelect = document.getElementById('support_plan');
        const supportPlanDetails = document.getElementById('support_plan_details');
        const activityNameInput = document.getElementById('activity_name');

        if (supportPlanSelect) {
            supportPlanSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];

                if (this.value === '') {
                    // 選択解除
                    supportPlanDetails.style.display = 'none';
                    return;
                }

                // 支援案の内容を取得
                const activityName = selectedOption.getAttribute('data-activity-name');
                const purpose = selectedOption.getAttribute('data-purpose');
                const content = selectedOption.getAttribute('data-content');
                const domains = selectedOption.getAttribute('data-domains');
                const other = selectedOption.getAttribute('data-other');

                // 活動名を自動入力
                if (activityName) {
                    activityNameInput.value = activityName;
                }

                // 詳細を表示
                let detailsHtml = '';

                if (purpose) {
                    detailsHtml += '<div style="margin-bottom: 15px;"><strong style="color: var(--primary-purple);">活動の目的:</strong><br>' + escapeHtml(purpose).replace(/\n/g, '<br>') + '</div>';
                }
                if (content) {
                    detailsHtml += '<div style="margin-bottom: 15px;"><strong style="color: var(--primary-purple);">活動の内容:</strong><br>' + escapeHtml(content).replace(/\n/g, '<br>') + '</div>';
                }
                if (domains) {
                    detailsHtml += '<div style="margin-bottom: 15px;"><strong style="color: var(--primary-purple);">五領域への配慮:</strong><br>' + escapeHtml(domains).replace(/\n/g, '<br>') + '</div>';
                }
                if (other) {
                    detailsHtml += '<div><strong style="color: var(--primary-purple);">その他:</strong><br>' + escapeHtml(other).replace(/\n/g, '<br>') + '</div>';
                }

                if (detailsHtml) {
                    supportPlanDetails.innerHTML = '<h3 style="color: var(--primary-purple); font-size: 28px; margin-bottom: var(--spacing-lg);"><span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">edit_note</span> 選択した支援案の内容</h3>' + detailsHtml;
                    supportPlanDetails.style.display = 'block';
                } else {
                    supportPlanDetails.style.display = 'none';
                }
            });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
