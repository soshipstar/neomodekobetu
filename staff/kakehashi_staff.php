<?php
/**
 * スタッフ用かけはし入力ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kakehashi_auto_generator.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$staffId = $_SESSION['user_id'];

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

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
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_periods
        WHERE student_id = ? AND is_active = 1
        ORDER BY submission_deadline DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $activePeriods = $stmt->fetchAll();

    // かけはし期間が存在しない場合は自動生成
    if (empty($activePeriods)) {
        $stmt = $pdo->prepare("SELECT student_name, support_start_date FROM students WHERE id = ?");
        $stmt->execute([$selectedStudentId]);
        $student = $stmt->fetch();

        if ($student && $student['support_start_date']) {
            try {
                $generatedPeriods = generateKakehashiPeriodsForStudent($pdo, $selectedStudentId, $student['support_start_date']);
                error_log("Auto-generated " . count($generatedPeriods) . " kakehashi periods for student {$selectedStudentId}");

                // 再度期間を取得
                $stmt = $pdo->prepare("
                    SELECT * FROM kakehashi_periods
                    WHERE student_id = ? AND is_active = 1
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
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフかけはし入力 - スタッフページ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 30px;
        }

        .selection-area {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.8;
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
        }

        .period-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .period-info p {
            margin: 5px 0;
        }

        .student-info {
            background: #f3e5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .domains-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-save {
            background: #28a745;
            color: white;
        }

        .btn-save:hover {
            background: #218838;
        }

        .btn-submit {
            background: #007bff;
            color: white;
        }

        .btn-submit:hover {
            background: #0056b3;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-draft {
            background: #ffc107;
            color: #856404;
        }

        .status-submitted {
            background: #28a745;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .btn-generate {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(245, 87, 108, 0.4);
        }

        .generate-info {
            background: #f3e5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            border-left: 4px solid #9c27b0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌉 スタッフかけはし入力</h1>
            <div class="nav-links">
                <a href="kakehashi_guardian_view.php">📋 保護者入力確認</a>
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-warning">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (empty($students)): ?>
                <div class="alert alert-info">
                    生徒情報が登録されていません。
                </div>
            <?php else: ?>
                <!-- 生徒選択エリア -->
                <div class="selection-area">
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
                </div>

                <?php if ($selectedStudentId && empty($activePeriods)): ?>
                    <div class="alert alert-info">
                        この生徒の支援開始日が設定されていないため、かけはし期間を自動生成できませんでした。<br>
                        生徒登録ページで支援開始日を設定してください。
                    </div>
                <?php elseif ($selectedStudentId && !empty($activePeriods)): ?>
                    <!-- 期間選択エリア -->
                    <div class="selection-area">
                        <div class="form-group">
                            <label>かけはし提出期限を選択 *</label>
                            <select id="periodSelect" onchange="changePeriod()">
                                <option value="">-- 期間を選択してください --</option>
                                <?php foreach ($activePeriods as $period): ?>
                                    <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                                        提出期限: <?= date('Y年m月d日', strtotime($period['submission_deadline'])) ?>
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

                        <!-- 本人の願い -->
                        <div class="section-title">💫 本人の願い</div>
                        <div class="form-group">
                            <label>本人が望んでいること、なりたい姿</label>
                            <textarea name="student_wish" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['student_wish'] ?? '' ?></textarea>
                        </div>

                        <!-- 目標設定 -->
                        <div class="section-title">🎯 目標設定</div>
                        <div class="form-group">
                            <label>短期目標（6か月）</label>
                            <textarea name="short_term_goal" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['short_term_goal'] ?? '' ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>長期目標（1年以上）</label>
                            <textarea name="long_term_goal" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['long_term_goal'] ?? '' ?></textarea>
                        </div>

                        <!-- 五領域の課題 -->
                        <div class="section-title">🌟 五領域の課題</div>
                        <div class="domains-grid">
                            <div class="form-group">
                                <label>健康・生活</label>
                                <textarea name="domain_health_life" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['domain_health_life'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>運動・感覚</label>
                                <textarea name="domain_motor_sensory" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['domain_motor_sensory'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>認知・行動</label>
                                <textarea name="domain_cognitive_behavior" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['domain_cognitive_behavior'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>言語・コミュニケーション</label>
                                <textarea name="domain_language_communication" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['domain_language_communication'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>人間関係・社会性</label>
                                <textarea name="domain_social_relations" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['domain_social_relations'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <!-- その他の課題 -->
                        <div class="section-title">📌 その他の課題</div>
                        <div class="form-group">
                            <label>その他、記載事項</label>
                            <textarea name="other_challenges" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['other_challenges'] ?? '' ?></textarea>
                        </div>

                        <!-- ボタン -->
                        <?php if (!$kakehashiData || !$kakehashiData['is_submitted']): ?>
                            <div class="button-group">
                                <button type="submit" class="btn btn-save" onclick="setAction('save')">💾 下書き保存</button>
                                <button type="submit" class="btn btn-submit" onclick="return confirmSubmit()">📤 提出する</button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                ✅ このかけはしは提出済みです。内容の確認のみ可能です。
                            </div>
                        <?php endif; ?>
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
                                <button type="submit" class="btn btn-generate">
                                    <span>🤖</span>
                                    <span>AIで自動生成</span>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = `kakehashi_staff.php?student_id=${studentId}`;
            }
        }

        function changePeriod() {
            const studentId = document.getElementById('studentSelect').value;
            const periodId = document.getElementById('periodSelect').value;
            if (studentId && periodId) {
                window.location.href = `kakehashi_staff.php?student_id=${studentId}&period_id=${periodId}`;
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
            return confirm('直近5か月の連絡帳データからAIが自動生成します。\n現在入力されている内容は上書きされます。\nよろしいですか？');
        }
    </script>
</body>
</html>
