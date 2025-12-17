<?php
/**
 * 支援案作成・編集フォーム
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/layouts/page_wrapper.php';

// スタッフまたは管理者のみアクセス可能
requireUserType(['staff', 'admin']);

$pdo = getDbConnection();
$currentUser = getCurrentUser();
$classroomId = $_SESSION['classroom_id'] ?? null;

$planId = $_GET['id'] ?? null;
$isEdit = !empty($planId);

// 編集モードの場合、支援案データを取得
$plan = null;
if ($isEdit) {
    if ($classroomId) {
        $stmt = $pdo->prepare("
            SELECT sp.* FROM support_plans sp
            WHERE sp.id = ? AND sp.classroom_id = ?
        ");
        $stmt->execute([$planId, $classroomId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM support_plans WHERE id = ?
        ");
        $stmt->execute([$planId]);
    }
    $plan = $stmt->fetch();

    if (!$plan) {
        $_SESSION['error'] = 'この支援案にアクセスする権限がありません';
        header('Location: support_plans.php');
        exit;
    }
}

// タグの定義
$availableTags = [
    'プログラミング', 'テキスタイル', 'CAD', '動画', 'イラスト',
    '企業支援', '農業', '音楽', '食', '学習',
    '自分取扱説明書', '心理', '言語', '教育', 'イベント', 'その他'
];

// 種別の定義
$planTypes = [
    'normal' => '通常活動',
    'event' => 'イベント',
    'other' => 'その他'
];

// 対象年齢層の定義
$targetGrades = [
    'preschool' => '小学生未満',
    'elementary' => '小学生',
    'junior_high' => '中学生',
    'high_school' => '高校生'
];

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityDate = $_POST['activity_date'] ?? '';
    $activityName = $_POST['activity_name'] ?? '';
    $planType = $_POST['plan_type'] ?? 'normal';
    $targetGrade = isset($_POST['target_grade']) ? implode(',', $_POST['target_grade']) : '';
    $activityPurpose = $_POST['activity_purpose'] ?? '';
    $activityContent = $_POST['activity_content'] ?? '';
    $fiveDomainsConsideration = $_POST['five_domains_consideration'] ?? '';
    $otherNotes = $_POST['other_notes'] ?? '';
    $tags = isset($_POST['tags']) ? implode(',', $_POST['tags']) : '';
    $dayOfWeek = isset($_POST['day_of_week']) ? implode(',', $_POST['day_of_week']) : '';

    try {
        if ($isEdit) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE support_plans
                SET activity_date = ?,
                    activity_name = ?,
                    plan_type = ?,
                    target_grade = ?,
                    activity_purpose = ?,
                    activity_content = ?,
                    tags = ?,
                    day_of_week = ?,
                    five_domains_consideration = ?,
                    other_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $activityDate,
                $activityName,
                $planType,
                $targetGrade ?: null,
                $activityPurpose,
                $activityContent,
                $tags,
                $dayOfWeek,
                $fiveDomainsConsideration,
                $otherNotes,
                $planId
            ]);
            $_SESSION['success'] = '支援案を更新しました';
        } else {
            // 新規作成
            $stmt = $pdo->prepare("
                INSERT INTO support_plans (
                    activity_date, activity_name, plan_type, target_grade, activity_purpose, activity_content,
                    tags, day_of_week,
                    five_domains_consideration, other_notes,
                    staff_id, classroom_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $activityDate,
                $activityName,
                $planType,
                $targetGrade ?: null,
                $activityPurpose,
                $activityContent,
                $tags,
                $dayOfWeek,
                $fiveDomainsConsideration,
                $otherNotes,
                $currentUser['id'],
                $classroomId
            ]);
            $_SESSION['success'] = '支援案を作成しました';
        }

        header('Location: support_plans.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
    }
}

// ページ開始
$currentPage = 'support_plan_form';
$pageTitle = $isEdit ? '支援案編集' : '支援案作成';
renderPageStart('staff', $currentPage, $pageTitle);
?>

<style>
        .form-container {
            background: var(--apple-bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-md);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: var(--text-subhead);
        }

        .form-group label .required {
            color: var(--apple-red);
            margin-left: 4px;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            font-size: var(--text-subhead);
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .help-text {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: var(--spacing-2xl);
        }

        .submit-btn {
            flex: 1;
            padding: 15px 30px;
            background: var(--apple-green);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: 600;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: var(--apple-green);
        }

        .cancel-btn {
            flex: 1;
            padding: 15px 30px;
            background: var(--apple-gray);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-callout);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .cancel-btn:hover {
            background: var(--apple-gray);
        }

        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: var(--radius-sm);
            border-left: 4px solid #2196F3;
            margin-bottom: 25px;
            font-size: var(--text-subhead);
            color: var(--text-primary);
        }

        .error-message {
            background: var(--apple-bg-secondary);
            color: #721c24;
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            margin-bottom: var(--spacing-lg);
            border-left: 4px solid var(--apple-red);
        }
    </style>

<!-- ページヘッダー -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><?php echo $isEdit ? '支援案編集' : '支援案作成'; ?></h1>
        <p class="page-subtitle">活動日専用の事前計画を作成</p>
    </div>
    <div class="page-header-actions">
        <a href="support_plans.php" class="btn btn-secondary">← 支援案一覧へ</a>
    </div>
</div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="info-box">
                支援案は活動日専用の事前計画です。連絡帳作成時に、その日の支援案が自動的に利用可能になります。
            </div>

            <?php if (!$isEdit): ?>
                <div style="margin-bottom: var(--spacing-lg); text-align: center;">
                    <button type="button" id="copyFromPastBtn" class="cancel-btn" style="background: var(--primary-purple); color: white;">
                        過去の支援案を引用する
                    </button>
                </div>

                <!-- 過去の支援案選択モーダル -->
                <div id="copyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
                    <div style="background: var(--apple-bg-primary); max-width: 900px; margin: 50px auto; border-radius: var(--radius-md); padding: var(--spacing-2xl);">
                        <h2 style="margin-bottom: var(--spacing-lg);">過去の支援案を選択</h2>

                        <!-- 検索ボックス -->
                        <div style="margin-bottom: var(--spacing-lg);">
                            <input type="text" id="searchPlan" placeholder="活動名で検索..." style="width: 100%; padding: var(--spacing-md); border: 2px solid var(--primary-purple); border-radius: var(--radius-sm); font-size: var(--text-subhead);">
                            <div style="font-size: var(--text-caption-1); color: var(--text-secondary); margin-top: 5px;">
                                活動名を入力すると、リアルタイムで絞り込まれます。
                            </div>
                        </div>

                        <!-- 期間選択 -->
                        <div style="margin-bottom: var(--spacing-lg);">
                            <div style="font-size: var(--text-subhead); color: var(--text-secondary); margin-bottom: 8px; font-weight: 600;">表示期間</div>

                            <!-- クイック選択ボタン -->
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                                <button type="button" class="period-btn" data-period="7" style="padding: var(--spacing-sm) 16px; border: 2px solid var(--primary-purple); background: var(--apple-bg-primary); color: var(--primary-purple); border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                    1週間
                                </button>
                                <button type="button" class="period-btn active" data-period="30" style="padding: var(--spacing-sm) 16px; border: 2px solid var(--primary-purple); background: var(--primary-purple); color: white; border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                    1ヶ月
                                </button>
                                <button type="button" class="period-btn" data-period="90" style="padding: var(--spacing-sm) 16px; border: 2px solid var(--primary-purple); background: var(--apple-bg-primary); color: var(--primary-purple); border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                    3ヶ月
                                </button>
                                <button type="button" class="period-btn" data-period="all" style="padding: var(--spacing-sm) 16px; border: 2px solid var(--primary-purple); background: var(--apple-bg-primary); color: var(--primary-purple); border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                    すべて
                                </button>
                            </div>

                            <!-- 日付範囲指定 -->
                            <div style="background: var(--apple-gray-6); padding: 15px; border-radius: var(--radius-sm); border: 2px solid #e9ecef;">
                                <div style="font-size: var(--text-footnote); color: var(--text-secondary); margin-bottom: var(--spacing-md); font-weight: 600;">期間を指定</div>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input type="date" id="startDate" style="padding: var(--spacing-sm); border: 2px solid var(--primary-purple); border-radius: var(--radius-sm); font-size: var(--text-subhead);">
                                    <span style="color: var(--text-secondary); font-weight: 600;">〜</span>
                                    <input type="date" id="endDate" style="padding: var(--spacing-sm); border: 2px solid var(--primary-purple); border-radius: var(--radius-sm); font-size: var(--text-subhead);">
                                    <button type="button" id="applyDateRange" style="padding: var(--spacing-sm) 20px; background: var(--primary-purple); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                        適用
                                    </button>
                                    <button type="button" id="clearDateRange" style="padding: var(--spacing-sm) 16px; background: var(--apple-gray); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; font-size: var(--text-subhead); font-weight: 600;">
                                        クリア
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 表示切り替えタブ -->
                        <div style="margin-bottom: var(--spacing-lg); border-bottom: 2px solid var(--apple-gray-5);">
                            <button type="button" id="viewByDateTab" class="cancel-btn" style="padding: var(--spacing-md) 20px; border-radius: var(--radius-sm) 5px 0 0; background: var(--primary-purple); color: white; margin-right: 5px; border: none;">
                                日付順
                            </button>
                            <button type="button" id="viewByListTab" class="cancel-btn" style="padding: var(--spacing-md) 20px; border-radius: var(--radius-sm) 5px 0 0; background: #e9ecef; color: var(--text-primary); border: none;">
                                一覧
                            </button>
                        </div>

                        <div id="pastPlansContainer"></div>
                        <button type="button" onclick="document.getElementById('copyModal').style.display='none'; document.getElementById('searchPlan').value='';" class="cancel-btn" style="margin-top: var(--spacing-lg);">閉じる</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="mainForm">
                <div class="form-group">
                    <label>
                        活動予定日<span class="required">*</span>
                    </label>
                    <input type="date" name="activity_date" value="<?php echo htmlspecialchars($plan['activity_date'] ?? ''); ?>" required>
                    <div class="help-text">この支援案を使用する活動の予定日を選択してください</div>
                </div>

                <div class="form-group">
                    <label>
                        活動名<span class="required">*</span>
                    </label>
                    <input type="text" name="activity_name" id="activityName" value="<?php echo htmlspecialchars($plan['activity_name'] ?? ''); ?>" required>
                    <div class="help-text">例: 公園での自然観察、クッキング活動、グループワーク</div>
                </div>

                <div class="form-group">
                    <label>種別<span class="required">*</span></label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php
                        $selectedType = $plan['plan_type'] ?? 'normal';
                        foreach ($planTypes as $value => $label):
                            $checked = ($selectedType === $value) ? 'checked' : '';
                            $typeColors = [
                                'normal' => '#007aff',
                                'event' => '#ff9500',
                                'other' => '#8e8e93'
                            ];
                            $color = $typeColors[$value];
                        ?>
                            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px 20px; border: 2px solid <?php echo $color; ?>; border-radius: 8px; background: <?php echo $checked ? $color : 'white'; ?>; color: <?php echo $checked ? 'white' : $color; ?>; font-weight: 600; transition: all 0.2s;">
                                <input type="radio" name="plan_type" value="<?php echo $value; ?>" <?php echo $checked; ?> style="display: none;" onchange="updateTypeStyle(this)">
                                <?php echo htmlspecialchars($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text">通常活動: 日常の活動、イベント: 特別なイベント、その他: 上記以外</div>
                </div>

                <div class="form-group">
                    <label>対象年齢層</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php
                        $selectedGrades = isset($plan['target_grade']) ? explode(',', $plan['target_grade']) : [];
                        $gradeColors = [
                            'preschool' => '#ff69b4',
                            'elementary' => '#34c759',
                            'junior_high' => '#007aff',
                            'high_school' => '#ff9500'
                        ];
                        foreach ($targetGrades as $value => $label):
                            $checked = in_array($value, $selectedGrades);
                            $color = $gradeColors[$value];
                        ?>
                            <label class="grade-checkbox" style="display: flex; align-items: center; cursor: pointer; padding: 10px 20px; border: 2px solid <?php echo $color; ?>; border-radius: 8px; background: <?php echo $checked ? $color : 'white'; ?>; color: <?php echo $checked ? 'white' : $color; ?>; font-weight: 600; transition: all 0.2s;">
                                <input type="checkbox" name="target_grade[]" value="<?php echo $value; ?>" <?php echo $checked ? 'checked' : ''; ?> style="display: none;" onchange="updateGradeStyle(this, '<?php echo $color; ?>')">
                                <?php echo htmlspecialchars($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text">この活動の対象となる年齢層を選択してください（複数選択可、未選択の場合は全年齢対象）</div>
                </div>

                <div class="form-group">
                    <label>活動の目的</label>
                    <textarea name="activity_purpose" id="activityPurpose"><?php echo htmlspecialchars($plan['activity_purpose'] ?? ''); ?></textarea>
                    <div class="help-text">この活動を通して達成したい目標や狙いを記載してください</div>
                </div>

                <div class="form-group">
                    <label>活動の内容</label>
                    <textarea name="activity_content" id="activityContent"><?php echo htmlspecialchars($plan['activity_content'] ?? ''); ?></textarea>
                    <div class="help-text">具体的な活動の流れや内容を記載してください</div>
                </div>

                <div class="form-group">
                    <label>タグ</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 5px;">
                        <?php
                        $selectedTags = isset($plan['tags']) ? explode(',', $plan['tags']) : [];
                        foreach ($availableTags as $tag):
                        ?>
                            <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                                <input type="checkbox" name="tags[]" value="<?php echo htmlspecialchars($tag); ?>"
                                    <?php echo in_array($tag, $selectedTags) ? 'checked' : ''; ?>
                                    style="margin-right: 8px;">
                                <?php echo htmlspecialchars($tag); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text">活動に関連するタグを選択してください（複数選択可）</div>
                </div>

                <div class="form-group">
                    <label>実施曜日</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 5px;">
                        <?php
                        $daysOfWeek = [
                            'monday' => '月曜日',
                            'tuesday' => '火曜日',
                            'wednesday' => '水曜日',
                            'thursday' => '木曜日',
                            'friday' => '金曜日',
                            'saturday' => '土曜日',
                            'sunday' => '日曜日'
                        ];
                        $selectedDays = isset($plan['day_of_week']) ? explode(',', $plan['day_of_week']) : [];
                        foreach ($daysOfWeek as $value => $label):
                        ?>
                            <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                                <input type="checkbox" name="day_of_week[]" value="<?php echo $value; ?>"
                                    <?php echo in_array($value, $selectedDays) ? 'checked' : ''; ?>
                                    style="margin-right: 8px;">
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text">この支援案を実施する曜日を選択してください（複数選択可）</div>
                </div>

                <div class="form-group">
                    <label>五領域への配慮</label>
                    <textarea name="five_domains_consideration" id="fiveDomains"><?php echo htmlspecialchars($plan['five_domains_consideration'] ?? ''); ?></textarea>
                    <div class="help-text">健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性の五領域への配慮を記載してください</div>
                </div>

                <div class="form-group">
                    <label>その他</label>
                    <textarea name="other_notes" id="otherNotes"><?php echo htmlspecialchars($plan['other_notes'] ?? ''); ?></textarea>
                    <div class="help-text">特記事項や注意点などがあれば記載してください</div>
                </div>

                <div class="button-group">
                    <a href="support_plans.php" class="cancel-btn">キャンセル</a>
                    <button type="submit" class="submit-btn">
                        <?php echo $isEdit ? '更新する' : '作成する'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // 種別選択のスタイル更新
    function updateTypeStyle(radio) {
        const typeColors = {
            'normal': '#007aff',
            'event': '#ff9500',
            'other': '#8e8e93'
        };

        // すべてのラベルをリセット
        document.querySelectorAll('input[name="plan_type"]').forEach(input => {
            const label = input.closest('label');
            const color = typeColors[input.value];
            if (input.checked) {
                label.style.background = color;
                label.style.color = 'white';
            } else {
                label.style.background = 'white';
                label.style.color = color;
            }
        });
    }

    // 対象年齢層のスタイル更新
    function updateGradeStyle(checkbox, color) {
        const label = checkbox.closest('label');
        if (checkbox.checked) {
            label.style.background = color;
            label.style.color = 'white';
        } else {
            label.style.background = 'white';
            label.style.color = color;
        }
    }
    </script>

    <?php if (!$isEdit): ?>
    <script>
    // 過去の支援案を引用する機能
    const copyFromPastBtn = document.getElementById('copyFromPastBtn');
    const copyModal = document.getElementById('copyModal');
    const pastPlansContainer = document.getElementById('pastPlansContainer');
    const searchPlan = document.getElementById('searchPlan');
    const viewByDateTab = document.getElementById('viewByDateTab');
    const viewByListTab = document.getElementById('viewByListTab');
    const periodBtns = document.querySelectorAll('.period-btn');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const applyDateRange = document.getElementById('applyDateRange');
    const clearDateRange = document.getElementById('clearDateRange');

    let allPlans = [];
    let currentView = 'date'; // 'date' or 'list'
    let currentPeriod = '30'; // デフォルトは1ヶ月
    let currentStartDate = null;
    let currentEndDate = null;

    // 期間ボタンのイベントリスナー
    periodBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            currentPeriod = this.dataset.period;
            currentStartDate = null;
            currentEndDate = null;

            // ボタンのアクティブ状態を切り替え
            periodBtns.forEach(b => {
                b.style.background = 'white';
                b.style.color = '#667eea';
                b.classList.remove('active');
            });
            this.style.background = '#667eea';
            this.style.color = 'white';
            this.classList.add('active');

            // 日付フィールドをクリア
            startDate.value = '';
            endDate.value = '';

            // 支援案を再取得
            await loadPlans();
        });
    });

    // 日付範囲適用ボタン
    applyDateRange.addEventListener('click', async function() {
        if (!startDate.value || !endDate.value) {
            alert('開始日と終了日を両方入力してください');
            return;
        }

        if (startDate.value > endDate.value) {
            alert('開始日は終了日より前の日付を指定してください');
            return;
        }

        currentStartDate = startDate.value;
        currentEndDate = endDate.value;

        // 期間ボタンを非アクティブに
        periodBtns.forEach(b => {
            b.style.background = 'white';
            b.style.color = '#667eea';
            b.classList.remove('active');
        });

        await loadPlans();
    });

    // 日付範囲クリアボタン
    clearDateRange.addEventListener('click', function() {
        startDate.value = '';
        endDate.value = '';
        currentStartDate = null;
        currentEndDate = null;

        // デフォルトの1ヶ月に戻す
        currentPeriod = '30';
        periodBtns.forEach(b => {
            if (b.dataset.period === '30') {
                b.style.background = '#667eea';
                b.style.color = 'white';
                b.classList.add('active');
            } else {
                b.style.background = 'white';
                b.style.color = '#667eea';
                b.classList.remove('active');
            }
        });

        loadPlans();
    });

    // 支援案を取得する関数
    async function loadPlans() {
        try {
            let url = 'get_past_support_plans.php';

            if (currentStartDate && currentEndDate) {
                // 日付範囲が指定されている場合
                url += `?start_date=${currentStartDate}&end_date=${currentEndDate}`;
            } else {
                // 期間ボタンが選択されている場合
                url += `?period=${currentPeriod}`;
            }

            const response = await fetch(url);
            allPlans = await response.json();

            if (allPlans.length === 0) {
                pastPlansContainer.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">過去の支援案がありません</p>';
            } else {
                renderPlans(allPlans);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('過去の支援案の取得に失敗しました');
        }
    }

    copyFromPastBtn.addEventListener('click', async function() {
        // 過去の支援案を取得
        await loadPlans();
        copyModal.style.display = 'flex';
    });

    // 検索機能
    searchPlan.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const filteredPlans = allPlans.filter(plan =>
            plan.activity_name.toLowerCase().includes(searchTerm) ||
            (plan.activity_purpose && plan.activity_purpose.toLowerCase().includes(searchTerm)) ||
            (plan.activity_content && plan.activity_content.toLowerCase().includes(searchTerm))
        );
        renderPlans(filteredPlans);
    });

    // タブ切り替え
    viewByDateTab.addEventListener('click', function() {
        currentView = 'date';
        viewByDateTab.style.background = '#667eea';
        viewByDateTab.style.color = 'white';
        viewByListTab.style.background = '#e9ecef';
        viewByListTab.style.color = '#333';
        renderPlans(getCurrentFilteredPlans());
    });

    viewByListTab.addEventListener('click', function() {
        currentView = 'list';
        viewByListTab.style.background = '#667eea';
        viewByListTab.style.color = 'white';
        viewByDateTab.style.background = '#e9ecef';
        viewByDateTab.style.color = '#333';
        renderPlans(getCurrentFilteredPlans());
    });

    function getCurrentFilteredPlans() {
        const searchTerm = searchPlan.value.toLowerCase();
        if (!searchTerm) return allPlans;

        return allPlans.filter(plan =>
            plan.activity_name.toLowerCase().includes(searchTerm) ||
            (plan.activity_purpose && plan.activity_purpose.toLowerCase().includes(searchTerm)) ||
            (plan.activity_content && plan.activity_content.toLowerCase().includes(searchTerm))
        );
    }

    function renderPlans(plans) {
        if (plans.length === 0) {
            pastPlansContainer.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">該当する支援案がありません</p>';
            return;
        }

        if (currentView === 'date') {
            renderByDate(plans);
        } else {
            renderByList(plans);
        }
    }

    function renderByDate(plans) {
        // 日付ごとにグループ化
        const plansByDate = {};
        plans.forEach(plan => {
            if (!plansByDate[plan.activity_date]) {
                plansByDate[plan.activity_date] = [];
            }
            plansByDate[plan.activity_date].push(plan);
        });

        // 日付順にソート（新しい順）
        const sortedDates = Object.keys(plansByDate).sort((a, b) => b.localeCompare(a));

        let html = '';
        sortedDates.forEach(date => {
            const dateStr = formatDate(date);

            html += `<div style="margin-bottom: var(--spacing-2xl);">`;
            html += `<h3 style="color: var(--primary-purple); border-bottom: 2px solid var(--primary-purple); padding-bottom: 5px; margin-bottom: 15px;">${dateStr}</h3>`;

            plansByDate[date].forEach(plan => {
                html += renderPlanCard(plan);
            });

            html += `</div>`;
        });

        pastPlansContainer.innerHTML = html;
    }

    function renderByList(plans) {
        let html = '<div style="margin-bottom: 15px; color: var(--text-secondary); font-size: var(--text-subhead);">全 ' + plans.length + ' 件の支援案</div>';
        plans.forEach(plan => {
            html += renderPlanCard(plan, true);
        });
        pastPlansContainer.innerHTML = html;
    }

    function formatDate(dateStr) {
        // YYYY-MM-DD形式の文字列を解析
        const parts = dateStr.split('-');
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const day = parseInt(parts[2], 10);
        return year + '年' + month + '月' + day + '日';
    }

    function renderPlanCard(plan, showDate = false) {
        const dateStr = formatDate(plan.activity_date);

        return `
            <div style="border: 1px solid var(--apple-gray-5); border-radius: var(--radius-sm); padding: 15px; margin-bottom: 15px; background: var(--apple-gray-6);">
                <div style="margin-bottom: var(--spacing-md);">
                    <strong style="font-size: var(--text-callout);">${escapeHtml(plan.activity_name)}</strong>
                    ${showDate ? `<span style="color: var(--primary-purple); font-size: var(--text-subhead); margin-left: 10px;">${dateStr}</span>` : ''}
                </div>
                ${plan.activity_purpose ? `<div style="margin-bottom: 8px; font-size: var(--text-subhead);"><strong>目的:</strong> ${escapeHtml(plan.activity_purpose).substring(0, 100)}${plan.activity_purpose.length > 100 ? '...' : ''}</div>` : ''}
                ${plan.activity_content ? `<div style="margin-bottom: 8px; font-size: var(--text-subhead);"><strong>内容:</strong> ${escapeHtml(plan.activity_content).substring(0, 100)}${plan.activity_content.length > 100 ? '...' : ''}</div>` : ''}
                <button type="button" class="submit-btn" style="padding: var(--spacing-sm) 16px; font-size: var(--text-subhead); margin-top: 10px;" onclick="copyPlan(${plan.id})">
                    この支援案を引用
                </button>
            </div>
        `;
    }

    // HTMLエスケープ
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 支援案をコピー
    async function copyPlan(planId) {
        try {
            const response = await fetch('get_support_plan.php?id=' + planId);
            const plan = await response.json();

            if (plan) {
                document.getElementById('activityName').value = plan.activity_name;
                document.getElementById('activityPurpose').value = plan.activity_purpose || '';
                document.getElementById('activityContent').value = plan.activity_content || '';
                document.getElementById('fiveDomains').value = plan.five_domains_consideration || '';
                document.getElementById('otherNotes').value = plan.other_notes || '';

                copyModal.style.display = 'none';
                alert('支援案の内容を引用しました。活動予定日を設定して保存してください。');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('支援案の引用に失敗しました');
        }
    }

    // モーダルの外側をクリックしたら閉じる
    copyModal.addEventListener('click', function(e) {
        if (e.target === copyModal) {
            copyModal.style.display = 'none';
        }
    });
    </script>
    <?php endif; ?>

<?php renderPageEnd(); ?>