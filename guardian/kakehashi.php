<?php
/**
 * 保護者用かけはし入力ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'guardian') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();
$guardianId = $_SESSION['user_id'];

// 保護者の子どもを取得
$stmt = $pdo->prepare("SELECT id, student_name FROM students WHERE guardian_id = ? AND is_active = 1");
$stmt->execute([$guardianId]);
$students = $stmt->fetchAll();

// 選択された生徒
$selectedStudentId = $_GET['student_id'] ?? ($students[0]['id'] ?? null);

// 選択された生徒の有効な期間を取得
$activePeriods = [];
if ($selectedStudentId) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_periods
        WHERE student_id = ? AND is_active = 1
        ORDER BY start_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $activePeriods = $stmt->fetchAll();
}

$selectedPeriodId = $_GET['period_id'] ?? ($activePeriods[0]['id'] ?? null);

// 既存のかけはしデータを取得
$kakehashiData = null;
if ($selectedStudentId && $selectedPeriodId) {
    $stmt = $pdo->prepare("
        SELECT * FROM kakehashi_guardian
        WHERE student_id = ? AND period_id = ?
    ");
    $stmt->execute([$selectedStudentId, $selectedPeriodId]);
    $kakehashiData = $stmt->fetch();
}

// 選択された期間の情報
$selectedPeriod = null;
if ($selectedPeriodId) {
    $stmt = $pdo->prepare("SELECT * FROM kakehashi_periods WHERE id = ?");
    $stmt->execute([$selectedPeriodId]);
    $selectedPeriod = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>かけはし - 保護者ページ</title>
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

        .deadline-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌉 かけはし（保護者入力）</h1>
            <div class="nav-links">
                <a href="dashboard.php">← ダッシュボード</a>
            </div>
        </div>

        <div class="content">
            <?php if (empty($students)): ?>
                <div class="alert alert-info">
                    お子様の情報が登録されていません。管理者にお問い合わせください。
                </div>
            <?php elseif (empty($activePeriods)): ?>
                <div class="alert alert-info">
                    現在、入力可能なかけはし期間がありません。<br>
                    <small>※ スタッフが期間を作成すると、開始日から1か月以内に入力・提出できるようになります</small>
                </div>
            <?php else: ?>
                <!-- 生徒・期間選択エリア -->
                <div class="selection-area">
                    <div class="form-group">
                        <label>お子様を選択</label>
                        <select id="studentSelect" onchange="changePeriod()">
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $student['id'] == $selectedStudentId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['student_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>提出期間を選択</label>
                        <select id="periodSelect" onchange="changePeriod()">
                            <?php foreach ($activePeriods as $period): ?>
                                <option value="<?= $period['id'] ?>" <?= $period['id'] == $selectedPeriodId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($period['period_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if ($selectedPeriod): ?>
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

                    <?php
                    $daysUntilDeadline = floor((strtotime($selectedPeriod['submission_deadline']) - time()) / 86400);
                    if ($daysUntilDeadline <= 7 && $daysUntilDeadline >= 0):
                    ?>
                        <div class="deadline-warning">
                            ⚠️ 提出期限まで残り<strong><?= $daysUntilDeadline ?></strong>日です
                        </div>
                    <?php endif; ?>

                    <!-- かけはし入力フォーム -->
                    <form method="POST" action="kakehashi_save.php" id="kakehashiForm">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                        <input type="hidden" name="action" id="formAction" value="save">

                        <!-- 家庭での課題 -->
                        <div class="section-title">📝 家庭での課題</div>
                        <div class="form-group">
                            <label>家庭で気になっていること、取り組みたいこと</label>
                            <textarea name="home_challenges" <?= $kakehashiData && $kakehashiData['is_submitted'] ? 'readonly' : '' ?>><?= $kakehashiData['home_challenges'] ?? '' ?></textarea>
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
                            <label>その他、お伝えしたいこと</label>
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
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changePeriod() {
            const studentId = document.getElementById('studentSelect').value;
            const periodId = document.getElementById('periodSelect').value;
            window.location.href = `kakehashi.php?student_id=${studentId}&period_id=${periodId}`;
        }

        function setAction(action) {
            document.getElementById('formAction').value = action;
        }

        function confirmSubmit() {
            setAction('submit');
            return confirm('提出すると内容の変更ができなくなります。提出してよろしいですか？');
        }
    </script>
</body>
</html>
