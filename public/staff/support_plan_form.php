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

// タグの定義（データベースから取得、なければデフォルト）
$defaultTags = [
    'プログラミング', 'テキスタイル', 'CAD', '動画', 'イラスト',
    '企業支援', '農業', '音楽', '食', '学習',
    '自分取扱説明書', '心理', '言語', '教育', 'イベント', 'その他'
];

$availableTags = $defaultTags;
if ($classroomId) {
    try {
        $stmt = $pdo->prepare("
            SELECT tag_name FROM classroom_tags
            WHERE classroom_id = ? AND is_active = 1
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$classroomId]);
        $dbTags = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dbTags)) {
            $availableTags = $dbTags;
        }
    } catch (PDOException $e) {
        // テーブルがない場合はデフォルトを使用
    }
}

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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .routine-btn {
            display: inline-block;
            padding: var(--spacing-sm) 16px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-size: var(--text-footnote);
            font-weight: 600;
            cursor: pointer;
            margin: 4px;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .routine-btn:hover {
            background: #f57c00;
            transform: translateY(-1px);
        }

        .routine-btn.added {
            background: var(--apple-green);
        }

        .no-routines {
            color: var(--text-secondary);
            font-size: var(--text-footnote);
            font-style: italic;
        }

        /* スケジュール管理スタイル */
        .schedule-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: var(--apple-bg-primary);
            border: 1px solid var(--apple-gray-5);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .schedule-item:hover {
            border-color: var(--primary-purple);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .schedule-item.routine {
            border-left: 4px solid #ff9800;
        }

        .schedule-item.main-activity {
            border-left: 4px solid var(--apple-blue);
        }

        .schedule-order {
            width: 28px;
            height: 28px;
            background: var(--primary-purple);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: var(--text-footnote);
            flex-shrink: 0;
        }

        .schedule-item.routine .schedule-order {
            background: #ff9800;
        }

        .schedule-item.main-activity .schedule-order {
            background: var(--apple-blue);
        }

        .schedule-info {
            flex: 1;
            min-width: 0;
        }

        .schedule-name {
            font-weight: 500;
        }

        .schedule-content-preview {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .schedule-type {
            font-size: var(--text-caption-1);
            color: var(--text-secondary);
            background: var(--apple-gray-6);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .schedule-duration {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .schedule-duration input {
            width: 60px;
            padding: 4px 8px;
            border: 1px solid var(--apple-gray-5);
            border-radius: 4px;
            text-align: center;
        }

        .schedule-actions {
            display: flex;
            gap: 4px;
        }

        .schedule-actions button {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: var(--text-caption-1);
        }

        .move-up-btn, .move-down-btn {
            background: var(--apple-gray-5);
            color: var(--text-primary);
        }

        .remove-btn {
            background: var(--apple-red);
            color: white;
        }

        .routine-selector-btn {
            padding: 8px 16px;
            background: #fff3e0;
            border: 2px solid #ff9800;
            border-radius: var(--radius-sm);
            color: #e65100;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .routine-selector-btn:hover {
            background: #ff9800;
            color: white;
        }

        .routine-selector-btn.added {
            background: #ff9800;
            color: white;
            opacity: 0.6;
        }

        .time-warning {
            color: var(--apple-red);
            font-weight: 600;
        }

        .time-ok {
            color: var(--apple-green);
            font-weight: 600;
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

                <!-- 総活動時間 -->
                <div class="form-group">
                    <label>総活動時間<span class="required">*</span></label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="number" name="total_duration" id="totalDuration"
                               value="<?php echo htmlspecialchars($plan['total_duration'] ?? '180'); ?>"
                               min="30" max="480" style="width: 100px;" required>
                        <span style="font-weight: 600;">分</span>
                        <span style="color: var(--text-secondary); font-size: var(--text-footnote); margin-left: 10px;">
                            （残り時間: <span id="remainingTime">-</span>分）
                        </span>
                    </div>
                    <div class="help-text">活動全体の所要時間を入力してください（30〜480分）</div>
                </div>

                <div class="form-group">
                    <label>活動の目的</label>
                    <textarea name="activity_purpose" id="activityPurpose"><?php echo htmlspecialchars($plan['activity_purpose'] ?? ''); ?></textarea>
                    <div class="help-text">この活動を通して達成したい目標や狙いを記載してください</div>
                </div>

                <!-- 活動スケジュール設定 -->
                <div class="form-group" style="background: #f5f5f5; padding: 20px; border-radius: var(--radius-md); border: 2px solid var(--apple-gray-5);">
                    <label style="color: var(--text-primary); margin-bottom: 15px; font-size: var(--text-callout);">活動スケジュール</label>
                    <p style="font-size: var(--text-footnote); color: var(--text-secondary); margin-bottom: 15px;">
                        毎日の支援と主活動を追加し、順番と所要時間を設定してください。<br>
                        <a href="daily_routines_settings.php" style="color: var(--primary-purple);">毎日の支援を設定する</a>
                    </p>

                    <!-- 毎日の支援選択 -->
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: var(--text-footnote); color: var(--text-secondary); margin-bottom: 8px; display: block;">毎日の支援を追加</label>
                        <div id="dailyRoutinesSelector" style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <p style="color: var(--text-secondary); font-size: var(--text-footnote);">読み込み中...</p>
                        </div>
                    </div>

                    <!-- 主活動追加 -->
                    <div style="margin-bottom: 15px; background: #e3f2fd; padding: 15px; border-radius: var(--radius-sm);">
                        <label style="font-size: var(--text-footnote); color: var(--apple-blue); font-weight: 600; margin-bottom: 10px; display: block;">主活動を追加</label>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                            <input type="text" id="newMainActivity" placeholder="主活動名を入力" style="flex: 1; min-width: 200px;">
                            <input type="number" id="newMainActivityDuration" placeholder="時間" min="5" max="240" style="width: 80px;">
                            <span style="align-self: center;">分</span>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <textarea id="newMainActivityContent" placeholder="主活動の内容を入力（この内容がAI生成時に参照されます）" style="width: 100%; min-height: 80px; resize: vertical;"></textarea>
                        </div>
                        <button type="button" id="addMainActivityBtn" class="cancel-btn" style="background: var(--apple-blue); color: white; padding: 8px 16px;">主活動を追加</button>
                    </div>

                    <!-- スケジュールリスト -->
                    <div id="scheduleList" style="background: white; border-radius: var(--radius-sm); padding: 10px; min-height: 100px; border: 1px dashed var(--apple-gray-4);">
                        <p id="scheduleEmpty" style="color: var(--text-secondary); font-size: var(--text-footnote); text-align: center; padding: 20px;">
                            活動を追加してください
                        </p>
                    </div>

                    <!-- 隠しフィールド（スケジュールデータ保存用） -->
                    <input type="hidden" name="activity_schedule" id="activityScheduleData">
                </div>

                <!-- AI詳細生成ボタン -->
                <div class="form-group" style="background: #e8f5e9; padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid #4caf50;">
                    <label style="color: #2e7d32; margin-bottom: 10px;">AIで活動内容を生成</label>
                    <p style="font-size: var(--text-footnote); color: var(--text-secondary); margin-bottom: 10px;">
                        活動名、目的、スケジュールを設定後、AIが時間配分を含めた詳細な活動内容を自動生成します。
                    </p>
                    <button type="button" id="generateDetailBtn" class="cancel-btn" style="background: #4caf50; color: white; width: auto;">
                        スケジュールをもとに活動内容を生成
                    </button>
                    <div id="detailGenerating" style="display: none; margin-top: 10px; color: #2e7d32;">
                        <span class="spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #4caf50; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px; vertical-align: middle;"></span>
                        生成中...（しばらくお待ちください）
                    </div>
                </div>

                <div class="form-group">
                    <label>活動の内容</label>
                    <textarea name="activity_content" id="activityContent" style="min-height: 300px;"><?php echo htmlspecialchars($plan['activity_content'] ?? ''); ?></textarea>
                    <div class="help-text">具体的な活動の流れや内容を記載してください（AIで自動生成可能）</div>
                </div>

                <!-- AI生成ボタン -->
                <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: var(--radius-sm); border-left: 4px solid #2196F3;">
                    <label style="color: #1565c0; margin-bottom: 10px;">AIで詳細を生成</label>
                    <p style="font-size: var(--text-footnote); color: var(--text-secondary); margin-bottom: 10px;">
                        活動名、目的、内容を入力後、AIが「五領域への配慮」と「その他」を自動生成します。
                    </p>
                    <button type="button" id="generateAiBtn" class="cancel-btn" style="background: #1976D2; color: white; width: auto;">
                        AIで五領域への配慮を生成
                    </button>
                    <div id="aiGenerating" style="display: none; margin-top: 10px; color: #1565c0;">
                        <span class="spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #1976D2; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px; vertical-align: middle;"></span>
                        生成中...
                    </div>
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
                    <textarea name="five_domains_consideration" id="fiveDomains" style="min-height: 300px;"><?php echo htmlspecialchars($plan['five_domains_consideration'] ?? ''); ?></textarea>
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

    <script>
    // スケジュール管理
    let scheduleItems = [];
    let availableRoutines = [];

    // 毎日の支援を読み込む（選択用）
    async function loadDailyRoutinesForSelector() {
        const container = document.getElementById('dailyRoutinesSelector');

        try {
            const response = await fetch('get_daily_routines.php');
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                availableRoutines = result.data;
                renderRoutineSelector();
            } else {
                container.innerHTML = '<p class="no-routines">毎日の支援が登録されていません。<a href="daily_routines_settings.php" style="color: var(--primary-purple);">設定する</a></p>';
            }
        } catch (error) {
            console.error('Error loading daily routines:', error);
            container.innerHTML = '<p class="no-routines">読み込みに失敗しました</p>';
        }
    }

    // ルーティーン選択ボタンを描画
    function renderRoutineSelector() {
        const container = document.getElementById('dailyRoutinesSelector');
        let html = '';

        availableRoutines.forEach(routine => {
            const isAdded = scheduleItems.some(item => item.type === 'routine' && item.routineId === routine.id);
            const timeStr = routine.scheduled_time ? ` (${routine.scheduled_time}分)` : '';
            html += `<button type="button" class="routine-selector-btn ${isAdded ? 'added' : ''}"
                        data-id="${routine.id}"
                        data-name="${escapeAttr(routine.routine_name)}"
                        data-content="${escapeAttr(routine.routine_content || '')}"
                        data-duration="${routine.scheduled_time || 15}"
                        onclick="addRoutineToSchedule(this)"
                        ${isAdded ? 'disabled' : ''}>
                ${escapeHtml(routine.routine_name)}${timeStr}
            </button>`;
        });

        container.innerHTML = html || '<p class="no-routines">毎日の支援が登録されていません</p>';
    }

    // ルーティーンをスケジュールに追加
    function addRoutineToSchedule(btn) {
        const id = parseInt(btn.dataset.id);
        const name = btn.dataset.name;
        const content = btn.dataset.content;
        const duration = parseInt(btn.dataset.duration) || 15;

        scheduleItems.push({
            type: 'routine',
            routineId: id,
            name: name,
            content: content,
            duration: duration
        });

        renderScheduleList();
        renderRoutineSelector();
        updateRemainingTime();
    }

    // 主活動を追加
    document.getElementById('addMainActivityBtn').addEventListener('click', function() {
        const nameInput = document.getElementById('newMainActivity');
        const durationInput = document.getElementById('newMainActivityDuration');
        const contentInput = document.getElementById('newMainActivityContent');

        const name = nameInput.value.trim();
        const duration = parseInt(durationInput.value) || 30;
        const content = contentInput.value.trim();

        if (!name) {
            alert('主活動名を入力してください');
            return;
        }

        scheduleItems.push({
            type: 'main-activity',
            name: name,
            content: content,
            duration: duration
        });

        nameInput.value = '';
        durationInput.value = '';
        contentInput.value = '';

        renderScheduleList();
        updateRemainingTime();
    });

    // スケジュールリストを描画
    function renderScheduleList() {
        const container = document.getElementById('scheduleList');
        const emptyMsg = document.getElementById('scheduleEmpty');

        if (scheduleItems.length === 0) {
            container.innerHTML = '<p id="scheduleEmpty" style="color: var(--text-secondary); font-size: var(--text-footnote); text-align: center; padding: 20px;">活動を追加してください</p>';
            document.getElementById('activityScheduleData').value = '';
            return;
        }

        let html = '';
        scheduleItems.forEach((item, index) => {
            const typeLabel = item.type === 'routine' ? '毎日の支援' : '主活動';
            const contentPreview = item.content ? `<div class="schedule-content-preview">${escapeHtml(item.content.substring(0, 100))}${item.content.length > 100 ? '...' : ''}</div>` : '';
            html += `
                <div class="schedule-item ${item.type}" data-index="${index}">
                    <div class="schedule-order">${index + 1}</div>
                    <div class="schedule-info">
                        <div class="schedule-name">${escapeHtml(item.name)}</div>
                        ${contentPreview}
                    </div>
                    <span class="schedule-type">${typeLabel}</span>
                    <div class="schedule-duration">
                        <input type="number" value="${item.duration}" min="5" max="240"
                               onchange="updateItemDuration(${index}, this.value)">
                        <span>分</span>
                    </div>
                    <div class="schedule-actions">
                        <button type="button" class="move-up-btn" onclick="moveItem(${index}, -1)" ${index === 0 ? 'disabled' : ''}>↑</button>
                        <button type="button" class="move-down-btn" onclick="moveItem(${index}, 1)" ${index === scheduleItems.length - 1 ? 'disabled' : ''}>↓</button>
                        <button type="button" class="remove-btn" onclick="removeItem(${index})">×</button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // 隠しフィールドにデータを保存
        document.getElementById('activityScheduleData').value = JSON.stringify(scheduleItems);
    }

    // アイテムの時間を更新
    function updateItemDuration(index, value) {
        scheduleItems[index].duration = parseInt(value) || 15;
        updateRemainingTime();
        document.getElementById('activityScheduleData').value = JSON.stringify(scheduleItems);
    }

    // アイテムを移動
    function moveItem(index, direction) {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= scheduleItems.length) return;

        const temp = scheduleItems[index];
        scheduleItems[index] = scheduleItems[newIndex];
        scheduleItems[newIndex] = temp;

        renderScheduleList();
    }

    // アイテムを削除
    function removeItem(index) {
        const item = scheduleItems[index];
        scheduleItems.splice(index, 1);

        renderScheduleList();
        if (item.type === 'routine') {
            renderRoutineSelector();
        }
        updateRemainingTime();
    }

    // 残り時間を更新
    function updateRemainingTime() {
        const totalDuration = parseInt(document.getElementById('totalDuration').value) || 180;
        const usedTime = scheduleItems.reduce((sum, item) => sum + item.duration, 0);
        const remaining = totalDuration - usedTime;

        const remainingSpan = document.getElementById('remainingTime');
        remainingSpan.textContent = remaining;
        remainingSpan.className = remaining < 0 ? 'time-warning' : 'time-ok';
    }

    // 総活動時間変更時に残り時間を更新
    document.getElementById('totalDuration').addEventListener('change', updateRemainingTime);

    // AI詳細生成機能（スケジュールベース）
    document.getElementById('generateDetailBtn').addEventListener('click', async function() {
        const activityName = document.getElementById('activityName').value.trim();
        const activityPurpose = document.getElementById('activityPurpose').value.trim();
        const totalDuration = parseInt(document.getElementById('totalDuration').value) || 180;

        if (!activityName) {
            alert('活動名を入力してください');
            return;
        }

        if (scheduleItems.length === 0) {
            alert('活動スケジュールに少なくとも1つの活動を追加してください');
            return;
        }

        // 対象年齢層を取得
        const targetGrades = [];
        document.querySelectorAll('input[name="target_grade[]"]:checked').forEach(cb => {
            targetGrades.push(cb.value);
        });

        const btn = this;
        const loadingDiv = document.getElementById('detailGenerating');

        btn.disabled = true;
        loadingDiv.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('activity_name', activityName);
            formData.append('activity_purpose', activityPurpose);
            formData.append('total_duration', totalDuration);
            formData.append('schedule', JSON.stringify(scheduleItems));
            formData.append('target_grade', targetGrades.join(','));

            const response = await fetch('generate_support_plan_schedule_ai.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // 値を文字列に変換する関数（オブジェクトの場合は中身のテキストを抽出）
                function toStringValue(val) {
                    if (val === null || val === undefined) return '';
                    if (typeof val === 'string') {
                        // JSON文字列の場合はパースして中身を取得
                        if (val.trim().startsWith('{') || val.trim().startsWith('[')) {
                            try {
                                const parsed = JSON.parse(val);
                                if (typeof parsed === 'object' && parsed !== null) {
                                    // オブジェクトの最初の文字列値を取得
                                    for (const key of Object.keys(parsed)) {
                                        if (typeof parsed[key] === 'string') {
                                            return parsed[key];
                                        }
                                    }
                                }
                            } catch (e) {
                                // パース失敗時はそのまま返す
                            }
                        }
                        return val;
                    }
                    if (typeof val === 'object') {
                        // オブジェクトから文字列値を抽出
                        for (const key of Object.keys(val)) {
                            if (typeof val[key] === 'string') {
                                return val[key];
                            }
                        }
                        // 文字列値がなければ空文字
                        return '';
                    }
                    return String(val);
                }

                const contentValue = toStringValue(result.data.activity_content);
                const otherNotesValue = toStringValue(result.data.other_notes);

                // 活動の内容を設定
                const contentField = document.getElementById('activityContent');
                if (contentField.value.trim() === '') {
                    contentField.value = contentValue;
                } else {
                    if (confirm('既存の「活動の内容」を上書きしますか？')) {
                        contentField.value = contentValue;
                    }
                }

                // その他（配慮事項）を設定
                if (otherNotesValue) {
                    const otherNotesField = document.getElementById('otherNotes');
                    if (otherNotesField.value.trim() === '') {
                        otherNotesField.value = otherNotesValue;
                    } else {
                        if (confirm('既存の「その他」を上書きしますか？')) {
                            otherNotesField.value = otherNotesValue;
                        }
                    }
                }

                alert('AIによる活動内容の生成が完了しました');
            } else {
                alert('生成に失敗しました: ' + (result.error || '不明なエラー'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
        } finally {
            btn.disabled = false;
            loadingDiv.style.display = 'none';
        }
    });

    // AI生成機能（五領域への配慮）
    document.getElementById('generateAiBtn').addEventListener('click', async function() {
        const activityName = document.getElementById('activityName').value.trim();
        const activityPurpose = document.getElementById('activityPurpose').value.trim();
        const activityContent = document.getElementById('activityContent').value.trim();

        if (!activityName) {
            alert('活動名を入力してください');
            return;
        }

        const btn = this;
        const loadingDiv = document.getElementById('aiGenerating');

        btn.disabled = true;
        loadingDiv.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('activity_name', activityName);
            formData.append('activity_purpose', activityPurpose);
            formData.append('activity_content', activityContent);

            const response = await fetch('generate_support_plan_ai.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // 値を文字列に変換する関数（オブジェクトの場合は中身のテキストを抽出）
                function toStringValue(val, formatAsDomains = false) {
                    if (val === null || val === undefined) return '';
                    if (typeof val === 'string') {
                        // JSON文字列の場合はパースして処理
                        if (val.trim().startsWith('{') || val.trim().startsWith('[')) {
                            try {
                                const parsed = JSON.parse(val);
                                return toStringValue(parsed, formatAsDomains);
                            } catch (e) {
                                // パース失敗時はそのまま返す
                            }
                        }
                        return val;
                    }
                    if (typeof val === 'object') {
                        // 五領域のオブジェクトの場合は整形して出力
                        const domainKeys = ['健康・生活', '運動・感覚', '認知・行動', '言語・コミュニケーション', '人間関係・社会性'];
                        const keys = Object.keys(val);

                        // 五領域のキーが含まれているかチェック
                        const hasDomainKeys = domainKeys.some(dk => keys.includes(dk));

                        if (hasDomainKeys || formatAsDomains) {
                            // 五領域形式で整形
                            let result = [];
                            for (const key of keys) {
                                if (typeof val[key] === 'string' && val[key].trim()) {
                                    result.push(`【${key}】\n${val[key]}`);
                                }
                            }
                            return result.join('\n\n');
                        } else {
                            // 単一の文字列値を取得
                            for (const key of keys) {
                                if (typeof val[key] === 'string') {
                                    return val[key];
                                }
                            }
                        }
                        return '';
                    }
                    return String(val);
                }

                const fiveDomainsValue = toStringValue(result.data.five_domains_consideration, true);
                const otherNotesValue = toStringValue(result.data.other_notes);

                // 五領域への配慮を設定
                const fiveDomainsField = document.getElementById('fiveDomains');
                if (fiveDomainsField.value.trim() === '') {
                    fiveDomainsField.value = fiveDomainsValue;
                } else {
                    if (confirm('既存の「五領域への配慮」を上書きしますか？')) {
                        fiveDomainsField.value = fiveDomainsValue;
                    }
                }

                // その他を設定
                const otherNotesField = document.getElementById('otherNotes');
                if (otherNotesField.value.trim() === '') {
                    otherNotesField.value = otherNotesValue;
                } else {
                    if (confirm('既存の「その他」を上書きしますか？')) {
                        otherNotesField.value = otherNotesValue;
                    }
                }

                alert('AIによる生成が完了しました');
            } else {
                alert('生成に失敗しました: ' + (result.error || '不明なエラー'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('通信エラーが発生しました');
        } finally {
            btn.disabled = false;
            loadingDiv.style.display = 'none';
        }
    });

    // ページ読み込み時の初期化
    document.addEventListener('DOMContentLoaded', function() {
        loadDailyRoutinesForSelector();
        updateRemainingTime();

        // Enterキーによるフォーム送信を防止（テキストエリア以外）
        const form = document.getElementById('mainForm');
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                return false;
            }
        });
    });

    // HTMLエスケープ
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 属性用エスケープ
    function escapeAttr(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;')
                   .replace(/"/g, '&quot;')
                   .replace(/'/g, '&#39;')
                   .replace(/</g, '&lt;')
                   .replace(/>/g, '&gt;');
    }
    </script>

<?php renderPageEnd(); ?>