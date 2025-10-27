<?php
/**
 * スタッフ用 保護者入力かけはし確認ページ
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// 認証チェック
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDbConnection();

// スタッフの教室IDを取得
$classroomId = $_SESSION['classroom_id'] ?? null;

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guardian_kakehashi'])) {
    $deleteStudentId = $_POST['student_id'];
    $deletePeriodId = $_POST['period_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM kakehashi_guardian WHERE student_id = ? AND period_id = ?");
        $stmt->execute([$deleteStudentId, $deletePeriodId]);

        $_SESSION['success'] = '保護者用かけはしを削除しました。';
        header("Location: kakehashi_guardian_view.php?student_id=$deleteStudentId");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '削除に失敗しました: ' . $e->getMessage();
    }
}

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
}

// 選択された期間（URLパラメータから取得のみ、デフォルト値なし）
$selectedPeriodId = $_GET['period_id'] ?? null;

// 保護者入力かけはしデータを取得（単一レコード）
$kakehashiData = null;
if ($selectedStudentId && $selectedPeriodId) {
    $stmt = $pdo->prepare("
        SELECT
            kg.*,
            s.student_name,
            s.birth_date,
            u.full_name as guardian_name
        FROM kakehashi_guardian kg
        INNER JOIN students s ON kg.student_id = s.id
        LEFT JOIN users u ON s.guardian_id = u.id
        WHERE kg.student_id = ? AND kg.period_id = ?
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
    <title>保護者入力かけはし確認 - スタッフページ</title>
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
            max-width: 1400px;
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

        .filter-area {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            align-items: flex-end;
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

        .period-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .period-info p {
            margin: 5px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-submitted {
            background: #28a745;
            color: white;
        }

        .status-draft {
            background: #ffc107;
            color: #856404;
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

        .btn-save {
            background: #28a745;
            color: white;
        }

        .btn-save:hover {
            background: #218838;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
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

        .student-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .header, .filter-area, .nav-links, .btn {
                display: none;
            }

            .container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 保護者入力かけはし確認</h1>
            <div class="nav-links">
                <a href="kakehashi_staff.php">✏️ スタッフ入力</a>
                <a href="renrakucho_activities.php">← 戻る</a>
            </div>
        </div>

        <div class="content">
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
            </div>

            <?php if ($selectedStudentId && empty($activePeriods)): ?>
                <div style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bee5eb;">
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

                    <!-- 編集フォーム -->
                    <form method="POST" action="kakehashi_guardian_save.php" id="kakehashiForm">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">

                        <!-- 本人の願い -->
                        <div class="section-title">💫 本人の願い</div>
                        <div class="form-group">
                            <label>お子様が望んでいること、なりたい姿</label>
                            <textarea name="student_wish"><?= htmlspecialchars($kakehashiData['student_wish'] ?? '') ?></textarea>
                        </div>

                        <!-- 家庭での願い -->
                        <div class="section-title">🏠 家庭での願い</div>
                        <div class="form-group">
                            <label>家庭で気になっていること、取り組みたいこと</label>
                            <textarea name="home_challenges"><?= htmlspecialchars($kakehashiData['home_challenges'] ?? '') ?></textarea>
                        </div>

                        <!-- 目標設定 -->
                        <div class="section-title">🎯 目標設定</div>
                        <div class="form-group">
                            <label>短期目標（6か月）</label>
                            <textarea name="short_term_goal"><?= htmlspecialchars($kakehashiData['short_term_goal'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>長期目標（1年以上）</label>
                            <textarea name="long_term_goal"><?= htmlspecialchars($kakehashiData['long_term_goal'] ?? '') ?></textarea>
                        </div>

                        <!-- 五領域の課題 -->
                        <div class="section-title">🌟 五領域の課題</div>
                        <div class="domains-grid">
                            <div class="form-group">
                                <label>健康・生活</label>
                                <textarea name="domain_health_life"><?= htmlspecialchars($kakehashiData['domain_health_life'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>運動・感覚</label>
                                <textarea name="domain_motor_sensory"><?= htmlspecialchars($kakehashiData['domain_motor_sensory'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>認知・行動</label>
                                <textarea name="domain_cognitive_behavior"><?= htmlspecialchars($kakehashiData['domain_cognitive_behavior'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>言語・コミュニケーション</label>
                                <textarea name="domain_language_communication"><?= htmlspecialchars($kakehashiData['domain_language_communication'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>人間関係・社会性</label>
                                <textarea name="domain_social_relations"><?= htmlspecialchars($kakehashiData['domain_social_relations'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- その他の課題 -->
                        <div class="section-title">📌 その他の課題</div>
                        <div class="form-group">
                            <label>その他、お伝えしたいこと</label>
                            <textarea name="other_challenges"><?= htmlspecialchars($kakehashiData['other_challenges'] ?? '') ?></textarea>
                        </div>

                        <!-- ボタン -->
                        <div class="button-group">
                            <button type="submit" class="btn btn-save">💾 保存する</button>
                        </div>
                    </form>

                    <!-- 削除フォーム -->
                    <form method="POST" style="margin-top: 20px;" onsubmit="return confirm('この保護者用かけはしを削除してもよろしいですか？\nこの操作は取り消せません。');">
                        <input type="hidden" name="delete_guardian_kakehashi" value="1">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">
                        <button type="submit" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">🗑️ この保護者用かけはしを削除</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeStudent() {
            const studentId = document.getElementById('studentSelect').value;
            if (studentId) {
                window.location.href = `kakehashi_guardian_view.php?student_id=${studentId}`;
            }
        }

        function changePeriod() {
            const studentId = document.getElementById('studentSelect').value;
            const periodId = document.getElementById('periodSelect').value;
            if (studentId && periodId) {
                window.location.href = `kakehashi_guardian_view.php?student_id=${studentId}&period_id=${periodId}`;
            }
        }
    </script>
</body>
</html>
