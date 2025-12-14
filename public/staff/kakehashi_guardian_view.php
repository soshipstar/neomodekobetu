<?php
/**
 * スタッフ用 保護者入力かけはし確認ページ
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/kakehashi_auto_generator.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 非表示・再表示処理は kakehashi_guardian_save.php で処理

// 自分の教室の生徒を取得
if ($classroomId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_name
        FROM students s
        INNER JOIN users u ON s.guardian_id = u.id
        WHERE s.is_active = 1 AND u.classroom_id = ?
        ORDER BY s.student_name
    ");
    $stmt->execute([$classroomId]);
} else {
    $stmt = $pdo->query("SELECT id, student_name FROM students WHERE is_active = 1 ORDER BY student_name");
}
$students = $stmt->fetchAll();

// 選択された生徒（URLパラメータから取得、デフォルト値なし）
$selectedStudentId = $_GET['student_id'] ?? null;

// 表示フィルター（all: すべて, visible: 表示中のみ, hidden: 非表示のみ）
// デフォルトを 'all' に変更して、非表示フラグに関係なくすべて表示
$showFilter = $_GET['show'] ?? 'all';

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

    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_periods
        WHERE student_id = ? AND is_active = 1
        ORDER BY submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $activePeriods = $stmt->fetchAll();
}

// 選択された期間（URLパラメータから取得のみ、デフォルト値なし）
$selectedPeriodId = $_GET['period_id'] ?? null;

// 保護者入力かけはしデータを取得（単一レコード）
$kakehashiData = null;
if ($selectedStudentId && $selectedPeriodId) {
    // フィルターに応じてWHERE条件を変更
    $whereClauses = ["kg.student_id = ?", "kg.period_id = ?"];
    $params = [$selectedStudentId, $selectedPeriodId];

    if ($showFilter === 'visible') {
        $whereClauses[] = "(kg.is_hidden IS NULL OR kg.is_hidden = 0)";
    } elseif ($showFilter === 'hidden') {
        $whereClauses[] = "kg.is_hidden = 1";
    }
    // 'all'の場合は条件を追加しない

    $whereClause = implode(' AND ', $whereClauses);

    $stmt = $pdo->prepare("
        SELECT
            kg.*,
            s.student_name,
            s.birth_date,
            u.full_name as guardian_name
        FROM kakehashi_guardian kg
        INNER JOIN students s ON kg.student_id = s.id
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $kakehashiData = $stmt->fetch();
}

// 選択された期間の情報
$selectedPeriod = null;
if ($selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$selectedPeriodId]);
    $selectedPeriod = $stmt->fetch();
}

// ページ開始
$currentPage = 'kakehashi_guardian_view';
renderPageStart('staff', $currentPage, '保護者入力かけはし確認');
?>

<style>
.filter-area {
    display: flex;
    gap: 20px;
    margin-bottom: var(--spacing-2xl);
    padding: var(--spacing-lg);
    background: var(--apple-gray-6);
    border-radius: var(--radius-md);
    align-items: flex-end;
    flex-wrap: wrap;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--apple-blue);
    margin: var(--spacing-2xl) 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--apple-blue);
}

.domains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: var(--spacing-lg);
}

.period-info {
    background: rgba(0,122,255,0.1);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: var(--spacing-lg);
    border-left: 4px solid var(--apple-blue);
}

.period-info p { margin: 5px 0; }

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: var(--radius-xl);
    font-size: var(--text-subhead);
    font-weight: 600;
}

.status-submitted { background: var(--apple-green); color: white; }
.status-draft { background: rgba(255,149,0,0.15); color: var(--apple-orange); }

.button-group {
    display: flex;
    gap: 15px;
    margin-top: var(--spacing-2xl);
    justify-content: flex-end;
    flex-wrap: wrap;
}

.student-name {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
}

.view-box {
    background: var(--apple-bg-tertiary);
    border: 1px solid var(--border-primary);
    border-radius: var(--radius-sm);
    padding: 15px;
    min-height: 80px;
    color: var(--text-primary);
    line-height: 1.6;
    white-space: pre-wrap;
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

@media print {
    .sidebar, .mobile-header, .filter-area, .quick-links { display: none !important; }
    .main-content { margin: 0 !important; padding: 10px !important; }
    .period-info { background: #f5f5f5; border: 1px solid #ccc; }
    .view-box { background: #fafafa; border: 1px solid #ddd; }
    .section-title { color: #333; border-bottom-color: #333; }
    .domains-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .filter-area { flex-direction: column; }
}
</style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">保護者入力かけはし確認</h1>
        <p class="page-subtitle">保護者が入力したかけはしを確認</p>
    </div>
</div>

<!-- クイックリンク -->
<div class="quick-links">
    <a href="kakehashi_staff.php" class="quick-link">✏️ スタッフ入力</a>
    <a href="renrakucho_activities.php" class="quick-link">← 戻る</a>
</div>

<!-- メッセージ表示 -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- 生徒選択エリア（常に表示） -->
            <div class="filter-area">
                <div class="form-group">
                    <label>生徒を選択 *</label>
                    <select id="studentSelect" onchange="changeStudent()">
                        <option value="">-- 生徒を選択してください --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['student_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedStudentId): ?>
                <div class="form-group">
                    <label>表示フィルター</label>
                    <select id="showFilter" onchange="changeFilter()">
                        <option value="visible" <?= $showFilter === 'visible' ? 'selected' : '' ?>>表示中のみ</option>
                        <option value="hidden" <?= $showFilter === 'hidden' ? 'selected' : '' ?>>非表示のみ</option>
                        <option value="all" <?= $showFilter === 'all' ? 'selected' : '' ?>>すべて</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($selectedStudentId && empty($activePeriods)): ?>
                <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: var(--radius-sm); margin-bottom: var(--spacing-lg); border: 1px solid #bee5eb;">
                    この生徒のかけはし期間がまだ設定されていません。生徒登録ページで初回かけはし提出期限を設定してください。
                </div>
            <?php elseif ($selectedStudentId && !empty($activePeriods)): ?>
                <!-- 期間選択エリア（生徒選択後に表示） -->
                <div class="filter-area">
                    <div class="form-group">
                        <label>かけはし提出期限を選択 *</label>
                        <select id="periodSelect" onchange="changePeriod()">
                            <option value="">-- 提出期限を選択してください --</option>
                            <?php foreach ($activePeriods as $period): ?>
                                <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                                    提出期限: <?= date('Y年n月j日', strtotime($period['submission_deadline'])) ?>
                                    (対象期間: <?= date('Y/m/d', strtotime($period['start_date'])) ?> ～ <?= date('Y/m/d', strtotime($period['end_date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>

            <!-- かけはし編集フォーム -->
            <?php if ($selectedStudentId && $selectedPeriodId): ?>
                <?php if (!$kakehashiData): ?>
                    <div class="alert alert-info">
                        この生徒・期間の保護者入力かけはしがまだ作成されていません。保護者が最初に入力する必要があります。
                    </div>
                <?php else: ?>
                    <!-- 期間情報 -->
                    <div class="period-info">
                        <p><strong>生徒:</strong> <?= htmlspecialchars($kakehashiData['student_name']) ?></p>
                        <p><strong>保護者:</strong> <?= htmlspecialchars($kakehashiData['guardian_name'] ?? '未設定') ?></p>
                        <p><strong>📋 個別支援計画:</strong> <?= getIndividualSupportPlanStartMonth($selectedPeriod) ?>開始分</p>
                        <p><strong>対象期間:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['start_date'])) ?> ～ <?= date('Y年m月d日', strtotime($selectedPeriod['end_date'])) ?></p>
                        <p><strong>提出期限:</strong> <?= date('Y年m月d日', strtotime($selectedPeriod['submission_deadline'])) ?></p>
                        <p>
                            <strong>状態:</strong>
                            <?php if ($kakehashiData['is_submitted']): ?>
                                <span class="status-badge status-submitted">提出済み</span>
                                <small>（提出日時: <?= date('Y年m月d日 H:i', strtotime($kakehashiData['submitted_at'])) ?>）</small>
                            <?php else: ?>
                                <span class="status-badge status-draft">下書き</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- 印刷ボタン（目立つ位置に配置） -->
                    <div style="margin-bottom: var(--spacing-xl); display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="kakehashi_guardian_pdf.php?student_id=<?= $selectedStudentId ?>&period_id=<?= $selectedPeriodId ?>"
                           target="_blank"
                           class="btn"
                           style="background: var(--primary-purple); color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            🖨️ PDF印刷
                        </a>
                        <button onclick="window.print();" class="btn" style="background: var(--apple-blue); color: white;">
                            🖨️ このページを印刷
                        </button>
                    </div>

                    <!-- 閲覧表示（読み取り専用） -->
                    <div class="view-content">
                        <!-- 本人の願い -->
                        <div class="section-title">💫 本人の願い</div>
                        <div class="form-group">
                            <label>お子様が望んでいること、なりたい姿</label>
                            <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['student_wish'] ?? '（未入力）')) ?></div>
                        </div>

                        <!-- 家庭での願い -->
                        <div class="section-title">🏠 家庭での願い</div>
                        <div class="form-group">
                            <label>家庭で気になっていること、取り組みたいこと</label>
                            <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['home_challenges'] ?? '（未入力）')) ?></div>
                        </div>

                        <!-- 目標設定 -->
                        <div class="section-title">🎯 目標設定</div>
                        <div class="form-group">
                            <label>短期目標（6か月）</label>
                            <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['short_term_goal'] ?? '（未入力）')) ?></div>
                        </div>
                        <div class="form-group">
                            <label>長期目標（1年以上）</label>
                            <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['long_term_goal'] ?? '（未入力）')) ?></div>
                        </div>

                        <!-- 五領域の課題 -->
                        <div class="section-title">🌟 五領域の課題</div>
                        <div class="domains-grid">
                            <div class="form-group">
                                <label>健康・生活</label>
                                <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['domain_health_life'] ?? '（未入力）')) ?></div>
                            </div>
                            <div class="form-group">
                                <label>運動・感覚</label>
                                <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['domain_motor_sensory'] ?? '（未入力）')) ?></div>
                            </div>
                            <div class="form-group">
                                <label>認知・行動</label>
                                <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['domain_cognitive_behavior'] ?? '（未入力）')) ?></div>
                            </div>
                            <div class="form-group">
                                <label>言語・コミュニケーション</label>
                                <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['domain_language_communication'] ?? '（未入力）')) ?></div>
                            </div>
                            <div class="form-group">
                                <label>人間関係・社会性</label>
                                <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['domain_social_relations'] ?? '（未入力）')) ?></div>
                            </div>
                        </div>

                        <!-- その他の課題 -->
                        <div class="section-title">📌 その他の課題</div>
                        <div class="form-group">
                            <label>その他、お伝えしたいこと</label>
                            <div class="view-box"><?= nl2br(htmlspecialchars($kakehashiData['other_challenges'] ?? '（未入力）')) ?></div>
                        </div>
                    </div>

                    <!-- 非表示・再表示フォーム -->
                    <form method="POST" action="kakehashi_guardian_save.php" style="margin-top: var(--spacing-lg);">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                        <input type="hidden" name="redirect_show" value="<?= htmlspecialchars($showFilter) ?>">
                        <?php if ($kakehashiData['is_hidden']): ?>
                            <input type="hidden" name="unhide_guardian_kakehashi" value="1">
                            <button type="submit" style="background: var(--apple-green); color: white; border: none; padding: var(--spacing-md) 20px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600;">👁️ この保護者用かけはしを再表示</button>
                        <?php else: ?>
                            <input type="hidden" name="hide_guardian_kakehashi" value="1">
                            <button type="submit" onclick="return confirm('この保護者用かけはしを非表示にしてもよろしいですか？\n再表示することもできます。');" style="background: var(--apple-orange); color: #856404; border: none; padding: var(--spacing-md) 20px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600;">🙈 この保護者用かけはしを非表示</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

<?php
$inlineJs = <<<JS
function changeStudent() {
    const studentId = document.getElementById('studentSelect').value;
    if (studentId) {
        window.location.href = 'kakehashi_guardian_view.php?student_id=' + studentId;
    }
}

function changePeriod() {
    const studentId = document.getElementById('studentSelect').value;
    const periodId = document.getElementById('periodSelect').value;
    const showFilter = document.getElementById('showFilter')?.value || 'visible';
    if (studentId && periodId) {
        window.location.href = 'kakehashi_guardian_view.php?student_id=' + studentId + '&period_id=' + periodId + '&show=' + showFilter;
    }
}

function changeFilter() {
    const studentId = document.getElementById('studentSelect').value;
    const periodId = document.getElementById('periodSelect')?.value || '';
    const showFilter = document.getElementById('showFilter').value;
    if (studentId) {
        let url = 'kakehashi_guardian_view.php?student_id=' + studentId + '&show=' + showFilter;
        if (periodId) {
            url += '&period_id=' + periodId;
        }
        window.location.href = url;
    }
}
JS;

renderPageEnd(['inlineJs' => $inlineJs]);
?>
