<?php
/**
 * 全生徒の grade_level を再計算して更新するページ
 * ブラウザから実行可能
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/student_helper.php';

// ログインチェック（管理者またはスタッフ）
requireUserType(['admin', 'staff']);

$pdo = getDbConnection();
$executed = false;
$results = [];

// POSTリクエストで実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    $executed = true;

    // 全生徒を取得
    $stmt = $pdo->query("SELECT id, student_name, birth_date, grade_level, grade_adjustment FROM students");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        if (empty($student['birth_date'])) {
            $results[] = [
                'id' => $student['id'],
                'name' => $student['student_name'],
                'status' => 'warning',
                'message' => '生年月日が未設定'
            ];
            continue;
        }

        // 学年を再計算
        $gradeAdjustment = $student['grade_adjustment'] ?? 0;
        $oldGradeLevel = $student['grade_level'];
        $newGradeLevel = calculateGradeLevel($student['birth_date'], null, $gradeAdjustment);

        // 更新
        try {
            $updateStmt = $pdo->prepare("UPDATE students SET grade_level = ? WHERE id = ?");
            $updateStmt->execute([$newGradeLevel, $student['id']]);

            $changed = ($oldGradeLevel !== $newGradeLevel);

            $results[] = [
                'id' => $student['id'],
                'name' => $student['student_name'],
                'status' => $changed ? 'changed' : 'unchanged',
                'old' => $oldGradeLevel,
                'new' => $newGradeLevel,
                'adjustment' => $gradeAdjustment
            ];
        } catch (Exception $e) {
            $results[] = [
                'id' => $student['id'],
                'name' => $student['student_name'],
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

// 学年レベルのラベル
function getGradeLevelLabel($level) {
    $labels = [
        'elementary' => '小学部',
        'junior_high' => '中学部',
        'high_school' => '高等部'
    ];
    return $labels[$level] ?? $level;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学年レベル一括更新</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info {
            background: #e7f3ff;
            border: 1px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .button:hover {
            background: #5568d3;
        }
        .button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-changed {
            background: #d4edda !important;
        }
        .status-warning {
            background: #fff3cd !important;
        }
        .status-error {
            background: #f8d7da !important;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
            font-weight: bold;
        }
        .badge-elementary {
            background: #28a745;
        }
        .badge-junior {
            background: #007bff;
        }
        .badge-high {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 生徒の学年レベル一括更新</h1>

        <?php if (!$executed): ?>
            <div class="warning">
                <strong>⚠ 注意:</strong> このページは全生徒の「grade_level」カラムを再計算して更新します。<br>
                生年月日と学年調整（grade_adjustment）に基づいて、正しい学年レベルが設定されます。
            </div>

            <div class="info">
                <strong>📝 実行内容:</strong>
                <ul>
                    <li>全生徒の生年月日と学年調整から学年レベルを再計算</li>
                    <li>データベースの「grade_level」カラムを更新</li>
                    <li>変更があった生徒のみ表示</li>
                </ul>
            </div>

            <form method="POST">
                <button type="submit" name="execute" value="1" class="button">実行する</button>
            </form>
        <?php else: ?>
            <div class="info">
                <strong>✓ 完了</strong><br>
                更新が完了しました。
            </div>

            <h2>更新結果</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>生徒名</th>
                        <th>変更前</th>
                        <th>変更後</th>
                        <th>学年調整</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <?php
                        $rowClass = '';
                        if ($result['status'] === 'changed') $rowClass = 'status-changed';
                        if ($result['status'] === 'warning') $rowClass = 'status-warning';
                        if ($result['status'] === 'error') $rowClass = 'status-error';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo $result['id']; ?></td>
                            <td><?php echo htmlspecialchars($result['name']); ?></td>
                            <?php if ($result['status'] === 'warning' || $result['status'] === 'error'): ?>
                                <td colspan="4">
                                    <?php echo htmlspecialchars($result['message'] ?? ''); ?>
                                </td>
                            <?php else: ?>
                                <td>
                                    <?php if ($result['old'] !== $result['new']): ?>
                                        <span class="badge badge-<?php echo $result['old'] === 'elementary' ? 'elementary' : ($result['old'] === 'junior_high' ? 'junior' : 'high'); ?>">
                                            <?php echo getGradeLevelLabel($result['old']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $result['new'] === 'elementary' ? 'elementary' : ($result['new'] === 'junior_high' ? 'junior' : 'high'); ?>">
                                        <?php echo getGradeLevelLabel($result['new']); ?>
                                    </span>
                                </td>
                                <td><?php echo $result['adjustment']; ?></td>
                                <td><?php echo $result['status'] === 'changed' ? '変更' : '変更なし'; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <a href="staff/students.php" class="button">生徒一覧に戻る</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
