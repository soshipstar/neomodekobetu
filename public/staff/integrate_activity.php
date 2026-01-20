<?php
/**
 * 活動内容統合ページ（AI統合）
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/chatgpt.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

// 強力なtrim処理関数
if (!function_exists('powerTrim')) {
    function powerTrim($text) {
        if ($text === null || $text === '') {
            return '';
        }
        return preg_replace('/^[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+|[\s\x{00A0}-\x{200B}\x{3000}\x{FEFF}]+$/u', '', $text);
    }
}

$pdo = getDbConnection();
$currentUser = getCurrentUser();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

$activityId = $_GET['activity_id'] ?? null;

if (!$activityId) {
    $_SESSION['error'] = '活動IDが指定されていません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 活動情報を取得（同じ教室のスタッフが作成した活動も統合可能）
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id, dr.support_plan_id,
               u.full_name as staff_name,
               sp.activity_purpose, sp.activity_content, sp.five_domains_consideration, sp.other_notes
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        LEFT JOIN support_plans sp ON dr.support_plan_id = sp.id
        WHERE dr.id = ? AND u.classroom_id = ?
    ");
    $stmt->execute([$activityId, $classroomId]);
} else {
    $stmt = $pdo->prepare("
        SELECT dr.id, dr.activity_name, dr.common_activity, dr.record_date, dr.staff_id, dr.support_plan_id,
               u.full_name as staff_name,
               sp.activity_purpose, sp.activity_content, sp.five_domains_consideration, sp.other_notes
        FROM daily_records dr
        INNER JOIN users u ON dr.staff_id = u.id
        LEFT JOIN support_plans sp ON dr.support_plan_id = sp.id
        WHERE dr.id = ?
    ");
    $stmt->execute([$activityId]);
}
$activity = $stmt->fetch();

if (!$activity) {
    $_SESSION['error'] = 'この活動にアクセスする権限がありません';
    header('Location: renrakucho_activities.php');
    exit;
}

// 生徒ごとの記録を取得
$stmt = $pdo->prepare("
    SELECT sr.student_id, s.student_name, sr.daily_note,
           sr.domain1, sr.domain1_content, sr.domain2, sr.domain2_content
    FROM student_records sr
    JOIN students s ON sr.student_id = s.id
    WHERE sr.daily_record_id = ?
    ORDER BY s.student_name
");
$stmt->execute([$activityId]);
$studentRecords = $stmt->fetchAll();

// 既存の統合記録を取得（あれば）
$stmt = $pdo->prepare("
    SELECT student_id, integrated_content, is_sent
    FROM integrated_notes
    WHERE daily_record_id = ?
");
$stmt->execute([$activityId]);
$existingIntegrations = [];
foreach ($stmt->fetchAll() as $row) {
    $existingIntegrations[$row['student_id']] = $row;
}

// AIで統合文章を生成
$integratedNotes = [];
$newlyGenerated = []; // 新規生成された統合内容を追跡
foreach ($studentRecords as $record) {
    $studentId = $record['student_id'];

    // 既に統合済みの場合はそれを使用
    if (isset($existingIntegrations[$studentId])) {
        $integratedNotes[$studentId] = [
            'student_name' => $record['student_name'],
            'content' => $existingIntegrations[$studentId]['integrated_content'],
            'is_sent' => $existingIntegrations[$studentId]['is_sent']
        ];
        continue;
    }

    // AIで統合
    $domains = [];
    if (!empty($record['domain1']) && !empty($record['domain1_content'])) {
        $domains[] = [
            'category' => $record['domain1'],
            'content' => $record['domain1_content']
        ];
    }
    if (!empty($record['domain2']) && !empty($record['domain2_content'])) {
        $domains[] = [
            'category' => $record['domain2'],
            'content' => $record['domain2_content']
        ];
    }

    // 支援案情報を準備
    $supportPlan = null;
    if (!empty($activity['support_plan_id'])) {
        $supportPlan = [
            'purpose' => $activity['activity_purpose'] ?? '',
            'content' => $activity['activity_content'] ?? '',
            'domains' => $activity['five_domains_consideration'] ?? '',
            'other' => $activity['other_notes'] ?? ''
        ];
    }

    $integratedContent = generateIntegratedNote(
        $activity['activity_name'],
        $activity['common_activity'],
        $record['daily_note'] ?? '',
        $domains,
        $supportPlan
    );

    if ($integratedContent === false) {
        $integratedContent = "統合に失敗しました。手動で編集してください。\n\n" .
            "【活動内容】\n" . $activity['common_activity'] . "\n\n" .
            "【本日の様子】\n" . ($record['daily_note'] ?? '') . "\n\n" .
            "【気になったこと】\n" . implode("\n", array_map(function($d) {
                return getDomainLabel($d['category']) . ': ' . $d['content'];
            }, $domains));
    }

    $integratedNotes[$studentId] = [
        'student_name' => $record['student_name'],
        'content' => $integratedContent,
        'is_sent' => 0
    ];

    // 新規生成されたものとして記録
    $newlyGenerated[$studentId] = $integratedContent;
}

// 新規生成された統合内容を自動的に下書きとして保存
if (!empty($newlyGenerated)) {
    try {
        foreach ($newlyGenerated as $studentId => $content) {
            // 強力なtrim処理（全角スペース、特殊文字も削除）
            $content = powerTrim($content);

            // 空の内容はスキップ
            if (empty($content)) {
                continue;
            }

            // 既存レコードの確認
            $stmt = $pdo->prepare("
                SELECT id FROM integrated_notes
                WHERE daily_record_id = ? AND student_id = ?
            ");
            $stmt->execute([$activityId, $studentId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                // 新規挿入
                $stmt = $pdo->prepare("
                    INSERT INTO integrated_notes (daily_record_id, student_id, integrated_content, is_sent, created_at)
                    VALUES (?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$activityId, $studentId, $content]);
            }
        }
    } catch (Exception $e) {
        error_log("Auto-save draft integration error: " . $e->getMessage());
    }
}

// ページ開始
$currentPage = 'integrate_activity';
renderPageStart('staff', $currentPage, '統合内容の編集');
?>

<style>
.activity-info {
    background: var(--md-bg-primary);
    padding: 15px 20px;
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.activity-info h2 {
    color: var(--primary-purple);
    font-size: 18px;
    margin-bottom: var(--spacing-md);
}

.student-note {
    background: var(--md-bg-primary);
    padding: var(--spacing-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-lg);
    box-shadow: var(--shadow-md);
}

.student-note h3 {
    color: var(--text-primary);
    font-size: 20px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-purple);
}

.student-note textarea {
    width: 100%;
    padding: 15px;
    border: 1px solid var(--md-gray-5);
    border-radius: var(--radius-sm);
    font-size: var(--text-subhead);
    font-family: inherit;
    resize: vertical;
    min-height: 200px;
    line-height: 1.8;
    background: var(--md-bg-tertiary);
    color: var(--text-primary);
}

.sent-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--md-green);
    color: white;
    border-radius: var(--radius-lg);
    font-size: var(--text-caption-1);
    margin-left: 10px;
}

.button-group {
    display: flex;
    gap: 15px;
    margin-bottom: var(--spacing-lg);
}

.draft-save-btn {
    flex: 1;
    padding: 15px 30px;
    background: var(--primary-purple);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: background var(--duration-normal);
}

.draft-save-btn:disabled {
    background: var(--md-gray-4);
    cursor: not-allowed;
}

.message {
    padding: var(--spacing-md) 20px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    font-size: var(--text-subhead);
    text-align: center;
}

.message.success {
    background: rgba(52,199,89,0.15);
    color: var(--md-green);
    border-left: 4px solid var(--md-green);
}

.message.error {
    background: rgba(255,59,48,0.15);
    color: var(--md-red);
    border-left: 4px solid var(--md-red);
}

.last-saved {
    text-align: center;
    color: var(--text-secondary);
    font-size: var(--text-footnote);
    margin-bottom: 15px;
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
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">統合内容の編集</h1>
        <p class="page-subtitle">AIが生成した統合内容を確認・編集</p>
    </div>
</div>

<a href="renrakucho_activities.php" class="quick-link">← 活動一覧へ戻る</a>

        <div class="activity-info">
            <h2><?php echo htmlspecialchars($activity['activity_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p style="color: var(--text-secondary); font-size: var(--text-subhead); margin-bottom: var(--spacing-md);">
                作成者: <?php echo htmlspecialchars($activity['staff_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($activity['staff_id'] == $currentUser['id']): ?>
                    <span style="color: var(--primary-purple); font-weight: bold;">(自分)</span>
                <?php endif; ?>
            </p>
            <p><?php echo nl2br(htmlspecialchars($activity['common_activity'], ENT_QUOTES, 'UTF-8')); ?></p>
        </div>

        <p class="info-text" style="background: var(--md-bg-secondary); padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid var(--md-orange);">
            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">lightbulb</span> AIが生成した統合内容を確認・編集できます。<br>
            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">edit_note</span> 途中保存した内容は、次回アクセス時に続きから編集できます。<br>
            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">save</span> 「途中保存」ボタンで下書き保存（自動保存: 5分ごと / ショートカット: Ctrl+S）<br>
            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">upload_file</span> 「活動内容を送信」ボタンで保護者に配信されます。
        </p>

        <div id="messageArea"></div>
        <div id="lastSaved" class="last-saved"></div>

        <form id="integrationForm" method="POST" action="send_to_guardians.php">
            <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">

            <?php foreach ($integratedNotes as $studentId => $note): ?>
                <div class="student-note">
                    <h3>
                        <?php echo htmlspecialchars($note['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($note['is_sent']): ?>
                            <span class="sent-badge">送信済み</span>
                        <?php endif; ?>
                    </h3>
                    <textarea
                        name="notes[<?php echo $studentId; ?>]"
                        <?php echo $note['is_sent'] ? 'readonly' : ''; ?>
                    ><?php echo htmlspecialchars($note['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            <?php endforeach; ?>

            <div class="button-group">
                <button type="button" id="draftSaveBtn" class="draft-save-btn"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">save</span> 途中保存</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">upload_file</span> 活動内容を送信</button>
            </div>
        </form>

<?php
$inlineJs = <<<JS
// 途中保存機能
const draftSaveBtn = document.getElementById('draftSaveBtn');
const form = document.getElementById('integrationForm');
const messageArea = document.getElementById('messageArea');
const lastSavedDiv = document.getElementById('lastSaved');

// メッセージ表示関数
function showMessage(message, type) {
    type = type || 'success';
    messageArea.innerHTML = '<div class="message ' + type + '">' + message + '</div>';
    setTimeout(function() {
        messageArea.innerHTML = '';
    }, 5000);
}

// 最終保存時刻を更新
function updateLastSaved() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    lastSavedDiv.textContent = '最終保存: ' + timeStr;
}

// 途中保存処理
draftSaveBtn.addEventListener('click', async function() {
    draftSaveBtn.disabled = true;
    draftSaveBtn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">save</span> 保存中...';

    const formData = new FormData(form);

    try {
        const response = await fetch('save_draft_integration.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showMessage(result.message, 'success');
            updateLastSaved();
            alert('保存しました');
        } else {
            showMessage(result.error || '保存に失敗しました', 'error');
            alert('保存に失敗しました');
        }
    } catch (error) {
        console.error('Error:', error);
        showMessage('通信エラーが発生しました', 'error');
    } finally {
        draftSaveBtn.disabled = false;
        draftSaveBtn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">save</span> 途中保存';
    }
});

// 自動保存（5分ごと）
let autoSaveInterval = setInterval(async function() {
    const formData = new FormData(form);

    try {
        const response = await fetch('save_draft_integration.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            updateLastSaved();
            console.log('Auto-saved:', result.message);
        }
    } catch (error) {
        console.error('Auto-save error:', error);
    }
}, 5 * 60 * 1000); // 5分

// ページ離脱時に自動保存を停止
window.addEventListener('beforeunload', function() {
    clearInterval(autoSaveInterval);
});

// Ctrl+S / Cmd+S で途中保存
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        draftSaveBtn.click();
    }
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
