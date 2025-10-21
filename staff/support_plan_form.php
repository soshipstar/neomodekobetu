<?php
/**
 * 支援案作成・編集フォーム
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

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

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityDate = $_POST['activity_date'] ?? '';
    $activityName = $_POST['activity_name'] ?? '';
    $activityPurpose = $_POST['activity_purpose'] ?? '';
    $activityContent = $_POST['activity_content'] ?? '';
    $fiveDomainsConsideration = $_POST['five_domains_consideration'] ?? '';
    $otherNotes = $_POST['other_notes'] ?? '';

    try {
        if ($isEdit) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE support_plans
                SET activity_date = ?,
                    activity_name = ?,
                    activity_purpose = ?,
                    activity_content = ?,
                    five_domains_consideration = ?,
                    other_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $activityDate,
                $activityName,
                $activityPurpose,
                $activityContent,
                $fiveDomainsConsideration,
                $otherNotes,
                $planId
            ]);
            $_SESSION['success'] = '支援案を更新しました';
        } else {
            // 新規作成
            $stmt = $pdo->prepare("
                INSERT INTO support_plans (
                    activity_date, activity_name, activity_purpose, activity_content,
                    five_domains_consideration, other_notes,
                    staff_id, classroom_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $activityDate,
                $activityName,
                $activityPurpose,
                $activityContent,
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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? '支援案編集' : '支援案作成'; ?> - 個別支援連絡帳システム</title>
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
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .back-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 4px;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .submit-btn {
            flex: 1;
            padding: 15px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #218838;
        }

        .cancel-btn {
            flex: 1;
            padding: 15px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .cancel-btn:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
            margin-bottom: 25px;
            font-size: 14px;
            color: #333;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 <?php echo $isEdit ? '支援案編集' : '支援案作成'; ?></h1>
            <a href="support_plans.php" class="back-btn">← 支援案一覧へ</a>
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
                💡 支援案は活動日専用の事前計画です。連絡帳作成時に、その日の支援案が自動的に利用可能になります。
            </div>

            <?php if (!$isEdit): ?>
                <div style="margin-bottom: 20px; text-align: center;">
                    <button type="button" id="copyFromPastBtn" class="cancel-btn" style="background: #667eea; color: white;">
                        📋 過去の支援案を引用する
                    </button>
                </div>

                <!-- 過去の支援案選択モーダル -->
                <div id="copyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
                    <div style="background: white; max-width: 900px; margin: 50px auto; border-radius: 10px; padding: 30px;">
                        <h2 style="margin-bottom: 20px;">過去の支援案を選択</h2>

                        <!-- 検索ボックス -->
                        <div style="margin-bottom: 20px;">
                            <input type="text" id="searchPlan" placeholder="🔍 活動名で検索..." style="width: 100%; padding: 12px; border: 2px solid #667eea; border-radius: 5px; font-size: 14px;">
                            <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                活動名を入力すると、リアルタイムで絞り込まれます
                            </div>
                        </div>

                        <!-- 期間選択 -->
                        <div style="margin-bottom: 20px;">
                            <div style="font-size: 14px; color: #666; margin-bottom: 8px; font-weight: 600;">📆 表示期間</div>

                            <!-- クイック選択ボタン -->
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                                <button type="button" class="period-btn" data-period="7" style="padding: 8px 16px; border: 2px solid #667eea; background: white; color: #667eea; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    1週間
                                </button>
                                <button type="button" class="period-btn active" data-period="30" style="padding: 8px 16px; border: 2px solid #667eea; background: #667eea; color: white; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    1ヶ月
                                </button>
                                <button type="button" class="period-btn" data-period="90" style="padding: 8px 16px; border: 2px solid #667eea; background: white; color: #667eea; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    3ヶ月
                                </button>
                                <button type="button" class="period-btn" data-period="all" style="padding: 8px 16px; border: 2px solid #667eea; background: white; color: #667eea; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                    すべて
                                </button>
                            </div>

                            <!-- 日付範囲指定 -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 2px solid #e9ecef;">
                                <div style="font-size: 13px; color: #666; margin-bottom: 10px; font-weight: 600;">期間を指定</div>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <input type="date" id="startDate" style="padding: 8px; border: 2px solid #667eea; border-radius: 5px; font-size: 14px;">
                                    <span style="color: #666; font-weight: 600;">～</span>
                                    <input type="date" id="endDate" style="padding: 8px; border: 2px solid #667eea; border-radius: 5px; font-size: 14px;">
                                    <button type="button" id="applyDateRange" style="padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                        適用
                                    </button>
                                    <button type="button" id="clearDateRange" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                        クリア
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 表示切替タブ -->
                        <div style="margin-bottom: 20px; border-bottom: 2px solid #eee;">
                            <button type="button" id="viewByDateTab" class="cancel-btn" style="padding: 10px 20px; border-radius: 5px 5px 0 0; background: #667eea; color: white; margin-right: 5px; border: none;">
                                📅 日付順
                            </button>
                            <button type="button" id="viewByListTab" class="cancel-btn" style="padding: 10px 20px; border-radius: 5px 5px 0 0; background: #e9ecef; color: #333; border: none;">
                                📋 一覧
                            </button>
                        </div>

                        <div id="pastPlansContainer"></div>
                        <button type="button" onclick="document.getElementById('copyModal').style.display='none'; document.getElementById('searchPlan').value='';" class="cancel-btn" style="margin-top: 20px;">閉じる</button>
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
                    <label>活動の目的</label>
                    <textarea name="activity_purpose" id="activityPurpose"><?php echo htmlspecialchars($plan['activity_purpose'] ?? ''); ?></textarea>
                    <div class="help-text">この活動を通して達成したい目標や狙いを記入してください</div>
                </div>

                <div class="form-group">
                    <label>活動の内容</label>
                    <textarea name="activity_content" id="activityContent"><?php echo htmlspecialchars($plan['activity_content'] ?? ''); ?></textarea>
                    <div class="help-text">具体的な活動の流れや内容を記入してください</div>
                </div>

                <div class="form-group">
                    <label>五領域への配慮</label>
                    <textarea name="five_domains_consideration" id="fiveDomains"><?php echo htmlspecialchars($plan['five_domains_consideration'] ?? ''); ?></textarea>
                    <div class="help-text">健康・生活、運動・感覚、認知・行動、言語・コミュニケーション、人間関係・社会性の各領域への配慮を記入してください</div>
                </div>

                <div class="form-group">
                    <label>その他</label>
                    <textarea name="other_notes" id="otherNotes"><?php echo htmlspecialchars($plan['other_notes'] ?? ''); ?></textarea>
                    <div class="help-text">特記事項や注意点などがあれば記入してください</div>
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
                pastPlansContainer.innerHTML = '<p style="text-align: center; color: #999;">過去の支援案がありません</p>';
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
        copyModal.style.display = 'block';
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
            pastPlansContainer.innerHTML = '<p style="text-align: center; color: #999;">該当する支援案がありません</p>';
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

            html += `<div style="margin-bottom: 30px;">`;
            html += `<h3 style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px; margin-bottom: 15px;">${dateStr}</h3>`;

            plansByDate[date].forEach(plan => {
                html += renderPlanCard(plan);
            });

            html += `</div>`;
        });

        pastPlansContainer.innerHTML = html;
    }

    function renderByList(plans) {
        let html = '<div style="margin-bottom: 15px; color: #666; font-size: 14px;">全 ' + plans.length + ' 件の支援案</div>';
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
            <div style="border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; background: #f8f9fa;">
                <div style="margin-bottom: 10px;">
                    <strong style="font-size: 16px;">${escapeHtml(plan.activity_name)}</strong>
                    ${showDate ? `<span style="color: #667eea; font-size: 14px; margin-left: 10px;">📅 ${dateStr}</span>` : ''}
                </div>
                ${plan.activity_purpose ? `<div style="margin-bottom: 8px; font-size: 14px;"><strong>目的:</strong> ${escapeHtml(plan.activity_purpose).substring(0, 100)}${plan.activity_purpose.length > 100 ? '...' : ''}</div>` : ''}
                ${plan.activity_content ? `<div style="margin-bottom: 8px; font-size: 14px;"><strong>内容:</strong> ${escapeHtml(plan.activity_content).substring(0, 100)}${plan.activity_content.length > 100 ? '...' : ''}</div>` : ''}
                <button type="button" class="submit-btn" style="padding: 8px 16px; font-size: 14px; margin-top: 10px;" onclick="copyPlan(${plan.id})">
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
</body>
</html>
