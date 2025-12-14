<?php
/**
 * スタッフ用かけはし入力ページ
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff_kakehashi'])) {
    $deleteStudentId = $_POST['student_id'];
    $deletePeriodId = $_POST['period_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM kakehashi_staff WHERE student_id = ? AND period_id = ?");
        $stmt->execute([$deleteStudentId, $deletePeriodId]);

        $_SESSION['success'] = 'スタッフ用かけはしを削除しました。';
        header("Location: kakehashi_staff.php?student_id=$deleteStudentId");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }
}

// 自分の教室の生徒を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name, s.support_start_date
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("SELECT id, student_name, support_start_date FROM students WHERE is_active = 1 ORDER BY student_name");
}
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? null;

// 選択された生徒の有効な期間を取得
$activePeriods = [];
if ($selectedStudentId) {
    // まず、次のかけはし期間を自動生成（期限1ヶ月前になったら生成）
    try {
        $stmt = $pdo->prepare("SELECT student_name FROM students WHERE id = ?");
        $stmt->execute([$selectedStudentId]);
        $studentInfo = $stmt->fetch();
        if ($studentInfo && shouldGenerateNextKakehashi($pdo, $selectedStudentId)) {
            $newPeriod = generateNextKakehashiPeriod($pdo, $selectedStudentId, $studentInfo['student_name']);
            if ($newPeriod) {
                error_log("Auto-generated next kakehashi period for student {$selectedStudentId}: " . $newPeriod['period_name']);
            }
        }
    } catch (Exception $e) {
        error_log("Error auto-generating next kakehashi period: " . $e->getMessage());
    }

    // 提出期限が今日から1ヶ月以内の期間のみ表示
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_periods
        WHERE student_id = ? AND is_active = 1
        AND submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
        ORDER BY submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $activePeriods = $stmt->fetchAll();

    // かけはし期間が存在しない場合は初回から自動生成
    if (empty($activePeriods)) {
        $stmt = $pdo->prepare("SELECT student_name, support_start_date FROM students WHERE id = ?");
        $stmt->execute([$selectedStudentId]);
        $student = $stmt->fetch();

        if ($student && $student['support_start_date']) {
            try {
                $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $selectedStudentId, $student['support_start_date']);
                error_log("Auto-generated " . count($generatedPeriods) . " kakehashi periods for student {$selectedStudentId}");

                // 再度期間を取得（提出期限が今日から1ヶ月以内の期間のみ）
                $stmt = $pdo->prepare("
                    SELECT * FROM kakehashi_periods
                    WHERE student_id = ? AND is_active = 1
                    AND submission_deadline <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                    ORDER BY submission_deadline DESC
                ");
                $stmt->execute([$selectedStudentId]);
                $activePeriods = $stmt->fetchAll();
            } catch (Exception $e) {
                error_log("Error auto-generating kakehashi periods: " . $e->getMessage());
            }
        }
    }
}

$selectedPeriodId = $_GET['period_id'] ?? null;

// 既存のかけはしデータを取得
$kakehashiData = null;
if ($selectedStudentId && $selectedPeriodId) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_staff
        WHERE student_id = ? AND period_id = ?
    ");
    $stmt->execute([$selectedStudentId, $selectedPeriodId]);
    $kakehashiData = $stmt->fetch();
}

// 自動生成されたデータがセッションにある場合は上書き
if (isset($_SESSION['generated_kakehashi'])) {
    $generatedData = $_SESSION['generated_kakehashi'];
    if (!$kakehashiData) {
        $kakehashiData = $generatedData;
    } else {
        // 既存データに自動生成データをマージ
        foreach ($generatedData as $key => $value) {
            if ($value) {
                $kakehashiData[$key] = $value;
            }
        }
    }
    unset($_SESSION['generated_kakehashi']);
}

// 選択された期間の情報
$selectedPeriod = null;
if ($selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$selectedPeriodId]);
    $selectedPeriod = $stmt->fetch();
}

// 選択された生徒の情報
$selectedStudent = null;
if ($selectedStudentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$selectedStudentId]);
    $selectedStudent = $stmt->fetch();
}

// ページ開始
$currentPage = 'kakehashi_staff';
renderPageStart('staff', $currentPage, 'スタッフかけはし入力');
?>

<style>
.selection-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--apple-gray-6);
    border-radius: var(--radius-md);
}

.period-info {
    background: rgba(0, 122, 255, 0.1);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-blue);
}

.period-info p { margin: 5px 0; }

.student-info {
    background: var(--apple-bg-secondary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--apple-blue);
    margin: var(--spacing-xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
}

.domains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: var(--spacing-lg);
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-subhead);
    font-weight: 600;
}

.status-draft { background: var(--apple-orange); color: white; }
.status-submitted { background: var(--apple-green); color: white; }

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-xl);
    justify-content: flex-end;
}

.generate-info {
    background: var(--apple-bg-secondary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-top: var(--spacing-lg);
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    border-left: 4px solid var(--apple-purple);
}

.btn-generate {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(245, 87, 108, 0.4);
}

.quick-links {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
    margin-bottom: var(--spacing-lg);
}

.quick-link {
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--apple-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
}
.quick-link:hover { background: var(--apple-gray-5); }

@media (max-width: 768px) {
    .selection-area {
        flex-direction: column;
    }
    .domains-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">🌉 スタッフかけはし入力</h1>
        <p class="page-subtitle">生徒の五領域の課題と目標を記録します</p>
    </div>
</div>

<!-- クイックリンク -->
<div class="quick-links">
    <a href="kakehashi_guardian_view.php" class="quick-link">📋 保護者入力確認</a>
    <a href="renrakucho_activities.php" class="quick-link">← 戻る</a>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (empty($students)): ?>
    <div class="alert alert-info">生徒情報が登録されていません。</div>
<?php else: ?>
    <!-- 生徒選択エリア -->
    <div class="selection-area">
        <div class="form-group" style="flex: 1;">
            <label class="form-label">生徒を選択 *</label>
            <select id="studentSelect" onchange="changeStudent()" class="form-control">
                <option value="">-- 生徒を選択してください --</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['student_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($selectedStudentId && empty($activePeriods)): ?>
        <div class="alert alert-info">
            この生徒の支援開始日が設定されていないため、かけはし期間を自動生成できませんでした。<br>
            生徒登録ページで支援開始日を設定してください。
        </div>
    <?php elseif ($selectedStudentId && !empty($activePeriods)): ?>
        <!-- 期間選択エリア -->
        <div class="selection-area">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">かけはし提出期限を選択 *</label>
                <select id="periodSelect" onchange="changePeriod()" class="form-control">
                    <option value="">-- 期間を選択してください --</option>
                    <?php foreach ($activePeriods as $period): ?>
                        <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                            [<?= getIndividualSupportPlanStartMonth($period) ?>開始] 提出期限: <?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?>
                            (対象期間: <?= date('Y/m/d', strtotime($period['start_date'])) ?> ～ <?= date('Y/m/d', strtotime($period['end_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedPeriod && $selectedStudent): ?>
        <!-- 生徒情報 -->
        <div class="student-info">
            <p><strong>生徒名:</strong> <?= htmlspecialchars($selectedStudent['student_name']) ?></p>
            <?php if ($selectedStudent['birth_date']): ?>
                <p><strong>生年月日:</strong> <?= date('Y年m月d日', strtotime($selectedStudent['birth_date'])) ?></p>
            <?php endif; ?>
        </div>

        <!-- 期間情報 -->
        <div class="period-info">
            <p><strong>📋 個別支援計画:</strong> <?= getIndividualSupportPlanStartMonth($selectedPeriod) ?>開始分</p>
            <p><strong>対象期間:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['start_date'])) ?> ～ <?= date('Y年m月d日', strtotime($selectedPeriod['end_date'])) ?></p>
            <p><strong>提出期限:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['submission_deadline'])) ?></p>
            <p>
                <strong>状態:</strong>
                <?php if ($kakehashiData && $kakehashiData['is_submitted']): ?>
                    <span class="status-badge status-submitted">提出済み</span>
                    <small>（提出日時: <?= date('Y年m月d日 H:i', strtotime($kakehashiData['submitted_at'])) ?>）</small>
                <?php else: ?>
                    <span class="status-badge status-draft">下書き</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- かけはし入力フォーム -->
        <form method="POST" action="kakehashi_staff_save.php" id="kakehashiForm">
            <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
            <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
            <input type="hidden" name="action" id="formAction" value="save">

            <div class="card">
                <div class="card-body">
                    <!-- 本人の願い -->
                    <div class="section-title">💫 本人の願い</div>
                    <div class="form-group">
                        <label class="form-label">本人が望んでいること、なりたい姿</label>
                        <textarea name="student_wish" class="form-control" rows="4"><?= $kakehashiData['student_wish'] ?? '' ?></textarea>
                    </div>

                    <!-- 目標設定 -->
                    <div class="section-title">🎯 目標設定</div>
                    <div class="form-group">
                        <label class="form-label">短期目標（6か月）</label>
                        <textarea name="short_term_goal" class="form-control" rows="4"><?= $kakehashiData['short_term_goal'] ?? '' ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">長期目標（1年以上）</label>
                        <textarea name="long_term_goal" class="form-control" rows="4"><?= $kakehashiData['long_term_goal'] ?? '' ?></textarea>
                    </div>

                    <!-- 五領域の課題 -->
                    <div class="section-title">🌟 五領域の課題</div>
                    <div class="domains-grid">
                        <div class="form-group">
                            <label class="form-label">健康・生活</label>
                            <textarea name="domain_health_life" class="form-control" rows="4"><?= $kakehashiData['domain_health_life'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">運動・感覚</label>
                            <textarea name="domain_motor_sensory" class="form-control" rows="4"><?= $kakehashiData['domain_motor_sensory'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">認知・行動</label>
                            <textarea name="domain_cognitive_behavior" class="form-control" rows="4"><?= $kakehashiData['domain_cognitive_behavior'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">言語・コミュニケーション</label>
                            <textarea name="domain_language_communication" class="form-control" rows="4"><?= $kakehashiData['domain_language_communication'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">人間関係・社会性</label>
                            <textarea name="domain_social_relations" class="form-control" rows="4"><?= $kakehashiData['domain_social_relations'] ?? '' ?></textarea>
                        </div>
                    </div>

                    <!-- その他の課題 -->
                    <div class="section-title">📌 その他の課題</div>
                    <div class="form-group">
                        <label class="form-label">その他、記載事項</label>
                        <textarea name="other_challenges" class="form-control" rows="4"><?= $kakehashiData['other_challenges'] ?? '' ?></textarea>
                    </div>

                    <!-- ボタン -->
                    <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success" onclick="setAction('save')">💾 下書き保存</button>
                            <button type="submit" class="btn btn-primary" onclick="return confirmSubmit()">📤 提出する</button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="margin-top: var(--spacing-lg);">
                            ✅ このかけはしは提出済みです。<br>
                            <small>※スタッフは提出後も内容を修正できます。</small>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success" onclick="setAction('update')">📝 内容を修正</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- 自動生成ボタン -->
        <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
            <div class="generate-info">
                <strong>🤖 AI自動生成機能</strong><br>
                直近5か月の連絡帳データから、AIが五領域の課題と目標を自動生成します。<br>
                生成された内容は確認・編集できます。
            </div>
            <form method="POST" action="kakehashi_staff_generate.php" onsubmit="return confirmGenerate()" style="margin-top: 15px;">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                <div style="display: flex; justify-content: center;">
                    <button type="submit" class="btn btn-generate">🤖 AIで自動生成</button>
                </div>
            </form>
        <?php endif; ?>

        <!-- PDF印刷ボタン -->
        <?php if ($selectedStudentId && $selectedPeriodId): ?>
            <div style="margin-top: var(--spacing-lg); text-align: center;">
                <a href="kakehashi_staff_pdf.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $selectedPeriodId ?>"
                   target="_blank"
                   class="btn btn-primary">
                    🖨️ PDF印刷用ダウンロード（スタッフ・保護者統合版）
                </a>
            </div>
        <?php endif; ?>

        <!-- 削除フォーム -->
        <?php if ($kakehashiData): ?>
            <form method="POST" style="margin-top: var(--spacing-lg); text-align: center;" onsubmit="return confirm('このスタッフ用かけはしを削除してもよろしいですか？\nこの操作は取り消せません。');">
                <input type="hidden" name="delete_staff_kakehashi" value="1">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                <button type="submit" class="btn btn-danger">🗑️ このスタッフ用かけはしを削除</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php
$inlineJs = <<<JS
function changeStudent() {
    const studentId = document.getElementById('studentSelect').value;
    if (studentId) {
        window.location.href = 'kakehashi_staff.php?student_id=' + studentId;
    }
}

function changePeriod() {
    const studentId = document.getElementById('studentSelect').value;
    const periodId = document.getElementById('periodSelect').value;
    if (studentId && periodId) {
        window.location.href = 'kakehashi_staff.php?student_id=' + studentId + '&period_id=' + periodId;
    }
}

function setAction(action) {
    document.getElementById('formAction').value = action;
}

function confirmSubmit() {
    setAction('submit');
    return confirm('提出すると内容の変更ができなくなります。提出してよろしいですか？');
}

function confirmGenerate() {
    return confirm('直近5か月の連絡帳データからAIが自動生成します。\\n現在入力されている内容は上書きされます。\\nよろしいですか？');
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
