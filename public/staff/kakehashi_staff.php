<?php
/**
 * スタッフ用かけはし入力ページ
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理（自分の教室の生徒のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff_kakehashi'])) {
    $deleteStudentId = $_POST['student_id'];
    $deletePeriodId = $_POST['period_id'];

    try {
        // 生徒が自分の教室に所属しているか確認
        if ($classroomId) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND classroom_id = ?");
            $stmt->execute([$deleteStudentId, $classroomId]);
            if (!$stmt->fetch()) {
                throw new Exception('アクセス権限がありません。');
            }
        }

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
        // 自分の教室の生徒のみ
        if ($classroomId) {
            $stmt = $pdo->prepare("SELECT student_name FROM students WHERE id = ? AND classroom_id = ?");
            $stmt->execute([$selectedStudentId, $classroomId]);
        } else {
            $stmt = $pdo->prepare("SELECT student_name FROM students WHERE id = ?");
            $stmt->execute([$selectedStudentId]);
        }
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
        if ($classroomId) {
            $stmt = $pdo->prepare("SELECT student_name, support_start_date, support_plan_start_type FROM students WHERE id = ? AND classroom_id = ?");
            $stmt->execute([$selectedStudentId, $classroomId]);
        } else {
            $stmt = $pdo->prepare("SELECT student_name, support_start_date, support_plan_start_type FROM students WHERE id = ?");
            $stmt->execute([$selectedStudentId]);
        }
        $student = $stmt->fetch();

        // support_plan_start_type が 'next' の場合は自動生成しない（次回の期間まで待機）
        $supportPlanStartType = $student['support_plan_start_type'] ?? 'current';

        if ($student && $student['support_start_date'] && $supportPlanStartType === 'current') {
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

// 選択された生徒のsupport_plan_start_typeを取得（メッセージ表示用）
$selectedStudentSupportPlanStartType = 'current';
if ($selectedStudentId) {
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT support_plan_start_type FROM students WHERE id = ? AND classroom_id = ?");
        $stmt->execute([$selectedStudentId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT support_plan_start_type FROM students WHERE id = ?");
        $stmt->execute([$selectedStudentId]);
    }
    $typeResult = $stmt->fetch();
    $selectedStudentSupportPlanStartType = $typeResult['support_plan_start_type'] ?? 'current';

    // 「次回の期間から作成する」設定の場合は、既存のかけはし期間も非表示にする
    if ($selectedStudentSupportPlanStartType === 'next') {
        $activePeriods = [];
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
    if ($classroomId) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND classroom_id = ?");
        $stmt->execute([$selectedStudentId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$selectedStudentId]);
    }
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
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
}

.period-info {
    background: rgba(0, 122, 255, 0.1);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--md-blue);
}

.period-info p { margin: 5px 0; }

.student-info {
    background: var(--md-bg-secondary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--md-blue);
    margin: var(--spacing-xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--md-blue);
}

.domains-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: var(--spacing-lg);
}

.domains-grid .form-group {
    width: 100%;
}

.domains-grid textarea {
    min-height: 150px;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-subhead);
    font-weight: 600;
}

.status-draft { background: var(--md-orange); color: white; }
.status-submitted { background: var(--md-green); color: white; }

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-xl);
    justify-content: flex-end;
}

.generate-info {
    background: var(--md-bg-secondary);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-top: var(--spacing-lg);
    font-size: var(--text-subhead);
    color: var(--text-secondary);
    border-left: 4px solid var(--md-purple);
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
    background: var(--md-bg-secondary);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: var(--text-footnote);
    font-weight: 500;
    transition: all var(--duration-fast);
}
.quick-link:hover { background: var(--md-gray-5); }

@media (max-width: 768px) {
    .selection-area {
        flex-direction: column;
    }
    .domains-grid {
        grid-template-columns: 1fr;
    }
}

/* Apple-style buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    white-space: nowrap;
}

.btn:hover {
    transform: translateY(-1px);
}

.btn:active {
    transform: translateY(0);
}

.btn-primary {
    background: linear-gradient(180deg, #1976D2 0%, #1565C0 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(0, 122, 255, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(180deg, #42A5F5 0%, #1976D2 100%);
    box-shadow: 0 4px 12px rgba(0, 122, 255, 0.4);
}

.btn-secondary {
    background: linear-gradient(180deg, #757575 0%, #636366 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(142, 142, 147, 0.3);
}

.btn-secondary:hover {
    background: linear-gradient(180deg, #9E9EA3 0%, #757575 100%);
    box-shadow: 0 4px 12px rgba(142, 142, 147, 0.4);
}

.btn-success {
    background: linear-gradient(180deg, #4CAF50 0%, #388E3C 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(52, 199, 89, 0.3);
}

.btn-success:hover {
    background: linear-gradient(180deg, #66BB6A 0%, #4CAF50 100%);
    box-shadow: 0 4px 12px rgba(52, 199, 89, 0.4);
}

.btn-danger {
    background: linear-gradient(180deg, #F44336 0%, #D32F2F 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(255, 59, 48, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(180deg, #EF5350 0%, #F44336 100%);
    box-shadow: 0 4px 12px rgba(255, 59, 48, 0.4);
}

.btn-generate {
    background: linear-gradient(135deg, #AF52DE 0%, #5856D6 100%);
    color: white;
    box-shadow: 0 2px 12px rgba(88, 86, 214, 0.4);
    padding: 14px 28px;
    font-size: 16px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(88, 86, 214, 0.5);
}

.action-buttons-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 15px;
    margin-top: var(--spacing-xl);
    padding: var(--spacing-lg);
    background: var(--md-gray-6);
    border-radius: var(--radius-md);
}

.action-button-form {
    margin: 0;
}

@media (max-width: 768px) {
    .action-buttons-row {
        flex-direction: column;
        align-items: stretch;
    }
    .action-buttons-row .btn {
        width: 100%;
        justify-content: center;
    }
}

/* ヘルプアイコン */
.help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    background: var(--md-gray-4);
    color: white;
    border-radius: 50%;
    font-size: 12px;
    font-weight: bold;
    margin-left: 6px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.help-icon:hover {
    background: var(--md-blue);
    transform: scale(1.1);
}
.help-tooltip {
    display: none;
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    margin-top: 8px;
    padding: 12px 16px;
    background: var(--md-bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-size: 13px;
    font-weight: normal;
    color: var(--text-secondary);
    white-space: normal;
    width: 300px;
    z-index: 1000;
    line-height: 1.5;
}
.help-tooltip::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 6px solid var(--border-color);
}
.help-tooltip::after {
    content: '';
    position: absolute;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-bottom: 5px solid var(--md-bg-primary);
}
.help-icon.active .help-tooltip {
    display: block;
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><span class="material-symbols-outlined">handshake</span> スタッフかけはし入力</h1>
        <p class="page-subtitle">生徒の五領域の課題と目標を記録します</p>
    </div>
</div>

<!-- クイックリンク -->
<div class="quick-links">
    <a href="kakehashi_guardian_view.php" class="quick-link"><span class="material-symbols-outlined">assignment</span> 保護者入力確認</a>
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
        <?php if ($selectedStudentSupportPlanStartType === 'next'): ?>
        <div class="alert alert-info">
            この生徒は「次回の期間から個別支援計画を作成する」設定になっています。<br>
            現在は連絡帳のみ利用可能です。次回の期間が近づくと自動的にかけはしが作成されます。
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            この生徒の支援開始日が設定されていないため、かけはし期間を自動生成できませんでした。<br>
            生徒登録ページで支援開始日を設定してください。
        </div>
        <?php endif; ?>
    <?php elseif ($selectedStudentId && !empty($activePeriods)): ?>
        <!-- 期間選択エリア -->
        <div class="selection-area">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">かけはし提出期限を選択 *
                    <span class="help-icon" onclick="toggleHelp(this, event)">?
                        <div class="help-tooltip">個別支援計画書の期間に対応したかけはしの提出期限です。保護者とスタッフそれぞれが、この期限までにかけはしを記入・提出します。</div>
                    </span>
                </label>
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
            <p><strong><span class="material-symbols-outlined">assignment</span> 個別支援計画:</strong> <?= getIndividualSupportPlanStartMonth($selectedPeriod) ?>開始分</p>
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
                    <div class="section-title"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">auto_awesome</span> 本人の願い</div>
                    <div class="form-group">
                        <label class="form-label">本人が望んでいること、なりたい姿</label>
                        <textarea name="student_wish" class="form-control" rows="6"><?= $kakehashiData['student_wish'] ?? '' ?></textarea>
                    </div>

                    <!-- 目標設定 -->
                    <div class="section-title"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">target</span> 目標設定</div>
                    <div class="form-group">
                        <label class="form-label">短期目標（6か月）</label>
                        <textarea name="short_term_goal" class="form-control" rows="6"><?= $kakehashiData['short_term_goal'] ?? '' ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">長期目標（1年以上）</label>
                        <textarea name="long_term_goal" class="form-control" rows="6"><?= $kakehashiData['long_term_goal'] ?? '' ?></textarea>
                    </div>

                    <!-- 五領域の課題 -->
                    <div class="section-title"><span class="material-symbols-outlined">star</span> 五領域の課題</div>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-md); font-size: var(--text-subhead);">各領域300文字程度を目安に、活動記録を基に課題と支援方針を記述してください。</p>
                    <div class="domains-grid">
                        <div class="form-group">
                            <label class="form-label"><span class="material-symbols-outlined">favorite</span> 健康・生活</label>
                            <textarea name="domain_health_life" class="form-control" rows="6"><?= $kakehashiData['domain_health_life'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><span class="material-symbols-outlined">directions_run</span> 運動・感覚</label>
                            <textarea name="domain_motor_sensory" class="form-control" rows="6"><?= $kakehashiData['domain_motor_sensory'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><span class="material-symbols-outlined">psychology</span> 認知・行動</label>
                            <textarea name="domain_cognitive_behavior" class="form-control" rows="6"><?= $kakehashiData['domain_cognitive_behavior'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><span class="material-symbols-outlined">chat</span> 言語・コミュニケーション</label>
                            <textarea name="domain_language_communication" class="form-control" rows="6"><?= $kakehashiData['domain_language_communication'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><span class="material-symbols-outlined">group</span> 人間関係・社会性</label>
                            <textarea name="domain_social_relations" class="form-control" rows="6"><?= $kakehashiData['domain_social_relations'] ?? '' ?></textarea>
                        </div>
                    </div>

                    <!-- その他の課題 -->
                    <div class="section-title"><span class="material-symbols-outlined">push_pin</span> その他の課題</div>
                    <div class="form-group">
                        <label class="form-label">その他、記載事項</label>
                        <textarea name="other_challenges" class="form-control" rows="6"><?= $kakehashiData['other_challenges'] ?? '' ?></textarea>
                    </div>

                    <!-- ボタン -->
                    <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success" onclick="setAction('save')"><span class="material-symbols-outlined">save</span> 下書き保存</button>
                            <button type="submit" class="btn btn-primary" onclick="return confirmSubmit()"><span class="material-symbols-outlined">upload_file</span> 提出する</button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="margin-top: var(--spacing-lg);">
                            <span class="material-symbols-outlined">check_circle</span> このかけはしは提出済みです。<br>
                            <small>※スタッフは提出後も内容を修正できます。</small>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success" onclick="setAction('update')"><span class="material-symbols-outlined">edit_note</span> 内容を修正</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- AI分析機能説明 -->
        <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
            <div class="generate-info">
                <strong><span class="material-symbols-outlined">monitoring</span> AI分析機能</strong><br>
                直近5か月の連絡帳データから、AIが五領域の課題と目標を自動生成します。<br>
                生成された内容は確認・編集できます。
            </div>
        <?php endif; ?>

        <!-- アクションボタン（横並び） -->
        <div class="action-buttons-row">
            <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
                <form method="POST" action="kakehashi_staff_generate.php" onsubmit="return confirmGenerate()" class="action-button-form">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                    <button type="submit" class="btn btn-generate"><span class="material-symbols-outlined">monitoring</span> 分析してかけはしを生成</button>
                </form>
            <?php endif; ?>

            <?php if ($selectedStudentId && $selectedPeriodId): ?>
                <a href="kakehashi_staff_pdf.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $selectedPeriodId ?>&staff_only=1"
                   target="_blank"
                   class="btn btn-secondary">
                    <span class="material-symbols-outlined">print</span> スタッフかけはしをPDF出力
                </a>
            <?php endif; ?>

            <?php if ($kakehashiData): ?>
                <form method="POST" onsubmit="return confirm('このスタッフ用かけはしを削除してもよろしいですか？\nこの操作は取り消せません。');" class="action-button-form">
                    <input type="hidden" name="delete_staff_kakehashi" value="1">
                    <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                    <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                    <button type="submit" class="btn btn-danger"><span class="material-symbols-outlined">delete</span> このスタッフ用かけはしを削除</button>
                </form>
            <?php endif; ?>
        </div>
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

// ヘルプアイコンのツールチップ切り替え
function toggleHelp(element, event) {
    event.stopPropagation();
    document.querySelectorAll('.help-icon.active').forEach(icon => {
        if (icon !== element) {
            icon.classList.remove('active');
        }
    });
    element.classList.toggle('active');
}

// ページ全体クリックでヘルプを閉じる
document.addEventListener('click', function(event) {
    if (!event.target.closest('.help-icon')) {
        document.querySelectorAll('.help-icon.active').forEach(icon => {
            icon.classList.remove('active');
        });
    }
});
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
